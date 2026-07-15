"""Health endpoint tests."""

from unittest.mock import patch


def test_health_endpoint_reports_tesseract(client):
    with patch("app.main.is_tesseract_available", return_value=True):
        response = client.get("/health")

    assert response.status_code == 200
    assert response.json() == {
        "success": True,
        "status": "healthy",
        "ocr_engine": "tesseract",
        "tesseract_available": True,
    }


def test_missing_tesseract_behavior(client, sample_png_bytes):
    with patch("app.ocr_engine.is_tesseract_available", return_value=False):
        response = client.post(
            "/ocr/image",
            files={"file": ("student.png", sample_png_bytes, "image/png")},
        )

    assert response.status_code == 503
    assert response.json()["error"]["code"] == "TESSERACT_UNAVAILABLE"

