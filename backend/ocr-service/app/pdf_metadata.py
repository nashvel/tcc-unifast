"""PDF document metadata extraction via PyMuPDF (no authenticity scoring here)."""

from __future__ import annotations

from typing import Any

import fitz


def extract_pdf_metadata(document: fitz.Document) -> dict[str, Any]:
    """Return native PDF info dictionary fields plus encryption flag."""
    raw = document.metadata or {}
    return {
        "format": raw.get("format") or None,
        "title": raw.get("title") or None,
        "author": raw.get("author") or None,
        "subject": raw.get("subject") or None,
        "keywords": raw.get("keywords") or None,
        "creator": raw.get("creator") or None,
        "producer": raw.get("producer") or None,
        "creationDate": raw.get("creationDate") or None,
        "modDate": raw.get("modDate") or None,
        "encryption": raw.get("encryption") or None,
        "is_encrypted": bool(document.is_encrypted),
        "page_count": document.page_count,
        "engine": "pymupdf",
    }
