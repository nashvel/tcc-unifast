"""Shared document processing service used by API and CLI."""

from app.config import Settings
from app.errors import EMPTY_FILE, FILE_TOO_LARGE, OcrServiceError
from app.image_loader import decode_image, validate_image_upload
from app.metadata_checker import extract_metadata
from app.ocr_engine import ocr_image_array
from app.pdf_parser import parse_pdf_bytes, validate_pdf_upload
from app.qr_detector import detect_qr_code
from app.schemas import ImageOcrResponse, PdfMetadataInfo, PdfOcrResponse


def process_image_upload(content: bytes, mime_type: str, settings: Settings) -> ImageOcrResponse:
    """Validate, preprocess, OCR, and enrich an uploaded image."""
    if len(content) > settings.max_image_size_bytes:
        raise OcrServiceError(FILE_TOO_LARGE, "Image uploads are limited to 10 MB.")
    validate_image_upload(content, mime_type)
    cv_image, pil_image = decode_image(content)
    metadata = extract_metadata(pil_image)
    qr_code = detect_qr_code(cv_image)
    result = ocr_image_array(cv_image, settings)
    return ImageOcrResponse(result=result, metadata=metadata, qr_code=qr_code)


def process_pdf_upload(content: bytes, mime_type: str, settings: Settings) -> PdfOcrResponse:
    """Validate and process an uploaded PDF."""
    if not content:
        raise OcrServiceError(EMPTY_FILE, "Uploaded file is empty.")
    if len(content) > settings.max_pdf_size_bytes:
        raise OcrServiceError(FILE_TOO_LARGE, "PDF uploads are limited to 20 MB.")
    validate_pdf_upload(content, mime_type)
    result, metadata = parse_pdf_bytes(content, settings)
    return PdfOcrResponse(result=result, pdf_metadata=PdfMetadataInfo(**metadata))
