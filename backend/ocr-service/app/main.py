"""FastAPI application for the standalone OCR prototype."""

import logging
import time

from fastapi import Depends, FastAPI, File, Request, UploadFile
from fastapi.responses import JSONResponse

from app.config import Settings, get_settings
from app.errors import OCR_PROCESSING_FAILURE, OcrServiceError
from app.ocr_engine import is_tesseract_available
from app.schemas import ErrorDetail, ErrorResponse, HealthResponse, ImageOcrResponse, PdfOcrResponse
from app.service import process_image_upload, process_pdf_upload

logger = logging.getLogger("ocr_service")
logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(message)s")

app = FastAPI(title="TCC UniFAST OCR Prototype", version="0.1.0")


@app.exception_handler(OcrServiceError)
async def ocr_error_handler(request: Request, exc: OcrServiceError) -> JSONResponse:
    """Return structured JSON for expected application errors."""
    logger.info(
        "endpoint=%s file_category=unknown file_size=0 duration_ms=0 engine=tesseract success=false error_type=%s",
        request.url.path,
        exc.code,
    )
    return JSONResponse(
        status_code=exc.http_status,
        content=ErrorResponse(error=ErrorDetail(code=exc.code, message=exc.message)).model_dump(),
    )


@app.exception_handler(Exception)
async def unexpected_error_handler(request: Request, exc: Exception) -> JSONResponse:
    """Avoid exposing stack traces in API responses."""
    logger.exception("endpoint=%s success=false error_type=%s", request.url.path, type(exc).__name__)
    return JSONResponse(
        status_code=500,
        content=ErrorResponse(
            error=ErrorDetail(code=OCR_PROCESSING_FAILURE, message="OCR processing failed.")
        ).model_dump(),
    )


@app.get("/health", response_model=HealthResponse)
def health(settings: Settings = Depends(get_settings)) -> HealthResponse:
    """Return service and OCR engine availability."""
    return HealthResponse(
        status="healthy",
        ocr_engine="tesseract",
        tesseract_available=is_tesseract_available(settings),
    )


@app.post("/ocr/image", response_model=ImageOcrResponse)
async def ocr_image(file: UploadFile = File(...), settings: Settings = Depends(get_settings)) -> ImageOcrResponse:
    """Run OCR on an uploaded image."""
    start = time.perf_counter()
    content = await file.read()
    try:
        response = process_image_upload(content, file.content_type or "", settings)
        logger.info(
            "endpoint=/ocr/image file_category=image file_size=%s duration_ms=%s engine=tesseract success=true error_type=none",
            len(content),
            round((time.perf_counter() - start) * 1000),
        )
        return response
    except OcrServiceError as exc:
        logger.info(
            "endpoint=/ocr/image file_category=image file_size=%s duration_ms=%s engine=tesseract success=false error_type=%s",
            len(content),
            round((time.perf_counter() - start) * 1000),
            exc.code,
        )
        raise


@app.post("/ocr/pdf", response_model=PdfOcrResponse)
async def ocr_pdf(file: UploadFile = File(...), settings: Settings = Depends(get_settings)) -> PdfOcrResponse:
    """Extract text from an uploaded PDF with OCR fallback."""
    start = time.perf_counter()
    content = await file.read()
    try:
        response = process_pdf_upload(content, file.content_type or "", settings)
        logger.info(
            "endpoint=/ocr/pdf file_category=pdf file_size=%s duration_ms=%s engine=tesseract success=true error_type=none",
            len(content),
            round((time.perf_counter() - start) * 1000),
        )
        return response
    except OcrServiceError as exc:
        logger.info(
            "endpoint=/ocr/pdf file_category=pdf file_size=%s duration_ms=%s engine=tesseract success=false error_type=%s",
            len(content),
            round((time.perf_counter() - start) * 1000),
            exc.code,
        )
        raise
