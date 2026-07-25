"""Tesseract OCR execution and result shaping."""

import shutil
import time
from pathlib import Path

import cv2
import numpy as np
import pytesseract
from fastapi import status
from pytesseract import Output, TesseractError

from app.config import Settings
from app.errors import OCR_PROCESSING_FAILURE, TESSERACT_UNAVAILABLE, OcrServiceError
from app.image_preprocessing import preprocess_image
from app.schemas import BoundingBox, OcrResult, OcrWord, PreprocessingInfo
from app.text_cleaner import clean_text


def configure_tesseract(settings: Settings) -> None:
    """Apply optional Tesseract executable path to pytesseract."""
    if settings.tesseract_cmd:
        pytesseract.pytesseract.tesseract_cmd = settings.tesseract_cmd


def is_tesseract_available(settings: Settings) -> bool:
    """Return whether the Tesseract executable can be invoked."""
    configure_tesseract(settings)
    command = settings.tesseract_cmd or "tesseract"
    if settings.tesseract_cmd and not Path(settings.tesseract_cmd).exists():
        return False
    if not settings.tesseract_cmd and shutil.which(command) is None:
        return False
    try:
        pytesseract.get_tesseract_version()
        return True
    except (TesseractError, OSError):
        return False


def ensure_tesseract_available(settings: Settings) -> None:
    """Raise a clear structured error when Tesseract is unavailable."""
    if not is_tesseract_available(settings):
        raise OcrServiceError(
            TESSERACT_UNAVAILABLE,
            "Tesseract OCR is not available. Install it or set TESSERACT_CMD.",
            status.HTTP_503_SERVICE_UNAVAILABLE,
        )


def calculate_average_confidence(confidences: list[float]) -> float:
    """Average only valid non-negative confidence values."""
    valid = [value for value in confidences if value >= 0]
    if not valid:
        return 0.0
    return round(sum(valid) / len(valid), 2)


def _extract_words(data: dict[str, list]) -> tuple[list[OcrWord], float]:
    words: list[OcrWord] = []
    confidences: list[float] = []
    for index, text in enumerate(data.get("text", [])):
        token = str(text).strip()
        if not token:
            continue
        try:
            confidence = float(data["conf"][index])
        except (TypeError, ValueError):
            confidence = -1.0
        if confidence < 0:
            continue
        confidences.append(confidence)
        words.append(
            OcrWord(
                text=token,
                confidence=round(confidence, 2),
                bounding_box=BoundingBox(
                    x=int(data["left"][index]),
                    y=int(data["top"][index]),
                    width=int(data["width"][index]),
                    height=int(data["height"][index]),
                ),
            )
        )
    return words, calculate_average_confidence(confidences)


def run_tesseract_on_processed_image(
    processed_image: np.ndarray,
    preprocessing: PreprocessingInfo,
    settings: Settings,
) -> OcrResult:
    """Run OCR on a preprocessed image."""
    ensure_tesseract_available(settings)
    start = time.perf_counter()
    try:
        raw_text = pytesseract.image_to_string(
            processed_image,
            lang="eng",
            config=settings.tesseract_config,
        )
        data = pytesseract.image_to_data(
            processed_image,
            lang="eng",
            config=settings.tesseract_config,
            output_type=Output.DICT,
        )
    except (TesseractError, RuntimeError, OSError) as exc:
        raise OcrServiceError(OCR_PROCESSING_FAILURE, "Tesseract OCR processing failed.") from exc

    words, average_confidence = _extract_words(data)
    return OcrResult(
        raw_text=raw_text,
        cleaned_text=clean_text(raw_text),
        average_confidence=average_confidence,
        word_count=len(words),
        words=words,
        processing_time_ms=round((time.perf_counter() - start) * 1000),
        preprocessing=preprocessing,
    )


def ocr_image_array(image: np.ndarray, settings: Settings) -> OcrResult:
    """Preprocess and OCR an OpenCV image array."""
    processed, preprocessing = preprocess_image(
        image,
        save_debug_images=settings.save_debug_images,
        output_dir=settings.output_dir,
    )
    return run_tesseract_on_processed_image(processed, preprocessing, settings)


def render_pixmap_to_bgr(samples: bytes, width: int, height: int, channels: int) -> np.ndarray:
    """Convert PyMuPDF pixmap samples to a BGR OpenCV image."""
    array = np.frombuffer(samples, dtype=np.uint8).reshape(height, width, channels)
    if channels == 4:
        return cv2.cvtColor(array, cv2.COLOR_RGBA2BGR)
    return cv2.cvtColor(array, cv2.COLOR_RGB2BGR)

