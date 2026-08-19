# TCC UniFAST

Laravel 13, Vue 3, Python OCR, and n8n automation for the TCC UniFAST TES administrator and student portals.

The project is structured as deployable services:

| Service | Path | Purpose |
| --- | --- | --- |
| Frontend | `frontend/` | Vue 3 SPA served by nginx in Docker |
| Backend | `backend/` | Laravel API on FrankenPHP |
| Queue | `backend/` | Laravel queue worker for async OCR/submission jobs |
| Scheduler | `backend/` | Laravel scheduled tasks |
| OCR service | `backend/ocr-service/` | FastAPI + Tesseract OCR + ZBar |
| n8n | `n8n/` | Local workflow automation and Facebook posting workflows |
| MySQL / Redis | Docker/Kubernetes | Database and cache/runtime services |

## Quick Start

Run the full local stack:

```bash
docker compose -f compose.yml up -d --build
```

Open:

| App | URL |
| --- | --- |
| Frontend | http://localhost:5173 |
| Backend health | http://localhost:8000/health |
| OCR health | http://localhost:8001/health |
| OCR Swagger | http://localhost:8001/docs |
| n8n | http://localhost:5678 |

Docker Compose runs migrations through the one-shot `migrate` service before starting the queue and scheduler.

## Common Commands

```bash
docker compose -f compose.yml ps
docker compose -f compose.yml logs -f backend
docker compose -f compose.yml exec backend php artisan migrate
docker compose -f compose.yml down
```

Local host ports:

| Service | Host port | Internal service |
| --- | ---: | --- |
| Frontend | `5173` | `frontend:80` |
| Backend | `8000` | `backend:8080` |
| OCR | `8001` | `ocr-service:8001` |
| n8n | `5678` | `n8n:5678` |
| MySQL | `3307` | `mysql:3306` |
| Redis | `6380` | `redis:6379` |

## Kubernetes

Kubernetes manifests live under `k8s/` using a base/overlay layout.

```bash
kubectl apply -k k8s/overlays/local
```

For local clusters, build or load these images first:

```text
tcc-unifast/backend:local
tcc-unifast/frontend:local
tcc-unifast/ocr-service:local
```

See [Deployment](docs/deployment.md) for Kubernetes port-forwarding, internal URLs, and production overlay notes.

## Integration Docs

- [Deployment](docs/deployment.md) — Docker Compose and Kubernetes structure for all services.
- [n8n Facebook Batch Announcement Integration](docs/n8n-facebook-batch-announcement.md) — workflow guidance for controlled Facebook posting.

## Notes

- Keep secrets out of the repository. Use `.env`, `n8n/.env`, Docker secrets, or Kubernetes Secrets.
- `TCC_UNIFAST_N8N_WEBHOOK_URL` should stay empty until the n8n workflow is imported and activated.
- Laravel uses `OCR_SERVICE_URL=http://ocr-service:8001` inside Docker/Kubernetes.
- The backend image includes Python helper dependencies for `backend/python/pdf_extract.py`, `pdf_metadata.py`, and `gradeslip_qr.py`.
