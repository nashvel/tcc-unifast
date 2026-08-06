# PDF extraction + Gradeslip QR (PyMuPDF / pyzbar)

Grade Slip / Course History text extraction and TCC Grade Slip "Scan to Verify" QR decode.

```bash
cd python
python -m venv .venv
.venv\Scripts\pip install -r requirements.txt
..\python\.venv\Scripts\python.exe pdf_extract.py path\to\grade-slip.pdf --type grade_slip --pretty
..\python\.venv\Scripts\python.exe gradeslip_qr.py path\to\grade-slip.pdf --pretty
```

Laravel `ProcessRequirementSubmissionPipeline` shells out to `pdf_extract.py` for Course History / Grade Slip text (PyMuPDF) and `gradeslip_qr.py` for `grade_slip` QR (domains from `TCC_REGISTRAR_DOMAINS`). QR is optional: a pyzbar DLL failure must not clear `extracted_text`.

### Queue worker (required after submit)

OCR runs asynchronously. With `QUEUE_CONNECTION=database`, keep a worker running or staff OCR stays empty:

```bash
cd backend
C:\php84\php.exe artisan queue:work --queue=default
```

Set `GRADESLIP_QR_PYTHON` (or rely on auto-detect) to `backend/python/.venv/Scripts/python.exe`. The pipeline stores `pending_count` / failed / dropped in `metadata_payload.grade_summary` and OCR course rows for the staff Document Detail panel.

Scripts write JSON as UTF-8 bytes on stdout so Windows cp1252 consoles do not corrupt ñ and other characters (PHP `json_decode` previously failed with empty OCR).

### Windows / pyzbar

`pyzbar` needs the ZBar shared library. Pip wheels ship `libzbar-64.dll`, but load often fails without **libiconv** (and sometimes zlib) plus the [Microsoft Visual C++ Redistributable](https://learn.microsoft.com/en-us/cpp/windows/latest-supported-vc-redist).

Typical error: `Could not find module '...libzbar-64.dll' (or one of its dependencies)` / missing `libiconv.dll`.

Mitigations:

1. Install VC++ Redistributable (x64)
2. Place a compatible `libiconv.dll` where Windows can load it (e.g. next to `libzbar-64.dll` under `site-packages/pyzbar/`, or on PATH)
3. Reinstall: `.venv\Scripts\pip install --force-reinstall pyzbar Pillow`

The PHP → Python call path stays wired even when pyzbar is unavailable (`status: unavailable` / `error_code: dependency_missing`; no false QR risk points until a real decode runs).
