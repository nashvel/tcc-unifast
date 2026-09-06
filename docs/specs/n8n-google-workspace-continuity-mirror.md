# n8n and Google Workspace Continuity Mirror

Status: Implementation authorized; student upload destination amendment pending
Date: 2026-09-06

## Implementation findings (2026-09-06)

Implementation tracking: `tasks/continuity-implementation.md`.

Google's [Forms question documentation](https://support.google.com/docs/answer/7322334)
states that file-upload questions cannot be used when a Form is stored in a
Shared Drive. The [Forms API reference](https://developers.google.com/workspace/forms/api/reference/rest/v1/forms)
also states that the API cannot create file-upload questions. The original
student-upload layout below therefore needs an amendment before it can be
implemented.

Proposed amendment, awaiting the user's choice: keep upload Forms and temporary
intake in a dedicated school-owned Workspace account's My Drive, manually add
the file-upload questions, and transfer validated files into the private Shared
Drive. While Laravel is unavailable, submissions stay in private intake until
the transfer service can resume. This exception does not change Shared Drive
as the intended primary storage for accepted application business files.

The merge service can compare complete normalized snapshots, but Sheets
read-then-write operations do not lock out human editors. A durable inbound
change journal or another tested write-coordination mechanism is required
before enabling concurrent bidirectional edits; checksums alone are insufficient.
This requirement follows Google's documented
[collaborative update semantics](https://developers.google.com/workspace/sheets/api/reference/rest/v4/spreadsheets/batchUpdate).

## Objective

Provide a private, bidirectional digital mirror of UniFAST TES business records
through Google Sheets, Google Forms, and Google Drive. Authorized staff can
continue normal administrative work while the application is offline or under
maintenance. When the application is available, n8n initiates an hourly sync or
an administrator starts **Sync now**.

The synchronization behaves like a simplified Git workflow:

- every synchronized record has a stable identity and revision history;
- non-conflicting changes merge automatically;
- changes to different fields of the same record can merge independently;
- competing changes to the same field create a conflict;
- administrators review conflicts side by side in the application's Sync
  Center and select the final values;
- destructive and financially sensitive operations require explicit approval.

This mirror is a business-continuity interface, not a replacement database and
not an export of technical or security internals.

## Confirmed requirements

- Use an organization-owned Google Workspace Shared Drive.
- Use separate module workbooks, not one workbook with tabs. Google permissions
  apply at the file level, and hiding or protecting a tab does not prevent a
  workbook viewer from accessing it.
- Administrators connect the organization Google account in an Integration
  Settings screen and select the Shared Drive and managed workbooks.
- Google OAuth client ID and client secret remain protected server
  configuration. OAuth access and refresh tokens are encrypted in MySQL.
- Administrators grant access only to selected system users whose Google email
  address is linked to their account.
- Google file roles implement module read-only/read-write access. The
  application's RBAC remains authoritative and reconciles Google permissions.
- Drive files are private and never use “anyone with the link.”
- Google Drive becomes the primary storage provider for user-uploaded business
  files. Laravel stores stable Drive file IDs, checksums, ownership, and safe
  metadata rather than public URLs.
- Each student-facing submission workflow has a Google Form. Form uploads go
  into the Shared Drive and are staged for matching and review.
- Students have Google accounts and can sign in for emergency Form submissions
  and file uploads.
- Staff may also receive physical documents during downtime, enter the business
  data in the appropriate workbook, and upload the scanned files to the
  designated private Drive folder.
- Users may work in the mirror both during outages and while the application is
  online.
- Synchronization runs hourly and through an administrator-only **Sync now**
  action.
- Rows are archived, never physically deleted by spreadsheet users. Archive
  requests require administrator approval before affecting live records.
- New student submissions created during downtime remain temporary until an
  administrator matches them using student number/email or approves creation of
  a new live record.
- Eligibility decisions, billing amounts, distribution/payout records, archive
  operations, and other high-impact changes always require administrator
  approval even when there is no technical conflict.
- Spreadsheet changes update business records only. They never directly send
  announcements, issue billing, record a completed payout, publish reports, or
  perform other external side effects. Those actions require confirmation in
  the restored application.

## Scope

### Mirrored business modules

Create one managed workbook for each access boundary:

| Workbook | Primary continuity use |
| --- | --- |
| Masterlist | Review imported CHED rows and intake status |
| Grantees | View and update non-technical grantee business details |
| Batches | View academic batch details and enrollment grouping |
| Academic Programs | View program reference information |
| Onboarding and KYC | Track completion and human review status |
| Requirement Documents | Track required files, receipt, and validation |
| Academic Records | Record term-level academic information and supporting files |
| Eligibility | Review inputs and propose eligibility decisions |
| Announcements | Draft business content without sending it |
| Billing | Prepare and review billing records and amounts |
| Distribution | Prepare and review fund distribution records |
| Support Tickets | Continue support intake and staff responses |

An additional administrator-only **Continuity Control** workbook contains a
human-readable sync summary, unresolved conflict references, approval queue
references, and last successful synchronization times. Detailed conflict
resolution remains in the application's Sync Center.

### Emergency Google Forms

Separate Google Forms feed staging sheets associated with the relevant module
workbooks. Initial forms cover:

- new or returning grantee intake;
- onboarding/KYC document submission;
- requirement document submission;
- academic record and supporting-document submission;
- support ticket submission.

Forms collect only the minimum human-readable business fields required for the
workflow. A Form response is an immutable submission event; staff corrections
are made in a reviewed staging row, not by rewriting the original response.

### Explicitly excluded data

Do not mirror technical or security data, including:

- passwords, PINs, password reset material, sessions, CAPTCHA data, and 2FA
  secrets;
- face descriptors, liveness templates, biometric vectors, or internal model
  output;
- API tokens, OAuth secrets, webhook secrets, signing secrets, and encryption
  keys;
- raw technical logs, stack traces, queue payloads, database internals, and
  infrastructure configuration;
- Social Posts/Facebook data and credentials.

Human-readable document review outcomes and business audit history are in
scope. Technical security telemetry is not exposed to spreadsheet users.

## Source of truth and availability

MySQL remains the canonical transactional database while the application is
online. Google Workspace is the continuity mirror and the primary object store
for uploaded business files.

If Laravel is unavailable:

1. Google Forms continue receiving signed-in student submissions.
2. Authorized staff continue working in their permitted workbooks and folders.
3. Each change remains pending in Google Workspace; failed n8n attempts do not
   mark it synchronized or overwrite it.
4. When Laravel returns, the next hourly run or **Sync now** imports pending
   changes through the normal merge and approval process.

n8n orchestrates the schedule and calls protected Laravel integration
endpoints. Laravel owns data mapping, validation, authorization, transactions,
merge decisions, Google API access, and the encrypted Google credential. The
OAuth token is never placed in an n8n workflow, webhook payload, or execution
history.

## Google Workspace layout and permissions

```text
UniFAST TES Continuity (Shared Drive)
├── 00 Continuity Control (admin-only workbook)
├── 01 Masterlist (workbook)
├── 02 Grantees (workbook)
├── 03 Batches (workbook)
├── 04 Academic Programs (workbook)
├── 05 Onboarding and KYC (workbook + private file folder)
├── 06 Requirement Documents (workbook + private file folder)
├── 07 Academic Records (workbook + private file folder)
├── 08 Eligibility (workbook)
├── 09 Announcements (workbook)
├── 10 Billing (workbook + generated-file folder)
├── 11 Distribution (workbook + generated-file folder)
├── 12 Support Tickets (workbook + attachment folder)
└── 90 Form Intake (forms, response sheets, and intake uploads)
```

Users are not granted broad Shared Drive membership when that would expose
unrelated modules. The integration assigns file/folder permissions to each
linked Google email:

- module read permission maps to Google `reader`;
- module write permission maps to Google `writer`;
- administrators responsible for continuity management receive the minimum
  Google role needed to manage the selected structure;
- removing or reducing a system grant triggers permission reconciliation;
- direct Google permissions that are not represented by an approved system
  grant are reported and removed after administrator review.

Every managed file records its Google file ID in the database. Links shown in
the application are authenticated references, never public sharing links.

## Human-facing workbook design

Each workbook contains a clearly labeled business table and instructions. Staff
see familiar labels rather than database names. Standard visible columns are:

- business identifier, such as student number, batch code, or ticket number;
- the module's approved editable business fields;
- file name and private Drive link where relevant;
- record status;
- sync status: `Synced`, `Pending`, `Needs approval`, `Conflict`, or `Error`;
- conflict/approval reference number, when applicable;
- last synchronized time.

Stable record IDs, base revisions, schema versions, origin markers, and hashes
are stored in protected metadata columns or Google Sheets developer metadata.
They are not part of the normal staff workflow. The importer treats any
tampering with protected sync metadata as an invalid row and never guesses a
record match.

Validation rules and dropdowns constrain enumerated business values. User text
is written with safe value handling to prevent spreadsheet formula injection.
Headers, metadata columns, formulas, and validation ranges are protected from
normal editors.

## Git-style synchronization model

### Record identity and baseline

Every mirrored entity has an immutable application UUID. For each record and
workbook, Laravel stores:

- last common/base revision;
- normalized base field values and hash;
- latest system revision and hash;
- latest mirror revision and hash;
- Google workbook, row, and file identifiers;
- last successful inbound and outbound sync times.

Database outbox events identify system changes without scanning entire tables.
Inbound Google rows are normalized and validated before comparison.

### Three-way merge

For every allowed field, compare the base, current system, and current mirror
values:

| System value | Mirror value | Result |
| --- | --- | --- |
| unchanged | unchanged | No operation |
| changed | unchanged | Export system value |
| unchanged | changed | Import mirror value if allowed |
| changed to same value | changed to same value | Accept and advance baseline |
| changed differently | changed differently | Create conflict |

Changes to different fields merge automatically. A conflict freezes only the
affected record fields; unrelated records continue syncing.

### Conflict review

The Sync Center presents:

- workbook/module and human-readable record identity;
- base, system, and spreadsheet values side by side;
- author/source and timestamps where available;
- **Use system**, **Use spreadsheet**, and field-by-field resolution actions;
- a required administrator note for high-impact resolutions.

After resolution, Laravel commits the chosen values transactionally, creates a
new common revision, updates the workbook, and appends an audit entry. The
spreadsheet displays only `Conflict` and its reference until resolved.

This is Git-like versioning, not literal Git storage: MySQL holds the revision
and conflict ledger, while Google revision history remains an additional
operator aid.

### New records and duplicates

New Form/staff-created rows receive a temporary continuity ID. Laravel proposes
matches using normalized student number and email, but never auto-merges an
ambiguous identity. An administrator must confirm an existing grantee or
approve creation of a new record. Idempotency keys prevent repeated Form
responses or n8n retries from creating duplicate records.

### Archives and high-impact changes

Spreadsheet row deletion is not a supported command. Staff set an **Archive
requested** business status; Laravel restores accidentally removed rows from
the baseline and creates a review item.

The following always enter the approval queue:

- archive requests;
- eligibility decisions or overrides;
- billing amounts, finalization, and report-ready state;
- distribution amounts, release status, or payout completion;
- identity/KYC approval or rejection;
- any field later classified as high-impact by the module schema.

Approving a mirrored value updates the business record only. Sending an
announcement, issuing billing, completing a payout, or publishing a report is a
separate authenticated action in the live application.

## Primary Google Drive file storage

Add a storage abstraction so existing secure document services use a Google
Drive implementation rather than embedding Drive calls in controllers.

For each file, store at minimum:

- immutable Google Drive file ID and Shared Drive ID;
- owning application entity and document category;
- original display name, MIME type, byte size, and checksum;
- uploader and upload source (`application`, `google_form`, or `staff_drive`);
- scan/validation status and timestamps;
- current permission policy and lifecycle state.

Application uploads stream server-side to the approved Shared Drive folder.
Downloads remain protected by existing authentication, RBAC, signed-access,
audit, and validation boundaries. The backend verifies permission and either
streams the file or produces a short-lived authenticated Google access path.

Existing local files are migrated by an idempotent command/job that uploads,
checksums, verifies, and records each Drive mapping. Local originals are not
deleted automatically. Their later removal is a separately approved,
recoverable operation after migration reports show complete verification.

Student Form uploads enter an intake folder. They are not treated as validated
documents until identity matching, file-type/size checks, malware/processing
checks, and the relevant human review succeed.

## Integration configuration

The Integration Settings screen is administrator/developer-only and provides:

- **Connect Google Workspace** OAuth flow;
- selected Shared Drive;
- provision or select managed module workbooks/folders;
- Google email mapping and permission reconciliation status;
- last sync, next hourly sync, health, pending changes, conflicts, and approvals;
- **Sync now**, **Validate connection**, **Repair structure**, and
  **Disconnect** actions with confirmation and audit logs.

Server environment configuration contains only application-level values:

```dotenv
GOOGLE_WORKSPACE_CLIENT_ID=
GOOGLE_WORKSPACE_CLIENT_SECRET=
GOOGLE_WORKSPACE_REDIRECT_URI=http://127.0.0.1:8000/api/integrations/google-workspace/callback
GOOGLE_WORKSPACE_HTTP_TIMEOUT=20
```

The selected Drive/workbook IDs and encrypted OAuth refresh token are stored in
MySQL. API responses expose connection health and names but no credentials.
Disconnecting does not delete Drive files or business data; it stops sync and
removes local access credentials after confirmation.

## Proposed application data model

Use additive Laravel migrations for focused tables:

| Table | Responsibility |
| --- | --- |
| `google_workspace_connections` | Singleton organization connection, encrypted tokens, expiry, health, selected Shared Drive |
| `continuity_resources` | Managed workbook/form/folder IDs, module, schema version, health |
| `continuity_user_grants` | System user, Google email, module, read/write level, reconciliation status |
| `continuity_record_states` | Entity UUID, resource/row identity, base/system/mirror revisions and hashes |
| `continuity_sync_runs` | Hourly/manual run, cursors, counts, health, sanitized errors |
| `continuity_changes` | Append-only record/field change ledger and origin |
| `continuity_conflicts` | Base/system/mirror values, state, resolution, resolver |
| `continuity_approvals` | High-impact/archive/new-identity proposals and decision audit |
| `stored_files` extensions | Drive ID, checksum, source, lifecycle and ownership metadata |

Tokens use Laravel encrypted casts backed by `TEXT`, remain hidden from
serialization, and are redacted from logs, jobs, audits, and n8n payloads.
Business change payloads containing personal data remain encrypted where the
existing data classification requires it.

## Service and API boundaries

Laravel services own:

- Google OAuth and encrypted credential lifecycle;
- Drive, Sheets, and Forms API clients;
- workbook schema/provisioning and access reconciliation;
- file storage and secure retrieval;
- outbound change collection;
- inbound normalization and validation;
- three-way merge, conflicts, approvals, and audit history.

n8n owns:

- the hourly trigger;
- administrator-requested orchestration;
- bounded retry/backoff of signed Laravel workflow calls;
- operational success/failure routing without storing record bodies longer
  than necessary.

Representative protected endpoints:

| Method and path | Purpose |
| --- | --- |
| `GET /api/integrations/google-workspace/status` | Safe connection and resource health |
| `POST /api/integrations/google-workspace/oauth` | Start admin OAuth flow |
| `GET /api/integrations/google-workspace/callback` | Validate state and exchange code |
| `PUT /api/integrations/google-workspace/resources` | Select/provision Shared Drive resources |
| `POST /api/integrations/google-workspace/sync` | Queue administrator manual sync |
| `GET /api/continuity/sync-runs` | View history and per-module counts |
| `GET /api/continuity/conflicts` | Paginated unresolved conflicts |
| `POST /api/continuity/conflicts/{conflict}/resolve` | Commit an admin resolution |
| `GET /api/continuity/approvals` | Paginated high-impact proposals |
| `POST /api/continuity/approvals/{approval}/decide` | Approve or reject proposal |
| `POST /api/internal/n8n/continuity-sync` | Signed, throttled n8n trigger |

All endpoints preserve Sanctum, full-session, role, permission, onboarding,
throttling, validation, and audit boundaries. n8n receives an opaque run ID and
summary, not Google credentials.

## Sync execution

1. n8n calls the signed internal trigger hourly, or an administrator uses
   **Sync now**.
2. Laravel obtains a distributed lock and creates a sync run. Duplicate
   triggers return the existing active run.
3. Laravel refreshes the Google credential if needed and validates managed
   resource IDs/schema versions.
4. It imports Form responses and changed workbook rows using cursors/checksums.
5. It validates and applies automatic merges in bounded database transactions,
   then creates conflict/approval records for changes requiring review.
6. It exports committed system changes with batched Google API operations and
   advances baselines only after confirmed writes.
7. It reconciles approved user permissions and records exceptions.
8. It updates safe workbook statuses and completes the run with counts.
9. The frontend receives reactive status updates through the existing event
   pattern or targeted query invalidation; it does not poll aggressively.

Retries are idempotent. Partial failure is recorded per module/resource, and a
failed module does not falsely mark other pending changes as synchronized.

## Security and privacy controls

- OAuth state is random, single-use, user-bound, and short-lived.
- Request only the Google scopes required for selected Drive, Sheets, Forms,
  and file permissions operations; exact scopes require security review before
  implementation.
- Shared Drive/workbook/folder IDs are allowlisted. The integration cannot read
  arbitrary organization Drive content.
- No public/domain-wide sharing. Every access grant targets an approved linked
  Google identity or tightly controlled Google group.
- Permission reconciliation is serialized because concurrent Drive permission
  changes use last-write-wins behavior.
- Imported values have type, length, enumeration, formula, identifier, and
  authorization validation. Files retain the project's upload validation and
  secure access rules.
- Conflict and approval actions require full-session admin authorization and
  produce immutable audit entries.
- Logs and n8n executions contain IDs, counts, safe codes, and timings—not
  access tokens or unnecessary student data.
- Exported columns use a per-module allowlist; adding a database field never
  automatically exposes it to Google.
- Data retention, legal holds, backups, and Shared Drive trash/recovery rules
  must be documented before production launch.

## Failure and recovery behavior

| Failure | Behavior |
| --- | --- |
| Laravel unavailable | Google work continues; pending rows remain unsynced |
| n8n unavailable | Application and Google work continue; admin can retry after recovery |
| Google unavailable | Application remains canonical; outbound queue and uploads report retryable failure |
| Expired/revoked Google credential | Mark reconnect required, pause Google mutations, notify admins once |
| Workbook schema changed | Quarantine affected resource; do not guess column mappings |
| Duplicate/replayed request | Return prior idempotent result |
| Ambiguous student identity | Create manual matching review item |
| Conflicting field edit | Preserve both versions and create conflict |
| High-impact spreadsheet edit | Create approval; do not mutate live record yet |
| Deleted spreadsheet row | Restore row and create archive review item |

## Testing and verification

Implementation follows red-green-refactor in incremental slices.

Automated coverage includes:

- encrypted token storage and secret-free serialization/logging;
- OAuth state, callback, expiry, replay, disconnect, and reconnect behavior;
- per-module read/write/admin authorization and Drive ACL reconciliation;
- workbook schema/version validation and allowlisted field mapping;
- all three-way merge cases, field-level merges, conflicts, and resolutions;
- high-impact approval gates and prevention of external side effects;
- new student staging, matching, ambiguity, and retry idempotency;
- archive request handling and physical-row-deletion recovery;
- Forms response/file import validation and duplicate prevention;
- Google-primary file upload/download authorization and checksum verification;
- hourly/manual trigger locking, partial failure, retry, and resume behavior;
- n8n webhook signature validation and credential-free execution payloads;
- permission removal and no-public-link enforcement.

Google API calls are mocked in normal tests. A dedicated Workspace test Shared
Drive supplies end-to-end smoke tests with fictional records and files.

Required repository checks include:

```bash
cd backend && php artisan test
cd backend && ./vendor/bin/pint --test
cd frontend && npm run lint
cd frontend && npm run build
docker compose --env-file n8n/.env -f compose.yml config --quiet
```

Database-sensitive migrations and locking behavior also require host MySQL
verification.

## Delivery sequence

1. Architecture decision record, field-level data classification, module
   workbook schemas, and threat model.
2. Google Workspace connection, encrypted credentials, safe status, and admin
   configuration UI.
3. Google-primary file storage abstraction and verified non-destructive
   migration of existing files.
4. Continuity tables, outbox/revision ledger, and read-only outbound mirrors.
5. RBAC-to-Google permission reconciliation and access audit.
6. Forms/staff intake staging and temporary student matching.
7. Three-way inbound merge, conflict Sync Center, and archive behavior.
8. High-impact approval queue and external-side-effect boundaries.
9. Hourly/manual n8n workflow, notifications, observability, recovery tests,
   and operational runbook.

Each slice must be deployable or feature-flagged, testable, and reversible.
Bidirectional spreadsheet writes are not enabled until the outbound mirror,
revision baseline, access controls, and restore procedure have been verified.

## Boundaries

### Always

- Use private Shared Drive resources, explicit field allowlists, immutable IDs,
  idempotency, revision history, validation, audit logs, and admin conflict
  resolution.
- Keep non-conflicting work moving while isolating conflicting fields.
- Preserve the live application's existing security and review gates.

### Ask before expanding

- Adding a new mirrored module/field, public or domain-wide sharing, real-time
  syncing, automated external side effects, multiple Workspace organizations,
  or access for users without linked approved Google identities.
- Deleting verified local file originals after the Drive migration.

### Never

- Treat a spreadsheet as a direct database table or blindly overwrite one side
  based only on timestamp.
- Expose technical/security secrets or biometric templates.
- Allow workbook deletion to directly delete live data.
- Let spreadsheet edits directly send announcements, issue billing, complete a
  payout, or bypass required review.
- Put Google OAuth tokens into n8n payloads, logs, workflow JSON, or frontend
  responses.

## Acceptance criteria

The continuity mirror is complete when:

1. Administrators can connect one organization Workspace, select a private
   Shared Drive, and provision/choose all managed module resources.
2. Selected users receive only their authorized module workbooks/folders with
   the correct read/write level, and revocation is reconciled.
3. Students can submit the approved emergency Forms and files with Google
   sign-in while Laravel is unavailable.
4. Staff can continue approved module work, including physical-document intake,
   without seeing technical fields or unrelated modules.
5. The next hourly or manual run automatically merges non-conflicting changes,
   stages new identities, and creates conflicts/approvals without data loss.
6. Administrators can resolve conflicts side by side and approve/reject
   high-impact changes from the Sync Center with a complete audit trail.
7. Google Drive serves as the primary secure object store and existing files
   have a verified, non-destructive migration path.
8. No spreadsheet action directly causes an external announcement, billing,
   payout, report publication, or destructive deletion.
9. Automated checks, MySQL verification, fictional-data Workspace smoke tests,
   restore testing, and operational documentation pass.

## Documentation deliverables

Implementation must also update:

- `SYSTEM_MAP.md` and `docs/features-modules.md`;
- `docs/database-schema-reference.md`;
- environment examples and deployment guidance;
- an ADR for the continuity mirror, three-way merge, and Google-primary file
  storage decision;
- an administrator guide for connection, access grants, Forms, conflicts,
  approvals, and maintenance-mode operations;
- an operations runbook for credential recovery, schema repair, failed sync,
  export/restore, and Workspace outage handling.

## Authoritative source notes

- Google documents permissions as ACLs on Drive files/folders/drives, with
  reader/writer-style roles and inherited access behavior:
  <https://developers.google.com/workspace/drive/api/guides/manage-sharing>.
- Google states that hidden sheets remain accessible to spreadsheet viewers and
  can be unhidden from copies, which is why confidential modules use separate
  workbooks:
  <https://support.google.com/docs/answer/1218656>.
- Google Sheets supports batched value operations, and spreadsheet batch
  requests can apply together atomically, while concurrent collaborator edits
  still require application-level conflict handling:
  <https://developers.google.com/workspace/sheets/api/reference/rest/v4/spreadsheets/batchUpdate>.
- Google Forms response resources include stable response IDs, timestamps,
  respondent email, and Drive file IDs for uploaded files:
  <https://developers.google.com/workspace/forms/api/reference/rest/v1/forms.responses>.
- Google Drive change notifications indicate that changes exist but require the
  consumer to retrieve details from the change feed; the initial design uses
  the confirmed hourly schedule rather than depending on real-time delivery:
  <https://developers.google.com/workspace/drive/api/guides/manage-changes>.

Exact OAuth scopes, Google Workspace edition policies, Forms ownership rules,
API quotas, retention requirements, and Shared Drive restrictions must be
verified against the organization's current Workspace configuration before
implementation and production authorization.
