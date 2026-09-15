"""Tests for face_engine and /face/match endpoint."""

from unittest.mock import patch
from app.face_engine import FaceMatchResult, match_faces, is_ready


def test_match_faces_returns_error_when_engine_not_ready():
    with patch("app.face_engine.is_ready", return_value=False):
        result = match_faces(b"ref", b"live")
        assert result.matched is False
        assert result.reference_face_found is False
        assert result.live_face_found is False
        assert result.error == "Face engine not initialised"


def test_match_faces_handles_undecodable_bytes():
    with patch("app.face_engine.is_ready", return_value=True):
        result = match_faces(b"notanimage", b"notanimage")
        assert result.matched is False
        assert result.reference_face_found is False
        assert result.live_face_found is False
        assert "could not be decoded" in (result.error or "")


def test_face_match_endpoint_503_when_engine_unavailable(client, sample_png_bytes):
    with patch("app.face_engine.is_ready", return_value=False):
        response = client.post(
            "/face/match",
            files={
                "reference_file": ("ref.png", sample_png_bytes, "image/png"),
                "live_file": ("live.png", sample_png_bytes, "image/png"),
            },
        )
        assert response.status_code == 503
        assert response.json()["error"]["code"] == "FACE_ENGINE_UNAVAILABLE"


def test_face_match_endpoint_success(client, sample_png_bytes):
    mock_result = FaceMatchResult(
        matched=True,
        score=92.5,
        cosine_distance=0.075,
        l2_distance=0.45,
        reference_face_found=True,
        live_face_found=True,
    )
    with (
        patch("app.face_engine.is_ready", return_value=True),
        patch("app.face_engine.match_faces", return_value=mock_result),
    ):
        response = client.post(
            "/face/match",
            files={
                "reference_file": ("ref.png", sample_png_bytes, "image/png"),
                "live_file": ("live.png", sample_png_bytes, "image/png"),
            },
        )
        assert response.status_code == 200
        payload = response.json()
        assert payload["success"] is True
        assert payload["matched"] is True
        assert payload["score"] == 92.5
        assert payload["cosine_distance"] == 0.075
        assert payload["reference_face_found"] is True
        assert payload["live_face_found"] is True
