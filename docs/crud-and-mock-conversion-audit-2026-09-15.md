# CRUD and Mock Conversion Audit

**Date:** 2026-09-15  
**Scope:** Static audit of Vue modules, mock handlers, Laravel API routes, and existing backend tests. Live browser/device behavior and host MySQL data were not verifiable from the sandbox.

## Overall Status

| Area | Progress | Assessment |
| --- | ---: | --- |
| Laravel CRUD/API coverage | **82%** | Most operational resources have authenticated, validated API routes and feature-test coverage. |
| Frontend real-data integration | **68%** | Main staff/student modules use API composables; several developer tools still retain mock branches or static data. |
| Mock/placeholder removal | **45%** | Global mock infrastructure remains enabled through `VITE_USE_MOCK`; visible mock-only actions remain. |
| CRUD production readiness | **65%** | Core CRUD is largely implemented, but mock-only actions and unverified live workflows prevent a production-ready claim. |

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
| Collaborators | List, invite, delete | Real lifecycle CRUD |
| Support tickets | List, create, update status | Partial CRUD — reply thread is not confirmed as a dedicated backend resource |
| Social media posts | List, create, detail, dispatch, reactions/comments | Real workflow CRUD; external Facebook result still needs live verification |
| Continuity grants | List, create, delete | Real CRUD |
| Masterlist imports | List, preview, detail, confirm, delete | Real workflow CRUD |

## Partial or Missing CRUD

| Priority | Area | Finding | Required work |
| --- | --- | --- |
| P0 | Support ticket replies | `Developer/Support.vue` contains mock reply behavior; visible replies are not proven to persist through a dedicated API. | Add a reply model/API or extend the existing ticket update contract, then replace mock mutation UI. |
| P0 | Form response detail | `FormResponsesTab.vue` displays “Detailed response view coming soon.” | Connect the existing `GET /forms/{id}/responses/{rid}` endpoint to an accessible detail dialog. |
| P1 | Security findings/memory | Security pages import static data from `constants/mockAdmin.ts`. | Define/verify list/detail APIs for findings and security-memory records, then replace static imports. |
| P1 | Developer-page mock branches | FAQs, Terms, Database, Audit, Support, Collaborators, RBAC, and Changelog preserve `isMockMode` paths. | Convert each page to fail visibly when its real API fails; restrict mock mode to explicit demos only. |
| P1 | Global mock transport | `api/client.ts` dynamically routes requests to `mock/handlers.ts` when `VITE_USE_MOCK=true` or no API base is available. | Make real API configuration mandatory outside an explicitly marked demo build; then retire unused mock handlers incrementally. |
| P2 | Changelog fallback | Local mock changelog JSON remains. | Keep only if an intentional offline demo is required; otherwise use the backend GitHub integration/error state. |
| P2 | Mock copy/locales | Some localized strings describe non-persistent mock actions. | Remove once the corresponding persistent actions are shipped. |

## Mock Inventory

| Location | Purpose | Removal condition |
| --- | --- | --- |
| `frontend/src/mock/handlers.ts` | Fake API responses for auth, batches, grantees, documents, database, terms, FAQs, social posts, dashboard, and more | Every consuming screen works against Laravel and demo mode is formally retired or isolated |
| `frontend/src/mock/data.ts` and `vercel/mocks` exports | Fake records | No pages import them in production builds |
| `frontend/src/constants/mockAdmin.ts` | Static security/admin findings | Security APIs and UI are live |
| `frontend/src/mock/changelogs.json` | Changelog fallback | Real backend integration and offline policy are agreed |
| `CaptchaModalMock.vue` | Mock captcha component | Confirm whether it is still routed; remove only after no imports remain |

## Recommended Vertical Slices

1. **Support replies (P0):** database model/migration, authorized API, frontend mutation, optimistic update, Laravel feature tests.
2. **Form response detail (P0):** consume the existing endpoint in a dialog, add loading/error/empty states, type-check and browser test.
3. **Security findings (P1):** create or validate API contract, replace static mock import, add RBAC tests.
4. **Developer mock retirement (P1):** one page at a time; remove fallback only after its real API has tests and an error state.
5. **Global mock mode decision (P1):** keep it as an explicitly labeled demo build or remove it from production configuration; never silently fall back to fake data.

## Acceptance Criteria for Completion

- [ ] No reachable production route shows a mock-only action or “coming soon” placeholder.
- [ ] Each CRUD screen uses a typed Laravel API client and has loading, error, empty, and success states.
- [ ] Create/update/delete operations have backend feature tests and permission checks.
- [ ] `VITE_USE_MOCK` is disabled for every non-demo build and API failures never return fake records.
- [ ] Browser verification confirms persistence after refresh for each converted module.

