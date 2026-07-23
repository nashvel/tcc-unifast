"""QR code detection using OpenCV."""

import cv2
import numpy as np

from app.schemas import QrResult


def detect_qr_code(image: np.ndarray) -> QrResult:
    """Detect a QR code without allowing detector failures to fail OCR."""
    try:
        detector = cv2.QRCodeDetector()
        value, points, _ = detector.detectAndDecode(image)
        if value and points is not None:
            return QrResult(found=True, value=value, bounding_box=points.reshape(-1, 2).tolist())
    except cv2.error:
        pass
    return QrResult(found=False, value=None, bounding_box=[])

