## TCC UniFAST TES Grantee Management — React Prototype

A clickable, mock-data prototype of the TES Grantee Management & Document Validation System built on the project's existing React + TanStack Start + Tailwind + shadcn stack. No backend, all data lives in `src/data/*` and Zustand stores.

### Stack mapping (Vue → React equivalents)
- Vue 3 → React 19 (already installed)
- Vue Router → TanStack Router (file-based, already installed)
- Pinia → Zustand (lightweight, Pinia-equivalent ergonomics)
- `@tabler/icons-vue` → `@tabler/icons-react`
- Tailwind → Tailwind v4 (already installed); design tokens go in `src/styles.css` `@theme` block (the project's convention) instead of a separate `variables.css`, but every token you listed will be defined.

### Design system
Minimal, dense, enterprise. White/light-gray surfaces, thin borders, compact spacing, subtle hover, small shadows. All Tabler icons. Tokens: `--primary/-hover/-soft`, `--success/-soft`, `--warning/-soft`, `--danger/-soft`, `--info/-soft`, `--bg`, `--surface`, `--surface-muted`, `--border/-strong`, `--text/-muted/-soft`, `--sidebar-bg/-active/-active-text`, `--input-bg`, `--ring`. Wired into Tailwind utilities via `@theme inline`.

### Folder structure
```text
src/
  routes/                        # TanStack file-based routes
    _auth.tsx                    # AuthLayout (centered card)
    _auth.login.tsx
    _auth.forgot-password.tsx
    _auth.activate.tsx
    _auth.activate.success.tsx
    _auth.locked.tsx
    _app.tsx                     # AppLayout (sidebar + topbar, admin/staff)
    _app.index.tsx               # Dashboard
    _app.masterlist.*            # upload, preview
    _app.batches.*               # list, $id
    _app.grantees.*              # list, $id
    _app.documents.*             # queue, $id
    _app.academic.*              # list, $id
    _app.eligibility.*           # list, $id
    _app.announcements.*         # list, new, $id.edit, logs
    _app.reports.*               # index, generate, preview
    _app.audit.tsx
    _app.users.*                 # list, roles, permissions
    _app.settings.tsx
    _student.tsx                 # StudentLayout
    _student.index.tsx           # student dashboard
    _student.profile / documents / upload / submissions / announcements / notifications
  components/
    layout/   AppSidebar, AppTopbar, StudentSidebar, PageHeader
    ui/       (existing shadcn) + StatusBadge, StatCard, DataTable, EmptyState,
              LoadingState, ConfirmModal, DetailDrawer, SearchInput,
              FilterDropdown, FileUpload, FormField, ChartCard, ActivityTimeline
  data/       mockMasterlist, mockGrantees, mockBatches, mockDocuments,
              mockAcademicRecords, mockAnnouncements, mockAuditLogs, mockUsers,
              mockNotifications
  stores/     authStore, masterlistStore, granteeStore, batchStore,
              documentStore, notificationStore  (Zustand)
  services/   thin mock async wrappers around data/
```

### Auth & role gating
- `authStore` holds `{ user, role }` with roles: `admin | staff | head | student`.
- `_app` layout `beforeLoad` redirects to `/login` if no admin/staff/head session.
- `_student` layout `beforeLoad` redirects to `/login` if not a student.
- Activation flow: student enters student#, birthdate, email/contact → checked vs `mockMasterlist` → password setup → mark account active in store → success page. No open registration.
- Demo login buttons on `/login` for each role so the prototype is instantly explorable.

### Modules (all pages from the brief)
1. Auth (login, forgot password, activate, activation success, locked)
2. Admin dashboard — 8 stat cards, recent activity, recent announcements, batch progress, validation summary, CSS/SVG mini charts
3. Masterlist & batches — upload, import preview with duplicate/invalid warnings, batch CRUD, generated account status badges
4. Grantee management — list w/ search + 5 filters + pagination, profile w/ personal/academic/account/requirements/validation history/notes/status controls
5. Student portal — dashboard, profile, required docs checklist, upload, submission tracker, history, announcements, notifications
6. Document validation — submission UI, staff queue, detail page with OCR/EXIF/face/risk panels and decision controls
7. Academic records — list, detail with semester records, GWA, failed/dropped subjects, retention result
8. Eligibility — list, evaluation detail, criteria checklist, status update modal, export buttons
9. Announcements & notifications — list, create/edit, audience selector, channel badges, status badges, notification logs
10. Reports — dashboard, generator, preview with PDF/Excel UI-only buttons
11. Audit trail — table with filters (user/action/module/date), detail drawer with before/after, IP, timestamp
12. Users & access — user list, role mgmt, permission matrix grouped by module, MFA badge, activate/deactivate, reset password UI

### Behavior
Loading / empty / error / confirmation states throughout. Dense tables, accessible forms with labels + helper text, mobile responsive (sidebar collapses on mobile). Realistic Filipino-context sample data (student names, universities, batches, etc.).

### Out of scope
No real backend, no real OCR/face verification, no real PDF/Excel export, no email/SMS sending — all are visual placeholders as specified.

### Build sequence
1. Install `@tabler/icons-react` and `zustand`
2. Tokens in `src/styles.css` + base utilities
3. Mock data + Zustand stores + mock service layer
4. Layouts (Auth, App, Student) + Sidebar + Topbar
5. Reusable components
6. Auth pages (incl. activation flow)
7. Admin modules in the order listed above
8. Student portal
9. Polish pass: empty/loading states, responsive checks, replace placeholder index

Shall I proceed?
