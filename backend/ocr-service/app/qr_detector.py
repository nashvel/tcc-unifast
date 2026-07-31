"""QR code detection via pyzbar (preferred) with OpenCV fallback.

Soft-fails when ZBar DLLs are missing on Windows — never blocks OCR.
"""

from __future__ import annotations

import os
from pathlib import Path

import cv2
import numpy as np
from PIL import Image

from app.schemas import QrResult

_NATIVE_DEP_NEEDLES = (
    "libzbar",
    "libiconv",
    "dll",
    "shared object",
    "could not find module",
    "zlib",
    "loadlibrary",
)


def _looks_like_native_dep_error(message: str) -> bool:
    lowered = message.lower()
    return any(n in lowered for n in _NATIVE_DEP_NEEDLES)


def _ensure_zbar_dll_path() -> None:
    """Prefer bundled / local ZBar DLLs under ocr-service/native when present."""
    root = Path(__file__).resolve().parents[1]
    candidates = [
        root / "native",
        root / "zbar",
        Path(os.environ.get("ZBAR_DLL_PATH", "")),
    ]
    # Also add the installed pyzbar package dir (ships libzbar-64.dll + libiconv.dll).
    try:
        import pyzbar as _pyzbar_pkg

        candidates.insert(0, Path(_pyzbar_pkg.__file__).resolve().parent)
    except Exception:  # noqa: BLE001
        pass

    for path in candidates:
        if path and path.is_dir():
            path_str = str(path)
            if path_str not in os.environ.get("PATH", ""):
                os.environ["PATH"] = path_str + os.pathsep + os.environ.get("PATH", "")
            if hasattr(os, "add_dll_directory"):
                try:
                    os.add_dll_directory(path_str)
                except (OSError, FileNotFoundError):
                    pass


def _decode_pyzbar(image: np.ndarray) -> QrResult | None:
    """Return QrResult on success, None if no codes, raise ImportError on missing DLL."""
    _ensure_zbar_dll_path()
    try:
        from pyzbar.pyzbar import decode as pyzbar_decode
    except Exception as exc:  # noqa: BLE001 — ImportError or Windows DLL load failures
        message = str(exc)
        if isinstance(exc, ImportError) or _looks_like_native_dep_error(message):
            raise ImportError(message) from exc
        raise

    if len(image.shape) == 2:
        pil = Image.fromarray(image)
    else:
        rgb = cv2.cvtColor(image, cv2.COLOR_BGR2RGB)
        pil = Image.fromarray(rgb)

    codes = pyzbar_decode(pil)
    if not codes:
        # Retry on upscaled / contrast-boosted gray for small phone captures.
        gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY) if len(image.shape) == 3 else image
        boosted = cv2.convertScaleAbs(gray, alpha=1.4, beta=10)
        large = cv2.resize(boosted, None, fx=1.8, fy=1.8, interpolation=cv2.INTER_CUBIC)
        codes = pyzbar_decode(Image.fromarray(large))

    if not codes:
        return None

    best = codes[0]
    raw = best.data.decode("utf-8", errors="replace")
    box: list[list[float]] = []
    if best.polygon:
        box = [[float(p.x), float(p.y)] for p in best.polygon]
    elif best.rect:
        r = best.rect
        box = [
            [float(r.left), float(r.top)],
            [float(r.left + r.width), float(r.top)],
            [float(r.left + r.width), float(r.top + r.height)],
            [float(r.left), float(r.top + r.height)],
        ]
    return QrResult(found=True, value=raw, type=str(best.type), engine="pyzbar", bounding_box=box)


def _decode_opencv(image: np.ndarray) -> QrResult:
    try:
        detector = cv2.QRCodeDetector()
        value, points, _ = detector.detectAndDecode(image)
        if value and points is not None:
            return QrResult(
                found=True,
                value=value,
                type="QRCODE",
                engine="opencv",
                bounding_box=points.reshape(-1, 2).tolist(),
            )
    except cv2.error:
        pass
    return QrResult(found=False, value=None, type=None, engine="opencv", bounding_box=[])


def detect_qr_code(image: np.ndarray) -> QrResult:
    """Detect a QR code without allowing detector failures to fail OCR."""
    try:
        result = _decode_pyzbar(image)
        if result is not None:
            return result
        # pyzbar available but no code — still try OpenCV once.
        return _decode_opencv(image)
    except ImportError as exc:
        fallback = _decode_opencv(image)
        if fallback.found:
            return fallback
        return QrResult(
            found=False,
            value=None,
            type=None,
            engine="unavailable",
            bounding_box=[],
            error=f"pyzbar unavailable: {exc}",
        )
    except Exception as exc:  # noqa: BLE001 — soft-fail any decode crash
        fallback = _decode_opencv(image)
        if fallback.found:
            return fallback
        return QrResult(
            found=False,
            value=None,
            type=None,
            engine="error",
            bounding_box=[],
            error=str(exc),
        )
