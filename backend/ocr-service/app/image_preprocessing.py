"""Reusable OpenCV preprocessing pipeline for document OCR."""

from pathlib import Path
from uuid import uuid4

import cv2
import numpy as np

from app.schemas import PreprocessingInfo


def _order_points(pts: np.ndarray) -> np.ndarray:
    """Order quadrilateral points: top-left, top-right, bottom-right, bottom-left."""
    rect = np.zeros((4, 2), dtype="float32")
    s = pts.sum(axis=1)
    rect[0] = pts[np.argmin(s)]
    rect[2] = pts[np.argmax(s)]
    diff = np.diff(pts, axis=1)
    rect[1] = pts[np.argmin(diff)]
    rect[3] = pts[np.argmax(diff)]
    return rect


def _four_point_transform(image: np.ndarray, pts: np.ndarray) -> np.ndarray:
    rect = _order_points(pts)
    (tl, tr, br, bl) = rect
    width_a = np.linalg.norm(br - bl)
    width_b = np.linalg.norm(tr - tl)
    max_width = max(int(width_a), int(width_b), 1)
    height_a = np.linalg.norm(tr - br)
    height_b = np.linalg.norm(tl - bl)
    max_height = max(int(height_a), int(height_b), 1)
    dst = np.array(
        [[0, 0], [max_width - 1, 0], [max_width - 1, max_height - 1], [0, max_height - 1]],
        dtype="float32",
    )
    matrix = cv2.getPerspectiveTransform(rect, dst)
    return cv2.warpPerspective(image, matrix, (max_width, max_height))


def warp_card_perspective(image: np.ndarray) -> tuple[np.ndarray, bool]:
    """
    Detect the largest rectangular card-like contour and apply a four-point warp.
    Falls back to the original image when no reliable contour is found.
    """
    height, width = image.shape[:2]
    if height < 40 or width < 40:
        return image, False

    scale = 800.0 / max(height, width)
    if scale < 1.0:
        small = cv2.resize(image, None, fx=scale, fy=scale, interpolation=cv2.INTER_AREA)
    else:
        small = image.copy()
        scale = 1.0

    gray = cv2.cvtColor(small, cv2.COLOR_BGR2GRAY)
    blur = cv2.GaussianBlur(gray, (5, 5), 0)
    edges = cv2.Canny(blur, 50, 150)
    edges = cv2.dilate(edges, np.ones((3, 3), np.uint8), iterations=1)

    contours, _ = cv2.findContours(edges, cv2.RETR_LIST, cv2.CHAIN_APPROX_SIMPLE)
    if not contours:
        return image, False

    area_img = float(small.shape[0] * small.shape[1])
    best: np.ndarray | None = None
    best_area = 0.0
    for contour in sorted(contours, key=cv2.contourArea, reverse=True)[:15]:
        peri = cv2.arcLength(contour, True)
        approx = cv2.approxPolyDP(contour, 0.02 * peri, True)
        if len(approx) != 4:
            continue
        area = float(cv2.contourArea(approx))
        if area < area_img * 0.18 or area > area_img * 0.98:
            continue
        if area > best_area:
            best_area = area
            best = approx.reshape(4, 2).astype("float32")

    if best is None:
        return image, False

    pts = best / scale
    warped = _four_point_transform(image, pts)
    wh, ww = warped.shape[:2]
    if wh < 80 or ww < 80:
        return image, False
    return warped, True


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


def enhance_for_ocr(image: np.ndarray) -> tuple[np.ndarray, float]:
    """CLAHE + denoise + adaptive threshold + rotation deskew on a BGR (or gray) image."""
    if len(image.shape) == 3:
        gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    else:
        gray = image
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
    return _deskew(thresholded)


def region_crops(warped_bgr: np.ndarray) -> list[tuple[str, np.ndarray]]:
    """
    Relative ROI heuristics for TCC School ID (after perspective warp).
    Front: upper/mid name+ID band. Back: emergency contact + footer lines.
    """
    h, w = warped_bgr.shape[:2]
    crops: list[tuple[str, np.ndarray]] = []

    # Upper third — name / student ID dense on front.
    crops.append(("upper", warped_bgr[0 : int(h * 0.42), 0:w]))
    # Mid band — IDs / course often sit here.
    crops.append(("mid", warped_bgr[int(h * 0.28) : int(h * 0.72), 0:w]))
    # Lower third — back emergency / footer contact.
    crops.append(("lower", warped_bgr[int(h * 0.55) : h, 0:w]))
    # Left column (back QR sits right; text often left).
    crops.append(("left", warped_bgr[0:h, 0 : int(w * 0.58)]))

    return [(name, crop) for name, crop in crops if crop.size > 0 and min(crop.shape[:2]) >= 24]


def preprocess_image(
    image: np.ndarray,
    save_debug_images: bool = False,
    output_dir: Path = Path("outputs"),
) -> tuple[np.ndarray, PreprocessingInfo, np.ndarray]:
    """
    Warp (optional), enhance, and return:
    - processed binary image for primary OCR
    - preprocessing metadata
    - warped BGR (or upscaled original) for QR / ROI / invert passes
    """
    original_height, original_width = image.shape[:2]
    working = image.copy()

    if min(original_width, original_height) < 1000:
        working = cv2.resize(working, None, fx=2, fy=2, interpolation=cv2.INTER_CUBIC)

    warped, did_warp = warp_card_perspective(working)
    deskewed, angle = enhance_for_ocr(warped)

    if save_debug_images:
        debug_dir = output_dir / "debug"
        debug_dir.mkdir(parents=True, exist_ok=True)
        stamp = uuid4().hex
        cv2.imwrite(str(debug_dir / f"{stamp}_warped.png"), warped)
        cv2.imwrite(str(debug_dir / f"{stamp}_processed.png"), deskewed)

    processed_height, processed_width = deskewed.shape[:2]
    info = PreprocessingInfo(
        original_width=original_width,
        original_height=original_height,
        processed_width=processed_width,
        processed_height=processed_height,
        deskew_angle=angle,
        perspective_warped=did_warp,
        inverted_pass=False,
        region_ocr=False,
        empty_text=False,
    )
    return deskewed, info, warped
