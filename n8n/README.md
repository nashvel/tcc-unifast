# Local n8n

This folder runs local n8n and OCR services for TCC UniFAST workflow development.

## Start

```bash
cp n8n/.env.example n8n/.env
docker compose --env-file n8n/.env -f n8n/docker-compose.yml up -d
```

Open:

```text
n8n: http://localhost:5678
OCR health: http://localhost:8001/health
OCR Swagger: http://localhost:8001/docs
```

On first launch, n8n asks you to create the local owner account.

## Stop

```bash
docker compose --env-file n8n/.env -f n8n/docker-compose.yml down
```

## Update

```bash
docker compose --env-file n8n/.env -f n8n/docker-compose.yml pull
docker compose --env-file n8n/.env -f n8n/docker-compose.yml up -d
```

## Data

The Compose file persists n8n data in the Docker volume `n8n_n8n_data`.
This stores workflows, credentials, execution history, and the settings file.

OCR debug output is stored in the Docker volume `n8n_ocr_outputs`.

The `local-files/` folder is mounted into n8n at `/files` for workflows that
need to read or write local files.

Set `N8N_ENCRYPTION_KEY` in `n8n/.env` before creating credentials if you want
a stable key outside the Docker volume. Keep `n8n/.env` private.

## Import the TCC UniFAST workflow

Import the project workflow from:

```text
docs/n8n-tcc-unifast-facebook-social-post-workflow.json
```

Then configure the n8n credentials and variables described in:

```text
docs/n8n-facebook-batch-announcement.md
```

## Laravel OCR config

For the Laravel backend running on your host machine, keep:

```env
OCR_SERVICE_URL=http://127.0.0.1:8001
OCR_SERVICE_TIMEOUT=120
OCR_SPACE_API_KEY=
```

If Laravel is later run inside the same Compose network, use
`OCR_SERVICE_URL=http://ocr-service:8001` from that container instead.

The OCR container includes Tesseract OCR and ZBar/pyzbar support for best-effort
School ID back QR decoding.
