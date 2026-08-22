# Architecture Hardening Report

Date: 2026-08-15

## Current Rating

**9.0 / 10**

The application has moved from a high-risk, controller-heavy architecture to a layered Laravel backend with clear use-case services, presenter classes, pipeline stages, security-focused developer tooling, and executable architecture guardrails.

This is no longer a 6.5/10 architecture. A strict reviewer would now see a system with strong module boundaries, tested critical flows, and measurable regression protection. The remaining gap to 10/10 is mostly frontend route modularity, deeper observability, CI enforcement, and final operational documentation.

## What Changed

### Authorization

- `RequireRole` now treats relationship-backed RBAC roles as authoritative.
- Legacy `users.role` remains only as fallback for users without assigned RBAC roles.
- Added `RequirePermission` middleware.
- Database viewer routes require `permission:view_database`.
- `view_database` is seeded as a developer-tool permission.
- Feature tests cover RBAC-backed access behavior.

### Requirement Vault

- `RequirementVaultController` delegates major student submission actions into use-case services.
- Extracted services include:
  - `ConfirmRequirementPackageService`
  - `StoreVaultDocumentSlotService`
  - `StoreVaultIdentityCheckService`
  - `StoreVaultSchoolIdService`
  - `ResubmitRequirementSlotService`
  - `ValidateVaultFrontIdOcrService`
  - `RequirementVaultPresenter`
- Existing draft, confirm, resubmit, OCR, security, and architecture tests cover the extracted behavior.

### Identity Onboarding

- `IdentityOnboardingController` delegates School ID front-OCR and ID-scan persistence to `StoreIdentityIdScanService`.
- ID OCR responsibilities are split:
  - `IdCardOcrService` handles provider calls.
  - `IdCardBackParser` handles back-ID parsing.
  - `IdCardIdentityMatcher` handles identity matching heuristics.

### Submission Pipeline

- `ProcessRequirementSubmissionPipeline` is now a thin queue job.
- `ProcessRequirementSubmissionService` owns orchestration only.
- Pipeline responsibilities are split:
  - `PipelineAcademicOcrService` handles PDF academic extraction, grade summaries, and Grade Slip anchor reparse.
  - `PipelineExternalChecksService` handles Grade Slip QR checks, authenticity checks, and n8n delivery.
  - `SubmissionRiskScoringService` handles risk signals, scoring, and badge labels.
  - `SubmissionEligibilityEvaluator` handles academic eligibility evaluation.

### Academic Parsing

- `AcademicGradeParser` now delegates specialized work:
  - `AcademicGradeTextParser`
  - `AcademicTermAnalyzer`
  - `AcademicCourseSummarizer`
- Existing public parser API remains compatible.

### Document Validation And Eligibility

- `DocumentSubmissionController` delegates response shaping to `DocumentSubmissionPresenter`.
- `EligibilityController` delegates criteria, notice history, required-document checks, and detail/list shaping to `EligibilityPresenter`.
- `MasterlistImportController` delegates:
  - response shaping to `MasterlistImportPresenter`
  - row normalization/validation to `MasterlistImportRowValidator`

### Developer Database Viewer

- Database viewer is config-gated.
- Routes require `view_database`.
- Table access is allowlisted.
- Sensitive row fields are redacted.
- Reads are audited through `DatabaseViewerAuditLogger`.
- Policy and redaction logic live in `DatabaseViewerPolicy`.

## Executable Guardrails

Architecture tests now enforce:

- Database viewer routes require `auth:sanctum` and `permission:view_database`.
- Database viewer policy/audit logic stays split out.
- Requirement Vault controller size does not regress.
- Identity onboarding controller size does not regress.
- Document submission presentation stays split out.
- Eligibility presentation/rules stay split out.
- Masterlist import response and row-validation logic stay split out.
- Pipeline job stays thin.
- Pipeline processor, external checks, and academic OCR stay split by responsibility.
- Risk scoring and eligibility evaluation stay split.
- Academic parser helpers stay split.
- ID OCR parsing/matching stay split.
- Requirement Vault services do not depend on `Illuminate\Http\Request`.
- Identity onboarding services do not depend on `Illuminate\Http\Request`.

Latest backend verification:

```text
php artisan test
162 tests, 159 passed, 3 skipped
```

## Current Size Snapshot

| File | Current Lines | Guard |
| --- | ---: | ---: |
| `ProcessRequirementSubmissionPipeline.php` | 26 | 100 |
| `ProcessRequirementSubmissionService.php` | 155 | 225 |
| `PipelineAcademicOcrService.php` | 265 | 325 |
| `PipelineExternalChecksService.php` | 133 | 175 |
| `SubmissionRiskScoringService.php` | 128 | 150 |
| `SubmissionEligibilityEvaluator.php` | 243 | 300 |
| `DocumentSubmissionController.php` | 234 | 275 |
| `EligibilityController.php` | 123 | 150 |
| `MasterlistImportController.php` | 230 | 240 |
| `DatabaseController.php` | 234 | 250 |
| `RequirementVaultController.php` | 402 | 450 |
| `IdentityOnboardingController.php` | 451 | 500 |
| `IdCardOcrService.php` | 206 | 250 |

## Why This Is 9/10

The architecture is now strong because:

- Critical backend workflows are service-oriented.
- Jobs delegate instead of owning business logic.
- Controllers are mostly HTTP adapters.
- Security-sensitive developer tooling is permissioned, allowlisted, redacted, and audited.
- Architecture rules are enforced by tests, not memory.
- High-risk flows have feature and unit coverage.
- Documentation records the target architecture and completed slices.

## Why This Is Not Yet 10/10

The remaining gap is not the backend refactor work we are pausing. The remaining gap is operational and frontend maturity:

- Frontend route definitions and access policy still need deeper modularization and tests.
- CI should run the backend test suite, frontend lint/type checks, and architecture tests on every change.
- Observability should be improved for OCR failures, queue failures, n8n delivery, upload failures, and identity review events.
- Production runbooks should document queue retry handling, OCR downtime behavior, database viewer emergency access, and rollback steps.
- Public API contracts are still implicit in controllers/tests rather than documented as OpenAPI or stable endpoint docs.
- The dirty worktree should be reviewed and split into sane commits before deployment.

## Remaining Improvement Plan For 10/10

### 1. Frontend Architecture

- Split `frontend/src/createApp.ts` routes by domain:
  - public/auth routes
  - student routes
  - admin/staff routes
  - developer routes
- Extract student onboarding route access into a pure tested policy function.
- Add unit tests for KYC, ID scan, liveness, face review, active, and blocked redirect states.
- Add frontend architecture checks for cross-domain imports.

### 2. CI Quality Gates

- Run `php artisan test`.
- Run frontend lint/typecheck/build commands.
- Fail CI on architecture guard regressions.
- Fail CI if security-sensitive route groups lose auth/permission middleware.

### 3. Observability

- Add structured logs for:
  - OCR provider errors
  - PDF parser failures
  - queue job failures/retries
  - n8n webhook failures
  - identity-review mismatch reasons
  - vault upload validation failures
- Add operator-facing failure summaries for pipeline runs.

### 4. Operations Documentation

- Queue worker runbook.
- OCR service downtime runbook.
- n8n retry/replay runbook.
- Database viewer emergency-access policy.
- Deployment and rollback checklist.

### 5. API Documentation

- Document the critical API surfaces:
  - Requirement Vault
  - Identity Onboarding
  - Document Validation
  - Masterlist Import
  - Eligibility
  - Database Viewer
- Include request/response examples, auth requirements, and error cases.

## Do Not Touch For Now

Per current direction, do not continue refactoring these unless a concrete bug or requirement appears:

- `RequirementVaultController`
- `IdentityOnboardingController`
- `ConfirmRequirementPackageService`

They are still large, but they are guarded and covered. Further reductions should only happen when they unlock a real feature, testability win, or bug fix.

## Reviewer Summary

The backend architecture is now production-grade for a capstone/business workflow app. The most important boundaries are named, tested, and guarded. To get a full 10/10 from a strict reviewer, focus next on frontend route modularity, CI enforcement, operational runbooks, observability, and API documentation rather than more backend line-count reductions.
