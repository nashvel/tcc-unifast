"""Shared test fixtures."""

from io import BytesIO
from unittest.mock import patch

import fitz
import pytest
from fastapi.testclient import TestClient
from PIL import Image, ImageDraw

from app.config import Settings, get_settings
from app.main import app


@pytest.fixture
def settings(tmp_path) -> Settings:
    return Settings(output_dir=tmp_path)


@pytest.fixture
def client(settings: Settings) -> TestClient:
    app.dependency_overrides[get_settings] = lambda: settings
    with TestClient(app) as test_client:
        yield test_client
    app.dependency_overrides.clear()


@pytest.fixture
def sample_png_bytes() -> bytes:
    image = Image.new("RGB", (900, 500), "white")
    draw = ImageDraw.Draw(image)
    lines = [
        "TCC STUDENT ID",
        "JUAN DELA CRUZ",
        "2026-000001",
        "BS INFORMATION TECHNOLOGY",
    ]
    for index, line in enumerate(lines):
        draw.text((40, 40 + index * 45), line, fill="black")
    buffer = BytesIO()
    image.save(buffer, format="PNG")
    return buffer.getvalue()


@pytest.fixture
def digital_pdf_bytes() -> bytes:
    document = fitz.open()
    page = document.new_page()
    page.insert_text((72, 72), "TCC STUDENT ID\nJUAN DELA CRUZ\n2026-000001")
    return document.tobytes()


@pytest.fixture
def scanned_pdf_bytes(sample_png_bytes: bytes) -> bytes:
    document = fitz.open()
    page = document.new_page(width=900, height=500)
    page.insert_image(page.rect, stream=sample_png_bytes)
    return document.tobytes()


@pytest.fixture
def mocked_tesseract():
    data = {
        "text": ["", "TCC", "STUDENT", "ID", "JUAN"],
        "conf": ["-1", "95.5", "88.0", "92", "96.25"],
        "left": [0, 10, 60, 150, 10],
        "top": [0, 10, 10, 10, 60],
        "width": [0, 40, 80, 20, 70],
        "height": [0, 20, 20, 20, 20],
    }
    with (
        patch("app.ocr_engine.is_tesseract_available", return_value=True),
        patch("app.ocr_engine.pytesseract.get_tesseract_version", return_value="5.3.0"),
        patch("app.ocr_engine.pytesseract.image_to_string", return_value="TCC STUDENT ID\nJUAN"),
        patch("app.ocr_engine.pytesseract.image_to_data", return_value=data),
    ):
        yield

