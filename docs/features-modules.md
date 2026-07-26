# UniFAST TES — System Feature Modules

> Capstone-style module descriptions for the Tagoloan Community College UniFAST TES platform.  
> Format: title · stack/approach line · Description · Steps.  
> Runtime implementation note: Laravel 13 + Vue 3 SPA (citations below keep the paper wording).

---

## a) Grantee Profiling and KYC Validation

Using Laravel 11 — Al-Tuhaifi et al., 2025

**Description:** This module handles the onboarding of CHED-identified grantees into the system. Since grantees are pre-selected by CHED and not by the institution, the system uses the imported CHED masterlist as the ground truth. When a grantee logs in for the first time, they must complete a KYC profile form. The system cross-checks every field they submit against the masterlist record. Any mismatch blocks the grantee from proceeding, ensuring that only legitimate CHED-listed beneficiaries can access the system.

**Steps:**

1. Grantee receives one-time invitation email after admin imports the CHED masterlist
2. Grantee clicks the activation link and is directed to the KYC profile form
3. Grantee fills in personal information — full name, student ID, program, year level, contact number, address, and socio-economic details
4. System cross-checks submitted name, student ID, and program against the imported CHED masterlist record in the database
5. If any field does not match — wrong name spelling, wrong student ID, wrong program — system blocks progression and displays a specific mismatch error
6. If all fields match — account is activated, KYC data is saved, and grantee gains access to the portal and Requirement Vault

---

## b) Masterlist Batch Import and Onboarding

Using Laravel Excel — Vincy, 2024

**Description:** This module allows the admin to import the CHED-provided Excel masterlist and organize grantees into semester batches. Instead of manually encoding each grantee record, the admin uploads the Excel file and the system parses, previews, validates, and creates all accounts in one operation. The batch system controls when grantees can submit requirements by opening and closing submission windows per batch.

**Steps:**

1. Admin opens the Onboarding Center from the admin panel
2. Admin creates a new batch — specifies batch name, academic year, semester, and submission deadline
3. Admin uploads the CHED-provided Excel file for that batch
4. Laravel Excel parses all rows and displays a preview table showing extracted data — name, student ID, program, year level, email
5. Error rows are highlighted in red — missing email, duplicate student ID, or incomplete required fields
6. Admin reviews the preview, corrects flagged errors if needed, and clicks Confirm Import
7. System creates one user account and one grantee record per valid row and assigns all to the selected batch with default status: Unverified
8. System dispatches one-time invitation emails to all successfully imported grantees via Laravel Mail
9. Admin activates the batch using a toggle — only one batch can be active at a time
10. Activation opens the submission window for all grantees in that batch
11. System sends a batch-opened notification to all grantees in the activated batch

---

## c) Batch and Submission Window Management

Using academic-year / semester batch windows — Laravel BatchController APIs

**Description:** This module defines when grantees may submit requirements. A batch holds academic year, semester, deadlines, and active state. Admins open or close the window, extend deadlines, and notify grantees. The student app reads the active submission window so uploads are only allowed while the batch is open.

**Steps:**

1. Admin/head creates a batch (name, academic year, semester, deadline)
2. Admin activates the batch — `opened_at` set, `is_active` true
3. System may send activation / open-window notifications to assigned grantees
4. Students see the open window on their dashboard and document vault
5. While active, requirement uploads and confirmations are allowed
6. Admin can extend the deadline if needed
7. Admin deactivates / closes the batch — submissions stop for that window
8. Batch detail shows roster progress and control actions
9. Window state changes are available for audit and reporting

---

## d) Eligibility and Risk Evaluation

Using rules-based scoring logic and automated policy thresholds — UniFAST TES evaluation design

**Description:** This module combines two evaluation processes into one staff-facing workflow. The risk scoring engine aggregates all signals from the validation pipeline into a single weighted score with a color-coded badge. The eligibility evaluation automatically checks the extracted academic records against the scholarship policy thresholds configured by the admin. Staff review both the risk score and the eligibility result in one unified grantee detail modal before making a final approve or reject decision.

**Steps:**

1. After the backend pipeline completes, the system automatically computes the total risk score from all signals:
   - Identity check failed — 50 points
   - AI-generated ID detected — 50 points
   - PDF metadata tampering — 30 points
   - Name or ID mismatch — 40 points
   - Semester inconsistency — 35 points
   - GWA computation mismatch — 40 points
   - QR code invalid or domain mismatch — 30 points
   - Isolation Forest anomaly — up to 20 points
2. Total score determines the risk badge:
   - 0–20 = Low (green)
   - 21–49 = Medium (amber)
   - 50–79 = High (red)
   - 80+ = Block (dark red)
3. System automatically runs eligibility checks against admin-configured policy thresholds:
   - GWA must not exceed maximum allowed (default 2.50)
   - Failed subjects must not exceed maximum allowed per semester (default 2)
   - Dropped subjects must not exceed maximum allowed per semester (default 1)
4. If any threshold is exceeded — submission is automatically flagged as Non-Compliant
5. Staff opens the Grantee Directory / Eligibility queue and sees evaluation results
6. Staff clicks View on any grantee — opens the three-section detail modal:
   - Section 1: Grantee info — name, student ID, program, batch, current status
   - Section 2: Risk score breakdown — per-signal rows with points contributed and eligibility check results per criterion
   - Section 3: Document viewer — tabbed: School ID, Course History, Grade Slip
7. Staff reviews all signals and eligibility results
8. Staff clicks Approve — status changes to Verified, grantee receives notification
9. Staff clicks Reject — reason text is required before the button enables, status changes to Rejected, grantee receives notification with reason
10. Decision is logged to the audit trail — actor, timestamp, IP, before and after values

---

## e) Document Validation and Staff Review

Using submission review APIs with optimistic UI and undo — Vue + Laravel DocumentSubmissionController

**Description:** This module is the operational queue where staff validate TES requirement packages. Each submission groups uploaded documents (school ID, grade slip, course history, etc.) with OCR and identity signals. Staff approve, reject, or return a submission. The UI uses toasts, optional 5-second undo, and audit-friendly status changes so decisions are fast but recoverable.

**Steps:**

1. Student confirms a requirement vault package — creates/updates a submission
2. Submission appears in Document Validation with status filters and pagination
3. Staff opens a submission detail — sees files, OCR matches, and review controls
4. Staff chooses Approve, Reject, or Return
5. For Reject/Return — reason may be required
6. UI updates optimistically; a short undo window can reverse before final commit
7. Backend persists the review decision and notifies the grantee
8. Grantee sees updated status on Student Documents / Notifications
9. Review action is recorded for audit (who, when, decision)

---

## f) Academic Records Review

Using stored GWA and unit metrics from submissions / history — AcademicRecordController

**Description:** This module lets staff inspect academic evidence used for eligibility — GWA, failed units, dropped units, and related anomalies. It supports list and detail views so reviewers can investigate a single grantee’s academic trajectory across batches before deciding compliance.

**Steps:**

1. Pipeline or staff process stores academic values (from OCR / `gwa_history` / academic records)
2. Staff opens Academic Records — paginated list with search/sort
3. Staff opens a record detail — GWA, failed/dropped counts, flags
4. Staff compares values against policy (linked to Eligibility module)
5. Anomalies (trend jumps, mismatches) are highlighted for follow-up
6. Findings feed risk scoring and eligibility checks
7. Access is limited to staff/admin/head roles

---

## g) Student KYC and Profile Onboarding

Using gated account status and KYC form APIs — StudentKycController

**Description:** This module collects the minimum identity and profile data before a grantee can use the full student portal. Until KYC is accepted, navigation is limited (dashboard / verify / settings only as configured). Completing KYC moves the account toward a normal verified student state.

**Steps:**

1. Newly activated student signs in — account status requires KYC
2. Router forces `/student/kyc` for incomplete accounts
3. Student completes KYC fields (personal / contact / required profile data)
4. Backend stores KYC and updates account status
5. On success, full student navigation unlocks (documents, announcements, etc.)
6. Profile module remains available for later updates
7. Staff can see KYC completeness as part of grantee readiness

---

## h) Identity Verification (Face and Liveness)

Using browser face-api.js descriptors and server face-verify APIs — StudentFaceVerificationController + Requirement Vault identity check

**Description:** This module proves the submitting student is the enrolled grantee. The client loads face models on demand, captures a live check, and compares against a stored face descriptor / ID sample. Results feed `identity_checks` and heavy risk points when match or liveness fails. Duplicate-face detection can flag another grantee’s descriptor.

**Steps:**

1. Student opens Verify Identity or runs identity check inside the requirement vault
2. Client lazy-loads face models (not in the main JS bundle)
3. Student completes liveness challenge and face capture
4. Client computes a face descriptor and sends it to the backend
5. Backend compares against enrolled descriptor / ID sample (distance threshold)
6. System records liveness result, match result, quality score, device/IP metadata
7. If duplicate face is found — link to the other grantee and raise risk
8. Pass allows document confirmation to proceed; fail blocks or flags the submission
9. Admin/staff may upload an ID sample for a student to improve matching

---

## i) Requirement Vault and Document Upload

Using multi-step vault (ID → documents → identity → confirm) — RequirementVaultController

**Description:** This is the student-facing package builder for TES requirements. The grantee uploads school ID (sides as needed), academic PDFs (grade slip, course history), runs identity check, then confirms the package for staff review. Uploads respect the active batch submission window.

**Steps:**

1. Student opens Required Documents while a batch window is open
2. Student uploads school ID images to the vault
3. Student uploads grade slip and course history (PDF/image)
4. Optional OCR runs on PDFs to extract name, student ID, GWA, semester, school year
5. Student completes identity check (face/liveness)
6. Student confirms the vault — creates a submission for staff
7. If the window is closed — confirm/upload is rejected with a clear message
8. Student can track status (pending, returned, approved, rejected) afterward
9. Files are stored with metadata (size, mime, original name, EXIF flags where applicable)

---

## j) OCR and PDF Field Extraction

Using OCR service URL and PyMuPDF text extraction — `ocr-service` / `python/pdf_extract.py`

**Description:** This module turns grade slip and course history PDFs into structured fields for matching and eligibility. Extracted name, student ID, GWA, semester, and school year are compared to the grantee master record. Mismatches raise risk points and fail corresponding eligibility/metadata checks.

**Steps:**

1. Student uploads a grade slip or course history PDF
2. Backend (or OCR microservice) receives the file
3. PyMuPDF / OCR pipeline extracts raw text and candidate fields
4. System maps fields: name, student ID, GWA, semester, school year
5. Match flags are computed (`name_match`, `id_match`, `semester_match`, `gwa_computed_match`, etc.)
6. Results are stored on `ocr_results` linked to the document
7. Mismatches contribute to risk score and appear in staff review UI
8. Staff can visually confirm against the document viewer tabs

---

## k) Grantee Directory

Using paginated grantee APIs — GranteeController + Vue Query lists

**Description:** This module is the staff directory of TES grantees. It supports search, sort, and pagination over student ID, name, program, year level, batch, and status. From here staff jump into detail views for documents, academics, and evaluation.

**Steps:**

1. Staff opens Grantees
2. List loads from API with cache (stale-while-revalidate)
3. Staff filters/searches by student number, name, or status
4. Staff pages through results (server pagination)
5. Staff opens a grantee detail — profile, batch, submission status
6. From detail, staff can navigate to documents / evaluation actions
7. Directory reflects masterlist imports and status changes from reviews

---

## l) Announcements and Mass Communication

Using announcement CRUD and delivery logs — staff create / student read

**Description:** This module broadcasts TES and campus notices to grantees. Staff compose announcements, edit them, and inspect delivery/activity logs. Students read announcements in their portal. Related mass-communication records can target batches for wider campaigns.

**Steps:**

1. Staff opens Announcements and clicks Create
2. Staff writes subject/body and selects audience (e.g., batch)
3. System saves and dispatches to recipients
4. Logs page shows send history and outcomes
5. Students open Announcements and read active notices
6. Optional notifications mirror high-priority announcements
7. Edits update the published content for subsequent views

---

## m) Notifications

Using database notifications with optional Laravel Echo realtime — StudentNotificationController

**Description:** This module delivers in-app alerts for activation, review decisions, returns, deadlines, and system events. Students list notifications, mark one or all as read. When Reverb/Pusher is configured, new notifications can arrive live; otherwise REST + cache still work.

**Steps:**

1. System event occurs (approve, reject, batch open, etc.)
2. Backend creates a notification for the notifiable user
3. Optional broadcast event pushes to the student’s private channel
4. Student opens Notifications — sees unread/read list
5. Student marks one as read or marks all read
6. UI updates optimistically and syncs via API
7. Unread count can surface on the student shell

---

## n) Reports and Monitoring

Using report templates and preview/generate flows — Reports module UI

**Description:** This module produces monitoring outputs for TES operations — eligibility distribution, compliance rosters, academic summaries, and full grantee exports. Staff choose a template, preview columns, and generate a downloadable or printable report for a batch or filter set.

**Steps:**

1. Staff opens Reports / Monitoring Reports
2. Staff chooses a report type (e.g., eligibility, compliance, academic, full roster)
3. Staff selects batch/filters and field set
4. Preview shows sample rows and columns
5. Staff generates the report file or printable view
6. Generated artifacts can be stored for later download (billing-adjacent exports use related tables when enabled)
7. Reports reflect latest submission and evaluation statuses

---

## o) Support Tickets

Using ticket list, create, and detail threads — Support module

**Description:** This module gives grantees and staff a channel for TES process issues (upload problems, eligibility questions, account disputes). Users create tickets; staff respond and update status until resolution.

**Steps:**

1. User opens Support and creates a ticket (subject, body, category)
2. Ticket appears in the staff/support queue
3. Staff opens detail — reads thread and context
4. Staff replies and updates status (open, in progress, resolved)
5. Requester is notified of updates
6. Closed tickets remain searchable for audit/history

---

## p) Audit Trail

Using append-only audit event APIs — AuditEventController

**Description:** This module records sensitive actions for accountability — logins, imports, batch window changes, document decisions, eligibility outcomes. Staff/admin browse filters by actor, subject, and time. Entries include description, subject, causer, properties, and often IP.

**Steps:**

1. Protected action occurs in the app
2. Backend (or client heartbeat for UI events) writes an audit record
3. Admin opens Audit Trail
4. Admin filters by date, actor, or event type
5. Admin opens a row — before/after or property payload
6. Records are retained per policy settings (non-editable by normal staff)

---

## q) Users, Roles, and Permissions

Using role-aware menus and permission matrix UI — Users module

**Description:** This module manages staff accounts and what they can do — validate documents, review academics, run eligibility, manage batches, etc. Navigation sections differ for admin vs staff vs student. Permission screens document capability grants used by middleware (`role:admin,head,staff`).

**Steps:**

1. Admin opens Users & Roles
2. Admin views staff list and assigned roles
3. Admin opens Permissions — matrix of capabilities per role
4. Backend enforces role middleware on APIs
5. Frontend shows only navigation allowed for that role
6. Deactivating a user blocks further sign-in (`is_active`)

---

## r) Settings, Appearance, and Policy

Using settings screens and `policy_settings` keys — admin configuration

**Description:** This module holds operational knobs — max failed subjects per semester, default pass grade, and per-program pass grades (e.g. BSIT = 3.0). Pass rule: fail if grade > pass_grade; blank grades ignored. Curriculum checks are skipped in v1. Appearance/style-guide pages keep the UI consistent.

**Steps:**

1. Admin opens Settings → Organization
2. Admin adds/edits programs (code, name, pass grade) and validation rules
3. Saved values apply to the next Course History OCR eligibility computation
4. Appearance / style guide pages document visual tokens for implementers
5. Changes can record `updated_by` for accountability

---

## s) Security Findings and Security Memory

Using security review UI for fraud and repeat patterns — Security modules

**Description:** This module surfaces high-risk or repeated fraud signals (duplicate faces, tampered PDFs, blocked scores) and a “memory” of prior findings so staff do not miss serial patterns across batches.

**Steps:**

1. Risk pipeline flags severe signals (block badge, duplicate face, etc.)
2. Finding appears under Security Findings
3. Staff reviews evidence and linked grantee/submission
4. Staff may escalate, reject submission, or open a dispute
5. Security Memory stores notable cases for future matching
6. Linked decisions remain visible in audit and grantee history

---

## t) Billing / Call-for-Billing Report

Using DomPDF + `billing_reports` / `billing_report_items` — Billing module

**Description:** This module lets admin/head open a call-for-billing period for a batch, query only Verified grantees, and produce a downloadable CHED-style PDF (full name, student ID, program, batch, PHP 10,000 stipend). The PDF is stored under `storage/app/public/billing-reports` and logged to the audit trail.

**Steps:**

1. Admin opens Billing / Call-for-Billing (nav or dashboard quick action)
2. Admin selects a batch and clicks Generate
3. System queries all grantees in the batch with Verified status
4. DomPDF builds the call-for-billing report (non-compliant and pending excluded)
5. Report row + items are saved; PDF written to storage
6. Admin downloads the PDF from the past-reports list
7. Generation is recorded in the audit trail

---

## u) Distribution Report

Using DomPDF + Laravel billing report type `distribution` — Distribution module

**Description:** After the distribution period closes, admin/head generates an auditable stipend release summary: total verified grantees, total stipend released, per-grantee status, and excluded grantees with reasons. Output uses CHED-compliant formatting, is downloadable as PDF, and is logged for audit.

**Steps:**

1. Admin opens Distribution Report after distribution closes
2. Admin selects a batch and clicks Generate
3. System aggregates verified inclusions and exclusion reasons for others
4. DomPDF produces the distribution summary PDF
5. Report is stored for audit; totals include verified count and stipend released
6. Admin downloads the PDF
7. Action is logged to the audit trail

---

## v) Student Dashboard

Using role-specific home with window status and alerts — StudentDashboard

**Description:** This module is the grantee home screen — submission window status, document progress, recent notifications, and shortcuts to verify identity or open the vault. It orients the student on what to do next for TES compliance.

**Steps:**

1. Student signs in and lands on `/student`
2. Dashboard loads window status and key counts
3. Student sees alerts (deadline, returned docs, unread notifications)
4. Shortcuts open Verify, Documents, or Announcements
5. Live notification hooks refresh alerts when Echo is enabled

---

## w) Staff / Admin Dashboard

Using operational KPIs and quick actions — AdminDashboard

**Description:** This module summarizes TES operations for staff — pipeline stages (masterlist → documents → academics → eligibility), counts, and quick actions such as Run Eligibility, Call for Billing, or open validation queues.

**Steps:**

1. Staff/admin opens `/app`
2. Dashboard shows summary cards and pipeline status
3. Quick actions route to Masterlist, Documents, Eligibility, Billing, Reports, etc.
4. Staff drills into any queue that needs attention
5. Figures refresh as batches and reviews progress

---

## Legend

| Label | Meaning |
| --- | --- |
| **Live / partial** | UI + APIs exist in this repo; depth varies by module |
| **Design-complete** | Described against schema + intended TES workflow (e.g. full risk point table in **d**) — implement remaining engine pieces as needed |

Module **d** matches the requested narrative format for Eligibility and Risk Evaluation.
