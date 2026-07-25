"""Reusable OpenCV preprocessing pipeline for document OCR."""

from pathlib import Path
from uuid import uuid4

import cv2
import numpy as np

from app.schemas import PreprocessingInfo


def _deskew(binary_image: np.ndarray) -> tuple[np.ndarray, float]:
    coords = np.column_stack(np.where(binary_image > 0))
    if coords.size == 0:
        return binary_image, 0.0
    angle = cv2.minAreaRect(coords)[-1]
    if angle < -45:
        angle = -(90 + angle)
    else:
        angle = -angle
    if abs(angle) < 0.2 or abs(angle) > 10:
        return binary_image, 0.0

    height, width = binary_image.shape[:2]
    center = (width // 2, height // 2)
    matrix = cv2.getRotationMatrix2D(center, angle, 1.0)
    rotated = cv2.warpAffine(
        binary_image,
        matrix,
        (width, height),
        flags=cv2.INTER_CUBIC,
        borderMode=cv2.BORDER_REPLICATE,
    )
    return rotated, round(float(angle), 2)


def preprocess_image(
    image: np.ndarray,
    save_debug_images: bool = False,
    output_dir: Path = Path("outputs"),
) -> tuple[np.ndarray, PreprocessingInfo]:
    """Preprocess a BGR image and return the processed image plus dimensions."""
    original_height, original_width = image.shape[:2]
    working = image.copy()

    if min(original_width, original_height) < 1000:
        working = cv2.resize(working, None, fx=2, fy=2, interpolation=cv2.INTER_CUBIC)

    gray = cv2.cvtColor(working, cv2.COLOR_BGR2GRAY)
    denoised = cv2.fastNlMeansDenoising(gray, h=10)
    clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8, 8))
    contrasted = clahe.apply(denoised)
    thresholded = cv2.adaptiveThreshold(
        contrasted,
        255,
        cv2.ADAPTIVE_THRESH_GAUSSIAN_C,
        cv2.THRESH_BINARY,
        31,
        11,
    )
    deskewed, angle = _deskew(thresholded)

    if save_debug_images:
        debug_dir = output_dir / "debug"
        debug_dir.mkdir(parents=True, exist_ok=True)
        cv2.imwrite(str(debug_dir / f"{uuid4().hex}.png"), deskewed)

    processed_height, processed_width = deskewed.shape[:2]
    info = PreprocessingInfo(
        original_width=original_width,
        original_height=original_height,
        processed_width=processed_width,
        processed_height=processed_height,
        deskew_angle=angle,
    )
    return deskewed, info

