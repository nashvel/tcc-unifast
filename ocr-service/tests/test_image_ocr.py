"""Image OCR endpoint and utility tests."""

from app.ocr_engine import calculate_average_confidence
from app.qr_detector import detect_qr_code
from app.text_cleaner import clean_text


def test_valid_image_upload(client, sample_png_bytes, mocked_tesseract):
    response = client.post(
        "/ocr/image",
        files={"file": ("student.png", sample_png_bytes, "image/png")},
    )

    assert response.status_code == 200
    payload = response.json()
    assert payload["success"] is True
    assert payload["document_type"] == "image"
    assert payload["result"]["cleaned_text"] == "TCC STUDENT ID\nJUAN"
    assert payload["result"]["word_count"] == 4
    assert payload["metadata"]["width"] == 900
    assert payload["qr_code"]["found"] is False


def test_unsupported_image_type(client):
    response = client.post(
        "/ocr/image",
        files={"file": ("notes.txt", b"hello", "text/plain")},
    )

    assert response.status_code == 400
    assert response.json()["error"]["code"] == "UNSUPPORTED_FILE_TYPE"


def test_empty_file(client):
    response = client.post(
        "/ocr/image",
        files={"file": ("empty.png", b"", "image/png")},
    )

    assert response.status_code == 400
    assert response.json()["error"]["code"] == "EMPTY_FILE"


def test_oversized_file(client, settings):
    settings.max_image_size_bytes = 4
    response = client.post(
        "/ocr/image",
        files={"file": ("large.png", b"\x89PNG\r\n\x1a\noversized", "image/png")},
    )

    assert response.status_code == 400
    assert response.json()["error"]["code"] == "FILE_TOO_LARGE"


def test_qr_detector_no_result_behavior(sample_png_bytes):
    import cv2
    import numpy as np

    image = cv2.imdecode(np.frombuffer(sample_png_bytes, dtype=np.uint8), cv2.IMREAD_COLOR)
    result = detect_qr_code(image)

    assert result.found is False
    assert result.bounding_box == []


def test_text_cleaner_preserves_ids_and_grades():
    cleaned = clean_text("JUAN   DELA   CRUZ\r\n\r\n\r\n2026-000001   GWA: 1.25")

    assert cleaned == "JUAN DELA CRUZ\n\n2026-000001 GWA: 1.25"


def test_confidence_calculation_ignores_negative_values():
    assert calculate_average_confidence([96.0, -1.0, 80.0]) == 88.0
    assert calculate_average_confidence([-1.0]) == 0.0

