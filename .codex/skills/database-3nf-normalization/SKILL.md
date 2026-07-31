---
name: database-3nf-normalization
description: Normalizes relational schemas to 3NF for UniFAST TES (Laravel + MySQL). Use when normalizing a database, designing schema for 3NF, refactoring tables to remove redundancy, planning Laravel migrations for normalization, or reviewing the UniFAST TES data model (grantees, batches, submissions, identity, eligibility, programs).
---

# Database 3NF Normalization (UniFAST TES)

## Overview

Practical workflow to inventory, analyze, and safely migrate the UniFAST TES MySQL schema toward 3NF. The agent already knows normalization theory — apply it to **this** Laravel codebase, prefer additive migrations, and treat intentional denormalization as an explicit decision.

**Stack:** Laravel backend under `backend/`, MySQL for the app (not SQLite). On this Windows machine use `C:\php84\php.exe` for artisan/tests against MySQL.

Domain table notes: see [reference.md](reference.md).

## When to Use

- Normalize / review schema for 3NF
- Refactor tables to remove redundancy or update anomalies
- Design new TES entities (batches, vault docs, identity, eligibility, programs)
- Plan Laravel migrations that split or re-key tables
- Decide whether a JSON/cast column is acceptable vs a child table

## Do Not Use For

- Query-only tuning or indexing without schema shape changes
- NoSQL / document-store design
- Destructive “drop and rewrite” without explicit user approval

## Workflow

### 1. Inventory current schema

Read before proposing splits:

| Source | Path |
|--------|------|
| Migrations | `backend/database/migrations/` |
| Models | `backend/app/Models/` |
| Factories / seeders | `backend/database/factories/`, `backend/database/seeders/` |
| Feature/unit tests | `backend/tests/` |

Checklist:

- [ ] List tables and PKs from migrations (ignore cache/jobs/tokens unless relevant)
- [ ] Note FKs, unique indexes, pivots (`role_permission`, `user_role`)
- [ ] Note JSON/`casts()` columns and encrypted/blob fields
- [ ] Map Eloquent relationships already declared on models
- [ ] Confirm app DB is MySQL (`backend/config/database.php` / `.env`) — do not treat SQLite phpunit defaults as the production shape

### 2. Map UniFAST TES entities

Core clusters (details in [reference.md](reference.md)):

| Cluster | Typical tables / models |
|---------|-------------------------|
| Auth / RBAC | `users`, `roles`, `permissions`, pivots |
| Masterlist / grantees | `batches`, `masterlist_imports`, `masterlist_rows`, `grantees` |
| Documents / vault | `document_submissions`, requirement vault fields, pipeline results |
| Identity | `grantee_identity_profiles`, `kyc_profiles`, identity checks / face descriptors |
| Academics / eligibility | `academic_programs`, `academic_records`, semesters, courses, policy settings |
| Ops | `audit_logs`, billing reports, support tickets, FAQs/terms |

For each entity ask: **one fact, one place** (or documented cache/snapshot).

### 3. Apply 1NF → 2NF → 3NF

| Form | Rule (short) | UniFAST-flavored violation |
|------|--------------|----------------------------|
| **1NF** | Atomic columns; no repeating groups; has a PK | Course rows as a JSON array that must be filtered/sorted in SQL; `course1`/`course2` columns |
| **2NF** | Non-key attrs depend on **whole** composite key | `(batch_id, student_no)` key carrying `batch_name` (depends only on `batch_id`) |
| **3NF** | No transitive deps between non-keys | Submission row stores `program_name` + `pass_grade` copied from program (drifts when policy changes) |

Process:

1. Fix 1NF (child tables or constrained JSON — see Laravel notes).
2. Split partial dependencies (2NF).
3. Move transitive attributes to their owning entity (3NF).
4. Re-check uniqueness and FKs after each split.

### 4. Intentional denormalization vs true 3NF

**Keep denormalized only when labeled and maintained:**

| Pattern | OK if… | Not OK if… |
|---------|--------|------------|
| Cached OCR / pipeline counts | Derived, recomputed by job, never source of truth | Staff edit the cache instead of source rows |
| Encrypted identity / face payloads | Opaque blob; not queried by subfields | App filters on fields inside the blob |
| Audit / submission snapshots | Immutable historical copy for compliance | Snapshot is updated when live program rules change |
| Display name on list row | Explicit cache with refresh path | Name duplicated across tables with no FK to grantee |

Always document: *source of truth table*, *who writes the cache*, *invalidation*.

### 5. Migration plan (never silent destruction)

Order:

1. **Additive** migration — new tables/columns/FKs/unique indexes  
2. **Backfill** command or migration step (idempotent)  
3. **Dual-write** (and dual-read if needed) in app code  
4. **Cutover** reads to normalized shape  
5. **Drop** old columns/tables — **only with explicit user OK**

Rules:

- Prefer new migrations over editing old ones that may already be applied
- Use transactions where MySQL engine/FK rules allow; large backfills may need chunked artisan commands
- Never `dropColumn` / drop table in the same change set as the first proposal unless the user approved destruction
- Verify on MySQL with `C:\php84\php.exe artisan migrate` (and targeted tests), not “tests pass on SQLite therefore done”

### 6. Update Laravel surface

After schema change:

- [ ] Eloquent models: `$fillable`/`$guarded`, `casts()`, relationships
- [ ] Factories and seeders
- [ ] Controllers/services that assumed old columns
- [ ] Feature/unit tests covering the split
- [ ] API types if the frontend contract changes (`frontend/src/api/types.ts`)

### 7. Verify

```text
C:\php84\php.exe artisan migrate:status
C:\php84\php.exe artisan migrate
C:\php84\php.exe artisan test --filter=<RelevantTest>
```

Spot-check MySQL: FKs exist, unique indexes enforce business keys (e.g. grantee per batch + student id), orphan counts = 0.

## Anti-patterns (flag these)

- Storing **repeating, queryable** course/OCR line items only as JSON
- Duplicating **grantee name / student no** across tables without FK to `grantees`
- Embedding **program pass grade** copies that can drift from `academic_programs` / policy settings
- **God tables** mixing masterlist, vault status, OCR payload, identity, and billing in one wide row
- Composite natural keys without unique indexes, relying on app checks alone
- “Fixing” 3NF by dropping columns that still feed production dual-read paths

## Laravel-specific guidance

| Concern | Prefer |
|---------|--------|
| Keys | `foreignId()->constrained()` (or explicit `foreign()`); cascade policy deliberate |
| Business uniqueness | `unique(['batch_id', 'student_no'])` etc. matching TES rules |
| M:N | Pivot tables (`role_permission`, `user_role` style) — not CSV in a column |
| JSON columns | Acceptable for opaque OCR payloads, face descriptor vectors, unstructured pipeline debug — **not** for rows you join/filter regularly |
| Casts | `array`/`encrypted:array`/`datetime` on the owning model; keep queryable attrs as real columns |
| Soft deletes | If used, define how uniques interact (`unique` + `deleted_at` strategy) |

## Output template (required)

Produce a findings table before writing migrations:

| Table | Violation | NF | Proposed split | Risk |
|-------|-----------|----|----------------|------|
| `example` | `pass_grade` depends on `program_id` | 3NF | Move to `academic_programs`; FK only on record | Dual-write during cutover; eligibility tests |

Then:

1. Short entity/relationship summary (bullet list)
2. Intentional denormalization list (keep / remove / document)
3. Ordered migration plan (additive → backfill → dual-write → cutover → drop*)
4. Model/test touch list
5. Explicit ask before any destructive step (*)

## Definition of done

- Findings table completed with NF labels
- Additive path defined; destruction gated on user OK
- Models/relationships/tests called out
- Verification commands use MySQL + `C:\php84\php.exe` on this machine
