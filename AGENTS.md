<!-- LOVABLE:BEGIN -->
> [!IMPORTANT]
> This project is connected to [Lovable](https://lovable.dev). Avoid rewriting
> published git history — force pushing, or rebasing/amending/squashing commits
> that are already pushed — as it rewrites history on Lovable's side and the
> user will likely lose their project history.
>
> Commits you push to the connected branch sync back to Lovable and show up in
> the editor, so keep the branch in a working state.
<!-- LOVABLE:END -->

# TCC UniFAST Repository Guide

These instructions apply to the entire repository. A more deeply nested
`AGENTS.md`, if added later, may provide additional rules for its subtree.

## Project Purpose

TCC UniFAST is a TES administrator and student portal for Tagoloan Community
College. Its core workflow is:

1. Import the CHED masterlist and create an academic batch.
2. Invite and activate grantees.
3. Complete KYC, ID scanning, liveness, and face verification.
4. Upload requirement and academic documents.
5. Run OCR, validation, risk scoring, and eligibility checks.
6. Let staff review submissions and generate billing/distribution reports.

Read `docs/features-modules.md` for the feature map, `SYSTEM_MAP.md` for the
system overview, and `docs/database-schema-reference.md` before changing a
cross-module workflow or schema.

## Stack and Layout

- `frontend/`: Vue 3, TypeScript, Vite, Tailwind CSS 4, Vue Router, TanStack
  Vue Query, Vue I18n, Laravel Echo, and browser-side face/QR tooling.
- `backend/`: Laravel 13 on PHP 8.4, Sanctum authentication, MySQL, queues,
  scheduled jobs, mail, PDF generation, and Excel imports.
- `backend/ocr-service/`: containerized FastAPI service with Tesseract,
  OpenCV, PyMuPDF, and ZBar/pyzbar.
- `mobile/`: Android Capacitor WebView wrapper for the deployed Vue portal.
- `n8n/`: containerized workflow automation and Facebook integration assets.
- `compose.yml`: local supporting services only.
- `backend/database/migrations/`: canonical database schema history.
- `backend/tests/`: PHPUnit feature and unit coverage.
- `docs/`: architecture, deployment, feature, integration, and security notes.

## Runtime Boundary

The frontend, backend, and MySQL run directly on the host. Do not add any of
the following to Docker Compose:

- Vue frontend
- Laravel backend
- MySQL or another application database
- Laravel migration jobs
- Laravel queue workers
- Laravel scheduler processes

Docker Compose must contain exactly these supporting services:

- `n8n`
- `ocr-service`
- `redis`

Host-run Laravel connects to them through:

```env
REDIS_HOST=127.0.0.1
REDIS_PORT=6380
OCR_SERVICE_URL=http://127.0.0.1:8081
TCC_UNIFAST_N8N_WEBHOOK_URL=http://127.0.0.1:5678/webhook/tcc-unifast/social-posts/facebook
```

n8n reaches host-run Laravel through
`LARAVEL_API_URL=http://host.docker.internal:8000`.

Kubernetes manifests are a separate deployment path. Do not use them as the
source of truth for the local Docker Compose runtime.

## Local Commands

Start or validate the Docker support services from the repository root:

```bash
docker compose -f compose.yml up -d --build
docker compose -f compose.yml config --quiet
docker compose -f compose.yml config --services
docker compose -f compose.yml down
```

Run Laravel on the host:

```bash
cd backend
composer install
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
```

Run background Laravel processes in separate host terminals when needed:

```bash
cd backend
php artisan queue:work
php artisan schedule:work
```

Run Vue on the host:

```bash
cd frontend
npm install
npm run dev
```

Sync or open the Android wrapper:

```bash
cd mobile
npm install
npm run sync
npm run open:android
```

## Verification

Use the smallest relevant checks while iterating, then run the broader checks
for the areas changed:

```bash
cd backend && php artisan test
cd backend && ./vendor/bin/pint --test
cd frontend && npm run lint
cd frontend && npm run build
docker compose -f compose.yml config --quiet
```

Backend tests use in-memory SQLite for isolation; the running application uses
host MySQL. A passing SQLite test does not prove a MySQL-specific query or
migration is valid, so verify database-sensitive changes against MySQL too.

## Backend Conventions

- Keep controllers focused on HTTP validation, authorization context, and
  response mapping.
- Put use-case orchestration and transactions in application services.
- Keep OCR parsing, risk scoring, eligibility, batch-window, and identity logic
  in focused domain services.
- Jobs should coordinate services instead of accumulating business logic.
- Use Form Requests or explicit Laravel validation for untrusted input.
- Preserve Sanctum, role, permission, full-session, and onboarding middleware
  boundaries when adding routes.
- Use migrations for schema changes. Do not modify a live schema manually or
  edit old migrations that may already have run in another environment.
- Preserve the existing API response conventions and pagination helpers.
- Store sensitive uploaded documents through the existing secure storage
  helpers; do not expose storage paths or create new unauthenticated file URLs.

## Frontend Conventions

When creating or modifying frontend code, use the project-local
`frontend-performance` skill in `.codex/skills/frontend-performance/SKILL.md`.
If that file is unavailable, use `Frontend-Performance-Skills.md` as the
repository fallback.

Apply its guidance for skeleton states, reactive updates, real-time UX,
optimistic updates, undo flows, efficient tables, caching, and standardized UI
states.

- Follow existing module patterns under `frontend/src/modules/` before adding
  a new structure.
- Keep remote state in TanStack Vue Query and use shared query keys and targeted
  invalidation.
- Avoid full-page reloads and repeated polling. Use reactive updates or the
  existing Echo event pattern where live updates are required.
- Lazy-load route-level pages and heavy browser libraries.
- Provide accessible loading, empty, error, offline, and success states.
- Keep UI copy translatable through the existing Vue I18n setup.
- Preserve role-specific navigation and onboarding route guards.

## Mobile Conventions

- Keep the wrapper under `mobile/`; do not merge native Android tooling into
  the Vue package or Docker Compose.
- Keep `@capacitor/core`, `@capacitor/android`, and any future iOS platform on
  the same release version.
- Use HTTPS for the production `CAPACITOR_SERVER_URL`. Cleartext is permitted
  only for explicit local-device testing.
- Request the minimum native permissions. Camera access is required by the
  existing identity workflows; do not add microphone, location, Bluetooth, or
  storage permissions without a feature requirement.
- Run `npm run sync` after changing Capacitor config or local wrapper assets.
- Do not commit signing keys, keystores, `local.properties`, or build output.

## Security and Data Rules

- Never commit `.env` files, secrets, access tokens, real student records, or
  production exports.
- Treat student IDs, KYC data, identity photos, face descriptors, academic
  records, and submitted documents as sensitive personal data.
- Do not weaken upload validation, signed-file access, throttling, audit logs,
  RBAC, CAPTCHA, PIN, 2FA, or onboarding gates to make a test pass.
- Use fictional or explicitly approved test data. Do not place real personal
  data in fixtures, screenshots, or logs.
- Keep the developer database viewer permission-gated and avoid expanding its
  production exposure.
- Maintain matching webhook secrets between Laravel and n8n; never hardcode
  them in source or documentation.

## Change Discipline

- Read the target file, related tests, and one nearby implementation pattern
  before editing.
- Keep changes scoped; do not mix feature work with unrelated cleanup.
- Add or update tests when behavior changes or a bug is fixed.
- Update documentation and environment examples when runtime configuration or
  operational commands change.
- Preserve user-authored work in a dirty worktree and do not discard unrelated
  changes.
- Do not rewrite published Git history. Never force-push or rebase, amend, or
  squash commits already pushed to the Lovable-connected branch.
- Do not commit generated build output, local logs, `.env` files, or credentials.

## Definition of Done

A change is complete when the requested behavior is implemented, relevant
tests and static checks pass, configuration validates, security boundaries are
preserved, documentation is current, and the final handoff identifies any
checks that could not be run.
