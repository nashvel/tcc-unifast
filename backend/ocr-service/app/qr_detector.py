"""QR code detection via pyzbar (preferred) with OpenCV fallback.

Soft-fails when ZBar DLLs are missing on Windows — never blocks OCR.
"""

from __future__ import annotations

import importlib.util
import os
import sys
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


def _pyzbar_package_dirs() -> list[Path]:
    """Locate site-packages/pyzbar without importing the C extension."""
    dirs: list[Path] = []
    try:
        spec = importlib.util.find_spec("pyzbar")
        if spec is not None:
            if spec.origin:
                dirs.append(Path(spec.origin).resolve().parent)
            if spec.submodule_search_locations:
                for loc in spec.submodule_search_locations:
                    dirs.append(Path(loc).resolve())
    except Exception:  # noqa: BLE001
        pass

    for entry in sys.path:
        candidate = Path(entry) / "pyzbar"
        if candidate.is_dir():
            dirs.append(candidate.resolve())

    # Dedupe while preserving order.
    seen: set[str] = set()
    unique: list[Path] = []
    for path in dirs:
        key = str(path)
        if key not in seen:
            seen.add(key)
            unique.append(path)
    return unique


def _ensure_zbar_dll_path() -> None:
    """Add pyzbar / native dirs to PATH and os.add_dll_directory before decode import."""
    root = Path(__file__).resolve().parents[1]
    candidates: list[Path] = [
        *_pyzbar_package_dirs(),
        root / "native",
        root / "zbar",
    ]
    env_path = Path(os.environ.get("ZBAR_DLL_PATH", "") or "")
    if env_path:
        candidates.append(env_path)

    for path in candidates:
        if not path or not path.is_dir():
            continue
        path_str = str(path)
        current_path = os.environ.get("PATH", "")
        if path_str not in current_path.split(os.pathsep):
            os.environ["PATH"] = path_str + os.pathsep + current_path
        if hasattr(os, "add_dll_directory"):
            try:
                os.add_dll_directory(path_str)
            except (OSError, FileNotFoundError):
                pass


# Register DLL dirs once at import so later pyzbar loads see them.
_ensure_zbar_dll_path()


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
