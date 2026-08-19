# CHED Masterlist Format Reference

This document describes the expected column layout of CHED-issued masterlist files
(DOCX and PDF) so that the `masterlist_extract.py` alias map can be kept accurate.

**Status: SKELETON — fill in the exact column names when a real CHED file arrives.**

---

## How the Extractor Works

`backend/python/masterlist_extract.py` uses adaptive table detection:

1. Opens the file (DOCX via `python-docx`, PDF via `PyMuPDF`)
2. Finds every table in the document
3. Scores each table by how many headers match the alias map below
4. Picks the highest-scoring table
5. Maps raw column names → canonical field names
6. Returns the result as JSON (consumed by Laravel)

The result JSON includes:
- `table_index` — which table (0-based) was selected
- `raw_headers` — the actual column names found in the file
- `matched_columns` — what each raw header mapped to
- `unmatched_headers` — columns that were ignored (e.g. Region, School)

---

## Current Alias Map

These are the raw column names the extractor currently recognizes.
Add new rows when CHED uses a different spelling.

| Raw header (case-insensitive) | Maps to |
|---|---|
| `student id`, `id number`, `id no`, `id no.` | `student_id` |
| `tes id`, `tes id no`, `tes id number` | `student_id` |
| `award no`, `award no.`, `award number`, `awno` | `award_number` |
| `student no`, `student no.`, `student number` | `student_number` |
| `school id`, `school id number`, `school id no` | `student_number` |
| `name`, `full name`, `student name`, `grantee name`, `grantee` | `full_name` |
| `last name`, `lastname`, `surname`, `family name` | `last_name` |
| `given name`, `given names`, `first name`, `firstname` | `given_name` |
| `middle name`, `middlename`, `middle initial` | `middle_name` |
| `ext`, `extension`, `extension name`, `suffix` | `name_ext` |
| `email`, `email address`, `student email`, `e-mail` | `email` |
| `program`, `course`, `degree program`, `degree`, `academic program` | `program` |
| `year`, `year level`, `level`, `year of study` | `year_level` |

---

## Name Assembly

If CHED sends separate `Last Name`, `First Name`, `Middle Name` columns instead of
a single `Name` column, the extractor automatically assembles:

```
DELA CRUZ, Juan Andres
```

Format: `{LAST_NAME}, {GIVEN_NAME} {MIDDLE_NAME} {EXT}`

---

## Year Level Normalization

The extractor normalizes year level values to a single digit:

| Raw value | Normalized |
|---|---|
| `1`, `1st`, `Year 1`, `First` | `1` |
| `2`, `2nd`, `Year 2`, `Second` | `2` |
| `3`, `3rd`, `Year 3`, `Third` | `3` |
| `4`, `4th`, `Year 4`, `Fourth` | `4` |

---

## ⚠️ FILL IN WHEN REAL CHED FILE ARRIVES

When TCC receives an actual CHED masterlist, do the following:

1. Upload it through the Masterlist Import page
2. Look at the **Detection Info** card — it shows:
   - Which table was selected
   - All raw column headers found
   - Which ones matched and which were ignored
3. If a required column was missed (e.g. `student_id` shows as "unmatched"):
   - Open `backend/python/masterlist_extract.py`
   - Find the `_MASTERLIST_ALIASES` dictionary
   - Add the exact column name as a new key:
     ```python
     "tes award no": "student_id",  # ← new alias
     ```
4. Update this file with the confirmed column names from the actual CHED document

---

## Known CHED Formats (to be confirmed)

| Format | Source | Confirmed? |
|---|---|---|
| DOCX with single table | CHED Regional Office | ❌ Not yet |
| PDF (text-layer) | CHED HEIDI portal | ❌ Not yet |
| PDF (scanned image) | Legacy paper-based | ❌ Not yet — will fail |

> [!NOTE]
> Scanned PDF masterlists (images, no text layer) **cannot be extracted** by this tool.
> Staff must manually convert to XLSX/CSV in that case, or request a digital copy from CHED.

---

## Adding a Completely New Format

If CHED sends a spreadsheet format not yet covered (e.g., ODS, XLS with unusual layout):

1. Confirm the file format and column names
2. Add aliases to `_MASTERLIST_ALIASES` in `masterlist_extract.py`
3. Update the MIME validation in `MasterlistImportController.php`
4. Update the file input `accept` attribute in `frontend/src/modules/masterlist/Index.vue`
5. Update this document
