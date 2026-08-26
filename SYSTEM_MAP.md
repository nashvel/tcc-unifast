# TCC UniFAST — System Knowledge Map
> Obsidian-style node graph. Each `[[link]]` represents a logical connection between nodes.
> Read top-to-bottom or jump to any heading to explore a domain.

---

## Table of Contents
- [[Infrastructure]]
- [[Auth & Identity]]
- [[User & RBAC]]
- [[Student Onboarding]]
- [[Requirement Vault & Submissions]]
- [[OCR & Grade Pipeline]]
- [[Eligibility Engine]]
- [[Batches & Masterlist]]
- [[Academic Records]]
- [[Dynamic Forms]]
- [[Social Media & Announcements]]
- [[Reports]]
- [[Audit & Security]]
- [[Notifications]]
- [[Support Tickets]]
- [[Frontend Architecture]]
- [[Frontend Modules — Staff Area]]
- [[Frontend Modules — Student Area]]
- [[External Integrations]]

---

## Infrastructure

```
tcc-unifast (Docker Compose)
│
├── frontend        (Vue 3 + Vite → served via Nginx, :5173)
├── backend         (Laravel 13 / PHP 8.4, :8000)
├── queue           (Laravel queue:work, same image as backend)
├── scheduler       (Laravel schedule:run loop, same image)
├── migrate         (artisan migrate --force, one-shot)
├── mysql           (MySQL 8.4, :3307)
├── redis           (Redis 7.4-alpine, :6380)
├── ocr-service     (Python / uvicorn + Tesseract, :8001)
└── n8n             (n8n automation node, :5678)
```

**Connections:**
- [[Backend]] depends on [[MySQL]], [[Redis]], [[OCR Service]], [[n8n]]
- [[Frontend]] talks to [[Backend]] via REST API (HttpOnly cookies)
- [[Queue Worker]] and [[Scheduler]] share the backend image and storage volume
- [[n8n]] handles [[Social Media Automation]] and [[Student Sync Webhook]]
- [[Redis]] is used for [[Session]], [[Cache]] (RBAC, DB viewer), and [[Queue]]

---

## Auth & Identity

**Entry Points:**
- `POST /api/auth/login` → [[AuthController]]
- `POST /api/auth/2fa/verify` → [[AuthController]]
- `GET  /api/auth/google/redirect` → [[Google OAuth]]
- `GET  /api/auth/google/callback` → [[Google OAuth]]
- `POST /api/auth/refresh` → [[AuthTokenService]]

**Flow — Password Login:**
```
Browser → POST /auth/login
  → Validate email/password + reCAPTCHA
  → Auth::attempt()
  → Check account_status (block unverified/blocked)
  → [if 2FA enabled] → TwoFactorAuthService.createChallenge()
                      → return { two_factor_required: true, challenge_id }
  → [else] → AuthTokenService.issuePair()
           → return { user }
```

**Flow — 2FA Challenge:**
```
Browser → POST /auth/2fa/verify
  → TwoFactorAuthService.verifyChallenge(challenge_id, code)
  → Validates TOTP or recovery code against AuthChallenge row
  → AuthTokenService.issuePair()
```

**Flow — Google OAuth:**
```
Browser → GET /auth/google/redirect → returns Google URL + session state
Browser → redirected to Google
Google  → GET /auth/google/callback
  → Validate state (CSRF)
  → Exchange code → access_token
  → Fetch userinfo (openidconnect)
  → Find User by google_id OR email
  → [if 2FA] → createChallenge → redirect to frontend with oauth_2fa param
  → else     → completeLogin → redirect with signed_in=google
```

**Token Pair (HttpOnly Cookies):**
```
unifast_access  (Sanctum PAT, short-lived ~20 min)
unifast_refresh (rotating refresh token, ~7 days)
```

**Services involved:**
- [[AuthTokenService]] — issues/rotates/revokes cookie pair
- [[TwoFactorAuthService]] — challenge creation + TOTP/recovery verification
- [[TotpService]] — TOTP secret generation, URI, verify, recovery codes
- [[StudentOnboardingNavigator]] — maps user state to next onboarding path

**Models involved:**
- [[User]] → `account_status`, `two_factor_secret`, `two_factor_recovery_codes`, `google_id`
- [[RefreshToken]] → `token_hash`, `family_id`, `revoked_at`, `replaced_by`
- [[AuthChallenge]] → `challenge_id`, `code_hash`, `expires_at`
- [[AuditLog]] → logged on every login/logout/2fa event

**Refresh Token Security:**
- Each token belongs to a `family_id`
- Reuse of a rotated (revoked) token → **entire family is revoked** (session hijack detection)
- Frontend `client.ts` does transparent 401 → refresh → retry

---

## User & RBAC

**Models:**
```
User
 ├── role (string — legacy flat role: student|staff|admin|developer|head)
 ├── hasMany RefreshToken
 ├── hasMany PersonalAccessToken (Sanctum)
 ├── hasOne KycProfile
 ├── hasOne Grantee (student users)
 ├── belongsToMany Role (RBAC roles table)
 └── getAllPermissions() via RbacService
```

```
Role
 ├── name, description, color, is_system
 └── belongsToMany Permission
```

```
Permission
 └── name (slug: e.g. "documents.review", "batches.manage")
```

**RBAC Service ([[RbacService]]):**
- CRUD for Roles and Permissions
- Assigns/syncs roles to users
- Permission check: `userHasPermission(user, 'slug')`
- All role/permission lists cached in [[Redis]] for 1 hour (`rbac:roles`, `rbac:permissions`, `rbac:user_permissions:{id}`)
- Cache busted on any role/permission mutation

**Route Middleware Guards:**
```
role:student               → student-only routes
role:developer,admin,head,staff  → staff routes
role:developer,admin       → admin/dev routes
auth:sanctum               → any authenticated user
```

**API Surface:**
```
GET  /rbac/roles
POST /rbac/roles
PUT  /rbac/roles/{role}
DELETE /rbac/roles/{role}
GET  /rbac/permissions
POST /rbac/permissions
GET  /rbac/users/{user}/roles
POST /rbac/users/{user}/roles
PUT  /rbac/users/{user}/roles  (sync)
```

---

## Student Onboarding

**State Machine (account_status):**
```
unverified
    ↓  email verification (ActivationToken)
pending_kyc
    ↓  KYC form submitted & verified
pending_identity
    ↓  ID scan + liveness submitted
pending_face_review
    ↓  staff approves face review
active
```

**Navigator ([[StudentOnboardingNavigator]]):**
```
nextStep(user, grantee?) →
  blocked          → /locked
  unverified       → /student/kyc
  pending_kyc      → /student/kyc
  pending_face_review → /student/onboarding/pending-review
  pending_identity:
    no identity profile → /student/onboarding/id-scan
    pending_id_scan     → /student/onboarding/id-scan
    pending_face_review → /student/onboarding/pending-review
    pending_liveness    → /student/onboarding/liveness
  done             → /student
```

**KYC Step ([[StudentKycController]]):**
- `GET /student/kyc` — returns KycProfile status + program options + mismatches
- `POST /student/kyc` — student submits identity data → validated against registrar data
- Creates/updates [[KycProfile]] → `status: verified`
- Advances `account_status` to `pending_identity`

**Identity Onboarding ([[IdentityOnboardingController]]):**
```
POST /student/identity-onboarding/id-scan/ocr-front
  → IdCardOcrService → OCR front of school ID
  → Validates name/ID match

POST /student/identity-onboarding/id-scan
  → Stores front + back ID scan images in vault
  → Creates/updates GranteeIdentityProfile
  → status: pending_liveness

POST /student/identity-onboarding/liveness
  → StudentFaceVerificationController
  → Runs face comparison (liveness frame vs ID photo)
  → IdCardIdentityMatcher
  → If match → status: active (or pending_face_review if score low)
  → Creates RequirementIdentityCheck record
```

**Face Review ([[FaceReviewController]]):**
```
GET  /face-reviews         → paginated list for staff
GET  /face-reviews/{id}    → detail with face comparison data
POST /face-reviews/{id}/approve → sets account_status = active
POST /face-reviews/{id}/reject  → sets account_status = pending_identity (re-scan)
```

**Models:**
- [[KycProfile]] — stores student KYC data, mismatch flags, status
- [[GranteeIdentityProfile]] — stores scanned ID file references, face scores, onboarding timestamps
- [[RequirementIdentityCheck]] — stores face comparison result, distance, confidence score, challenge_sequence

---

## Requirement Vault & Submissions

**What it is:** The document submission system where students upload required scholarship documents within an active [[Batch]] window.

**4 Document Slots (slot_key):**
```
school_id          → Physical school ID photo
course_history     → Subjects/enrollment history
grade_slip         → Official grade slip (triggers OCR)
specimen_signatures → Signature specimen sheet
```

**Vault Flow:**
```
Student → GET /student/requirement-vault
  → Returns current batch window, slot statuses, any review notes

Student → POST /student/requirement-vault/document
  → Upload file for a slot
  → Creates DocumentSubmission (status: pending_review)
  → Queues OCR job if slot = grade_slip or course_history

Student → POST /student/requirement-vault/confirm
  → Confirms all slots are ready
  → Sets submission package status to submitted

Staff   → POST /document-submissions/{id}/review
  → Reviews and approves/rejects individual document
  → DocumentSubmissionPresenter formats response

Staff   → GET /document-submission-packages/{granteeId}/{batchId}
  → Full package view across all slots for one student
```

**Models:**
- [[DocumentSubmission]] — `slot_key`, `status`, `risk_level`, `identity_review_required`, `ocr_payload`, `extracted_text`, `ocr_confidence`
- [[SubmissionPipelineResult]] — result of OCR → grade parse → eligibility pass

**Services:**
- [[RequirementVault/]] service directory — vault state management
- [[SubmissionPipeline/]] service directory — OCR → parse → evaluate chain
- [[SubmissionRiskScoringService]] — scores risk level from OCR confidence, mismatch flags
- [[DocumentSubmissionPresenter]] — formats submission detail for API response

---

## OCR & Grade Pipeline

**Pipeline Trigger:**
- Fired when a `grade_slip` or `course_history` document is submitted to vault
- Also callable directly: `POST /student/submissions/ocr`

**Stage 1 — OCR Extraction:**
```
DocumentSubmission (PDF/image)
  → PdfDocumentService (renders PDF page to image)
  → OCR Service (Python :8001, Tesseract)
  → returns extracted_text + ocr_confidence
```

**OCR Service (Python):**
- Endpoint: `http://ocr-service:8001`
- Uses Tesseract with configurable PSM mode
- Returns raw text + table-formatted text + confidence
- Fallback: OCR.space API (external)

**Stage 2 — Grade Parsing:**
```
AcademicGradeTextParser
  → AcademicGradeParser
  → AcademicTermAnalyzer
  → Parses academic terms, subjects, grades, units, GWA
  → Returns OcrTermBlock[] with OcrCourseRow[]
```

**Stage 3 — Summarization:**
```
AcademicCourseSummarizer
  → Aggregates terms into grade_summary
  → Counts: blank, pending, failed, dropped, retention
  → Identifies pass_grade threshold from PolicySetting
```

**Stage 4 — Risk Scoring:**
```
SubmissionRiskScoringService
  → OCR confidence < threshold → high risk
  → Name mismatch → high risk
  → Failed/dropped counts → medium risk
  → Returns: low | medium | high
```

**Stage 5 — Eligibility Evaluation:**
```
SubmissionEligibilityEvaluator
  → Uses PolicySetting (GWA threshold, max failures, etc.)
  → Returns: eligible | ineligible | pending
  → Stored in SubmissionPipelineResult
```

**QR Code Helpers:**
- [[TccRegistrarQrService]] — parses QR codes embedded in TCC registrar grade slips
- [[GradeslipQrService]] — validates QR authenticity against registrar domains
- [[IdCardOcrService]] — OCR front of school ID during identity onboarding
- [[IdCardBackParser]] — parses back of ID card for name/ID extraction
- [[IdCardIdentityMatcher]] — face comparison between liveness frame and ID photo

---

## Eligibility Engine

**Policy Settings ([[PolicySetting]]):**
```
gwa_threshold         (e.g. 2.50)
max_failed_units
max_dropped_units
allow_incomplete_grades
min_units_per_semester
```

**Evaluator ([[SubmissionEligibilityEvaluator]]):**
```
evaluate(grantee, batch, pipelineResult)
  → Checks grade_summary against PolicySetting
  → Returns: eligible | ineligible | pending | insufficient_data
  → Stores result + reasoning in SubmissionPipelineResult
```

**Staff View:**
```
GET  /eligibility            → paginated list with risk/eligibility columns
GET  /eligibility/{grantee}  → detail with full grade breakdown
POST /eligibility/{grantee}/notify → send eligibility notification
```

**EligibilityPresenter** formats the output combining:
- [[Grantee]] data
- [[AcademicRecord]] (GWA, semester breakdown)
- [[SubmissionPipelineResult]] (eligibility verdict, risk)
- [[DocumentSubmission]] (document review statuses)

---

## Batches & Masterlist

**Batch = one submission window (semester):**
```
Batch
  ├── name, academic_year, semester
  ├── submission_deadline
  ├── is_active
  ├── window_status: draft | active | closed | expired
  └── hasMany Grantee (via pivot or direct FK)
```

**[[BatchWindowService]]:**
- Manages window open/close transitions
- `activate(batch)` → opens submission window, notifies grantees
- `deactivate(batch)` → closes window
- `extendDeadline(batch, date)` → pushes deadline

**Masterlist Import Flow:**
```
Staff → POST /masterlist/imports/preview
  → MasterlistSpreadsheetParser (reads XLSX/CSV)
  → MasterlistImportRowValidator (validates each row)
  → Creates MasterlistImport with status: preview
  → Returns ImportPreview { valid_rows, invalid_rows, rows }

Staff → POST /masterlist/imports/{import}/confirm
  → MasterlistTruthService
  → Upserts Grantee records from MasterlistRow
  → Creates User accounts (if new student)
  → Queues ActivationToken emails
  → status: imported
```

**Models:**
- [[MasterlistImport]] — `status`, `total_rows`, `valid_rows`, `invalid_rows`
- [[MasterlistImportDetection]] — auto-detected column header mapping
- [[MasterlistRow]] — one validated row per student
- [[Grantee]] — canonical student grantee record
- [[ActivationToken]] — email verification token, time-limited

**Activation Flow:**
```
GET  /activation/{token}  → show activation page (email/name/token info)
POST /activation/{token}  → set password, verify email → account_status = pending_kyc
```

---

## Academic Records

**Data Model:**
```
AcademicRecord
  └── hasMany AcademicSemester
        └── hasMany AcademicCourse

AcademicRecord: student_id, grantee_id, latest_gwa, approved_submissions
AcademicSemester: term, gwa, units_taken, units_passed
AcademicCourse: code, title, units, grade, remark (Passed|Failed|Dropped)
```

**Population:**
- Records are built/updated by the [[OCR & Grade Pipeline]] when a grade_slip is reviewed and approved
- [[AcademicGradeParser]] → creates/updates AcademicSemester + AcademicCourse rows

**API:**
```
GET /academic-records         → paginated, searchable list
GET /academic-records/{id}    → full breakdown with semesters + courses
```

---

## Dynamic Forms

**What it is:** A form builder module that lets staff create surveys/forms for grantees, optionally public-facing.

**Data Model:**
```
Form
  ├── title, description, is_public, public_token
  ├── status: draft | active | closed
  ├── hasMany FormSection
  │     └── hasMany FormField
  │           ├── type: text|textarea|select|radio|checkbox|date|file|...
  │           ├── required, validation_rules
  │           └── hasMany FormFieldOption (for select/radio)
  │           └── hasMany FormFieldCondition (show/hide logic)
  └── hasMany FormResponse
        └── hasMany FormAnswer (one per field)
```

**Routes:**
```
Public (no auth):
  GET  /forms/public/{token}            → show public form
  POST /forms/public/{token}/responses  → submit response (rate-limited 5/min)

Student (auth):
  GET  /forms/assigned                  → forms assigned to grantee
  GET  /forms/{id}/schema               → form schema
  POST /forms/{id}/responses            → submit response

Staff (auth):
  GET  /forms                           → list all forms
  POST /forms                           → create form
  GET  /forms/{id}                      → form detail
  PUT  /forms/{id}                      → update form
  DELETE /forms/{id}                    → delete form
  GET  /forms/{id}/responses            → all responses
  GET  /forms/{id}/security-log         → abuse/security events
```

**Security:**
- [[FormSecurityService]] — rate limits, tracks anomalous submissions, generates security log
- [[FormSecurityLog]] — IP, UA, honeypot, timing signals per response
- `FormSecurityHeaders` middleware adds CSP/X-Frame headers to all form endpoints

---

## Social Media & Announcements

**Social Media Posts ([[SocialMediaPost]]):**
```
SocialMediaPost
  ├── title, message, channel (facebook), campaign
  ├── status: draft | queued | sent_to_n8n | scheduled | failed | published
  ├── approval_mode: approval_required | pre_approved
  ├── scheduled_for, submitted_at, published_at
  ├── n8n_request_id, n8n_status, error_message
  ├── external_post_id, external_permalink
  ├── engagement { reactions, comments, shares }
  └── belongsTo Batch (optional)
```

**Post Lifecycle:**
```
Staff creates draft → POST /social-media-posts
  → status: draft

Staff dispatches → POST /social-media-posts/{id}/dispatch
  → [approval_required] → status: queued (waiting for approval)
  → [pre_approved]      → status: sent_to_n8n
  → Calls n8n webhook (TCC_UNIFAST_N8N_WEBHOOK_URL)

n8n processes post → schedules with Facebook Graph API
  → POST /integrations/n8n/social-media-posts/{id}/status
  → Updates status: scheduled | published | failed

Staff can:
  → React as page: POST /social-media-posts/{id}/react
  → Comment as page: POST /social-media-posts/{id}/comments
  → Sync from Facebook: POST /social-media-posts/sync-facebook
```

**Integration Status:**
- `GET /social-media-posts/integration-status`
- Reports: n8n_configured, n8n_reachable, facebook_confirmed, page info, latest post

**Announcements:**
- Separate module (not social media)
- Staff create, students read on `/student/announcements`
- Pushed via [[Notifications]] system

---

## Reports

**Billing Reports ([[BillingReportController]]):**
```
BillingReport
  ├── belongsTo Batch
  ├── generated_by User
  ├── hasMany BillingReportItem (per grantee)
  └── download as PDF/Excel
```

**Distribution Reports ([[DistributionReportController]]):**
```
DistributionReport
  ├── belongsTo Batch
  └── summary of document submission completion per grantee
```

**Both reports:**
- `POST` to generate (async via queue or synchronous)
- `GET /{id}` to view
- `GET /{id}/download` to export file

---

## Audit & Security

**AuditLog** — every critical action is recorded:
```
AuditLog
  ├── actor (user name)
  ├── role (ucfirst role name)
  ├── action (slug: auth_login, auth_logout, auth_2fa_enabled, ...)
  ├── module (Authentication, Documents, Batches, ...)
  ├── target (email, document ID, etc.)
  ├── context (JSON: method, file_name, review_notes, etc.)
  └── ip_address
```

**Events logged automatically:**
- `auth_login`, `auth_logout`
- `auth_2fa_enabled`, `auth_2fa_disabled`
- Document review approve/reject
- Face review approve/reject
- Masterlist import confirm
- Batch activate/deactivate
- User creation/role change

**DatabaseViewerAuditLogger:**
- Logs every database table view/row access by developer/admin
- Stored in AuditLog with module: "Database Viewer"

**API:**
```
GET  /audit-logs              → paginated log (staff+)
POST /audit-events            → frontend can push custom events (rate-limited 240/min)
```

**Security Module (frontend `/app/security`):**
- Memory: `GET /security/memory` — runtime PHP memory, queue depth, Redis info
- System health: `GET /system/health` — all service statuses
- Database viewer: `GET /database/tables` + rows

---

## Notifications

**Student Notifications:**
```
GET  /student/notifications
POST /student/notifications/{id}/read
POST /student/notifications/read-all
```

**Staff Notifications:**
```
GET  /notifications
POST /notifications/{id}/read
POST /notifications/read-all
```

**Real-time:**
- Laravel Echo (Reverb or Pusher-compatible)
- [[StaffSubmissionNotifier]] — broadcasts to staff channel when student submits

**[[BatchNotification]]:**
- Tracks batch-level blast notifications (invite emails)
- `POST /onboarding-center/batches/{batch}/blast-invites`

---

## Support Tickets

```
SupportTicket
  ├── subject, message, status: open | in_progress | resolved | closed
  ├── created_by User
  └── hasMany SupportTicketReply

Routes:
  GET    /support-tickets                → staff list
  POST   /support-tickets               → create (student or staff)
  PATCH  /support-tickets/{id}          → update status (staff)
```

---

## Frontend Architecture

**Stack:**
```
Vue 3 (Composition API + <script setup>)
  + Vite (build)
  + vue-router (file-based module routing)
  + @tanstack/vue-query (server state)
  + vue-i18n (i18n with URL lang param)
  + Tailwind CSS
```

**Auth Session (`auth/session.ts`):**
```
authSession (reactive singleton)
  ├── user: AuthUser | null
  ├── loaded: boolean
  └── loadAuthUser() → GET /auth/me
```

**API Client (`api/client.ts`):**
```
apiFetch(url, options)
  → Adds CSRF header (X-XSRF-TOKEN from cookie)
  → On 401 → POST /auth/refresh → retry original request
  → Returns typed response
```

**Query Client (`lib/queryClient.ts`):**
- TanStack Query with stale-time tuning
- No Vuex/Pinia — all server state via query cache
- Invalidation keys per module (e.g. `['batches']`, `['grantees']`, `['forms']`)

**i18n Routing:**
- `installLanguageRouting(router)` — reads `?lang=` param, sets locale
- `withLang(path, lang?)` — appends lang param to redirect URLs
- `installSeoUpdates(router)` — updates `<title>` and `<meta>` per route

**Route Guard (router.beforeEach):**
```
1. Load auth user if not yet loaded
2. Unauthenticated → redirect to /login
3. Student accessing /app → redirect to /student
4. Staff accessing /student → redirect to /app
5. Student with incomplete onboarding → redirect to onboarding step
6. Enforce correct onboarding step order (kyc → id-scan → liveness)
```

---

## Frontend Modules — Staff Area (`/app/...`)

| Module | Path | Connects To |
|--------|------|-------------|
| Dashboard | `/app` | Summary stats |
| Grantees | `/app/grantees` | [[Batches & Masterlist]], [[Eligibility Engine]] |
| Batches | `/app/batches` | [[Batches & Masterlist]], [[Requirement Vault]] |
| Masterlist | `/app/masterlist` | [[Batches & Masterlist]] |
| Academic | `/app/academic` | [[Academic Records]] |
| Documents | `/app/documents` | [[Requirement Vault & Submissions]], [[OCR & Grade Pipeline]] |
| Face Reviews | `/app/face-reviews` | [[Student Onboarding]] |
| Eligibility | `/app/eligibility` | [[Eligibility Engine]] |
| Social Posts | `/app/social-posts` | [[Social Media & Announcements]] |
| Announcements | `/app/announcements` | [[Notifications]] |
| Reports | `/app/reports` | [[Reports]] |
| Billing | `/app/billing` | [[Reports]] |
| Distribution | `/app/distribution` | [[Reports]] |
| Audit | `/app/audit` | [[Audit & Security]] |
| Security | `/app/security` | [[Audit & Security]] |
| Users | `/app/users` | [[User & RBAC]] |
| Users/Permissions | `/app/users/permissions` | [[User & RBAC]] |
| Settings | `/app/settings` | [[PolicySetting]] |
| Forms | `/app/forms` | [[Dynamic Forms]] |
| Onboarding | `/app/onboarding` | [[Student Onboarding]] |
| Files | `/app/files` | File Manager |
| Activation Seeder | `/app/activation-seeder` | [[Batches & Masterlist]] |
| Programs | `/app/programs` | [[Academic Records]] |
| Developer Tools | `/app/developer/*` | RBAC, DB viewer, API docs, Changelog |

---

## Frontend Modules — Student Area (`/student/...`)

| Module | Path | Connects To |
|--------|------|-------------|
| Dashboard | `/student` | Summary, onboarding progress |
| KYC | `/student/kyc` | [[Student Onboarding]] |
| Onboarding Index | `/student/onboarding` | [[Student Onboarding]] |
| ID Scan | `/student/onboarding/id-scan` | [[OCR & Grade Pipeline]], [[Student Onboarding]] |
| Liveness | `/student/onboarding/liveness` | [[Student Onboarding]] |
| Pending Review | `/student/onboarding/pending-review` | [[Student Onboarding]] |
| Documents | `/student/documents` | [[Requirement Vault & Submissions]] |
| School ID Scan | `/student/documents/school-id-scan` | [[OCR & Grade Pipeline]] |
| Announcements | `/student/announcements` | [[Notifications]] |
| Notifications | `/student/notifications` | [[Notifications]] |
| Profile | `/student/profile` | [[User & RBAC]], Settings |
| Forms | `/student/forms` | [[Dynamic Forms]] |

---

## External Integrations

**n8n (Workflow Automation):**
```
Backend → n8n webhook (POST TCC_UNIFAST_N8N_WEBHOOK_URL)
  → Triggered by: social media post dispatch, student sync

n8n → Backend (POST /integrations/n8n/*)
  → /integrations/n8n/tcc-unifast/sync         ← student data sync
  → /integrations/n8n/tcc-unifast/students      ← GET students for n8n
  → /integrations/n8n/social-media-page/status  ← Facebook page profile update
  → /integrations/n8n/social-media-posts/{id}/status ← post published/failed callback
```

**Google OAuth:**
- Configured via `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`
- Redirect URI: `http://localhost:8000/api/auth/google/callback`
- Linked to [[AuthController.googleCallback]]

**OCR.space (fallback):**
- Used when local Tesseract confidence is too low
- `OCR_SPACE_API_KEY` config value

**TCC Registrar Domains:**
- `registrar.tcc.edu.ph`, `sis.tcc.edu.ph`, `tcc.edu.ph`
- Used by [[TccRegistrarQrService]] and [[GradeslipQrService]] to validate document authenticity

---

## Master Connection Map

```
User ──────────────────────── Auth (cookies, 2FA, Google)
  │                                     │
  │                               AuthTokenService
  │                               TwoFactorAuthService
  │
  ├── [student] ─── Grantee ──── KycProfile
  │                    │             │
  │                    │         GranteeIdentityProfile
  │                    │             │
  │                    │         RequirementIdentityCheck
  │                    │
  │                    ├── Batch (active window)
  │                    │       │
  │                    │   DocumentSubmission (4 slots)
  │                    │       │
  │                    │   OCR Service → AcademicGradeParser
  │                    │       │
  │                    │   SubmissionPipelineResult
  │                    │       │
  │                    │   SubmissionEligibilityEvaluator
  │                    │       │
  │                    │   AcademicRecord ─── AcademicSemester ─── AcademicCourse
  │                    │
  │                    └── FormResponse (dynamic forms)
  │
  ├── [staff/admin] ─── RBAC (Role ↔ Permission)
  │                        │
  │                    Face Reviews (approve/reject onboarding)
  │                    Document Reviews (approve/reject slots)
  │                    Eligibility Review
  │                    Batch Management
  │                    Masterlist Import → Grantee seeding
  │                    Reports (Billing, Distribution)
  │                    Social Media Posts → n8n → Facebook
  │                    Announcements → Notifications
  │
  └── AuditLog ← everything writes here

Infrastructure:
  MySQL ← primary data store
  Redis ← session, cache (RBAC, DB viewer), queue
  OCR Service ← grade slip and ID card image processing
  n8n ← social media automation, student sync webhooks
  Queue Worker ← async jobs (OCR, emails, notifications)
  Scheduler ← recurring tasks (window expiry, cleanup)
```
