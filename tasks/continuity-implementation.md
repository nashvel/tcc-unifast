# Google Workspace continuity implementation

Authorized by the user on 2026-09-06 from
`docs/specs/n8n-google-workspace-continuity-mirror.md`.

## Sequence and progress

- [x] Pure three-way merge rules and security-focused tests (8 tests, 15 assertions).
- [ ] Encrypted singleton Google connection, OAuth, admin status and selection.
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

- `php artisan test --compact --filter=ContinuityMergeTest`: passed.
- Pint check for the merge service and test: passed.
- No runtime routes, migrations, external resources, or storage switches have
  been activated. The merge service is pure; it does not write business data.
