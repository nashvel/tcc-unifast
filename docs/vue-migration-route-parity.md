# React to Vue Route Parity

Status values: `verified`, `placeholder`, `missing`.

## Authentication

| Route | Vue target | Status |
|---|---|---|
| `/login` | `auth/Login.vue` | verified |
| `/forgot-password` | `auth/ForgotPassword.vue` | verified |
| `/activate` | `auth/Activate.vue` | verified |
| `/activate-success` | `auth/ActivateSuccess.vue` | verified |
| `/locked` | `auth/Locked.vue` | verified |

## Administrator / Staff

| Route family | Status |
|---|---|
| Dashboard | verified |
| Announcements list | verified |
| Announcement create/edit/logs | verified |
| Reports list | verified |
| Report generate/preview | verified |
| Audit | verified |
| Security findings | verified |
| Security memory | verified |
| Support list | verified |
| Support create/detail | verified |
| Users / permissions | verified |
| Settings | verified |
| Masterlist | verified |
| Batches list/detail | verified |
| Grantees list/detail | verified |
| Documents list/detail | verified |
| Academic list/detail | verified |
| Eligibility list/detail | verified |
| File Manager | verified |
| Appearance | verified |
| Style Guide | verified |

## Student Portal

| Route | Status |
|---|---|
| Dashboard | verified |
| Profile | verified |
| Required Documents | verified |
| Upload Requirements | verified |
| Submission Status | verified |
| Announcements | verified |
| Notifications | verified |
| Settings | verified |

## Acceptance checklist per route

- Dedicated Vue route and domain page
- React-equivalent mock data
- Desktop and mobile layout parity
- Loading, empty, and error states
- Dialogs, drawers, filters, and actions
- Correct breadcrumbs and navigation state
- Correct role and permission behavior
- Type check, production build, and route test
