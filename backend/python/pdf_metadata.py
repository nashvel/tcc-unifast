#!/usr/bin/env python3
"""Extract PDF metadata with PyMuPDF for Laravel risk scoring."""

from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

import fitz


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="Extract PDF metadata via PyMuPDF.")
    parser.add_argument("pdf", type=Path)
    args = parser.parse_args(argv)

    if not args.pdf.is_file():
        print(json.dumps({"success": False, "error": f"File not found: {args.pdf}"}))
        return 1

    try:
        doc = fitz.open(args.pdf)
    except Exception as exc:  # noqa: BLE001
        print(json.dumps({"success": False, "error": str(exc)}))
        return 2

    try:
        raw = doc.metadata or {}
        payload = {
            "success": True,
            "metadata": {
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
            },
        }
    finally:
        doc.close()

    print(json.dumps(payload, ensure_ascii=False))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
