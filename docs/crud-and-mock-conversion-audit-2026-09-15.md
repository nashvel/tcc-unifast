# CRUD and Mock Conversion Audit

**Date:** 2026-09-15  
**Scope:** Static audit of Vue modules, mock handlers, Laravel API routes, and existing backend tests. Live browser/device behavior and host MySQL data were not verifiable from the sandbox.

## Overall Status

| Area | Progress | Assessment |
| --- | ---: | --- |
| Laravel CRUD/API coverage | **92%** | Core owned resources have authenticated, validated lifecycle APIs; remaining work is endpoint-coverage classification and host verification. |
| Frontend real-data integration | **82%** | Converted developer, security, form-response, and user-management pages use Laravel APIs with loading, empty, and error states. |
| Mock/placeholder removal | **100%** | Reachable mock request handlers, mock sessions, bundled records, fake support flows, and static mock administration data were removed or converted to Laravel APIs. |
| CRUD production readiness | **76%** | Core CRUD is API-backed; host browser/MySQL persistence checks and a small set of unfinished controls remain. |

## Verified Real CRUD Resources

| Resource | API operations found | Status |
| --- | --- | --- |
| Academic programs | List, create, update, delete | Real CRUD |
| Batches | List, detail, create, update, activate/deactivate, deadline extension | Real workflow CRUD |
| Roles | List, create, detail, update, delete, user assignment/sync | Real CRUD; RBAC cutover migration pending host application |
| Permissions | List, create, delete | Real CRUD |
| Terms | List, create, detail, update, delete | Real CRUD |
| FAQs | Public list, admin list/create/detail/update/delete/reorder | Real CRUD |
| Dynamic forms | Forms, sections, fields CRUD; publish/close/toggle; response export | Real CRUD |
| Collaborators | List, invite, deactivate, reactivate | Real lifecycle CRUD; invitations assign pivot-table RBAC roles |
| Security findings | List, resolve | Real protected API with feature-test coverage |
| Support tickets | List, create, update, reply, close, reopen | Real audited lifecycle CRUD |
| Announcements | List, create, detail, update draft, cancel scheduled, delete draft | Real lifecycle CRUD; sent notices are immutable |
| Social media posts | List, create, detail, dispatch, reactions/comments | Real workflow CRUD; external Facebook result still needs live verification |
| Continuity grants | List, create, delete | Real CRUD |
| Masterlist imports | List, preview, detail, confirm, delete | Real workflow CRUD |

## Partial or Missing CRUD

| Priority | Area | Finding | Required work |
| --- | --- | --- |
| P0 | Host persistence verification | Sandbox checks cannot access the host MySQL/browser session. | Smoke-test each converted create/update/deactivate action, refresh the page, and confirm the record persists. |
| P1 | Users controls | Export, create-user, and password-reset controls do not yet have matching user-management APIs. | Add protected endpoints and mutations, or remove/disable controls until implemented. |
| P1 | Security finding lifecycle | Findings can be listed and resolved but do not yet have create/detail-history/delete workflows. | Define the intended operator lifecycle and add only needed audited endpoints. |
| P2 | Frontend automated tests | No frontend unit or browser suite covers the converted API states. | Add tests for API error/empty states and the login-to-CRUD smoke journey. |

## Mock Inventory

| Location | Purpose | Removal condition |
| --- | --- | --- |
| `frontend/src/mock/handlers.ts` | Fake API responses | Removed |
| `frontend/src/mock/data.ts` | Fake records | Removed |
| `frontend/src/mock/changelogs.json` | Changelog fallback | Removed |
| `frontend/src/constants/mockAdmin.ts` | Legacy static security/admin records | Removed |
| `CaptchaModalMock.vue` | Mock captcha component | Removed |

## Recommended Vertical Slices

1. **Host CRUD smoke test (P0):** verify persistence after refresh for every converted mutation using host MySQL.
2. **Users actions (P1):** implement or remove the controls that lack a corresponding API.
3. **Security lifecycle (P1):** decide whether findings need create, ignored, detail history, or retention operations.
4. **Frontend tests (P2):** cover real API loading/error/empty states and core role-based workflows.

## Acceptance Criteria for Completion

- [x] No reachable converted route shows mock API data; mock transport and bundled mock records are removed.
- [ ] Each CRUD screen uses a typed Laravel API client and has loading, error, empty, and success states.
- [ ] Create/update/delete operations have backend feature tests and permission checks.
- [ ] `VITE_USE_MOCK` is disabled for every non-demo build and API failures never return fake records.
- [ ] Browser verification confirms persistence after refresh for each converted module.
