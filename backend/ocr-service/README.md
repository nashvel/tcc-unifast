# TCC UniFAST OCR Prototype

Standalone Python OCR prototype for proving that Tesseract OCR with OpenCV can extract printed text from student IDs, grade slips, course history documents, scanned academic documents, image files, and digital or scanned PDFs.

This prototype performs OCR only. It does not make authenticity conclusions and does not label documents as forged, authentic, fake, or similar.

## Stack

- Python 3.11
- Tesseract OCR
- pytesseract
- OpenCV (perspective card warp + CLAHE / denoise)
- pyzbar (School ID back QR; OpenCV fallback)
- PyMuPDF
- Pillow
- FastAPI
- pytest

## Windows Setup

1. Install Python 3.11 from <https://www.python.org/downloads/>.
2. Install Tesseract OCR for Windows.
3. Set the optional executable path if Tesseract is not on `PATH`:

```powershell
$env:TESSERACT_CMD="C:\Program Files\Tesseract-OCR\tesseract.exe"
```

4. Create and activate a virtual environment:

```powershell
cd backend\ocr-service
py -3.11 -m venv .venv
.\.venv\Scripts\Activate.ps1
python -m pip install --upgrade pip
pip install -r requirements.txt
```

5. **pyzbar / ZBar (School ID back QR):** pip installs `libzbar-64.dll`. If decode fails with missing DLL / `libiconv`:

```powershell
# Install Microsoft Visual C++ Redistributable, then:
mkdir native -ErrorAction SilentlyContinue
# Place libiconv.dll (and zlib1.dll if needed) into .\native\
$env:ZBAR_DLL_PATH=(Resolve-Path .\native).Path
.\.venv\Scripts\pip install --force-reinstall pyzbar Pillow
```

OCR still succeeds when QR is missing or pyzbar is unavailable (`qr_code.found=false`, optional `error`).

6. Run tests:

```powershell
pytest
```

7. Start FastAPI on **8001 only** (never bind Laravel/PHP on 8001):

```powershell
uvicorn app.main:app --host 127.0.0.1 --port 8001 --reload
```

8. Open Swagger:

<http://127.0.0.1:8001/docs>

## Ubuntu Setup

Install Tesseract:

```bash
sudo apt update
sudo apt install tesseract-ocr tesseract-ocr-eng libzbar0
```

Create a virtual environment and install dependencies:

```bash
cd /path/to/tcc-unifast/backend/ocr-service
python3.11 -m venv .venv
source .venv/bin/activate
python -m pip install --upgrade pip
pip install -r requirements.txt
```

Run tests:

```bash
pytest
```

Start FastAPI:

```bash
uvicorn app.main:app --host 127.0.0.1 --port 8001 --reload
```

Open Swagger:

<http://127.0.0.1:8001/docs>

## Environment

Copy `.env.example` to `.env` when local overrides are needed.

```env
TESSERACT_CMD=
TESSERACT_PSM=6
SAVE_DEBUG_IMAGES=false
ZBAR_DLL_PATH=
```

`TESSERACT_CMD` is optional and should point to the Tesseract executable only when it is not available on `PATH`. `SAVE_DEBUG_IMAGES=true` saves processed images under `outputs/debug/`.

Keep Laravel `OCR_SPACE_API_KEY` unset in local/dev so PHP always calls this service at `OCR_SERVICE_URL=http://127.0.0.1:8001`.

## API

### Health

```http
GET /health
```

Returns service status and whether Tesseract is available.

### Image OCR

```http
POST /ocr/image
```

Upload a multipart file using field name `file`. Supported types are JPEG, PNG, and WebP. Maximum size is 10 MB.

Response includes `result` (merged full + inverted + region OCR), `result.preprocessing` flags (`perspective_warped`, `inverted_pass`, `region_ocr`, `empty_text`), and `qr_code` (`found`, `value`, `type`, `engine`).

### PDF OCR

```http
POST /ocr/pdf
```

Upload a multipart PDF using field name `file`. Maximum size is 20 MB. PDFs are limited to 20 pages for this prototype. Embedded text is extracted directly; pages without useful embedded text are rendered and processed through OpenCV plus Tesseract.

## CLI

The CLI uses the same internal functions as the API.

```bash
python -m app.cli path/to/document.jpg
python -m app.cli path/to/document.pdf
```

It prints readable JSON to the terminal.

## Testing an ID Image and PDF

1. Start the server.
2. Open <http://127.0.0.1:8001/docs>.
3. Use `POST /ocr/image` for a JPEG, PNG, or WebP image of a fictional or consented document.
4. Use `POST /ocr/pdf` for a digital or scanned PDF.
5. Review `raw_text`, `cleaned_text`, word confidence, bounding boxes, metadata, QR result, and preprocessing information.

## Current Limitations

- OCR quality depends heavily on image resolution, lighting, blur, skew, and scan quality.
- Only English OCR (`eng`) is configured.
- Tesseract must be installed separately as a system dependency.
- QR decode is best-effort (glare may still miss); onboarding does not require QR.
- Structured field extraction is intentionally not implemented yet.
- No authenticity scoring or forensic conclusions are produced.

## Next Milestone

Implement structured field extraction for student number, full name, course, semester, GWA, subjects, and grades using the OCR text already returned by this prototype.
