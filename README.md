# TCC UniFAST

Laravel 13, Vue 3, Python OCR, and n8n automation for the TCC UniFAST TES administrator and student portals.

The application runs the frontend, backend, and MySQL directly on the host.
Docker Compose is reserved for supporting services:

| Service | Path | Purpose |
| --- | --- | --- |
| Frontend | `frontend/` | Vue 3 SPA run with Vite on the host |
| Backend | `backend/` | Laravel API, queue worker, and scheduler run on the host |
| MySQL | Host installation | Application database |
| Mobile | `mobile/` | Android Capacitor WebView wrapper |
| Redis | Docker Compose | Cache/runtime service |
| OCR service | Docker Compose | FastAPI + Tesseract OCR + ZBar |
| n8n | Docker Compose | Workflow automation and Facebook posting workflows |

## Quick Start

Start the three Docker support services:

```bash
docker compose -f compose.yml up -d --build
```

Open:

| App | URL |
| --- | --- |
| Frontend | http://localhost:5173 |
| Backend health | http://localhost:8000/health |
| OCR health | http://localhost:8081/health |
| OCR Swagger | http://localhost:8081/docs |
| n8n | http://localhost:5678 |

Configure `backend/.env` to use the host MySQL server, Redis at
`127.0.0.1:6380`, OCR at `127.0.0.1:8081`, and n8n at
`127.0.0.1:5678`. Then start the application on the host:

```bash
cd backend
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
```

In a separate terminal:

```bash
cd frontend
npm run dev
```

Run `php artisan queue:work` and `php artisan schedule:work` from `backend/`
in additional terminals when those background processes are needed.

Build or open the Android wrapper separately:

```bash
cd mobile
npm install
npm run sync
npm run open:android
```

See `mobile/README.md` for URL overrides, Android requirements, and build
commands.

## Common Commands

```bash
docker compose -f compose.yml ps
docker compose -f compose.yml logs -f n8n ocr-service redis
docker compose -f compose.yml down
```

Local host ports:

| Service | Host port | Internal service |
| --- | ---: | --- |
| Redis | `6380` | `redis:6379` |
| OCR | `8081` | `ocr-service:8001` |
| n8n | `5678` | `n8n:5678` |

The host-run frontend, backend, and MySQL are not Docker services.

## Kubernetes

Kubernetes manifests remain under `k8s/` as a separate deployment option using
a base/overlay layout. They are not used by the local Docker Compose workflow.

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

- [Deployment](docs/deployment.md) — host application setup, Docker support services, and Kubernetes notes.
- [n8n Facebook Batch Announcement Integration](docs/n8n-facebook-batch-announcement.md) — workflow guidance for controlled Facebook posting.

## Notes

- Keep secrets out of the repository. Use `.env`, `n8n/.env`, Docker secrets, or Kubernetes Secrets.
- `TCC_UNIFAST_N8N_WEBHOOK_URL` should stay empty until the n8n workflow is imported and activated.
- Host-run Laravel uses `OCR_SERVICE_URL=http://127.0.0.1:8081`.
- Host-run Laravel should use `TCC_UNIFAST_N8N_WEBHOOK_URL=http://127.0.0.1:5678/webhook/tcc-unifast/social-posts/facebook` when the workflow is active.
