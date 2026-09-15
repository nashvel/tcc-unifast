"""Server-side face detection and recognition using OpenCV DNN (YuNet + SFace).

Models are downloaded from the OpenCV Zoo on first startup and cached in /app/models/.
No API key, no usage fee, no expiry.

Licenses:
- YuNet face detector: MIT (OpenCV Zoo)
- SFace face recogniser: Apache 2.0 (OpenCV Zoo)
- OpenCV itself: Apache 2.0
"""

from __future__ import annotations

import logging
import urllib.request
from dataclasses import dataclass
from pathlib import Path
from typing import Optional

import cv2
import numpy as np

logger = logging.getLogger(__name__)

# ---------------------------------------------------------------------------
# Model paths and download URLs
# ---------------------------------------------------------------------------

MODELS_DIR = Path("/app/models")

_YUNET_FILENAME = "face_detection_yunet_2023mar.onnx"
_SFACE_FILENAME = "face_recognition_sface_2021dec.onnx"

_YUNET_URL = (
    "https://github.com/opencv/opencv_zoo/raw/main/models/"
    "face_detection_yunet/face_detection_yunet_2023mar.onnx"
)
_SFACE_URL = (
    "https://github.com/opencv/opencv_zoo/raw/main/models/"
    "face_recognition_sface/face_recognition_sface_2021dec.onnx"
)

YUNET_PATH = MODELS_DIR / _YUNET_FILENAME
SFACE_PATH = MODELS_DIR / _SFACE_FILENAME

DEFAULT_COSINE_THRESHOLD: float = 0.363

_detector: Optional[cv2.FaceDetectorYN] = None
_recognizer: Optional[cv2.FaceRecognizerSF] = None


def _ensure_model(path: Path, url: str) -> None:
    if path.exists():
        logger.info("Model already cached: %s", path.name)
        return
    logger.info("Downloading model %s from OpenCV Zoo ...", path.name)
    MODELS_DIR.mkdir(parents=True, exist_ok=True)
    tmp = path.with_suffix(".tmp")
    try:
        urllib.request.urlretrieve(url, tmp)
        tmp.rename(path)
        logger.info("Saved %s (%.1f MB)", path.name, path.stat().st_size / 1_048_576)
    except Exception as exc:
        tmp.unlink(missing_ok=True)
        raise RuntimeError(f"Failed to download {path.name}: {exc}") from exc


def load_models() -> None:
    global _detector, _recognizer
    _ensure_model(YUNET_PATH, _YUNET_URL)
    _ensure_model(SFACE_PATH, _SFACE_URL)
    _detector = cv2.FaceDetectorYN.create(
        str(YUNET_PATH),
        config="",
        input_size=(320, 320),
        score_threshold=0.85,
        nms_threshold=0.3,
        top_k=5000,
        backend_id=cv2.dnn.DNN_BACKEND_DEFAULT,
        target_id=cv2.dnn.DNN_TARGET_CPU,
    )
    _recognizer = cv2.FaceRecognizerSF.create(
        str(SFACE_PATH),
        config="",
        backend_id=cv2.dnn.DNN_BACKEND_DEFAULT,
        target_id=cv2.dnn.DNN_TARGET_CPU,
    )
    logger.info("Face engine ready -- YuNet: %s, SFace: %s", YUNET_PATH.name, SFACE_PATH.name)


def is_ready() -> bool:
    return _detector is not None and _recognizer is not None


@dataclass
class FaceMatchResult:
    matched: bool
    score: float
    cosine_distance: float
    l2_distance: float
    reference_face_found: bool
    live_face_found: bool
    error: Optional[str] = None


def _decode_image(raw_bytes: bytes, label: str) -> Optional[np.ndarray]:
    arr = np.frombuffer(raw_bytes, dtype=np.uint8)
    img = cv2.imdecode(arr, cv2.IMREAD_COLOR)
    if img is None:
        logger.warning("Could not decode %s image bytes.", label)
    return img


def _detect_largest_face(img: np.ndarray) -> Optional[np.ndarray]:
    if _detector is None:
        raise RuntimeError("Face detector is not initialised -- call load_models() first.")
    h, w = img.shape[:2]
    _detector.setInputSize((w, h))
    _, faces = _detector.detect(img)
    if faces is None or len(faces) == 0:
        return None
    areas = faces[:, 2] * faces[:, 3]
    return faces[np.argmax(areas)]


def _extract_feature(img: np.ndarray, face_box: np.ndarray) -> np.ndarray:
    if _recognizer is None:
        raise RuntimeError("Face recogniser is not initialised -- call load_models() first.")
    aligned = _recognizer.alignCrop(img, face_box)
    return _recognizer.feature(aligned)


def match_faces(
    reference_bytes: bytes,
    live_bytes: bytes,
    threshold: float = DEFAULT_COSINE_THRESHOLD,
) -> FaceMatchResult:
    """Compare a reference face image with a live selfie entirely server-side."""
    if not is_ready():
        return FaceMatchResult(
            matched=False, score=0.0, cosine_distance=2.0, l2_distance=float("inf"),
            reference_face_found=False, live_face_found=False,
            error="Face engine not initialised",
        )
    ref_img = _decode_image(reference_bytes, "reference")
    live_img = _decode_image(live_bytes, "live")
    if ref_img is None or live_img is None:
        return FaceMatchResult(
            matched=False, score=0.0, cosine_distance=2.0, l2_distance=float("inf"),
            reference_face_found=False, live_face_found=False,
            error="One or both images could not be decoded",
        )
    ref_face = _detect_largest_face(ref_img)
    live_face = _detect_largest_face(live_img)
    ref_found = ref_face is not None
    live_found = live_face is not None
    if not ref_found or not live_found:
        return FaceMatchResult(
            matched=False, score=0.0, cosine_distance=2.0, l2_distance=float("inf"),
            reference_face_found=ref_found, live_face_found=live_found,
            error="No face detected in one or both images",
        )
    ref_feat = _extract_feature(ref_img, ref_face)
    live_feat = _extract_feature(live_img, live_face)
    cosine_score = float(_recognizer.match(ref_feat, live_feat, cv2.FaceRecognizerSF.FR_COSINE))
    l2_distance = float(_recognizer.match(ref_feat, live_feat, cv2.FaceRecognizerSF.FR_NORM_L2))
    score_pct = round(cosine_score * 100, 2)
    cosine_distance = round(1.0 - cosine_score, 6)
    matched = cosine_score >= threshold
    logger.info(
        "Face match -- cosine_score=%.4f score_pct=%.2f l2=%.4f matched=%s threshold=%.3f",
        cosine_score, score_pct, l2_distance, matched, threshold,
    )
    return FaceMatchResult(
        matched=matched, score=score_pct,
        cosine_distance=cosine_distance, l2_distance=round(l2_distance, 6),
        reference_face_found=True, live_face_found=True,
    )
