"""PDF extraction tests."""

import fitz


def test_digital_pdf_extraction(client, digital_pdf_bytes):
    response = client.post(
        "/ocr/pdf",
        files={"file": ("digital.pdf", digital_pdf_bytes, "application/pdf")},
    )

    assert response.status_code == 200
    payload = response.json()
    assert payload["result"]["page_count"] == 1
    assert payload["result"]["pages"][0]["method"] == "embedded_text"
    assert "JUAN DELA CRUZ" in payload["result"]["combined_text"]


def test_scanned_pdf_ocr_path(client, scanned_pdf_bytes, mocked_tesseract):
    response = client.post(
        "/ocr/pdf",
        files={"file": ("scan.pdf", scanned_pdf_bytes, "application/pdf")},
    )

    assert response.status_code == 200
    page = response.json()["result"]["pages"][0]
    assert page["method"] == "tesseract_ocr"
    assert page["ocr"]["word_count"] == 4


def test_invalid_pdf(client):
    response = client.post(
        "/ocr/pdf",
        files={"file": ("bad.pdf", b"%PDF-not really a pdf", "application/pdf")},
    )

    assert response.status_code == 400
    assert response.json()["error"]["code"] == "INVALID_PDF"


def test_pdf_rejects_wrong_mime_type(client, digital_pdf_bytes):
    response = client.post(
        "/ocr/pdf",
        files={"file": ("digital.txt", digital_pdf_bytes, "text/plain")},
    )

    assert response.status_code == 400
    assert response.json()["error"]["code"] == "INVALID_PDF"


def test_encrypted_pdf(client):
    document = fitz.open()
    document.new_page().insert_text((72, 72), "secret")
    encrypted = document.tobytes(
        encryption=fitz.PDF_ENCRYPT_AES_256,
        owner_pw="owner",
        user_pw="user",
        permissions=0,
    )

    response = client.post(
        "/ocr/pdf",
        files={"file": ("encrypted.pdf", encrypted, "application/pdf")},
    )

    assert response.status_code == 400
    assert response.json()["error"]["code"] == "ENCRYPTED_PDF"


def test_pdf_page_limit(client, settings):
    settings.max_pdf_pages = 1
    document = fitz.open()
    document.new_page().insert_text((72, 72), "page one useful text")
    document.new_page().insert_text((72, 72), "page two useful text")

    response = client.post(
        "/ocr/pdf",
        files={"file": ("too-many.pdf", document.tobytes(), "application/pdf")},
    )

    assert response.status_code == 400
    assert response.json()["error"]["code"] == "TOO_MANY_PDF_PAGES"
