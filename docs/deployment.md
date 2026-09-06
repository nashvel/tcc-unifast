# Deployment

For local development, Vue, Laravel, and MySQL run directly on the host. Docker
Compose runs only Redis, OCR, and n8n:

| App | Container/Kubernetes service | Local URL |
| --- | --- | --- |
| Redis | `redis` | `localhost:6380` |
| Python OCR | `ocr-service` | <http://localhost:8081/health> |
| n8n | `n8n` | <http://localhost:5678> |

The host services remain available at:

| App | Local URL |
| --- | --- |
| Vue frontend | <http://localhost:5173> |
| Laravel API | <http://localhost:8000> |
| MySQL | `127.0.0.1:3306` by default |

## Docker Compose

Start the supporting services:

```bash
docker compose -f compose.yml up -d --build
```

Run Laravel migrations and application processes on the host:

```bash
cd backend
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
php artisan queue:work
php artisan schedule:work
```

Run each long-lived Laravel command in its own terminal. Start the frontend with
`npm run dev` from `frontend/`.

Stop the stack:

```bash
docker compose -f compose.yml down
```

The root `.env.example` contains overrides for the three Docker services.
Application and database settings belong in `backend/.env` and
`frontend/.env`.

## Internal URLs

Use these values in the host-run Laravel `backend/.env`:

```env
DB_HOST=127.0.0.1
DB_PORT=3306
REDIS_HOST=127.0.0.1
REDIS_PORT=6380
OCR_SERVICE_URL=http://127.0.0.1:8081
TCC_UNIFAST_N8N_WEBHOOK_URL=http://127.0.0.1:5678/webhook/tcc-unifast/social-posts/facebook
```

Keep `TCC_UNIFAST_N8N_WEBHOOK_URL` empty until the n8n workflow is imported and
activated. n8n reaches host-run Laravel through
`http://host.docker.internal:8000`, configured by `LARAVEL_API_URL` in the root
Compose environment. Set matching webhook secrets in both environments.

## Kubernetes

Kubernetes is a separate deployment path and does not participate in the local
Docker Compose stack. For local clusters, build or load these images into
kind/minikube:

```text
tcc-unifast/backend:local
tcc-unifast/frontend:local
tcc-unifast/ocr-service:local
```

Apply the local overlay:

```bash
kubectl apply -k k8s/overlays/local
```

Run migrations again after changing schema:

```bash
kubectl -n tcc-unifast exec deploy/backend -- php artisan migrate
```

For local access without an ingress controller:

```bash
kubectl -n tcc-unifast port-forward svc/frontend 5173:80
kubectl -n tcc-unifast port-forward svc/backend 8000:8080
kubectl -n tcc-unifast port-forward svc/ocr-service 8081:8001
kubectl -n tcc-unifast port-forward svc/n8n 5678:5678
```

For production, create a real overlay that replaces the local image names,
sets external URLs, disables local bypass flags, and sources secrets from your
cluster secret manager instead of committing them.
