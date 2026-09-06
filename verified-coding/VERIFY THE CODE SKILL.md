---
name: verified-coding
description: Enforces a plan-first, verify-before-claiming-done workflow for any coding task — for new features, requires writing and interrogating a plan (including DB normalization for any schema change) before implementation starts, so a half-built happy path never gets reported as a finished feature. Then, before saying anything is "done," "implemented," or "should work," requires evidence that every layer (frontend, backend route, controller, validation, auth, DB) is actually wired together, plus a security checklist. Use this for every coding task involving new features, full-stack changes, API integrations, CRUD endpoints, forms, auth-related code, database schema changes, or any change spanning more than one file/layer. Especially trigger this before writing any implementation plan, completion summary, status update, or "here's what I did" message.
---

# Verified Coding

## Why this exists

The failure mode this skill blocks: writing a plausible-looking piece of code (usually the
frontend half of a feature, or a happy-path implementation), then reporting it as complete
without checking that it is actually connected to the rest of the system or safe to expose.
Common concrete failures:

- A button/form is built and calls an API endpoint that was never registered, or calls the
  wrong method/path, so nothing happens when a real user clicks it.
- A feature is implemented only in the frontend (e.g. client-side filtering, client-side
  permission checks) with no backend enforcement — it "works" in the demo and is trivially
  bypassable or does nothing server-side.
- Validation, authorization, or sanitization is skipped because the happy path didn't need it
  to "look done."
- The response says "this should work now" without the code having actually been traced or run.

**The rule: a task is not done until you can point to evidence for every link in the chain.
"I wrote code that should do X" is not evidence. "I traced X through file A line N, file B
line M, and confirmed the route/table/field names match" is evidence.**

## Step 0 — Plan and interrogate the feature before writing any code

For any *new* feature (not a one-line bugfix), implementation does not start until a short plan
exists and has survived active interrogation. This step exists because "looks complete" and
"is complete" are different things — a plan built without pushback tends to quietly cover only
the happy path.

1. **Write the plan first**, covering:
   - The entities/data involved and how they relate — new tables, new columns, new relationships.
   - Every user flow the feature needs, not just the main one (create AND edit AND delete AND
     the empty/first-time state, not just "create").
   - Who is allowed to do what (roles/ownership) — this feeds directly into the Step 3 security
     checklist later, so get it right here.
   - Explicit edge cases: empty states, duplicate submissions, concurrent edits, invalid input,
     what happens when a related record is deleted, behavior at scale (pagination/large data).

2. **If the plan introduces or changes a database table, normalize it before treating the
   schema as final.** If a `db-normalization` skill (or equivalent) is available in this
   environment, use it — run the table/column list through 1NF–3NF checks, flag any
   denormalization as a deliberate, named tradeoff rather than an oversight, and check
   foreign-key/pivot design for any many-to-many relationship. A schema fixed after real data
   exists is far more expensive than one fixed on paper — don't start migrating before this pass.

3. **Grill the plan** — actively try to break it before building it, on paper, and write down
   the answers:
   - Is this the whole feature, or does it only cover the happy path? If anything is
     deliberately deferred, name it explicitly rather than letting it silently look "done."
   - What's the failure mode if the request is malformed, unauthorized, or duplicated?
   - Does this duplicate data or logic that already exists elsewhere in the codebase?
   - What does the UI look like with zero items / one item / a huge number of items?
   - Does anything here assume a field, table, or endpoint exists that hasn't actually been
     confirmed yet (see Step 1)?

4. **Only move to implementation once the plan has real answers to the above** — not a plan
   that raises these questions and skips past them. For anything with real user impact (touches
   money, permissions, or is hard to undo), surface the plan to the user for a quick
   confirmation before writing code, rather than assuming silence means agreement.

Skipping this step is exactly how "half a feature reported as a whole feature" happens: Steps
1–3 below catch whether what you built *works*, but only this step catches whether what you
built was the *right, complete* thing to build in the first place.

## Step 1 — Trace the technical chain

For any feature that touches more than one layer, write out (briefly, in your own reasoning,
not necessarily shown to the user) the full chain it needs:

```
UI event → frontend API call (method + exact path + payload shape)
  → backend route registration (does this route exist? exact method + path match?)
  → controller/handler
  → validation (are inputs actually validated, not just typed in TS/PropTypes?)
  → authorization (can THIS user act on THIS resource — not just "is logged in")
  → model/service/query
  → database (does the column/table/relationship exist as assumed?)
  → response shape
  → frontend consumes response (does the field name match what backend actually returns?)
```

If you don't know an answer (e.g. "does this route exist"), that's a signal to go check the
codebase — grep for it — not to assume it and move on.

## Step 2 — After writing code: verify, don't assume

Before reporting anything as finished, actually check it. For a modular Laravel + React /
Inertia stack specifically (adjust for other stacks the same way):

- **Route exists and matches**: grep the routes file(s) for the exact path and HTTP verb the
  frontend calls. A `POST` from the frontend calling a route only registered as `GET` is a bug
  that "looks fine" in code review but fails at runtime.
- **Controller method exists and is wired to that route** — not just present in the file, but
  actually bound to the route you found above.
- **Field names match end-to-end**: the JSON key the frontend reads (`response.data.foo`) must
  match what the backend actually serializes (check the Resource/serializer/`toArray()`, not
  just the model's DB column name — these silently drift).
- **Migration/schema actually has the column or table** you're querying — don't assume a field
  exists because it "should." Check the migration files or schema.
- **If you claim you "tested" something, you actually ran it** (a test suite, a manual request,
  a console check) — never write "tested and working" when what happened is "this looks right
  to me."

If any link in the chain can't be confirmed, say so explicitly instead of writing a confident
summary: *"The frontend calls X — I haven't confirmed a matching backend route exists; you'll
want to check `routes/api.php` before relying on this."* An honest gap flagged is far more
useful than a confident false "done."

## Step 3 — Security checklist (run this before calling anything complete)

Weak/fast models tend to skip this because the happy path doesn't need it. Go through this
list explicitly for any endpoint or feature that accepts input, touches auth, or exposes data:

- [ ] **Authorization is enforced server-side**, not just hidden in the UI (e.g. a hidden
      button is not access control — the backend must re-check permissions on every request).
- [ ] **Ownership / IDOR check**: if a request includes an ID (order id, user id, resource id),
      the backend confirms the *current* user is allowed to access *that specific* record —
      not just that they're logged in.
- [ ] **Input validation** exists on the backend for every field (type, length, format) — never
      rely on frontend `required`/type attributes alone, since the API can be called directly.
- [ ] **Mass assignment is restricted** (e.g. Laravel `$fillable`/`$guarded`) so a request can't
      set fields it shouldn't (like `is_admin`, `role`, `price`) by adding extra keys to the payload.
- [ ] **SQL injection**: only parameterized queries / query builder / ORM — no string-concatenated
      raw SQL with user input.
- [ ] **XSS**: any user-supplied content rendered in the UI is escaped, not injected as raw HTML.
- [ ] **CSRF protection** is in place for state-changing requests where the framework expects it.
- [ ] **Secrets** (API keys, tokens, credentials) are not hardcoded or committed — pulled from
      env/config.
- [ ] **File uploads** (if any) validate type/size and are not saved to a publicly executable path.
- [ ] **Error responses** don't leak stack traces, queries, or internal paths to the client.
- [ ] **Rate limiting / throttling** exists on sensitive or expensive endpoints (auth, search,
      anything that hits an external API or does heavy DB work).

If a box can't be checked, that's a finding to report, not something to silently skip.

## Step 4 — How to report the result

End every non-trivial coding task with a short, honest status, not just a demo-ready summary:

- **What the plan covered vs. deferred** (Step 0) — if any edge case or flow was deliberately
  left out, say so by name instead of letting the feature look fully finished.
- **DB normalization result**, if a schema changed — normal form reached, and any intentional
  denormalization called out.
- **What's wired and verified** (with the specific evidence — file/route/field you checked).
- **What's implemented but not yet verified** (be explicit this is unverified).
- **What's missing** (e.g. "backend enforcement not added yet — current authorization is
  frontend-only and should not be trusted").
- **Security checklist result** — call out anything that didn't get a check, don't just omit it.

This format is intentionally more verbose than "done!" — that's the point. A checklist a
weaker model can literally fill in beats a fluent paragraph that skipped the work.

## Portable version (for tools that don't support Claude Skills, e.g. Gemini)

Skills are a Claude-specific mechanism. To get the same discipline out of another model
(Gemini or otherwise), paste the following as a standing system instruction / custom
instruction for that tool:

> Before saying any coding task is done, you must: (1) state the full chain the feature
> touches — frontend call, backend route, controller, validation, auth, database — and for
> each link, either point to the exact file/line that proves it exists and matches, or state
> plainly that you have not verified it; (2) run through a security checklist covering
> server-side authorization, ownership/IDOR checks, input validation, mass-assignment
> protection, SQL injection, XSS, CSRF, hardcoded secrets, and error-message leakage, and
> report any item you didn't check; (3) never write "this should work" or "tested and
> working" unless you actually traced or ran it. If you are uncertain about any part of the
> chain, say so explicitly instead of guessing.
