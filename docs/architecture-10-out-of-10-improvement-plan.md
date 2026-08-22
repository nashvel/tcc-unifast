# Architecture 10/10 Improvement Plan

Date: 2026-08-13

## Goal

Raise the application architecture from **6.5/10** to a level that a strict external reviewer would rate **10/10** for maintainability, security, testability, scalability, and clarity.

This document is intentionally practical. It defines what to improve, why it matters, how to do it, and what must be true before the architecture can be considered excellent.

## Target Architecture Scorecard

| Area | Current State | Target State |
| --- | --- | --- |
| Backend layering | Controllers and jobs contain too much business logic | Controllers are thin, jobs delegate to services, domain logic lives in tested services |
| Authorization | `users.role` and RBAC tables both exist | One canonical RBAC/permission model controls all protected routes |
| Frontend structure | Routes and guards are centralized in `createApp.ts` | Routes and access policies are split by domain and tested |
| Critical workflows | Requirement vault and pipeline are large multi-purpose flows | Each workflow is split into clear use-case services and pipeline stages |
| Architecture tests | Only PHP runtime is checked | Automated tests enforce route security, layering, file size, and dependency rules |
| Developer tooling | Database viewer is powerful and broad | Dev tools are environment-gated, allowlisted, redacted, and audited |
| Observability | Some logs exist, but no complete operational picture | OCR, queue, n8n, auth, uploads, and pipeline failures are traceable |
| Documentation | Feature docs exist | Architecture, module ownership, decisions, and operating procedures are documented |

## Non-Negotiable Principles

1. **One source of truth for authorization.**
   RBAC must not be half scalar-column and half permissions table.

2. **Controllers do not own business processes.**
   Controllers validate input, call an application service, and return a response.

3. **Jobs are queue entry points, not business engines.**
   Jobs resolve a service and delegate the workflow.

4. **Every critical workflow has service-level tests and feature tests.**
   Feature tests prove the user flow works. Unit tests prove rules are correct.

5. **Developer-only power tools are disabled or heavily restricted in production.**
   Database browsing, API docs, playgrounds, and internal health details must not expose sensitive data.

6. **Architecture rules are executable.**
   Important boundaries must be enforced by tests or static checks, not memory.

## Phase 1: Fix Authorization Foundation

### Problem

The app currently has:

- `users.role`
- `roles`
- `permissions`
- `role_user`
- `permission_role`

But route middleware checks `users.role`, not the relationship-backed RBAC model.

### Target

All protected access must flow through one authorization model.

### Implementation Plan

1. Update `RequireRole` middleware to use `User::hasAnyRole()`.
2. Add a fallback transition rule only if needed:
   - If a user has no assigned roles, fallback to `users.role`.
   - Log or test this as legacy behavior.
3. Add permission middleware for fine-grained actions:
   - `permission:manage_batches`
   - `permission:review_submissions`
   - `permission:view_database`
   - `permission:manage_rbac`
4. Move sensitive routes from broad role checks to permissions.
5. Add tests for every protected route group.

### Acceptance Criteria

- Changing a user role through RBAC changes route access.
- No protected route depends only on stale `users.role`.
- Admin, staff, developer, head, and student permissions are documented.
- Route authorization tests fail if a new protected route lacks middleware.

### Implementation Status

Completed authorization foundation slices:

- `RequireRole` now treats assigned relationship-backed roles as authoritative, with legacy `users.role` fallback only for users that do not yet have RBAC assignments.
- Database viewer routes now require the canonical `view_database` permission through `permission:view_database` middleware instead of broad developer/admin role access.
- `view_database` is seeded as a developer-tool permission and is granted to the seeded developer role by default.
- Architecture tests enforce `auth:sanctum` plus `permission:view_database` on database viewer routes.
- Feature tests prove an assigned admin role without `view_database` is blocked while a role with `view_database` can access the viewer.

## Phase 2: Make Backend Layers Clean

### Target Backend Layers

```text
HTTP Controller
  -> Form Request / inline validation
  -> Application Service
  -> Domain Service
  -> Model / Repository where needed
  -> Presenter / Resource
```

### Controller Rule

A controller action should usually do only five things:

1. Read authenticated user/context.
2. Validate request data.
3. Call one use-case service.
4. Convert result to JSON.
5. Let exceptions become standard API errors.

### Files To Refactor First

| File | Reason |
| --- | --- |
| `backend/app/Http/Controllers/RequirementVaultController.php` | Critical student submission flow, very large |
| `backend/app/Http/Controllers/IdentityOnboardingController.php` | Sensitive identity workflow |
| `backend/app/Jobs/ProcessRequirementSubmissionPipeline.php` | Critical OCR/risk/eligibility pipeline |
| `backend/app/Services/AcademicGradeParser.php` | Large parsing logic, needs smaller tested units |

### Requirement Vault Target Services

Create use-case services under `backend/app/Services/RequirementVault/`:

- `ShowRequirementVaultService`
- `ValidateVaultFrontIdOcrService`
- `StoreVaultSchoolIdService`
- `StoreVaultDocumentSlotService`
- `StoreVaultIdentityCheckService`
- `ConfirmRequirementPackageService`
- `ResubmitRequirementSlotService`
- `RequirementVaultPresenter`

### Pipeline Target Services

Create pipeline services under `backend/app/Services/SubmissionPipeline/`:

- `LoadSubmissionPackage`
- `ExtractPdfEvidence`
- `AnalyzeAcademicEvidence`
- `AnalyzeQrEvidence`
- `EvaluateRiskSignals`
- `EvaluateEligibilityResult`
- `PersistPipelineResult`
- `DispatchPipelineIntegrations`
- `NotifyPipelineResult`

### Acceptance Criteria

- No critical controller exceeds 500 lines.
- No job exceeds 250 lines.
- Pipeline stages can be tested without running a queue worker.
- Business rules are named and discoverable by class name.

### Implementation Status

Completed Requirement Vault service extractions:

- `ConfirmRequirementPackageService` owns the final submit gate, required-slot validation, School ID face binding, name consistency checks, draft promotion, audit creation, and pipeline dispatch.
- `StoreVaultDocumentSlotService` owns non-School-ID requirement slot storage, replacement cleanup, legacy pending-review promotion, audit creation, and pipeline dispatch for late-added slots.
- `ResubmitRequirementSlotService` owns returned-slot resubmit validation, School ID face-bind enforcement during resubmit, status promotion, grantee submission-state updates, audit creation, and pipeline dispatch.
- `StoreVaultIdentityCheckService` owns optional submission liveness logging, server-authoritative face-distance checks, selfie storage, manual-review flagging, and audit creation.
- `StoreVaultSchoolIdService` owns School ID live-scan storage, face binding against onboarding references, front/back OCR handling, QR extraction flags, academic-year flags, audit creation, and pipeline dispatch for late-added School ID slots.
- `ValidateVaultFrontIdOcrService` owns the School ID front-OCR preflight check, hardened MIME verification, expected identity matching, provider-error mapping, and mismatch audit logging.
- `RequirementVaultPresenter` owns Requirement Vault response shaping for grantees, slots, documents, identity checks, and onboarding reference URLs.
- `RequirementVaultController` is now down to 402 lines and the architecture guardrail has been tightened to 450 lines.
- Existing vault draft, resubmit, security, and architecture tests cover these extracted flows.

Completed additional architecture slices:

- `ProcessRequirementSubmissionPipeline` is now a thin queue job that delegates to `ProcessRequirementSubmissionService`; the job is 26 lines and guarded at 100 lines.
- `ProcessRequirementSubmissionService` now delegates Grade Slip QR metadata merge, authenticity checks, and n8n webhook delivery to `PipelineExternalChecksService`; the processor is down to 411 lines and guarded at 450 lines.
- `IdentityOnboardingController` moved School ID front-OCR and ID-scan persistence into `StoreIdentityIdScanService`; the controller is 451 lines and guarded at 500 lines.
- `AcademicGradeParser` now delegates OCR text/program extraction to `AcademicGradeTextParser`, term-window / Grade Slip anchor analysis to `AcademicTermAnalyzer`, and course/term scoring to `AcademicCourseSummarizer` while preserving the existing public parser API; the parser is down to 545 lines and guarded at 600 lines.
- `IdCardOcrService` now owns provider calls only; ID-back parsing lives in `IdCardBackParser` and front-ID identity heuristics live in `IdCardIdentityMatcher`. The OCR service is down to 206 lines and guarded at 250 lines.
- `DocumentSubmissionController` now delegates submission and package response shaping to `DocumentSubmissionPresenter`; the controller is down to 235 lines and guarded at 275 lines while the presenter is guarded at 175 lines.
- `EligibilityController` now delegates eligibility detail/list response shaping, criteria notes, required-document checks, and notice history to `EligibilityPresenter`; the controller is down to 123 lines and guarded at 150 lines while the presenter is guarded at 275 lines.
- `MasterlistImportController` now delegates import response shaping to `MasterlistImportPresenter` and preview row normalization/validation to `MasterlistImportRowValidator`; the controller is down to 230 lines and guarded at 240 lines.
- `SubmissionRiskScoringService` now owns risk signals, score totals, and badges only; academic eligibility evaluation lives in `SubmissionEligibilityEvaluator`. The risk service is down to 128 lines and guarded at 150 lines while the evaluator is guarded at 300 lines.
- `DatabaseController` now delegates enablement/allowlist/redaction policy to `DatabaseViewerPolicy` and audit writes to `DatabaseViewerAuditLogger`; the controller is down to 235 lines and guarded at 250 lines while database viewer security tests continue to cover permission, allowlist, redaction, disablement, and audit behavior.
- `ProcessRequirementSubmissionService` now owns pipeline orchestration only; PDF academic extraction, grade summaries, and Grade Slip anchor reparse live in `PipelineAcademicOcrService`. The processor is down to 156 lines and guarded at 225 lines while academic OCR is guarded at 325 lines.

## Phase 3: Add Architecture Guardrail Tests

### Backend Tests To Add

Add architecture tests under `backend/tests/Unit/ArchitectureTest.php` or a dedicated `backend/tests/Architecture/` directory.

Required checks:

- Every `/api` route is authenticated unless explicitly allowlisted.
- Every authenticated route has role or permission middleware unless intentionally user-wide.
- Controllers do not call `Http::` directly except allowlisted integration controllers.
- Controllers do not call `DB::table` directly except allowlisted technical controllers.
- Controllers over 500 lines fail the architecture test.
- Jobs over 250 lines fail the architecture test.
- Services must not depend on `Illuminate\Http\Request`.
- Models must not call external HTTP services.

### Frontend Tests/Checks To Add

Required checks:

- `createApp.ts` must not exceed a small app-bootstrap threshold after route extraction.
- Domain modules should not import from unrelated domain modules.
- Shared code must live under `api`, `components`, `composables`, `lib`, `layouts`, `auth`, or `i18n`.
- Student onboarding redirects are covered by unit tests.

### Acceptance Criteria

- Architecture tests run in CI.
- A new route without auth/role policy fails tests.
- A controller that becomes too large fails tests.
- A cross-module frontend import fails lint/test checks.

## Phase 4: Harden Developer And Admin Tools

### Database Viewer

Current risk: broad table/row browsing can expose sensitive data.

Target behavior:

- Disabled by default in production.
- Controlled by `FEATURE_DATABASE_VIEWER=false`.
- Requires `view_database` permission.
- Uses an allowlist of readable tables.
- Redacts sensitive fields:
  - passwords
  - remember tokens
  - access tokens
  - face descriptors
  - raw OCR payloads when sensitive
  - private file paths where unnecessary
- Logs every table read.

### Developer Routes

Apply the same rule to:

- API docs
- playground
- database viewer
- internal health detail
- memory/security debug pages

### Acceptance Criteria

- Production cannot expose database rows unless explicitly enabled.
- Sensitive columns are redacted even for admins.
- Access to developer tools appears in audit logs.

### Implementation Status

Completed for the database viewer on 2026-08-14:

- `FEATURE_DATABASE_VIEWER` gates the viewer outside local/testing.
- `DATABASE_VIEWER_ALLOWED_TABLES` controls the readable table allowlist.
- Non-allowlisted tables return 404 and are excluded from table/stat listings.
- Row responses redact sensitive columns such as passwords, tokens, secrets, OCR payloads, face descriptors, metadata payloads, and stored file paths.
- Table, row, and stats reads create `audit_logs` records under the `database_viewer` module.
- Feature coverage lives in `backend/tests/Feature/DatabaseViewerSecurityTest.php`.

## Phase 5: Improve Observability

### Events To Log Structurally

Use structured logs with stable event names:

- `auth.login.succeeded`
- `auth.login.failed`
- `vault.slot.uploaded`
- `vault.package.confirmed`
- `identity.onboarding.id_scan_passed`
- `identity.onboarding.liveness_passed`
- `pipeline.started`
- `pipeline.stage.completed`
- `pipeline.stage.failed`
- `pipeline.completed`
- `ocr.request.failed`
- `n8n.dispatch.failed`
- `queue.job.failed`

### Metrics To Track

- OCR success/failure rate
- OCR latency
- Pipeline duration
- Queue wait time
- Number of failed jobs
- Submission approval/rejection/return rates
- n8n webhook success/failure rate
- Login failures per account/IP

### Acceptance Criteria

- A failed submission pipeline can be diagnosed from logs without reproducing locally.
- Queue failures are visible.
- OCR service failure is distinguishable from bad student input.
- n8n failures do not silently disappear.

## Phase 6: Improve Frontend Architecture

### Route Organization

Split route definitions:

```text
frontend/src/router/
  index.ts
  publicRoutes.ts
  adminRoutes.ts
  studentRoutes.ts
  guards/
    authGuard.ts
    studentOnboardingGuard.ts
```

### Module Organization

Use this convention:

```text
frontend/src/modules/<module>/
  Index.vue
  Detail.vue
  components/
  composables/
  types.ts
```

Shared code belongs outside modules:

```text
frontend/src/api/
frontend/src/components/
frontend/src/composables/
frontend/src/lib/
frontend/src/auth/
```

### Large Vue File Strategy

When a Vue file exceeds 500 lines:

1. Extract repeated UI into components.
2. Extract server state into composables.
3. Extract pure formatting/helpers into local `utils.ts`.
4. Keep the page component responsible for layout and workflow only.

### Acceptance Criteria

- `createApp.ts` only bootstraps app plugins and router.
- Onboarding route decisions are tested separately.
- Large pages are split into page + components + composables.
- Loading, empty, error, offline, and success states are consistent across modules.

## Phase 7: Strengthen Data Model And API Contracts

### API Response Contracts

Create consistent response shapes:

```json
{
  "data": {},
  "meta": {},
  "message": "Optional human-readable message"
}
```

Errors:

```json
{
  "message": "Validation failed.",
  "errors": {
    "field": ["Reason"]
  }
}
```

### Backend Resources

Use Laravel resources/presenters for:

- Grantees
- Batches
- Document submissions
- Requirement vault state
- Eligibility results
- Face review records
- Notifications

### Acceptance Criteria

- Frontend API types match backend response resources.
- No controller manually builds large nested JSON payloads.
- API contract changes are documented and tested.

## Phase 8: Improve Security Posture

### Immediate Improvements

- Ensure all upload endpoints use server-side MIME validation.
- Keep all student documents in private storage.
- Redact sensitive audit/context payloads.
- Add authorization policies for document file access.
- Add signed URL expiry tests.
- Add rate limits for expensive OCR/face endpoints.

### Higher-Level Improvements

- Add security headers tests.
- Add dependency audit in CI.
- Add permission review checklist before deployment.
- Define data retention rules for face descriptors and identity photos.

### Acceptance Criteria

- A student cannot access another student document by changing IDs.
- Staff access to identity photos is permission-controlled and audited.
- Face descriptors are encrypted or stored in a dedicated protected field.
- Upload validation tests cover extension spoofing and MIME mismatch.

## Phase 9: CI/CD Quality Gates

CI should run:

```bash
cd backend && composer test
cd backend && ./vendor/bin/pint --test
cd frontend && npm run lint
cd frontend && npm run build
```

Add custom gates:

- Backend architecture tests.
- Frontend module-boundary tests.
- Dependency audit.
- Migration smoke test.
- Docker build check for backend, frontend, and OCR service.

### Acceptance Criteria

- No merge/deploy if tests fail.
- No merge/deploy if architecture guardrails fail.
- Docker images build from a clean checkout.
- Required environment variables are documented and validated.

## Phase 10: Documentation That Reviewers Expect

Add or maintain these docs:

| Document | Purpose |
| --- | --- |
| `docs/architecture-overview.md` | System boundaries, services, request flow, deployment view |
| `docs/backend-module-map.md` | Controller/service/model ownership per feature |
| `docs/rbac-and-permissions.md` | Canonical roles, permissions, and access rules |
| `docs/requirement-vault-flow.md` | Upload, identity, confirm, review, resubmit lifecycle |
| `docs/submission-pipeline.md` | OCR, risk scoring, eligibility, notification flow |
| `docs/frontend-architecture.md` | Route/module/component/composable conventions |
| `docs/operations-runbook.md` | Local/prod operations, queue, OCR, n8n, failures |
| `docs/security-model.md` | Auth, file access, uploads, PII, identity data |
| `docs/testing-strategy.md` | Unit, feature, architecture, frontend, visual checks |
| `docs/architecture-decisions/` | ADRs for major decisions |

## Final 10/10 Checklist

The architecture can be considered excellent when all of these are true:

- RBAC has one canonical source of truth.
- Every protected route has tested authorization.
- Critical controllers are thin.
- Jobs delegate to services.
- Requirement vault logic is split by use case.
- Submission pipeline logic is split by stage.
- Developer-only tools are production-gated and audited.
- API responses are consistent and typed.
- Frontend routes are modular.
- Student onboarding route policy is tested.
- Large Vue files are decomposed.
- Upload and file access security tests pass.
- OCR and n8n failures are observable.
- CI runs backend, frontend, architecture, build, and dependency checks.
- Architecture documentation explains the system well enough for a new engineer to work safely.

## Recommended First Sprint

Do this first for maximum score improvement:

1. Fix RBAC middleware to use the canonical relationship-backed role model.
2. Add route authorization architecture tests.
3. Config-gate and redact the database viewer.
4. Extract `ConfirmRequirementPackageService`.
5. Extract `studentOnboardingGuard`.
6. Add documentation for roles/permissions and requirement vault flow.

This first sprint would likely move the architecture from **6.5/10** to around **7.8/10** because it addresses the biggest correctness, security, and maintainability concerns without rewriting the whole app.
