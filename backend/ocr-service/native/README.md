# Native ZBar DLLs (Windows)

`pyzbar` wheels ship `libzbar-64.dll` and `libiconv.dll` under
`site-packages/pyzbar/`. If import still fails with “or one of its
dependencies”, install the [Microsoft Visual C++ Redistributable](https://learn.microsoft.com/en-us/cpp/windows/latest-supported-vc-redist)
and optionally place extra DLLs here, then:

```powershell
$env:ZBAR_DLL_PATH=(Resolve-Path .).Path
..\ .venv\Scripts\python -c "from pyzbar.pyzbar import decode; print('ok')"
```

OCR and onboarding continue when pyzbar is unavailable — QR is best-effort
(`qr_code.engine=unavailable`). OpenCV `QRCodeDetector` is tried as fallback.
