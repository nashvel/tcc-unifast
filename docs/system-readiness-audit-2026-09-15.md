# System Readiness Audit

**Date:** 2026-09-15  
**Scope:** Repository inspection and local automated checks. This is not a production penetration test, legal review, or end-to-end test against a configured MySQL database, SIS, Facebook, Google Workspace, or a real device.

## Executive Summary

The project is a **feature-rich working prototype**, but it is **not release-ready**. The frontend production build, TypeScript check, and Laravel automated suite now pass. The sandbox used for this audit cannot reach the host MySQL instance, so database-backed runtime behavior was not verified here.

### Current Progress Estimate

| Measure | Estimate | Basis |
| --- | ---: | --- |
| Functional implementation | **75%** | Most documented modules, APIs, pages, migrations, jobs, and 50 backend test files exist. External integrations and some advanced processing remain unverified. |
| Automated quality readiness | **80%** | The frontend production build, type check, Laravel suite, and CI quality workflow are configured; PHP formatting remains unresolved. |
| Local operational readiness | **40%** | Redis, OCR, and n8n containers are running, but the audit sandbox cannot reach host MySQL, OCR, or n8n; their end-to-end behavior was not verified here. |
| Production readiness | **35%** | No CI workflow, no demonstrated external-integration test, unresolved quality gates, and privacy/security operational checks remain. |
| Overall system progress | **78%** | Useful for controlled development and demonstrations after local setup is repaired; not suitable for a production TES rollout yet. |

The percentages are planning estimates, not test coverage. They must be revised after a real environment test with role-based users, MySQL, queue worker, scheduler, OCR, and approved external credentials.

## Verification Snapshot

| Check | Result | Evidence |
| --- | --- | --- |
| Frontend production build | Pass | `npm run build` completed successfully. |
| Backend automated suite | Pass | 311 passed, 3 skipped; 1,510 assertions. |
| Frontend type check | Pass | `npm run lint` (`vue-tsc --noEmit`) completes with no errors after the current fixes. |
| PHP formatting | Fail | `./vendor/bin/pint --test` reports formatting drift in 30 files. |
| Docker Compose syntax | Pass | `docker compose -f compose.yml config --quiet` succeeds. |
| Support containers | Partial | Redis is healthy; OCR service and n8n containers are running. Service HTTP reachability was not proven from the audit shell. |
| Host database | Not verified from sandbox | Laravel in this sandbox cannot connect to `127.0.0.1:3306`; this does not establish whether the developer's host MySQL is healthy. |
| Frontend automated tests | Missing | No frontend `*.test.*` or `*.spec.*` files and no test script were found. |
| Continuous integration | Configured; pending first GitHub run | `.github/workflows/quality.yml` runs Laravel tests, frontend type-check/build, and Compose configuration validation on pull requests and pushes to `main`. |

## Priority Backlog

### P0 — Must verify before a usable local demo

| Finding | Impact | Required outcome |
| --- | --- | --- |
| Database-backed runtime has not been verified from this sandbox. `php artisan migrate:status` cannot reach host MySQL at `127.0.0.1:3306` from the sandbox. | The audit cannot confirm migration status, seed data, or MySQL-specific behavior. This is a verification gap, not evidence that the developer's MySQL is down. | On the host, run `php artisan migrate:status`, then smoke-test migrations, seeders, queue worker, scheduler, and an authenticated request. |

### P1 — Must resolve before staging or production

| Finding | Impact | Required outcome |
| --- | --- | --- |
| PHP formatting is not yet a CI gate. | The existing repository-wide Pint drift means formatting regressions are not blocked automatically. | Clear the existing Pint backlog in a formatting-only change, then add `./vendor/bin/pint --test` to the CI workflow. |
| RBAC has competing sources of truth (`users.role` and role/permission relations). | Editing roles in the RBAC UI may not control protected routes. | Select one canonical authorization model, migrate safely, and add route-access tests proving RBAC changes take effect. See `docs/architecture-audit.md`. |
| Developer database viewer can expose sensitive student/KYC information. | Elevated accounts can bypass domain-specific access boundaries. | Restrict it to local/staging by configuration, allowlist tables, redact sensitive fields, and audit every access. |
| Local auth can loop users back to sign-in when frontend and API use different hosts (`localhost` versus `127.0.0.1`). | Cookie-based sessions are unreliable during development; this was observed in the current environment. | Standardize on one hostname in frontend/API URLs, clear old cookies, and add a browser login/logout smoke test. |

### P2 — Schedule after release gates are green

| Finding | Impact | Required outcome |
| --- | --- | --- |
| PHP formatting drift affects 30 files. | Review noise and inconsistent code quality. | Apply Pint in a dedicated formatting-only change, then make Pint mandatory in CI. |
| No frontend tests exist. | Critical UI journeys rely only on build-time validation and manual testing. | Add unit tests for route guards/composables and browser tests for login, onboarding, requirement submission, and staff review. |
| Google Workspace continuity workflow JSON says its backend endpoint is “not implemented,” but `ContinuitySyncController` and route now exist. | Operators may leave a working integration inactive or follow obsolete setup instructions. | Update the workflow documentation/JSON after verifying the signed, idempotent queue flow in n8n. |
| OCR README states structured field extraction is intentionally not implemented. | Automated validation cannot yet rely on complete structured OCR output. | Define supported document fields, add fixtures, implement extraction, and verify accuracy/error handling. |
| OCR and n8n have no Compose health checks. | A running container can be mistaken for a functioning integration. | Add service health endpoints/checks and surface them in an operator health dashboard. |
| Large frontend assets and bundle warning. | Slow initial load on limited student connections. | Compress/resize the largest landing images and split the 665 kB main JS chunk further. |
| Legal documents were newly seeded but have not had institutional review. | Terms and Privacy Policy may not meet TCC/UniFAST legal and data privacy obligations. | Obtain formal review/approval from the designated data privacy officer or counsel before production publication. |

## Known Deferred or External-Dependency Work

- SIS OIDC requires a real SIS issuer, registered callback, pilot accounts, and operator validation.
- Facebook posting requires approved Meta credentials, n8n credential configuration, and a signed callback test.
- Google Workspace continuity requires credentials, secret matching, n8n activation, queue processing, and a recovery drill.
- Camera, liveness, face verification, QR scanning, and Android Capacitor behavior require real-device/browser acceptance testing.
- OCR, email, notifications, Redis, queues, and scheduled tasks require an integrated host environment; repository tests alone cannot validate them.

## Recommended Order of Work

1. On the host, verify MySQL connectivity and run the entire application locally.
2. Add CI so the recovered backend and frontend checks remain required.
3. Resolve the RBAC and database-viewer security boundaries.
4. Run role-based browser smoke tests, then integration tests for OCR, queue/scheduler, n8n, and external providers.
5. Perform privacy/legal review and deploy only after the staging checklist passes.

## Audit Artifacts

- Existing architecture risks: `docs/architecture-audit.md`
- Deployment/runbook: `docs/deployment.md`
- Feature map: `docs/features-modules.md`
- Current backend test command: `cd backend && php artisan test`
- Current frontend checks: `cd frontend && npm run lint && npm run build`
