# PDF extraction (PyMuPDF)

Grade Slip / Course History text extraction for the requirements / OCR flow.

```bash
cd python
python -m venv .venv
.venv\Scripts\pip install -r requirements.txt
..\python\.venv\Scripts\python.exe pdf_extract.py path\to\grade-slip.pdf --type grade_slip --pretty
```

Laravel proxies uploads to `OCR_SERVICE_URL` (`/ocr/pdf`). This CLI fills the gap until that service wraps `pdf_extract.py`.
