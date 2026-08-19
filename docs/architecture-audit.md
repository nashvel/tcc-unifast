# Architecture Audit

Date: 2026-08-13

## Rating

**6.5 / 10**

This was the original audit rating from 2026-08-13. After the backend hardening work, the current architecture rating is documented in [`architecture-hardening-report.md`](architecture-hardening-report.md) as **9.0 / 10**.

The application has a solid service-oriented foundation for a capstone system: Laravel API, Vue SPA, queue worker, scheduler, OCR microservice, n8n automation, MySQL, and Redis are separated as deployable services. The domain is also reasonably modeled through grantees, batches, submissions, identity profiles, pipeline results, policy settings, and audit logs.

The score is not higher because the most important workflows are concentrated in large controller/job files, RBAC has two competing sources of truth, and the project has almost no automated architecture guardrails.

## What Works Well

- The runtime split is clear: `frontend/`, `backend/`, `backend/ocr-service/`, `n8n/`, Docker Compose, and Kubernetes manifests.
- Backend domain services already exist for OCR, PDFs, risk scoring, billing, masterlist validation, batch windows, and file storage.
- Sensitive document access is mostly centralized through `VaultFileStorage`, authenticated routes, and signed routes.
- The frontend uses Vue Query with shared query keys, which gives the app a good base for caching and reactive list/detail flows.
- The test suite includes many feature and unit tests around the business-critical flows.

## Main Risks

### 1. RBAC Has Two Sources Of Truth

The code has both:

- `users.role`, used by route middleware.
- `roles`, `permissions`, `role_user`, and `permission_role`, exposed through the RBAC module.

Current route authorization checks the scalar role. That means role assignments in the RBAC module can appear to work while not actually controlling most protected routes.

**Improve by:**

- Choosing one canonical authorization model.
- Prefer relationship-backed roles and permissions for admin/staff/developer access.
- Updating middleware to use `User::hasAnyRole()` or Laravel policies.
- Keeping `users.role` only as a cached/display column if needed, with synchronization rules.
- Adding tests that prove RBAC screen changes affect route access.

### 2. Requirement Vault Controller Is Too Large

`RequirementVaultController` handles HTTP validation, onboarding checks, face matching, OCR calls, QR validation, policy checks, file storage, database writes, audit logs, response presentation, and queue dispatching.

This makes the most important student submission flow hard to reason about and risky to modify.

**Improve by extracting use-case services:**

- `ShowRequirementVault`
- `ValidateVaultIdScan`
- `StoreVaultDocumentSlot`
- `StoreVaultIdentityCheck`
- `ConfirmRequirementPackage`
- `PresentRequirementVault`

The controller should only adapt HTTP requests to these services and return responses.

### 3. Submission Pipeline Job Owns Too Much Business Logic

`ProcessRequirementSubmissionPipeline` currently coordinates PDF extraction, grade parsing, QR checks, risk scoring, eligibility status mutation, n8n dispatch, notifications, and persistence.

**Improve by turning the job into an orchestrator:**

- `ExtractSubmissionDocuments`
- `AnalyzeAcademicRecords`
- `EvaluateSubmissionRisk`
- `EvaluateEligibility`
- `PersistPipelineResult`
- `NotifyPipelineComplete`

Each stage should return a typed result array/object with clear success, warning, and failure states.

### 4. Frontend Routes And Student Access Rules Are Too Centralized

`frontend/src/createApp.ts` owns most route declarations and the full student onboarding guard.

**Improve by:**

- Moving admin routes, student routes, and public routes into separate route files.
- Moving onboarding access decisions into `resolveStudentRouteAccess(user, route)`.
- Unit testing the onboarding redirect matrix: KYC, ID scan, liveness, face review, active, blocked.

### 5. Developer Database Viewer Is Broadly Powerful

The database viewer can inspect tables and browse rows for developer/admin users. That is useful during development, but it bypasses domain-specific authorization and can expose sensitive data.

**Improve by:**

- Disabling database viewer routes outside local/staging through config.
- Whitelisting readable tables.
- Redacting sensitive columns by default.
- Logging table access with actor, table, filters, and timestamp.

### 6. Architecture Tests Are Missing

`backend/tests/Unit/ArchitectureTest.php` only checks PHP version. It does not enforce layering, route security, controller size, or dependency direction.

**Improve by adding tests for:**

- No public API routes except explicitly allowlisted routes.
- Protected routes must use role/policy middleware.
- Controllers over a line-count threshold are flagged.
- Controllers should not call external HTTP services directly.
- Controllers should not use raw `DB::table` except allowlisted technical controllers.
- Domain services should not depend on HTTP request classes.
- Frontend modules should not import from unrelated modules except through shared `api`, `components`, `composables`, or `lib`.

## Suggested Priority Plan

### Phase 1: Stabilize Boundaries

1. Decide the canonical RBAC model.
2. Add architecture tests for route auth and controller size.
3. Config-gate the database viewer.
4. Document backend module ownership.

### Phase 2: Extract Critical Use Cases

1. Extract requirement vault actions into service classes.
2. Extract submission pipeline stages.
3. Add focused unit tests for each extracted service.
4. Keep existing feature tests as end-to-end safety coverage.

### Phase 3: Improve Frontend Maintainability

1. Split route definitions by portal/domain.
2. Extract and test student onboarding route policy.
3. Continue moving large Vue modules into smaller components/composables.
4. Standardize list/detail/loading/empty/error states across modules.

### Phase 4: Operational Hardening

1. Add structured logging around OCR, face verification, pipeline execution, and n8n dispatch.
2. Add queue failure visibility and retry policy documentation.
3. Add health checks for backend, OCR, Redis, queue, scheduler, database, and n8n webhook status.
4. Define production-only restrictions for developer tooling.

## Recommended Architecture Direction

Use a layered Laravel structure without overcomplicating the app:

- **Controllers:** request validation, authentication context, response mapping.
- **Application services:** use-case orchestration and transactions.
- **Domain services:** OCR parsing, risk scoring, eligibility rules, batch window logic.
- **Support services:** storage, upload safety, URL generation, external clients.
- **Jobs:** queue entry points only; delegate real work to services.
- **Policies/middleware:** authorization rules.
- **Resources/presenters:** API response shape.

For the Vue app:

- **Routes:** grouped by portal/domain.
- **API clients:** one file per backend resource.
- **Composables:** server state and UI workflow state.
- **Modules:** page-level screens only.
- **Components:** reusable UI and domain widgets.

## Definition Of Done For Architecture Improvements

- RBAC has one source of truth.
- Requirement vault and pipeline files are below 500 lines each or split by responsibility.
- Architecture tests fail when new routes bypass auth or when controllers absorb domain work.
- Database viewer is disabled or restricted in production.
- Critical flows have both feature tests and service-level unit tests.
- Onboarding route behavior is tested without needing a browser.
