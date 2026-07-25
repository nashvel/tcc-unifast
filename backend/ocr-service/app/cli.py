"""Command-line utility for the OCR prototype."""

import argparse
import json
from pathlib import Path

from app.config import get_settings
from app.errors import OcrServiceError
from app.image_loader import IMAGE_MIME_SIGNATURES
from app.pdf_parser import PDF_SIGNATURE
from app.schemas import CliResult, ErrorDetail
from app.service import process_image_upload, process_pdf_upload


def _detect_mime(content: bytes) -> str | None:
    if content.startswith(PDF_SIGNATURE):
        return "application/pdf"
    for mime_type in IMAGE_MIME_SIGNATURES:
        if mime_type == "image/webp" and content.startswith(b"RIFF") and content[8:12] == b"WEBP":
            return mime_type
        if mime_type != "image/webp" and any(content.startswith(sig) for sig in IMAGE_MIME_SIGNATURES[mime_type]):
            return mime_type
    return None


def main() -> int:
    """Run OCR for a local image or PDF and print JSON."""
    parser = argparse.ArgumentParser(description="Run OCR on an image or PDF.")
    parser.add_argument("path", help="Path to a JPEG, PNG, WebP, or PDF document.")
    args = parser.parse_args()

    path = Path(args.path)
    settings = get_settings()
    try:
        content = path.read_bytes()
        mime_type = _detect_mime(content)
        if mime_type == "application/pdf":
            response = process_pdf_upload(content, mime_type, settings)
        elif mime_type:
            response = process_image_upload(content, mime_type, settings)
        else:
            raise OcrServiceError("UNSUPPORTED_FILE_TYPE", "Unsupported file type.")
        payload = response.model_dump(mode="json")
    except OcrServiceError as exc:
        payload = CliResult(
            success=False,
            error=ErrorDetail(code=exc.code, message=exc.message),
        ).model_dump(mode="json")
    except OSError as exc:
        payload = CliResult(
            success=False,
            error=ErrorDetail(code="FILE_READ_ERROR", message=str(exc)),
        ).model_dump(mode="json")

    print(json.dumps(payload, indent=2, ensure_ascii=False))
    return 0 if payload.get("success") else 1


if __name__ == "__main__":
    raise SystemExit(main())
