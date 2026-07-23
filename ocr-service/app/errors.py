"""Structured application errors."""

from fastapi import status


class OcrServiceError(Exception):
    """Expected error returned as structured JSON."""

    def __init__(
        self,
        code: str,
        message: str,
        http_status: int = status.HTTP_400_BAD_REQUEST,
    ) -> None:
        super().__init__(message)
        self.code = code
        self.message = message
        self.http_status = http_status


UNSUPPORTED_FILE_TYPE = "UNSUPPORTED_FILE_TYPE"
FILE_TOO_LARGE = "FILE_TOO_LARGE"
EMPTY_FILE = "EMPTY_FILE"
UNREADABLE_IMAGE = "UNREADABLE_IMAGE"
INVALID_PDF = "INVALID_PDF"
ENCRYPTED_PDF = "ENCRYPTED_PDF"
TOO_MANY_PDF_PAGES = "TOO_MANY_PDF_PAGES"
TESSERACT_UNAVAILABLE = "TESSERACT_UNAVAILABLE"
OCR_PROCESSING_FAILURE = "OCR_PROCESSING_FAILURE"

