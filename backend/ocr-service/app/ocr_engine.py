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
from app.image_preprocessing import enhance_for_ocr, preprocess_image, region_crops
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


def _tesseract_pass(processed_image: np.ndarray, settings: Settings) -> tuple[str, list[OcrWord], float]:
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
    words, average_confidence = _extract_words(data)
    return raw_text, words, average_confidence


def run_tesseract_on_processed_image(
    processed_image: np.ndarray,
    preprocessing: PreprocessingInfo,
    settings: Settings,
) -> OcrResult:
    """Run OCR on a preprocessed image."""
    ensure_tesseract_available(settings)
    start = time.perf_counter()
    try:
        raw_text, words, average_confidence = _tesseract_pass(processed_image, settings)
    except (TesseractError, RuntimeError, OSError) as exc:
        raise OcrServiceError(OCR_PROCESSING_FAILURE, "Tesseract OCR processing failed.") from exc

    cleaned = clean_text(raw_text)
    preprocessing.empty_text = cleaned.strip() == ""
    return OcrResult(
        raw_text=raw_text,
        cleaned_text=cleaned,
        average_confidence=average_confidence,
        word_count=len(words),
        words=words,
        processing_time_ms=round((time.perf_counter() - start) * 1000),
        preprocessing=preprocessing,
    )


def _merge_ocr_texts(parts: list[str]) -> str:
    """Merge multi-pass OCR strings while dropping duplicate lines."""
    seen: set[str] = set()
    lines: list[str] = []
    for part in parts:
        for line in str(part or "").splitlines():
            stripped = line.strip()
            if not stripped:
                continue
            key = stripped.lower()
            if key in seen:
                continue
            seen.add(key)
            lines.append(stripped)
    return "\n".join(lines)


def _merge_words(passes: list[list[OcrWord]]) -> list[OcrWord]:
    """Keep primary-pass words, then unique tokens from later passes."""
    if not passes:
        return []
    merged = list(passes[0])
    seen = {word.text.strip().lower() for word in merged if word.text.strip()}
    for extra in passes[1:]:
        for word in extra:
            key = word.text.strip().lower()
            if not key or key in seen:
                continue
            seen.add(key)
            merged.append(word)
    return merged


def ocr_image_array(image: np.ndarray, settings: Settings) -> tuple[OcrResult, np.ndarray]:
    """
    Warp, enhance, run normal + inverted + region OCR, merge text.
    Returns (OcrResult, warped_bgr) so callers can decode QR on the warped card.
    """
    ensure_tesseract_available(settings)
    start = time.perf_counter()
    processed, preprocessing, warped = preprocess_image(
        image,
        save_debug_images=settings.save_debug_images,
        output_dir=settings.output_dir,
    )

    try:
        raw_parts: list[str] = []
        word_passes: list[list[OcrWord]] = []
        confidences: list[float] = []

        primary_raw, primary_words, primary_conf = _tesseract_pass(processed, settings)
        raw_parts.append(primary_raw)
        word_passes.append(primary_words)
        if primary_conf > 0:
            confidences.append(primary_conf)

        # Polarity invert for white-on-black policy strips (common on ID backs).
        inverted = cv2.bitwise_not(processed)
        inv_raw, inv_words, inv_conf = _tesseract_pass(inverted, settings)
        if clean_text(inv_raw).strip():
            preprocessing.inverted_pass = True
            raw_parts.append(inv_raw)
            word_passes.append(inv_words)
            if inv_conf > 0:
                confidences.append(inv_conf)

        # Relative ROI OCR on warped color card.
        for _name, crop in region_crops(warped):
            crop_processed, _ = enhance_for_ocr(crop)
            region_raw, region_words, region_conf = _tesseract_pass(crop_processed, settings)
            if clean_text(region_raw).strip():
                preprocessing.region_ocr = True
                raw_parts.append(region_raw)
                word_passes.append(region_words)
                if region_conf > 0:
                    confidences.append(region_conf)

    except (TesseractError, RuntimeError, OSError) as exc:
        raise OcrServiceError(OCR_PROCESSING_FAILURE, "Tesseract OCR processing failed.") from exc

    merged_raw = _merge_ocr_texts(raw_parts)
    cleaned = clean_text(merged_raw)
    preprocessing.empty_text = cleaned.strip() == ""
    all_words = _merge_words(word_passes)

    result = OcrResult(
        raw_text=merged_raw,
        cleaned_text=cleaned,
        average_confidence=calculate_average_confidence(confidences) if confidences else 0.0,
        word_count=len(all_words),
        words=all_words,
        processing_time_ms=round((time.perf_counter() - start) * 1000),
        preprocessing=preprocessing,
    )
    return result, warped


def render_pixmap_to_bgr(samples: bytes, width: int, height: int, channels: int) -> np.ndarray:
    """Convert PyMuPDF pixmap samples to a BGR OpenCV image."""
    array = np.frombuffer(samples, dtype=np.uint8).reshape(height, width, channels)
    if channels == 4:
        return cv2.cvtColor(array, cv2.COLOR_RGBA2BGR)
    return cv2.cvtColor(array, cv2.COLOR_RGB2BGR)
