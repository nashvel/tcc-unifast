# Implementation Plan: Core Laravel CRUD Completion

## Overview

Bring the Laravel application-service API surface to 100% completion for owned,
mutable resources. Completion means every supported state transition has an
authorized, validated, audited endpoint and focused feature coverage. It does
not mean adding unsafe or semantically incorrect CRUD to derived resources such
as security findings, imports, reports, or external-provider records.

## Architecture Decisions

- Preserve normalized tables and existing resource-specific lifecycle routes;
  do not force every domain object into generic delete semantics.
- Treat security findings as system-generated lifecycle records: list, inspect,
  resolve, and ignore are their supported operations.
- Treat support tickets as durable conversation records: create, list, update,
  reply, and explicit close/reopen are the intended lifecycle.
- Keep staff account creation invitation-only; reset actions dispatch a link and
  never expose a credential.
- Add focused tests for each uncovered mutation before marking the slice done.

## Task List

### Phase 1: Close identified endpoint gaps

- [x] Task 1: Add explicit support-ticket close/reopen lifecycle endpoints and
  audit events.
- [x] Task 2: Add announcement deletion with authorization and a policy for
  sent announcements (cancel rather than hard-delete where appropriate).
- [ ] Task 3: Add collaborator password-reset feature coverage and verify role
  boundary behavior.

### Checkpoint: Lifecycle coverage

- [x] Focused feature tests for Tasks 1-2 pass.
- [ ] Existing full Laravel suite passes.

### Phase 2: API inventory verification

- [ ] Task 4: Classify every API-backed module as CRUD, lifecycle, read-only,
  integration-owned, or intentionally external-only in the audit document.
- [ ] Task 5: Add or update focused tests for every mutation currently missing
  test coverage in the classified core modules.

### Checkpoint: Core API completion

- [ ] Each mutable core resource has authorization, validation, audit behavior
  where sensitive, and at least one feature test for each supported mutation.
- [ ] `php artisan test` passes.

### Phase 3: Host verification

- [ ] Task 6: Run browser/MySQL persistence checks using real host services.
- [ ] Task 7: Record results and any MySQL-specific corrections.

## Risks and Mitigations

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Adding generic delete to operational records | High | Use domain lifecycle status/cancel endpoints instead. |
| Email/password reset test dependency | Medium | Fake notifications/mail and assert no credential disclosure. |
| Sandbox cannot reach host MySQL | Medium | Keep SQLite tests for behavior and perform host smoke tests separately. |

## Open Questions

- Should sent announcements be immutable, cancellable, or editable with an audit revision?
- What retention rule should govern closed support tickets and ignored findings?
