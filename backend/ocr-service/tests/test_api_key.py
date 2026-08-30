"""Shared-secret enforcement on the OCR endpoints."""

import pytest
from fastapi.testclient import TestClient

from app.config import Settings, get_settings
from app.main import app


@pytest.fixture
def keyed_client() -> TestClient:
    """Client for a service configured with an API key."""
    app.dependency_overrides[get_settings] = lambda: Settings(api_key="secret-key")
    yield TestClient(app)
    app.dependency_overrides.pop(get_settings, None)


def test_missing_key_is_rejected(keyed_client: TestClient) -> None:
    response = keyed_client.post("/ocr/image", files={"file": ("x.jpg", b"\xff\xd8\xff", "image/jpeg")})
    assert response.status_code == 401


def test_wrong_key_is_rejected(keyed_client: TestClient) -> None:
    response = keyed_client.post(
        "/ocr/pdf",
        files={"file": ("x.pdf", b"%PDF-1.4", "application/pdf")},
        headers={"X-OCR-Key": "wrong"},
    )
    assert response.status_code == 401


def test_correct_key_passes_authentication(keyed_client: TestClient) -> None:
    """Auth passes; the upload is then rejected on its own merits, not on 401."""
    response = keyed_client.post(
        "/ocr/image",
        files={"file": ("x.jpg", b"not-a-real-jpeg", "image/jpeg")},
        headers={"X-OCR-Key": "secret-key"},
    )
    assert response.status_code != 401


def test_health_stays_open(keyed_client: TestClient) -> None:
    """Health must not require the key — container probes depend on it."""
    assert keyed_client.get("/health").status_code == 200
