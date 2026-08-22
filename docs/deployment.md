# Deployment

This repo now has one local Docker stack and Kubernetes manifests with the same
service names:

| App | Container/Kubernetes service | Local URL |
| --- | --- | --- |
| Vue frontend | `frontend` | <http://localhost:5173> |
| Laravel API | `backend` | <http://localhost:8000> |
| Laravel queue worker | `queue` | internal |
| Laravel scheduler | `scheduler` | internal |
| MySQL | `mysql` | `localhost:3307` |
| Redis | `redis` | `localhost:6380` |
| Python OCR | `ocr-service` | <http://localhost:8001/health> |
| n8n | `n8n` | <http://localhost:5678> |

## Docker Compose

Start the full local stack:

```bash
docker compose -f compose.yml up -d --build
```

Laravel migrations run automatically through the one-shot `migrate` service.
You can rerun them manually when needed:

```bash
docker compose -f compose.yml exec backend php artisan migrate
```

Stop the stack:

```bash
docker compose -f compose.yml down
```

The root `.env.example` shows override variables for ports, database
credentials, `APP_KEY`, and `N8N_ENCRYPTION_KEY`.

## Internal URLs

Use these URLs from containers and Kubernetes pods:

```env
DB_HOST=mysql
REDIS_HOST=redis
OCR_SERVICE_URL=http://ocr-service:8001
TCC_UNIFAST_N8N_WEBHOOK_URL=http://n8n:5678/webhook/tcc-unifast/social-posts/facebook
```

Keep `TCC_UNIFAST_N8N_WEBHOOK_URL` empty until the n8n workflow is imported and
activated. Then set the webhook secret in the runtime secret store.

## Kubernetes

For local clusters, build or load these images into kind/minikube:

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
kubectl -n tcc-unifast port-forward svc/ocr-service 8001:8001
kubectl -n tcc-unifast port-forward svc/n8n 5678:5678
```

For production, create a real overlay that replaces the local image names,
sets external URLs, disables local bypass flags, and sources secrets from your
cluster secret manager instead of committing them.
