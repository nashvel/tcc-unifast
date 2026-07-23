## Goal

1. Reframe the **admin** role as monitor-only (audit, security, users, reports, announcements — read/oversight), and give **staff** access to those same monitoring menus in addition to their ops menus.
2. Add a **Support Tickets** channel so staff can raise tickets/bug reports to admins, and both can converse on them.

## 1. Role behavior (no schema change)

Roles today: `admin`, `staff`, `head`, `student` (from `app_role` enum + `is_staff`).

Rules going forward:
- **admin**: read-only across monitoring surfaces + full access to Support Tickets (respond/close). No batch/grantee/document mutations.
- **staff / head**: full ops access AND full read access to the same monitoring menus admins see. Can open/reply to their own tickets.
- **student**: unchanged.

Implementation:
- Add a small `useRole()` helper (reads `user_roles` for current user, cached).
- In `src/routes/app.tsx` sidebar, show every current admin menu to both `admin` and `staff/head`.
- Add per-page write gates: in grantees/documents/batches/eligibility/masterlist, hide primary action buttons (Approve, New batch, Process import, Activate, etc.) when `role === 'admin'`. Read/search/export stays.
- Add "Read-only mode" pill in header when admin.

## 2. Support Tickets feature

New menu item **Support** at `/app/support` (list) and `/app/support/$id` (thread).

### Schema (one migration)

- `support_tickets`: `subject`, `body`, `category` (`bug` | `question` | `request`), `priority` (`low`|`normal`|`high`), `status` (`open`|`in_progress`|`resolved`|`closed`), `created_by` (uuid), `assigned_to` (uuid, nullable).
- `support_ticket_messages`: `ticket_id`, `author_id`, `body`, `is_internal` (bool).
- RLS:
  - Staff/head: SELECT/INSERT/UPDATE own tickets (`created_by = auth.uid()`); INSERT messages on own tickets.
  - Admin: SELECT all, UPDATE status/assignment, INSERT messages on any ticket.
  - Students: no access.
- GRANT SELECT/INSERT/UPDATE to `authenticated`; ALL to `service_role`.
- `updated_at` trigger on both.

### UI

- `app.support.index.tsx`: filter bar (search + status + category + priority), table (Subject · Category · Priority · Status · Opened · Last activity · Assignee).
- `app.support.$id.tsx`: ticket header + status/assignee controls (admin only) + message thread + reply composer.
- `app.support.new.tsx`: staff-side create form (subject, category, priority, body).
- Sidebar entry for both roles; students don't see it.

## Technical notes

- Reuse `PageHeader`, `DataTable`, `StatusBadge`, `SearchInput`, `Selectish`, `TextInput`, `Btn`.
- Hooks: `useSupportTickets`, `useSupportTicket(id)`, `useCreateTicket`, `useAddMessage`, `useUpdateTicketStatus` in `src/hooks/queries.ts` using the browser `supabase` client (RLS enforces access).
- `useRole()` in `src/hooks/use-role.ts` — queries `user_roles` once, cached via TanStack Query.
- No breaking changes to existing tables.

## Out of scope

- Email notifications on new ticket / new reply (mention as follow-up).
- File attachments on tickets (follow-up).
- Ticket SLA metrics (follow-up).