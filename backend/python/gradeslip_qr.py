#!/usr/bin/env python3
"""
Decode the TCC Grade Slip "Scan to Verify" QR via pyzbar (+ Pillow / PyMuPDF).

Install (from this folder):
  python -m venv .venv && .venv\\Scripts\\pip install -r requirements.txt

Windows note: pyzbar needs the ZBar shared library. Pip wheels often ship
``libzbar-64.dll``, but if import/decode fails install the Visual C++
Redistributable and ensure zlib is available, then reinstall pyzbar.

Run:
  python\\.venv\\Scripts\\python.exe python\\gradeslip_qr.py path\\to\\grade-slip.pdf
  python\\.venv\\Scripts\\python.exe python\\gradeslip_qr.py path\\to\\slip.png --pretty

Env:
  TCC_REGISTRAR_DOMAINS=registrar.tcc.edu.ph,sis.tcc.edu.ph,tcc.edu.ph
"""

from __future__ import annotations

import argparse
import json
import os
import re
import sys
from pathlib import Path
from typing import Any
from urllib.parse import parse_qs, urlparse

IMAGE_SUFFIXES = {".jpg", ".jpeg", ".png", ".webp", ".bmp", ".tif", ".tiff", ".gif"}
DEFAULT_DOMAINS = "registrar.tcc.edu.ph,sis.tcc.edu.ph,tcc.edu.ph"
STUDENT_ID_RE = re.compile(r"\b(?:20\d{2}[-–]?\d{4,}|\d{4,}[-–]\d{4,})\b")


def emit_json(payload: dict[str, Any], *, pretty: bool = False) -> None:
    text = json.dumps(payload, indent=2 if pretty else None, ensure_ascii=False)
    sys.stdout.buffer.write(text.encode("utf-8", errors="replace"))
    sys.stdout.buffer.write(b"\n")


def allowed_domains() -> list[str]:
    raw = os.environ.get("TCC_REGISTRAR_DOMAINS", DEFAULT_DOMAINS)
    return [d.strip().lower() for d in raw.split(",") if d.strip()]


def domain_matches(payload: str, domains: list[str]) -> bool:
    text = (payload or "").strip().lower()
    if not text or not domains:
        return False

    host = ""
    parsed = urlparse(text if "://" in text else f"https://{text}")
    if parsed.hostname:
        host = parsed.hostname.lower()

    for domain in domains:
        if host and (host == domain or host.endswith("." + domain)):
            return True
        if domain in text:
            return True
    return False


def parse_payload_fields(payload: str) -> dict[str, Any]:
    fields: dict[str, Any] = {}
    text = (payload or "").strip()
    if not text:
        return fields

    url_text = text if "://" in text else (f"https://{text}" if text.startswith("www.") else text)
    parsed = urlparse(url_text)
    if parsed.scheme and parsed.netloc:
        fields["url"] = text
        fields["scheme"] = parsed.scheme
        fields["host"] = parsed.hostname
        fields["path"] = parsed.path
        query = {k: (v[0] if len(v) == 1 else v) for k, v in parse_qs(parsed.query).items()}
        if query:
            fields["query"] = query
            for key in ("student_id", "studentId", "sid", "id", "stud_no", "student_no"):
                if key in query and query[key]:
                    fields["student_id"] = str(query[key])
                    break
            for key in ("name", "full_name", "student_name"):
                if key in query and query[key]:
                    fields["name"] = str(query[key])
                    break
            for key in ("term", "semester", "period"):
                if key in query and query[key]:
                    fields["term"] = str(query[key])
                    break

    sid = STUDENT_ID_RE.search(text)
    if sid and "student_id" not in fields:
        fields["student_id"] = sid.group(0)

    return fields


def payload_looks_valid(payload: str, domains: list[str]) -> bool:
    if domain_matches(payload, domains):
        return True
    fields = parse_payload_fields(payload)
    # Non-URL QR that still embeds a student identifier (some SIS encode JSON/text).
    return bool(fields.get("student_id")) and (
        "tcc" in payload.lower() or "verify" in payload.lower() or "sis" in payload.lower()
    )


def _looks_like_native_dep_error(message: str) -> bool:
    lowered = message.lower()
    needles = (
        "libzbar",
        "libiconv",
        "dll",
        "shared object",
        "could not find module",
        "zlib",
        "loadlibrary",
    )
    return any(n in lowered for n in needles)


def decode_pil_image(image: Any) -> list[dict[str, Any]]:
    try:
        from pyzbar.pyzbar import decode as pyzbar_decode
    except Exception as exc:  # noqa: BLE001 — ImportError or DLL load failures on Windows
        message = str(exc)
        if isinstance(exc, ImportError) or _looks_like_native_dep_error(message):
            raise ImportError(message) from exc
        raise

    codes: list[dict[str, Any]] = []
    for item in pyzbar_decode(image):
        raw = item.data.decode("utf-8", errors="replace")
        codes.append(
            {
                "type": item.type,
                "raw_payload": raw,
                "rect": {
                    "left": item.rect.left,
                    "top": item.rect.top,
                    "width": item.rect.width,
                    "height": item.rect.height,
                },
            }
        )
    return codes


def decode_image_path(path: Path) -> list[dict[str, Any]]:
    from PIL import Image

    with Image.open(path) as img:
        return decode_pil_image(img.convert("RGB"))


def decode_pdf_pymupdf_barcode(path: Path) -> tuple[list[dict[str, Any]], str] | None:
    """Optional PyMuPDF barcode API (when built with barcode support); None if unavailable."""
    try:
        import fitz
    except ImportError:
        return None

    if not hasattr(fitz.Page, "get_barcodes") and not hasattr(fitz.Page, "find_barcodes"):
        return None

    doc = fitz.open(path)
    try:
        codes: list[dict[str, Any]] = []
        for page_index, page in enumerate(doc):
            finder = getattr(page, "get_barcodes", None) or getattr(page, "find_barcodes", None)
            if finder is None:
                continue
            try:
                found = finder()
            except Exception:  # noqa: BLE001
                continue
            for item in found or []:
                raw = ""
                if isinstance(item, dict):
                    raw = str(item.get("text") or item.get("data") or "")
                else:
                    raw = str(getattr(item, "text", None) or getattr(item, "data", "") or "")
                if not raw:
                    continue
                codes.append(
                    {
                        "type": "QRCODE",
                        "raw_payload": raw,
                        "page": page_index,
                        "source": "pymupdf_barcode",
                    }
                )
        if codes:
            return codes, "pymupdf_barcode"
        return [], "pymupdf_barcode"
    finally:
        doc.close()


def decode_pdf(path: Path) -> tuple[list[dict[str, Any]], str]:
    import fitz  # PyMuPDF
    from PIL import Image
    import io

    # Prefer pyzbar; if ZBar DLL is missing on Windows, try PyMuPDF barcode then soft-fail.
    try:
        doc = fitz.open(path)
        try:
            # Prefer embedded images (often the QR asset itself).
            for page_index, page in enumerate(doc):
                for img in page.get_images(full=True):
                    xref = img[0]
                    try:
                        extracted = doc.extract_image(xref)
                    except Exception:  # noqa: BLE001
                        continue
                    data = extracted.get("image")
                    if not data:
                        continue
                    try:
                        pil = Image.open(io.BytesIO(data)).convert("RGB")
                    except Exception:  # noqa: BLE001
                        continue
                    codes = decode_pil_image(pil)
                    if codes:
                        for code in codes:
                            code["page"] = page_index
                            code["source"] = "embedded_image"
                        return codes, "embedded_image"

            # Fallback: rasterize first page at 2x for small printed QR codes.
            if len(doc) == 0:
                return [], "empty_pdf"
            page = doc[0]
            pix = page.get_pixmap(matrix=fitz.Matrix(2, 2), alpha=False)
            pil = Image.frombytes("RGB", (pix.width, pix.height), pix.samples)
            codes = decode_pil_image(pil)
            for code in codes:
                code["page"] = 0
                code["source"] = "page_render"
            return codes, "page_render"
        finally:
            doc.close()
    except Exception as exc:  # noqa: BLE001
        message = str(exc)
        if isinstance(exc, ImportError) or _looks_like_native_dep_error(message):
            alt = decode_pdf_pymupdf_barcode(path)
            if alt is not None:
                return alt
        raise


def build_result(path: Path) -> dict[str, Any]:
    domains = allowed_domains()
    suffix = path.suffix.lower()
    engine = "pyzbar"

    try:
        if suffix == ".pdf":
            codes, source = decode_pdf(path)
            if source == "pymupdf_barcode":
                engine = "pymupdf"
        elif suffix in IMAGE_SUFFIXES:
            codes = decode_image_path(path)
            source = "image"
        else:
            return {
                "success": False,
                "found": False,
                "domain_valid": False,
                "raw_payload": None,
                "parsed_fields": {},
                "codes": [],
                "error": f"Unsupported file type: {suffix or '(none)'}",
                "source_path": str(path),
                "engine": engine,
            }
    except Exception as exc:  # noqa: BLE001
        message = str(exc)
        missing = isinstance(exc, ImportError) or _looks_like_native_dep_error(message)
        return {
            "success": False,
            "found": False,
            "domain_valid": False,
            "raw_payload": None,
            "parsed_fields": {},
            "codes": [],
            "error": (
                f"Missing dependency: {message}. Install pyzbar and Pillow "
                "(Windows: Visual C++ Redistributable; libiconv/zlib often required for libzbar). "
                "QR decode is optional — PDF text extraction still runs independently."
                if missing
                else message
            ),
            "error_code": "dependency_missing" if missing else "decode_failed",
            "source_path": str(path),
            "engine": engine,
        }

    raw = codes[0]["raw_payload"] if codes else None
    parsed = parse_payload_fields(raw or "")
    domain_valid = bool(raw) and payload_looks_valid(raw, domains)
    success = bool(raw) and domain_valid

    return {
        "success": success,
        "found": bool(codes),
        "domain_valid": domain_valid,
        "raw_payload": raw,
        "parsed_fields": parsed,
        "codes": codes,
        "allowed_domains": domains,
        "decode_source": source,
        "error": None if success else (
            "No QR code found" if not codes else "QR code invalid or domain mismatch"
        ),
        "error_code": None if success else (
            "qr_not_found" if not codes else "domain_mismatch"
        ),
        "source_path": str(path),
        "engine": engine,
    }


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(
        description="Decode TCC Grade Slip Scan-to-Verify QR with pyzbar."
    )
    parser.add_argument("path", type=Path, help="Path to grade slip image or PDF")
    parser.add_argument("--pretty", action="store_true", help="Pretty-print JSON")
    args = parser.parse_args(argv)

    if not args.path.is_file():
        emit_json({"success": False, "found": False, "error": f"File not found: {args.path}"})
        return 1

    payload = build_result(args.path)
    emit_json(payload, pretty=args.pretty)
    return 0 if payload.get("success") else 2


if __name__ == "__main__":
    sys.exit(main())
