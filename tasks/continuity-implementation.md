# Google Workspace continuity implementation

Authorized by the user on 2026-09-06 from
`docs/specs/n8n-google-workspace-continuity-mirror.md`.

## Sequence and progress

- [x] Pure three-way merge rules and security-focused tests (8 tests, 15 assertions).
- [x] Encrypted singleton Google connection, OAuth, admin status and selection (mocked provider tests; live validation pending).
- [ ] Shared Drive storage, verified migration, existing secure retrieval integration.
- [ ] Module-specific allowlisted schemas, outbox and version ledger.
- [ ] Private workbook provisioning and permission reconciliation.
- [ ] Google Forms/manual intake, matching and file validation.
- [ ] Conflict and approval persistence, resolution and Sync Center.
- [ ] Hourly/manual n8n triggers, retries, recovery and run history.
- [ ] Frontend/admin guide, integration verification and deployment checks.

## Confirmed implementation constraints

- Preserve existing authentication, signed files, review and RBAC boundaries.
- Keep OAuth tokens encrypted and out of n8n payloads/execution history.
- Keep storage migration non-destructive; do not activate Drive without a
  validated connection and verified file mappings.
- Google Forms cannot use upload questions while stored in Shared Drives;
  the Forms API also cannot create upload questions. A dedicated school-owned
  account's My Drive intake followed by verified transfer is proposed. User
  choice is pending; do not implement dependent intake behavior yet.
- File-upload questions must be added manually in Google Forms; setup should
  validate those questions and their restrictions before enabling intake.
- Concurrent edits during Sheets writes require a durable input history;
  read-then-write plus checksums alone cannot guarantee lossless merging.
  Do not enable editable mirror round trips until this is handled and tested.

Sources:
- https://support.google.com/docs/answer/7322334
- https://developers.google.com/workspace/forms/api/reference/rest/v1/forms
- https://developers.google.com/workspace/sheets/api/reference/rest/v4/spreadsheets/batchUpdate

## Verification so far

- Prepared local ignored env files and committed-safe examples for Google
  OAuth configuration and matching generated continuity signing secrets.
- Added inactive hourly/manual n8n JSON and implemented its HMAC/idempotency
  endpoint, queued sync service and run history. Dispatch recovery and
  end-to-end scheduler verification remain outstanding.

- `php artisan test --compact --filter=Continuity`: 31 passed, 134 assertions.
- Full backend suite: 225 passed, 11 failures, 1 error, 3 skipped. Failures are
  in batch/billing/onboarding/vault-resubmission, social status and SPA tests;
  their cause has not been established by this continuity slice.
- OAuth refresh tests prove temporary failures preserve the connection,
  revoked grants require reconnection, and rotated tokens persist encrypted.
- Workbook tests reject unapproved principals and excess roles, including
  permissions on later pages. Verified read grants and downgrades pass.
- Inactive administrators are denied the new admin route groups.
- New routes and migrations exist in source. Live MySQL migrations, Google
  resources, n8n activation and storage switches have not been performed.

## Remaining implementation gaps (do not enable yet)

- Wire verified Drive storage into existing secure business-file upload/download
  helpers, validate folder access, migrate existing files without deletion.
- Complete eligibility/announcements and detailed business schemas; validate
  inbound fields against existing domain rules, not generic string limits.
- Add durable intake history, new-student matching, archive requests and Forms
  ingestion after resolving the My Drive intake exception.
- Finish grants and review controls in the UI, protection integrity checks,
  permission crash recovery/revocation and queue dispatch/timeout recovery.
- Add readiness/activation controls and configurable app-level scheduling.
- Verify MySQL migrations, browser behavior and credentialed Google/n8n flows.
