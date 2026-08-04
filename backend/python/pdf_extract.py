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


def _clean_text(value: str) -> str:
    """Normalize PDF text to valid Unicode (dompdf Latin-1 quirks, etc.)."""
    if not value:
        return ""
    return value.encode("utf-8", errors="surrogatepass").decode("utf-8", errors="replace")


def emit_json(payload: dict[str, Any], *, pretty: bool = False) -> None:
    """Write UTF-8 JSON to stdout (Windows cp1252 consoles corrupt ensure_ascii=False)."""
    text = json.dumps(
        payload,
        indent=2 if pretty else None,
        ensure_ascii=False,
        allow_nan=False,
    )
    sys.stdout.buffer.write(text.encode("utf-8", errors="replace"))
    sys.stdout.buffer.write(b"\n")


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
# Course History term block: "2023-2024 1st" + "BSED Filipino — Year 1st" + ENROLLED
RE_TERM_HEADER = re.compile(
    r"(20\d{2}\s*[-–/]\s*(?:20\d{2}|\d{2})\s+(?:1st|2nd|3rd|Summer|Midyear))"
    r"\s+"
    r"(.+?)"
    r"\s*[—\-–]\s*"
    r"Year\s*(1st|2nd|3rd|4th|5th|First|Second|Third|Fourth|[1-5])"
    r"(?:\s+(ENROLLED|ACCEPTED|DROPPED))?",
    re.I,
)
RE_SEMESTER_GPA = re.compile(
    r"Semester\s*GPA\s*[:#]?\s*([0-9]+(?:\.[0-9]+)?)",
    re.I,
)
RE_GPA_LINE = re.compile(r"\bGPA\s*[:#]?\s*([0-9]+(?:\.[0-9]+)?)", re.I)
RE_GRADE_AFTER_UNITS = re.compile(r"\b([1-6])\s+([1-5](?:\.\d{1,2})?)\b")
RE_NUMERIC_GRADE = re.compile(r"^[1-5](?:\.\d{1,2})?$")
# Leading program code tokens used when mapping "BSED Filipino" → BSED.
_KNOWN_PROGRAM_PREFIXES = (
    "BSCRIM",
    "ABSOCIO",
    "DEVCOM",
    "BSED",
    "BEED",
    "BSIT",
    "BSBA",
    "BSPA",
    "BSFT",
    "BSAT",
    "BSET",
)

# Column labels (normalized) → canonical course field.
_HEADER_ALIASES: dict[str, str] = {
    "code": "code",
    "course code": "code",
    "coursecode": "code",
    "description": "description",
    "course description": "description",
    "coursedescription": "description",
    "units": "units",
    "grade": "grade",
    "re grade": "re_grade",
    "regrade": "re_grade",
    "instructor": "instructor",
    "instructor/professor": "instructor",
    "instructorprofessor": "instructor",
    "remarks": "remarks",
    "ched pr": "ched_pr",
    "chedpr": "ched_pr",
}

_SKIP_ROW_PREFIXES = (
    "total units",
    "units with grade",
    "semester gpa",
    "gpa",
    "printed on",
    "ched pr",
)


def _norm_header(value: str) -> str:
    cleaned = _clean_text(value or "")
    cleaned = cleaned.replace("\n", " ")
    cleaned = re.sub(r"\s+", " ", cleaned).strip().lower()
    cleaned = cleaned.replace("–", "-").replace("—", "-")
    return cleaned


def _canonical_header(value: str) -> str | None:
    """Map a header cell/phrase to a course field."""
    key = _norm_header(value)
    if key in _HEADER_ALIASES:
        return _HEADER_ALIASES[key]
    compact = re.sub(r"[^a-z0-9]+", "", key)
    for alias, field in _HEADER_ALIASES.items():
        if re.sub(r"[^a-z0-9]+", "", alias) == compact:
            return field
    # find_tables may truncate headers ("Instructor/Professor Re", "marks").
    if key.startswith("remark") or key in {"marks", "mark"}:
        return "remarks"
    if key.startswith("instructor") or "professor" in key:
        return "instructor"
    return None

def _repair_instructor_remarks(instructor: str, remarks: str) -> tuple[str, str]:
    """Fix find_tables splits like instructor='Señara, Alex Pa' remarks='ssed'."""
    instructor = _cell_str(instructor)
    remarks = _cell_str(remarks)
    if not instructor and not remarks:
        return instructor, remarks

    glued = f"{instructor} {remarks}".strip()
    m = re.search(r"^(.*)\s+(Passed|Failed|Dropped|Incomplete|INC)\s*$", glued, re.I)
    if m:
        return m.group(1).strip(" ,"), m.group(2)

    nospace = re.sub(r"[\s,]+", "", instructor + remarks)
    for label in ("Passed", "Failed", "Dropped", "Incomplete"):
        if not nospace.lower().endswith(label.lower()):
            continue
        target = nospace[: -len(label)]
        tokens = re.split(r"\s+", instructor) if instructor else []
        rebuilt: list[str] = []
        acc = ""
        for tok in tokens:
            piece = re.sub(r"[\s,]+", "", tok)
            cand = acc + piece
            if len(cand) <= len(target) and target.lower().startswith(cand.lower()):
                rebuilt.append(tok.rstrip(","))
                acc = cand
            else:
                break
        fixed_instructor = ", ".join(rebuilt) if rebuilt else instructor
        return fixed_instructor, label

    return instructor, remarks


def _cell_str(value: Any) -> str:
    if value is None:
        return ""
    text = _clean_text(str(value)).replace("\n", " ")
    return re.sub(r"\s+", " ", text).strip()


def _normalize_program_code(raw: str | None) -> str | None:
    """Map CH program labels like 'BSED Filipino' / 'BSIT' to org codes when possible."""
    text = _cell_str(raw or "")
    if not text:
        return None
    compact = re.sub(r"[^A-Za-z0-9]", "", text).upper()
    # Longest-prefix match against known TES codes (BSED before BS, etc.).
    for code in sorted(_KNOWN_PROGRAM_PREFIXES, key=len, reverse=True):
        if compact == code or compact.startswith(code):
            return code
    # Bare alphanumeric token (e.g. BSIT).
    m = re.match(r"^([A-Za-z]{2,12})", text)
    if m:
        return m.group(1).upper()
    return None


def _parse_term_header(line: str) -> dict[str, Any] | None:
    """Parse '2023-2024 1st BSED Filipino — Year 1st ENROLLED' style headers."""
    text = _cell_str(line)
    if not text:
        return None
    m = RE_TERM_HEADER.search(text)
    if not m:
        return None
    academic_term = re.sub(r"\s+", " ", m.group(1)).strip()
    program_raw = re.sub(r"\s+", " ", m.group(2)).strip(" :|-–—")
    year_level = f"Year {m.group(3)}"
    enrollment = (m.group(4) or "").upper() or None
    return {
        "academic_term": academic_term,
        "program_raw": program_raw or None,
        "program_code": _normalize_program_code(program_raw),
        "year_level": year_level,
        "enrollment_status": enrollment,
        "courses": [],
    }


def _empty_term(
    *,
    academic_term: str | None = None,
    program_raw: str | None = None,
    program_code: str | None = None,
    year_level: str | None = None,
) -> dict[str, Any]:
    return {
        "academic_term": academic_term,
        "program_raw": program_raw,
        "program_code": program_code or _normalize_program_code(program_raw),
        "year_level": year_level,
        "enrollment_status": None,
        "courses": [],
    }


def _annotate_course(course: dict[str, Any], term: dict[str, Any] | None) -> dict[str, Any]:
    """Copy term program/term onto a course row for flat-list consumers."""
    out = dict(course)
    if not term:
        return out
    if term.get("academic_term"):
        out["academic_term"] = term["academic_term"]
    if term.get("program_code"):
        out["program_code"] = term["program_code"]
    if term.get("program_raw"):
        out["program_raw"] = term["program_raw"]
    if term.get("year_level"):
        out["year_level"] = term["year_level"]
    return out


def _flatten_terms(terms: list[dict[str, Any]]) -> list[dict[str, Any]]:
    courses: list[dict[str, Any]] = []
    for term in terms:
        for course in term.get("courses") or []:
            if isinstance(course, dict):
                courses.append(_annotate_course(course, term))
    return courses


def _format_terms_table(terms: list[dict[str, Any]]) -> str:
    """Staff-readable table with per-term program headers."""
    if not terms:
        return ""
    blocks: list[str] = []
    for term in terms:
        header_bits = [
            str(term.get("academic_term") or "Term"),
            str(term.get("program_raw") or term.get("program_code") or "Program"),
        ]
        if term.get("year_level"):
            header_bits.append(str(term["year_level"]))
        if term.get("enrollment_status"):
            header_bits.append(str(term["enrollment_status"]))
        header = " | ".join(header_bits)
        body = format_courses_table(list(term.get("courses") or []))
        blocks.append(header if not body else f"{header}\n{body}")
    return "\n\n".join(blocks)


def _term_headers_from_page(page: fitz.Page) -> list[tuple[float, dict[str, Any]]]:
    """Return (y0, term) for each term header found on the page (top → bottom)."""
    words = page.get_text("words") or []
    if not words:
        return []
    lines: dict[int, list[tuple[float, float, str]]] = {}
    for w in words:
        bucket = _y_bucket(float(w[1]))
        lines.setdefault(bucket, []).append((float(w[0]), float(w[1]), str(w[4])))

    headers: list[tuple[float, dict[str, Any]]] = []
    for bucket in sorted(lines):
        items = sorted(lines[bucket], key=lambda t: t[0])
        line_text = " ".join(t[2] for t in items)
        parsed = _parse_term_header(line_text)
        if parsed:
            y0 = min(t[1] for t in items)
            headers.append((y0, parsed))
    return headers


def _pick_term_for_y(
    headers: list[tuple[float, dict[str, Any]]],
    y0: float,
    fallback: dict[str, Any] | None,
    *,
    tolerance: float = 28.0,
) -> dict[str, Any] | None:
    """Nearest term header for a table/row Y.

    find_tables bboxes usually include the term header as the first row, so the
    header's y0 is a few pixels *below* the table top — allow a small downward
    tolerance (not only headers strictly above the table).
    """
    chosen = fallback
    for hy, term in headers:
        if hy <= y0 + tolerance:
            chosen = term
        else:
            break
    return chosen


def _term_header_from_row(row: list[Any] | None) -> dict[str, Any] | None:
    """Parse a find_tables row that is a CH semester banner (not a course)."""
    if not row:
        return None
    line = " ".join(_cell_str(cell) for cell in row if cell not in (None, ""))
    return _parse_term_header(line) if line else None


def _normalize_grade(raw: str) -> str | None:
    """Return numeric grade string, non-numeric remark-like grade, or None if blank."""
    text = _cell_str(raw)
    if text == "":
        return None
    if RE_NUMERIC_GRADE.match(text):
        return text
    return text


def _course_row(
    *,
    code: str,
    description: str,
    units: str | None,
    grade: str | None,
    instructor: str | None,
    remarks: str | None,
    re_grade: str | None = None,
) -> dict[str, Any] | None:
    code = _cell_str(code)
    description = _cell_str(description)
    if code == "" and description == "":
        return None
    lower = f"{code} {description}".lower()
    if any(lower.startswith(prefix) for prefix in _SKIP_ROW_PREFIXES):
        return None
    if _canonical_header(code) in {"code", "description", "units", "grade", "instructor", "remarks"}:
        return None
    if code.upper() in {"COURSE CODE", "CODE", "COURSE\nCODE"}:
        return None

    row: dict[str, Any] = {
        "code": code or None,
        "description": description or None,
        "units": _cell_str(units) or None,
        "grade": _normalize_grade(grade or ""),
        "instructor": _cell_str(instructor) or None,
        "remarks": _cell_str(remarks) or None,
    }
    if re_grade is not None:
        row["re_grade"] = _normalize_grade(re_grade)
    return row


def _map_table_headers(header_row: list[Any]) -> dict[int, str]:
    mapping: dict[int, str] = {}
    for idx, cell in enumerate(header_row):
        field = _canonical_header(_cell_str(cell))
        if field and field not in mapping.values():
            mapping[idx] = field
    # Repair split Instructor / Remarks headers from find_tables.
    values = list(mapping.values())
    if "instructor" in values and "remarks" not in values:
        for idx, cell in enumerate(header_row):
            if idx in mapping:
                continue
            if "mark" in _norm_header(_cell_str(cell)):
                mapping[idx] = "remarks"
                break
    return mapping


def _rows_from_find_tables(page: fitz.Page) -> list[dict[str, Any]]:
    """Extract course rows from find_tables, grouped into term blocks when headers exist."""
    term_headers = _term_headers_from_page(page)
    # Stable term objects keyed by (academic_term, program_raw) so multiple tables merge.
    terms_by_key: dict[tuple[str, str], dict[str, Any]] = {}
    ordered_terms: list[dict[str, Any]] = []
    orphan_courses: list[dict[str, Any]] = []

    def ensure_term(template: dict[str, Any] | None) -> dict[str, Any] | None:
        if template is None:
            return None
        key = (
            str(template.get("academic_term") or ""),
            str(template.get("program_raw") or template.get("program_code") or ""),
        )
        existing = terms_by_key.get(key)
        if existing is not None:
            return existing
        term = {
            "academic_term": template.get("academic_term"),
            "program_raw": template.get("program_raw"),
            "program_code": template.get("program_code"),
            "year_level": template.get("year_level"),
            "enrollment_status": template.get("enrollment_status"),
            "courses": [],
        }
        terms_by_key[key] = term
        ordered_terms.append(term)
        return term

    try:
        # PyMuPDF may print a layout-package hint to stdout; keep JSON clean.
        _stdout = sys.stdout
        try:
            sys.stdout = sys.stderr
            finder = page.find_tables()
        finally:
            sys.stdout = _stdout
    except Exception:  # noqa: BLE001 — optional table detector
        return []

    tables = getattr(finder, "tables", None) or []
    for table in tables:
        try:
            raw_rows = table.extract() or []
        except Exception:  # noqa: BLE001
            continue
        if len(raw_rows) < 2:
            continue

        header_idx = None
        mapping: dict[int, str] = {}
        for i, row in enumerate(raw_rows[:4]):
            candidate = _map_table_headers(list(row or []))
            if {"code", "description"}.issubset(set(candidate.values())) or (
                "code" in candidate.values() and "units" in candidate.values()
            ):
                header_idx = i
                mapping = candidate
                break
        if header_idx is None or not mapping:
            continue

        # Prefer an in-table semester banner (usually row 0) over Y-proximity.
        # Y-pick alone mis-assigns when the next term's table top sits above that
        # term's header y (header is inside the bbox) — e.g. Summer electives +
        # following 1st-semester majors land in one Summer bucket.
        in_table_term: dict[str, Any] | None = None
        for prow in raw_rows[: header_idx + 1]:
            parsed = _term_header_from_row(list(prow or []))
            if parsed:
                in_table_term = parsed
                break

        table_y0 = float(getattr(table, "bbox", [0, 0, 0, 0])[1] or 0)
        active_term = in_table_term or _pick_term_for_y(term_headers, table_y0, None)
        term_bucket = ensure_term(active_term)

        for row in raw_rows[header_idx + 1 :]:
            cells = list(row or [])
            # Mid-table semester banner (merged multi-term tables).
            mid_term = _term_header_from_row(cells)
            if mid_term:
                active_term = mid_term
                term_bucket = ensure_term(active_term)
                continue
            # Column header repeated after a new term banner.
            remapped = _map_table_headers(cells)
            remapped_fields = set(remapped.values())
            if remapped and (
                {"code", "description"}.issubset(remapped_fields)
                or ("code" in remapped_fields and "units" in remapped_fields)
            ):
                mapping = remapped
                continue
            keyed = {field: _cell_str(cells[idx]) if idx < len(cells) else "" for idx, field in mapping.items()}
            # find_tables may glue remarks into instructor ("Alex Pa" / "ssed" → Passed).
            instructor = keyed.get("instructor", "")
            remarks = keyed.get("remarks", "")
            instructor, remarks = _repair_instructor_remarks(instructor, remarks)
            course = _course_row(
                code=keyed.get("code", ""),
                description=keyed.get("description", ""),
                units=keyed.get("units"),
                grade=keyed.get("grade"),
                instructor=instructor,
                remarks=remarks,
                re_grade=keyed.get("re_grade"),
            )
            if not course:
                continue
            if term_bucket is not None:
                term_bucket["courses"].append(course)
            else:
                orphan_courses.append(course)

    if ordered_terms:
        if orphan_courses:
            # Unmatched rows → last known term or a catch-all block.
            target = ordered_terms[-1]
            target["courses"].extend(orphan_courses)
        return ordered_terms
    if orphan_courses:
        return [_empty_term_with_courses(orphan_courses)]
    return []


def _empty_term_with_courses(courses: list[dict[str, Any]]) -> dict[str, Any]:
    term = _empty_term()
    term["courses"] = courses
    return term


def _y_bucket(y0: float) -> int:
    return int(round(y0 / 2.0))


def _rows_from_words(page: fitz.Page) -> list[dict[str, Any]]:
    """Column-aware extraction using header x positions (preserves blank Grade cells).

    Returns term blocks (with courses) when CH program headers are present.
    """
    words = page.get_text("words") or []
    if not words:
        return []

    # words: x0, y0, x1, y1, word, block_no, line_no, word_no
    lines: dict[int, list[tuple[float, float, float, float, str]]] = {}
    for w in words:
        bucket = _y_bucket(float(w[1]))
        lines.setdefault(bucket, []).append((float(w[0]), float(w[1]), float(w[2]), float(w[3]), str(w[4])))

    header_bucket: int | None = None
    header_cols: list[tuple[str, float]] = []  # (field, x0)
    for bucket in sorted(lines):
        items = sorted(lines[bucket], key=lambda t: t[0])
        text = " ".join(t[4] for t in items)
        norm = _norm_header(text)
        if "grade" not in norm:
            continue
        if "code" not in norm and "course code" not in norm and "coursecode" not in norm.replace(" ", ""):
            continue
        # Build columns from individual words / phrases on the header line.
        cols: list[tuple[str, float]] = []
        i = 0
        while i < len(items):
            # Prefer shorter phrases so "Code" / "Grade" win over greedy 3-word spans.
            matched = None
            for width in (1, 2, 3):
                if i + width > len(items):
                    continue
                phrase = " ".join(items[j][4] for j in range(i, i + width))
                field = _canonical_header(phrase)
                if field:
                    matched = (field, items[i][0], width)
                    break
            if matched:
                field, x0, width = matched
                if field not in {c[0] for c in cols}:
                    cols.append((field, x0))
                i += width
            else:
                i += 1
        if "code" in {c[0] for c in cols} and "grade" in {c[0] for c in cols}:
            header_bucket = bucket
            header_cols = cols
            break

    if header_bucket is None or len(header_cols) < 3:
        return []

    def build_bounds(cols: list[tuple[str, float]]) -> list[tuple[str, float, float]]:
        cols = sorted(cols, key=lambda c: c[1])
        out: list[tuple[str, float, float]] = []
        for idx, (field, x0) in enumerate(cols):
            if field == "remarks":
                left = x0 - 6.0
            elif idx == 0:
                left = x0 - 8.0
            else:
                left = (cols[idx - 1][1] + x0) / 2.0
            right = x0 + 500.0 if idx == len(cols) - 1 else (x0 + cols[idx + 1][1]) / 2.0
            if field == "remarks":
                right = max(right, x0 + 200.0)
            out.append((field, left, right))
        return out

    ordered_terms: list[dict[str, Any]] = []
    active_term: dict[str, Any] | None = None
    orphan_courses: list[dict[str, Any]] = []
    active_bounds = build_bounds(header_cols)
    active_header_bucket = header_bucket
    saw_first_table_header = False

    for bucket in sorted(lines):
        items = sorted(lines[bucket], key=lambda t: t[0])
        if not items:
            continue
        line_text = " ".join(t[4] for t in items)
        lower = line_text.lower()

        parsed_term = _parse_term_header(line_text)
        if parsed_term:
            active_term = parsed_term
            ordered_terms.append(active_term)
            continue

        if any(p in lower for p in ("total units", "semester gpa", "printed on", "units with grade")):
            continue
        if lower.strip().startswith("gpa"):
            continue

        # Column header row — refresh bounds; do not treat as a course.
        if "grade" in lower and ("code" in lower or "course code" in lower):
            cols: list[tuple[str, float]] = []
            i = 0
            while i < len(items):
                matched = None
                for width in (1, 2, 3):
                    if i + width > len(items):
                        continue
                    phrase = " ".join(items[j][4] for j in range(i, i + width))
                    field = _canonical_header(phrase)
                    if field:
                        matched = (field, items[i][0], width)
                        break
                if matched:
                    field, x0, width = matched
                    if field not in {c[0] for c in cols}:
                        cols.append((field, x0))
                    i += width
                else:
                    i += 1
            if "code" in {c[0] for c in cols} and "grade" in {c[0] for c in cols}:
                active_bounds = build_bounds(cols)
                active_header_bucket = bucket
                saw_first_table_header = True
                continue

        if not saw_first_table_header or bucket <= active_header_bucket:
            continue

        keyed: dict[str, list[str]] = {field: [] for field, _, _ in active_bounds}
        for x0, _y0, _x1, _y1, token in items:
            assigned = None
            for field, left, right in active_bounds:
                if left <= x0 < right:
                    assigned = field
                    break
            if assigned:
                keyed[assigned].append(token)

        course = _course_row(
            code=" ".join(keyed.get("code", [])),
            description=" ".join(keyed.get("description", [])),
            units=" ".join(keyed.get("units", [])),
            grade=" ".join(keyed.get("grade", [])),
            instructor=" ".join(keyed.get("instructor", [])),
            remarks=" ".join(keyed.get("remarks", [])),
            re_grade=" ".join(keyed.get("re_grade", [])) if "re_grade" in keyed else None,
        )
        if not course:
            continue
        if active_term is not None:
            active_term["courses"].append(course)
        else:
            orphan_courses.append(course)

    if ordered_terms:
        if orphan_courses:
            ordered_terms[-1]["courses"].extend(orphan_courses)
        return [t for t in ordered_terms if t.get("courses")]
    if orphan_courses:
        return [_empty_term_with_courses(orphan_courses)]
    return []


def extract_terms(pdf_path: Path) -> list[dict[str, Any]]:
    """Extract Course History / Grade Slip as term blocks with per-term program."""
    doc = fitz.open(pdf_path)
    try:
        terms: list[dict[str, Any]] = []
        for page in doc:
            # find_tables preserves blank Grade cells; word fallback for odd layouts.
            page_terms = _rows_from_find_tables(page)
            if not page_terms:
                page_terms = _rows_from_words(page)
            terms.extend(page_terms)
        return terms
    finally:
        doc.close()


def extract_courses(pdf_path: Path) -> list[dict[str, Any]]:
    """Flat course list (backward compatible); each row may carry term/program fields."""
    return _flatten_terms(extract_terms(pdf_path))


def format_courses_table(courses: list[dict[str, Any]]) -> str:
    """Aligned monospace table; blank grades shown as em dash (column preserved)."""
    if not courses:
        return ""

    headers = ["Code", "Description", "Units", "Grade", "Instructor", "Remarks"]
    rows: list[list[str]] = []
    for course in courses:
        grade = course.get("grade")
        grade_disp = "—" if grade is None or grade == "" else str(grade)
        rows.append(
            [
                str(course.get("code") or ""),
                str(course.get("description") or ""),
                str(course.get("units") or ""),
                grade_disp,
                str(course.get("instructor") or ""),
                str(course.get("remarks") or ""),
            ]
        )

    widths = [len(h) for h in headers]
    for row in rows:
        for i, cell in enumerate(row):
            widths[i] = max(widths[i], len(cell))

    def fmt(row: list[str]) -> str:
        return " | ".join(cell.ljust(widths[i]) for i, cell in enumerate(row))

    lines = [fmt(headers), "-+-".join("-" * w for w in widths)]
    lines.extend(fmt(row) for row in rows)
    return "\n".join(lines)


def extract_text(pdf_path: Path) -> str:
    doc = fitz.open(pdf_path)
    try:
        parts: list[str] = []
        for page in doc:
            parts.append(_clean_text(page.get_text("text") or ""))
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
    if not program:
        m = re.search(r"Program\s*[:#]?\s*([A-Z]{2,12})", text, re.I)
        if m:
            program = m.group(1).upper()
    return {
        "document_type": document_type,
        "extracted_text": text,
        "extracted_name": _first(RE_NAME, text),
        "extracted_student_id": _first(RE_STUDENT_ID, text),
        "extracted_gwa": _first(RE_GWA, text) or _first(RE_SEMESTER_GPA, text) or _first(RE_GPA_LINE, text),
        "extracted_semester": _first(RE_SEMESTER, text),
        "extracted_school_year": _first(RE_SCHOOL_YEAR, text),
        "extracted_program": program,
        "extracted_grades": grades,
        "page_char_count": len(text),
        "engine": "pymupdf",
    }


def extract_pdf(pdf_path: Path, document_type: str | None = None) -> dict[str, Any]:
    text = extract_text(pdf_path)
    terms = extract_terms(pdf_path)
    courses = _flatten_terms(terms)
    # Prefer term-grouped table for Course History staff review.
    formatted = _format_terms_table(terms) if terms else format_courses_table(courses)
    result = parse_fields(text, document_type=document_type)
    result["source_path"] = str(pdf_path)
    result["combined_text"] = text  # raw linear dump (legacy / search)
    result["formatted_table_text"] = formatted
    result["terms"] = terms
    result["courses"] = courses
    result["pdf_metadata"] = extract_metadata(pdf_path)
    result["method"] = "pymupdf_text_layer"
    result["has_useful_text"] = len([c for c in text if c.isalnum()]) >= 10
    # Prefer structured numeric grades when table parse succeeded.
    if courses:
        numeric: list[float] = []
        for course in courses:
            g = course.get("grade")
            if isinstance(g, str) and RE_NUMERIC_GRADE.match(g):
                numeric.append(float(g))
        result["extracted_grades"] = numeric
    # Latest term program wins for document-level extracted_program when multi-shift.
    if terms:
        for term in reversed(terms):
            code = term.get("program_code") or _normalize_program_code(term.get("program_raw"))
            if code:
                result["extracted_program"] = code
                break
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
        emit_json({"success": False, "error": f"File not found: {args.pdf}"})
        return 1

    doc_type = None if args.type == "auto" else args.type
    try:
        payload = extract_pdf(args.pdf, document_type=doc_type)
        out = {"success": True, "document_type": "pdf", "result": payload}
    except Exception as exc:  # noqa: BLE001 — CLI surface
        emit_json({"success": False, "error": str(exc)})
        return 2

    emit_json(out, pretty=args.pretty)
    return 0


if __name__ == "__main__":
    sys.exit(main())
