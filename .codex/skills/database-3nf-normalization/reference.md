# UniFAST TES — Domain Reference for 3NF Work

Companion to `SKILL.md`. Concrete clusters and example violations/fixes. Inventory from `backend/app/Models` and `backend/database/migrations` may drift — always re-read migrations.

## Entity map (canonical ownership)

```
users ──< user_role >── roles ──< role_permission >── permissions
  │
  └── grantees ── batch
        ├── kyc_profiles (1:1)
        ├── grantee_identity_profiles (1:1)  // face descriptors, onboarding photos refs
        ├── academic_records (1:1)
        │     └── academic_semesters
        │           └── academic_courses
        ├── document_submissions (vault slots / requirement docs)
        │     └── requirement_identity_checks
        ├── submission_pipeline_results (OCR / risk / pipeline outputs)
        └── identityChecks (as declared on Grantee)

batches ── masterlist_imports ── masterlist_rows
batches ── billing_reports ── billing_report_items

academic_programs + policy_settings  → eligibility / pass-grade rules (source of truth)
audit_logs, support_tickets, faqs, terms → orthogonal; normalize only if reviewing those areas
```

## Cluster notes

### Grantees / masterlist

- **Source of truth for enrolled TES student in a batch:** `grantees` (linked to `users` when activated).
- **Import staging:** `masterlist_rows` may hold raw import shape; do not permanently duplicate editable grantee PII in multiple live tables without FK.
- **2NF smell:** batch metadata (`batch_name`, window dates) on every masterlist/grantee row → belongs on `batches`.

### Batches

- Window status may be **computed** (`Batch::computedWindowStatus`) — prefer derivation over storing redundant status unless cached with clear refresh rules.
- Notifications (`batch_notifications`) are children of batch, not columns on grantee.

### Document submissions / vault

- One submission row per requirement slot (or explicit versioning policy) — avoid stuffing multiple requirement files into one JSON blob if staff query by type/status.
- Pipeline/OCR: opaque payload JSON on `submission_pipeline_results` is often **intentional**; extracted **queryable** fields (GPA, units, risk score) should be real columns or child tables once used in filters/reports.

### Identity

- `grantee_identity_profiles` / KYC: photos and face descriptors are opaque or encrypted — denormalized blobs OK.
- Do not copy national ID / name onto every `document_submissions` or check row; FK to grantee/profile instead.
- Audit snapshots of “what was verified” at submit time are OK as immutable copies — label them as snapshots.

### Eligibility / programs / pass grades

- **3NF smell:** storing `program_name`, `pass_grade`, or policy thresholds on academic records or submissions so they drift from `academic_programs` / `policy_settings`.
- Prefer FK to `academic_programs` and read current policy for live eligibility; store a **snapshot** only when the decision must be historically frozen.

### Academics (records → semesters → courses)

- Course line items are classic **1NF**: rows in `academic_courses` (or equivalent), not `courses: [{...}]` JSON when reports filter by course code/grade.
- Semester aggregates (units, GWA) may be cached on semester/record if recomputed from courses — document the cache.

## Example findings (illustrative)

| Table | Violation | NF | Proposed split | Risk |
|-------|-----------|----|----------------|------|
| `document_submissions` | Embedded `grantee_full_name` | 3NF | Drop; join `grantees`/`users` | List API must eager-load |
| `academic_records` | `pass_grade` + `program_code` text | 3NF | FK `academic_program_id`; snapshot column only if historical | Eligibility tests, seeders |
| `submission_pipeline_results` | Queryable course array in JSON | 1NF | `pipeline_courses` child table **or** keep JSON if never queried | Backfill + dual-read |
| `masterlist_rows` | `batch_label` with composite import key | 2NF | Use `batch_id` FK only | Import UI display |

## Acceptable denormalization checklist

- [ ] OCR/debug JSON — not used in WHERE/JOIN
- [ ] Encrypted face descriptor vectors
- [ ] Audit log `old_values` / `new_values` snapshots
- [ ] Billing report line **frozen** amounts after generation
- [ ] Cached counts updated only by pipeline job

## Related skills

- Schema change safety ↔ `deprecation-and-migration`, `incremental-implementation`
- API contract after splits ↔ `api-and-interface-design`
- PII / uploads ↔ `security-and-hardening`
