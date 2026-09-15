"""FastAPI application for the standalone OCR prototype."""

import hmac
import logging
import time
from contextlib import asynccontextmanager
from typing import AsyncGenerator

from fastapi import Depends, FastAPI, File, Header, HTTPException, Query, Request, UploadFile
from fastapi.responses import JSONResponse

from app import face_engine
from app.config import Settings, get_settings
from app.errors import FACE_ENGINE_UNAVAILABLE, FACE_NOT_FOUND, OCR_PROCESSING_FAILURE, OcrServiceError
from app.ocr_engine import is_tesseract_available
from app.schemas import (
    ErrorDetail,
    ErrorResponse,
    FaceMatchResponse,
    HealthResponse,
    ImageOcrResponse,
    PdfOcrResponse,
)
from app.service import process_image_upload, process_pdf_upload

logger = logging.getLogger("ocr_service")
logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(message)s")


@asynccontextmanager
async def lifespan(app: FastAPI) -> AsyncGenerator[None, None]:
    """Preload face recognition models once at container startup."""
    try:
        face_engine.load_models()
    except Exception:  # noqa: BLE001
        logger.exception("Face engine failed to load — /face/match will return 503.")
    yield


app = FastAPI(title="TCC UniFAST OCR Prototype", version="0.1.0", lifespan=lifespan)

MAX_UPLOAD_BYTES = 20 * 1024 * 1024


def require_api_key(
    x_ocr_key: str | None = Header(default=None),
    settings: Settings = Depends(get_settings),
) -> None:
    """Reject OCR requests without the shared secret.

    OCR is CPU-heavy and the service binds 0.0.0.0, so leaving it open lets anyone
    with network reach burn the box. When OCR_API_KEY is unset the service stays
    open and logs a warning — convenient locally, and loud enough to notice.
    """
    expected = settings.api_key
    if not expected:
        logger.warning("OCR_API_KEY is not set — OCR endpoints are unauthenticated.")
        return

    if not x_ocr_key or not hmac.compare_digest(x_ocr_key, expected):
        raise HTTPException(status_code=401, detail="Invalid or missing OCR API key.")


async def read_bounded_upload(file: UploadFile) -> bytes:
    """Read an upload, refusing anything past the hard cap.

    Reading in chunks means an oversized body is rejected before it is fully
    buffered in memory; per-type limits are still enforced downstream in service.py.
    """
    chunks: list[bytes] = []
    total = 0

    while True:
        chunk = await file.read(1024 * 1024)
        if not chunk:
            break
        total += len(chunk)
        if total > MAX_UPLOAD_BYTES:
            raise HTTPException(status_code=413, detail="Upload exceeds the maximum allowed size.")
        chunks.append(chunk)

    return b"".join(chunks)


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


@app.post("/face/match", response_model=FaceMatchResponse, dependencies=[Depends(require_api_key)])
async def face_match(
    reference_file: UploadFile = File(..., description="Reference face image (ID card crop)"),
    live_file: UploadFile = File(..., description="Live selfie image"),
    threshold: float = Query(default=None, ge=0.0, le=1.0),
    settings: Settings = Depends(get_settings),
) -> FaceMatchResponse:
    """Compare a reference face image with a live selfie entirely server-side.

    Both images are processed by YuNet (detector) + SFace (embedder) inside this
    container. The client never computes or submits face descriptors.

    Returns a JSON response with matched bool, similarity score, and distances.
    Returns HTTP 503 if the face engine did not load at startup.
    """
    if not face_engine.is_ready():
        raise OcrServiceError(
            code=FACE_ENGINE_UNAVAILABLE,
            message="Face verification engine is temporarily unavailable. Please try again in a few minutes.",
            http_status=503,
        )

    effective_threshold = threshold if threshold is not None else settings.face_match_threshold

    start = time.perf_counter()
    reference_bytes = await read_bounded_upload(reference_file)
    live_bytes = await read_bounded_upload(live_file)

    result = face_engine.match_faces(reference_bytes, live_bytes, threshold=effective_threshold)

    duration_ms = round((time.perf_counter() - start) * 1000)
    logger.info(
        "endpoint=/face/match duration_ms=%s matched=%s score=%.2f cosine_distance=%.4f l2=%.4f",
        duration_ms,
        result.matched,
        result.score,
        result.cosine_distance,
        result.l2_distance,
    )

    return FaceMatchResponse(
        matched=result.matched,
        score=result.score,
        cosine_distance=result.cosine_distance,
        l2_distance=result.l2_distance,
        reference_face_found=result.reference_face_found,
        live_face_found=result.live_face_found,
        error=result.error,
    )


@app.post("/ocr/image", response_model=ImageOcrResponse, dependencies=[Depends(require_api_key)])
async def ocr_image(file: UploadFile = File(...), settings: Settings = Depends(get_settings)) -> ImageOcrResponse:
    """Run OCR on an uploaded image."""
    start = time.perf_counter()
    content = await read_bounded_upload(file)
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


@app.post("/ocr/pdf", response_model=PdfOcrResponse, dependencies=[Depends(require_api_key)])
async def ocr_pdf(file: UploadFile = File(...), settings: Settings = Depends(get_settings)) -> PdfOcrResponse:
    """Extract text from an uploaded PDF with OCR fallback."""
    start = time.perf_counter()
    content = await read_bounded_upload(file)
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
