#!/usr/bin/env python3
"""
Extract text fields from Grade Slip / Course History PDFs (PyMuPDF / fitz).

Install (from this folder):
  python -m venv .venv && .venv\\Scripts\\pip install -r requirements.txt

Run:
  python\\.venv\\Scripts\\python.exe python\\pdf_extract.py path\\to\\file.pdf
  python\\.venv\\Scripts\\python.exe python\\pdf_extract.py path\\to\\file.pdf --type grade_slip

Laravel OCR currently POSTs to OCR_SERVICE_URL (/ocr/pdf|/ocr/image). Use this
CLI for local extraction until a FastAPI OCR service wraps these helpers.
"""

from __future__ import annotations

import argparse
import json
import re
import sys
from pathlib import Path
from typing import Any

import fitz  # PyMuPDF


# Patterns aimed at TES Grade Slip + Course History style documents.
RE_STUDENT_ID = re.compile(
    r"(?:student\s*(?:id|no\.?|number)|school\s*id|id\s*no\.?)\s*[:#]?\s*([A-Za-z0-9\-./]+)",
    re.I,
)
RE_NAME = re.compile(
    r"(?:student\s*name|full\s*name|name)\s*[:#]?\s*([A-Za-zÑñ ,.'\-]+)",
    re.I,
)
RE_GWA = re.compile(
    r"(?:GWA|general\s*weighted\s*average|weighted\s*average)\s*[:#]?\s*([0-9]+(?:\.[0-9]+)?)",
    re.I,
)
RE_SEMESTER = re.compile(
    r"(?:semester|term)\s*[:#]?\s*((?:1st|2nd|3rd|first|second|third|summer|midyear)[^,\n]{0,40})",
    re.I,
)
RE_SCHOOL_YEAR = re.compile(
    r"(?:school\s*year|academic\s*year|SY|A\.?Y\.?)\s*[:#]?\s*((?:20\d{2})\s*[-–/]\s*(?:20\d{2}|\d{2}))",
    re.I,
)
RE_PROGRAM_HEADER = re.compile(
    r"\b([A-Z]{2,8}(?:\s?[A-Z0-9]{1,6})?)\s*[—\-–]\s*Year\b",
    re.I,
)
RE_SEMESTER_GPA = re.compile(
    r"Semester\s*GPA\s*[:#]?\s*([0-9]+(?:\.[0-9]+)?)",
    re.I,
)
RE_GRADE_AFTER_UNITS = re.compile(r"\b([1-6])\s+([1-5](?:\.\d{1,2})?)\b")


def extract_text(pdf_path: Path) -> str:
    doc = fitz.open(pdf_path)
    try:
        parts: list[str] = []
        for page in doc:
            parts.append(page.get_text("text") or "")
        return "\n".join(parts).strip()
    finally:
        doc.close()


def extract_metadata(pdf_path: Path) -> dict[str, Any]:
    doc = fitz.open(pdf_path)
    try:
        raw = doc.metadata or {}
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
            "is_encrypted": bool(doc.is_encrypted),
            "page_count": doc.page_count,
            "engine": "pymupdf",
        }
    finally:
        doc.close()


def _first(pattern: re.Pattern[str], text: str) -> str | None:
    m = pattern.search(text)
    if not m:
        return None
    return re.sub(r"\s+", " ", m.group(1)).strip(" :#-\t") or None


def parse_fields(text: str, document_type: str | None = None) -> dict[str, Any]:
    """Return fields aligned with planned ocr_results columns."""
    grades = [float(m.group(2)) for m in RE_GRADE_AFTER_UNITS.finditer(text)]
    program = _first(RE_PROGRAM_HEADER, text)
    return {
        "document_type": document_type,
        "extracted_text": text,
        "extracted_name": _first(RE_NAME, text),
        "extracted_student_id": _first(RE_STUDENT_ID, text),
        "extracted_gwa": _first(RE_GWA, text) or _first(RE_SEMESTER_GPA, text),
        "extracted_semester": _first(RE_SEMESTER, text),
        "extracted_school_year": _first(RE_SCHOOL_YEAR, text),
        "extracted_program": program,
        "extracted_grades": grades,
        "page_char_count": len(text),
        "engine": "pymupdf",
    }


def extract_pdf(pdf_path: Path, document_type: str | None = None) -> dict[str, Any]:
    text = extract_text(pdf_path)
    result = parse_fields(text, document_type=document_type)
    result["source_path"] = str(pdf_path)
    result["combined_text"] = text  # shape compatible with Laravel PDF pipeline
    result["pdf_metadata"] = extract_metadata(pdf_path)
    result["method"] = "pymupdf_text_layer"
    result["has_useful_text"] = len([c for c in text if c.isalnum()]) >= 10
    return result


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(
        description="Extract Grade Slip / Course History fields from a PDF via PyMuPDF (text layer + metadata). Tesseract is not used here."
    )
    parser.add_argument("pdf", type=Path, help="Path to PDF file")
    parser.add_argument(
        "--type",
        choices=["grade_slip", "course_history", "auto"],
        default="auto",
        help="Document slot hint (default: auto)",
    )
    parser.add_argument("--pretty", action="store_true", help="Pretty-print JSON")
    args = parser.parse_args(argv)

    if not args.pdf.is_file():
        print(json.dumps({"success": False, "error": f"File not found: {args.pdf}"}))
        return 1

    doc_type = None if args.type == "auto" else args.type
    try:
        payload = extract_pdf(args.pdf, document_type=doc_type)
        out = {"success": True, "document_type": "pdf", "result": payload}
    except Exception as exc:  # noqa: BLE001 — CLI surface
        print(json.dumps({"success": False, "error": str(exc)}))
        return 2

    print(json.dumps(out, indent=2 if args.pretty else None, ensure_ascii=False))
    return 0


if __name__ == "__main__":
    sys.exit(main())
