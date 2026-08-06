"""PDF text extraction and OCR fallback using PyMuPDF."""

import time

import fitz

from app.config import Settings
from app.errors import ENCRYPTED_PDF, INVALID_PDF, TOO_MANY_PDF_PAGES, OcrServiceError
from app.ocr_engine import ocr_image_array, render_pixmap_to_bgr
from app.pdf_metadata import extract_pdf_metadata
from app.schemas import PdfPageResult, PdfResult
from app.text_cleaner import clean_text

PDF_SIGNATURE = b"%PDF-"
PDF_MIME_TYPES = {"application/pdf", "application/x-pdf"}


def validate_pdf_upload(content: bytes, mime_type: str = "application/pdf") -> None:
    """Validate PDF MIME type and signature."""
    if mime_type not in PDF_MIME_TYPES:
        raise OcrServiceError(INVALID_PDF, "Only PDF files are supported.")
    if not content.startswith(PDF_SIGNATURE):
        raise OcrServiceError(INVALID_PDF, "The uploaded file is not a valid PDF.")


def has_useful_text(text: str) -> bool:
    """Determine whether embedded text is useful enough to skip OCR."""
    compact = clean_text(text)
    alphanumeric = [char for char in compact if char.isalnum()]
    return len(alphanumeric) >= 10


def parse_pdf_bytes(content: bytes, settings: Settings) -> tuple[PdfResult, dict]:
    """Extract embedded text or OCR rendered pages in page order, plus PDF metadata."""
    start = time.perf_counter()
    validate_pdf_upload(content)
    try:
        document = fitz.open(stream=content, filetype="pdf")
    except fitz.FileDataError as exc:
        raise OcrServiceError(INVALID_PDF, "The uploaded PDF could not be opened.") from exc

    try:
        if document.is_encrypted:
            raise OcrServiceError(ENCRYPTED_PDF, "Encrypted PDFs are not supported.")
        if document.page_count > settings.max_pdf_pages:
            raise OcrServiceError(
                TOO_MANY_PDF_PAGES,
                f"PDF exceeds the {settings.max_pdf_pages}-page prototype limit.",
            )

        metadata = extract_pdf_metadata(document)
        pages: list[PdfPageResult] = []
        combined: list[str] = []
        for page_index in range(document.page_count):
            page = document.load_page(page_index)
            embedded = page.get_text("text")
            cleaned = clean_text(embedded)
            if has_useful_text(cleaned):
                pages.append(
                    PdfPageResult(
                        page=page_index + 1,
                        method="embedded_text",
                        text=cleaned,
                        useful_embedded_text=True,
                    )
                )
                combined.append(cleaned)
                continue

            pixmap = page.get_pixmap(matrix=fitz.Matrix(2, 2), alpha=False)
            image = render_pixmap_to_bgr(pixmap.samples, pixmap.width, pixmap.height, pixmap.n)
            ocr, _warped = ocr_image_array(image, settings)
            pages.append(
                PdfPageResult(
                    page=page_index + 1,
                    method="tesseract_ocr",
                    text=ocr.cleaned_text,
                    useful_embedded_text=False,
                    ocr=ocr,
                )
            )
            combined.append(ocr.cleaned_text)

        result = PdfResult(
            page_count=document.page_count,
            pages=pages,
            combined_text="\n\n".join(part for part in combined if part),
            processing_time_ms=round((time.perf_counter() - start) * 1000),
        )
        return result, metadata
    finally:
        document.close()
