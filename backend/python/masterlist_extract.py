#!/usr/bin/env python3
"""
Extract a grantee/student table from a CHED masterlist in DOCX or PDF format.

Usage:
  python masterlist_extract.py <file.docx|file.pdf>
  python masterlist_extract.py <file.docx|file.pdf> --pretty

Output (stdout, UTF-8 JSON):
  {
    "success": true,
    "file_type": "docx"|"pdf",
    "table_index": 0,
    "raw_headers": ["Award No.", "Last Name", "First Name", ...],
    "matched_columns": { "award_number": "Award No.", "last_name": "Last Name", ... },
    "unmatched_headers": ["School", "Region"],
    "row_count": 42,
    "rows": [
      { "student_id": "...", "full_name": "...", "email": "...", "program": "...", "year_level": "..." }
    ]
  }

On failure:
  {
    "success": false,
    "error": "No masterlist table found ...",
    "tables_found": 2,
    "raw_headers_per_table": [["No.", "School"], ["Award No.", "Last Name", ...]]
  }

Install (from backend/python folder):
  pip install python-docx pymupdf
Or use the shared venv:
  .venv/Scripts/pip install python-docx
"""

from __future__ import annotations

import argparse
import json
import re
import sys
from pathlib import Path
from typing import Any

# ──────────────────────────────────────────────────────────────────────────────
# Alias map — maps raw CHED header text (lowercased) to canonical field names.
# Add more aliases here when actual CHED files arrive (see CHED_FORMAT.md).
# ──────────────────────────────────────────────────────────────────────────────
_MASTERLIST_ALIASES: dict[str, str] = {
    # student_id
    "student id": "student_id",
    "studentid": "student_id",
    "id number": "student_id",
    "id no": "student_id",
    "id no.": "student_id",
    "tes id": "student_id",
    "tes id no": "student_id",
    "tes id number": "student_id",
    "award no": "award_number",
    "award no.": "award_number",
    "award number": "award_number",
    "awno": "award_number",
    # student_number (school-assigned)
    "student no": "student_number",
    "student no.": "student_number",
    "student number": "student_number",
    "school id": "student_number",
    "school id number": "student_number",
    "school id no": "student_number",
    # full_name (composite)
    "name": "full_name",
    "full name": "full_name",
    "fullname": "full_name",
    "student name": "full_name",
    "grantee name": "full_name",
    "grantee": "full_name",
    # name parts (assembled if full_name missing)
    "last name": "last_name",
    "lastname": "last_name",
    "surname": "last_name",
    "family name": "last_name",
    "given name": "given_name",
    "given names": "given_name",
    "givenname": "given_name",
    "first name": "given_name",
    "firstname": "given_name",
    "middle name": "middle_name",
    "middlename": "middle_name",
    "middle initial": "middle_name",
    "ext": "name_ext",
    "extension": "name_ext",
    "extension name": "name_ext",
    "suffix": "name_ext",
    # email
    "email": "email",
    "email address": "email",
    "emailaddress": "email",
    "student email": "email",
    "e-mail": "email",
    # program
    "program": "program",
    "course": "program",
    "degree program": "program",
    "degree": "program",
    "academic program": "program",
    # year_level
    "year": "year_level",
    "year level": "year_level",
    "yearlevel": "year_level",
    "level": "year_level",
    "year of study": "year_level",
}

# Fields required to consider a table a "masterlist" candidate.
_REQUIRED_FIELDS = {"full_name", "last_name", "given_name", "student_id", "award_number"}
# Minimum score (matched columns) to pick a table.
_MIN_SCORE = 2
# Fields needed for a complete row (at least one of these must be mapped).
_ID_FIELDS = {"student_id", "award_number"}


def _clean(value: Any) -> str:
    """Normalize cell value to trimmed string."""
    if value is None:
        return ""
    text = str(value).replace("\n", " ")
    return re.sub(r"\s+", " ", text).strip()


def _norm_header(value: str) -> str:
    """Lowercase + collapse whitespace for alias lookup."""
    return re.sub(r"\s+", " ", _clean(value).lower().replace("-", " ").replace("_", " ")).strip()


def _map_header(raw: str) -> str | None:
    """Return canonical field name or None."""
    key = _norm_header(raw)
    if key in _MASTERLIST_ALIASES:
        return _MASTERLIST_ALIASES[key]
    # Compact match (strip all non-alphanumeric)
    compact = re.sub(r"[^a-z0-9]", "", key)
    for alias, field in _MASTERLIST_ALIASES.items():
        if re.sub(r"[^a-z0-9]", "", alias) == compact:
            return field
    return None


def _score_headers(raw_headers: list[str]) -> tuple[dict[int, str], set[str]]:
    """
    Map column indices to canonical field names.
    Returns (mapping, matched_fields).
    """
    mapping: dict[int, str] = {}
    for idx, h in enumerate(raw_headers):
        field = _map_header(h)
        if field and field not in mapping.values():
            mapping[idx] = field
    return mapping, set(mapping.values())


def _assemble_name(row_data: dict[str, str]) -> str | None:
    """Build full_name from last_name + given_name + middle_name + name_ext."""
    parts = []
    last = row_data.get("last_name", "").strip(" ,")
    given = row_data.get("given_name", "").strip()
    middle = row_data.get("middle_name", "").strip()
    ext = row_data.get("name_ext", "").strip()

    if last:
        parts.append(last + ",")
    if given:
        parts.append(given)
    if middle:
        parts.append(middle)
    if ext:
        parts.append(ext)
    result = " ".join(parts).strip(" ,")
    return result or None


def _normalize_year(raw: str) -> str | None:
    """Normalize year level: '1', '1st', 'Year 1', 'First' → '1'."""
    text = raw.strip()
    if not text:
        return None
    # Already a digit
    m = re.match(r"^([1-6])(?:st|nd|rd|th)?$", text, re.I)
    if m:
        return m.group(1)
    # 'Year 1'
    m = re.match(r"year\s*([1-6])", text, re.I)
    if m:
        return m.group(1)
    # Written out
    words = {"first": "1", "second": "2", "third": "3", "fourth": "4", "fifth": "5"}
    if text.lower() in words:
        return words[text.lower()]
    # Return as-is (fallback)
    return text or None


def _build_row(mapping: dict[int, str], cells: list[str]) -> dict[str, str | None]:
    """Build a normalized masterlist row dict from raw cell values."""
    raw: dict[str, str] = {}
    for idx, field in mapping.items():
        value = _clean(cells[idx]) if idx < len(cells) else ""
        if value:
            raw[field] = value

    row: dict[str, str | None] = {
        "student_id": raw.get("student_id") or raw.get("award_number"),
        "award_number": raw.get("award_number"),
        "student_number": raw.get("student_number"),
        "full_name": raw.get("full_name") or _assemble_name(raw),
        "email": raw.get("email"),
        "program": raw.get("program"),
        "year_level": _normalize_year(raw.get("year_level", "")) if raw.get("year_level") else None,
    }
    return row


def _is_data_row(row: dict[str, str | None]) -> bool:
    """Filter out blank or header-repeat rows."""
    id_val = (row.get("student_id") or "").strip()
    name_val = (row.get("full_name") or "").strip()
    # Reject obvious header repeats
    if _norm_header(id_val) in {"student id", "id number", "award no", "award number", "id no"}:
        return False
    if _norm_header(name_val) in {"name", "full name", "student name", "grantee"}:
        return False
    return bool(id_val or name_val)


# ──────────────────────────────────────────────────────────────────────────────
# DOCX extraction
# ──────────────────────────────────────────────────────────────────────────────

def _docx_table_to_cells(table: Any) -> list[list[str]]:
    """Extract rows × cells from a python-docx Table object."""
    rows = []
    for row in table.rows:
        cells = [_clean(cell.text) for cell in row.cells]
        rows.append(cells)
    return rows


def _extract_from_docx(path: Path) -> dict[str, Any]:
    try:
        import docx  # python-docx
    except ImportError:
        return {
            "success": False,
            "error": "python-docx is not installed. Run: pip install python-docx",
        }

    doc = docx.Document(str(path))
    tables_raw: list[list[list[str]]] = [_docx_table_to_cells(t) for t in doc.tables]
    return _pick_best_table(tables_raw, file_type="docx")


# ──────────────────────────────────────────────────────────────────────────────
# PDF extraction (PyMuPDF — same library used by pdf_extract.py)
# ──────────────────────────────────────────────────────────────────────────────

def _extract_from_pdf(path: Path) -> dict[str, Any]:
    try:
        import fitz  # PyMuPDF
    except ImportError:
        return {
            "success": False,
            "error": "PyMuPDF is not installed. Run: pip install pymupdf",
        }

    tables_raw: list[list[list[str]]] = []

    doc = fitz.open(str(path))
    try:
        for page in doc:
            # Redirect stdout so PyMuPDF layout hints don't pollute our JSON output
            _stdout = sys.stdout
            try:
                sys.stdout = sys.stderr
                finder = page.find_tables()
            finally:
                sys.stdout = _stdout

            for table in getattr(finder, "tables", []):
                try:
                    raw = table.extract() or []
                except Exception:
                    continue
                if raw:
                    tables_raw.append([[_clean(cell) for cell in row] for row in raw])
    finally:
        doc.close()

    return _pick_best_table(tables_raw, file_type="pdf")


# ──────────────────────────────────────────────────────────────────────────────
# Shared table scoring + selection
# ──────────────────────────────────────────────────────────────────────────────

def _pick_best_table(
    tables_raw: list[list[list[str]]],
    file_type: str,
) -> dict[str, Any]:
    """Score all tables and return the best-matching one as a result dict."""

    all_raw_headers: list[list[str]] = []
    best_score = -1
    best_idx = -1
    best_mapping: dict[int, str] = {}
    best_headers: list[str] = []
    best_rows: list[list[str]] = []
    best_header_row_idx = 0

    for t_idx, table in enumerate(tables_raw):
        if not table:
            all_raw_headers.append([])
            continue

        # Try first 3 rows as potential header rows
        chosen_header_row = 0
        chosen_mapping: dict[int, str] = {}
        chosen_fields: set[str] = set()
        chosen_raw: list[str] = []

        for h_idx in range(min(3, len(table))):
            row = table[h_idx]
            mapping, fields = _score_headers(row)
            if len(fields) > len(chosen_fields):
                chosen_header_row = h_idx
                chosen_mapping = mapping
                chosen_fields = fields
                chosen_raw = row

        all_raw_headers.append(chosen_raw)

        # Score = number of recognized canonical fields
        score = len(chosen_fields)
        if score > best_score:
            best_score = score
            best_idx = t_idx
            best_mapping = chosen_mapping
            best_headers = chosen_raw
            best_rows = table[chosen_header_row + 1:]
            best_header_row_idx = chosen_header_row

    if best_score < _MIN_SCORE or best_idx < 0:
        return {
            "success": False,
            "error": (
                f"No masterlist table found with enough recognizable columns "
                f"(best score: {best_score}/{_MIN_SCORE} required). "
                "Check CHED_FORMAT.md to add missing column aliases."
            ),
            "tables_found": len(tables_raw),
            "raw_headers_per_table": all_raw_headers,
        }

    # Build canonical matched/unmatched info
    matched_columns: dict[str, str] = {
        field: best_headers[idx]
        for idx, field in best_mapping.items()
    }
    unmatched: list[str] = [
        h for i, h in enumerate(best_headers) if i not in best_mapping and h.strip()
    ]

    # Extract data rows
    rows_out: list[dict[str, str | None]] = []
    for cells in best_rows:
        if not any(_clean(c) for c in cells):
            continue  # skip blank rows
        row = _build_row(best_mapping, cells)
        if _is_data_row(row):
            rows_out.append(row)

    return {
        "success": True,
        "file_type": file_type,
        "table_index": best_idx,
        "raw_headers": best_headers,
        "matched_columns": matched_columns,
        "unmatched_headers": unmatched,
        "row_count": len(rows_out),
        "rows": rows_out,
    }


# ──────────────────────────────────────────────────────────────────────────────
# CLI entry point
# ──────────────────────────────────────────────────────────────────────────────

def emit_json(payload: dict[str, Any], *, pretty: bool = False) -> None:
    text = json.dumps(payload, indent=2 if pretty else None, ensure_ascii=False, allow_nan=False)
    sys.stdout.buffer.write(text.encode("utf-8", errors="replace"))
    sys.stdout.buffer.write(b"\n")


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(
        description="Extract a grantee table from a CHED masterlist DOCX or PDF."
    )
    parser.add_argument("file", type=Path, help="Path to .docx or .pdf masterlist file")
    parser.add_argument("--pretty", action="store_true", help="Pretty-print JSON output")
    args = parser.parse_args(argv)

    if not args.file.is_file():
        emit_json({"success": False, "error": f"File not found: {args.file}"})
        return 1

    ext = args.file.suffix.lower()
    try:
        if ext == ".docx":
            result = _extract_from_docx(args.file)
        elif ext == ".pdf":
            result = _extract_from_pdf(args.file)
        else:
            emit_json({"success": False, "error": f"Unsupported file type: {ext}. Use .docx or .pdf"})
            return 1
    except Exception as exc:  # noqa: BLE001
        emit_json({"success": False, "error": str(exc)})
        return 2

    emit_json(result, pretty=args.pretty)
    return 0 if result.get("success") else 1


if __name__ == "__main__":
    sys.exit(main())
