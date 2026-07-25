"""Safe image validation and decoding."""

from io import BytesIO

import cv2
import numpy as np
from PIL import Image, ImageOps, UnidentifiedImageError

from app.errors import EMPTY_FILE, UNREADABLE_IMAGE, UNSUPPORTED_FILE_TYPE, OcrServiceError

IMAGE_MIME_SIGNATURES = {
    "image/jpeg": (b"\xff\xd8\xff",),
    "image/png": (b"\x89PNG\r\n\x1a\n",),
    "image/webp": (b"RIFF",),
}


def validate_image_upload(content: bytes, mime_type: str) -> None:
    """Validate image MIME type and file signature."""
    if not content:
        raise OcrServiceError(EMPTY_FILE, "Uploaded file is empty.")
    if mime_type not in IMAGE_MIME_SIGNATURES:
        raise OcrServiceError(
            UNSUPPORTED_FILE_TYPE,
            "Only JPEG, PNG, and WebP images are supported.",
        )
    if mime_type == "image/webp":
        if not (content.startswith(b"RIFF") and content[8:12] == b"WEBP"):
            raise OcrServiceError(UNSUPPORTED_FILE_TYPE, "File signature does not match WebP.")
        return
    if not any(content.startswith(signature) for signature in IMAGE_MIME_SIGNATURES[mime_type]):
        raise OcrServiceError(UNSUPPORTED_FILE_TYPE, "File signature does not match the MIME type.")


def decode_image(content: bytes) -> tuple[np.ndarray, Image.Image]:
    """Decode uploaded bytes into OpenCV BGR and Pillow RGB images."""
    try:
        with Image.open(BytesIO(content)) as source:
            image_format = source.format
            oriented = ImageOps.exif_transpose(source)
            rgb_image = oriented.convert("RGB")
            rgb_image.format = image_format
            cv_image = cv2.cvtColor(np.array(rgb_image), cv2.COLOR_RGB2BGR)
            return cv_image, rgb_image.copy()
    except (UnidentifiedImageError, OSError, ValueError) as exc:
        raise OcrServiceError(UNREADABLE_IMAGE, "The uploaded image could not be decoded.") from exc
