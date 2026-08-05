# Native ZBar DLLs (Windows)

`pyzbar` wheels ship `libzbar-64.dll` and `libiconv.dll` under
`site-packages/pyzbar/`. The OCR service calls `os.add_dll_directory(...)`
on that folder (and on `ocr-service/native`) **before** importing
`pyzbar.pyzbar`.

If QR still soft-fails with “DLL load failed while importing zbar” / “or one
of its dependencies”:

## 1. Install VC++ Redistributable

Install the [Microsoft Visual C++ Redistributable (x64)](https://learn.microsoft.com/en-us/cpp/windows/latest-supported-vc-redist).

## 2. Smoke-test pyzbar from the OCR venv

From `backend/ocr-service` (PowerShell — use `;` not `&&`):

```powershell
cd C:\Users\BRANDON\Downloads\tcc-unifast\backend\ocr-service
.\.venv\Scripts\python.exe -c "import os; from pathlib import Path; import pyzbar; d=str(Path(pyzbar.__file__).parent); os.add_dll_directory(d); from pyzbar.pyzbar import decode; print('pyzbar ok', d)"
```

Or let the service helper register paths:

```powershell
cd C:\Users\BRANDON\Downloads\tcc-unifast\backend\ocr-service
.\.venv\Scripts\python.exe -c "from app.qr_detector import _ensure_zbar_dll_path; _ensure_zbar_dll_path(); from pyzbar.pyzbar import decode; print('ok')"
```

## 3. Optional: copy DLLs into `native/`

If the wheel DLLs are missing or broken, copy `libzbar-64.dll` + `libiconv.dll`
into this `native/` folder, then from `native/`:

```powershell
cd C:\Users\BRANDON\Downloads\tcc-unifast\backend\ocr-service\native
$env:ZBAR_DLL_PATH = (Resolve-Path .).Path
..\.venv\Scripts\python.exe -c "from pyzbar.pyzbar import decode; print('ok')"
```

## Soft-fail behavior

OCR and onboarding continue when pyzbar is unavailable — QR is best-effort
(`qr_code.engine=unavailable`). OpenCV `QRCodeDetector` is tried as fallback.
