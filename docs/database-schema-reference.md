# Database schema reference (normalize draft)

> **Incomplete reference for normalization planning.**  
> This is **not** the live source of truth. Prefer Laravel migrations / the running database for what is actually deployed. Field names and constraints here are a planning draft and may diverge from current tables.

**Roles:** `grantee`, `staff`, `admin`  
**Auth tokens:** `personal_access_tokens` is used for **Laravel Sanctum**.

---

## AUTH (3 tables)

### `users`

| Column | Notes |
| --- | --- |
| `id` | PK |
| `email` | UK |
| `role` | Values: grantee, staff, admin |
| `is_active` | |
| `email_verified_at` | |
| `created_at` | |
| `updated_at` | |

### `otp_codes`

| Column | Notes |
| --- | --- |
| `id` | PK |
| `user_id` | FK → users |
| `code` | |
| `expires_at` | |
| `used_at` | |
| `ip_address` | |
| `created_at` | |

### `personal_access_tokens`

| Column | Notes |
| --- | --- |
| `id` | PK |
| `tokenable_type` | Morph (Sanctum) |
| `tokenable_id` | FK (morph) |
| `token` | UK |
| `abilities` | |
| `last_used_at` | |
| `expires_at` | |
| `created_at` | |

Used for Laravel Sanctum authentication.

---

## GRANTEE (4 tables)

### `batches`

| Column | Notes |
| --- | --- |
| `id` | PK |
| `name` | UK |
| `academic_year` | |
| `semester` | |
| `submission_deadline` | |
| `is_active` | |
| `opened_at` | |
| `closed_at` | |
| `created_by` | FK → users |
| `created_at` | |

### `grantees`

| Column | Notes |
| --- | --- |
| `id` | PK |
| `user_id` | FK → users |
| `batch_id` | FK → batches |
| `student_id` | UK |
| `full_name` | |
| `program` | |
| `year_level` | |
| `status` | |
| `invite_token` | |
| `invite_expires_at` | |
| `invite_accepted_at` | |
| `face_descriptor` | |
| `created_at` | |
| `updated_at` | |

### `grantee_profiles`

| Column | Notes |
| --- | --- |
| `id` | PK |
| `grantee_id` | FK → grantees |
| `date_of_birth` | |
| `sex` | |
| `civil_status` | |
| `address` | |
| `contact_number` | |
| `guardian_name` | |
| `guardian_contact` | |
| `monthly_income` | |
| `listahanan_id` | |
| `is_pwd` | |
| `created_at` | |
| `updated_at` | |

### `gwa_history`

| Column | Notes |
| --- | --- |
| `id` | PK |
| `grantee_id` | FK → grantees |
| `batch_id` | FK → batches |
| `gwa_value` | |
| `failed_units` | |
| `dropped_units` | |
| `is_anomaly` | |
| `anomaly_score` | |
| `source` | |
| `created_at` | |

---

## DOCUMENTS (4 tables)

### `submissions`

| Column | Notes |
| --- | --- |
| `id` | PK |
| `grantee_id` | FK → grantees |
| `batch_id` | FK → batches |
| `status` | |
| `submitted_at` | |
| `capture_source` | |
| `capture_device` | |
| `ip_address` | |
| `created_at` | |
| `updated_at` | |

### `documents`

| Column | Notes |
| --- | --- |
| `id` | PK |
| `submission_id` | FK → submissions |
| `type` | e.g. school_id, course_history, grade_slip |
| `side` | |
| `file_path` | |
| `file_size_kb` | |
| `mime_type` | |
| `original_filename` | |
| `exif_data` | |
| `exif_flag` | |
| `qr_decoded` | |
| `qr_readable` | |
| `uploaded_at` | |

### `identity_checks`

| Column | Notes |
| --- | --- |
| `id` | PK |
| `submission_id` | FK → submissions |
| `grantee_id` | FK → grantees |
| `liveness_result` | |
| `liveness_challenge` | |
| `face_match_result` | |
| `euclidean_distance` | |
| `face_quality_score` | |
| `duplicate_face_found` | |
| `duplicate_grantee_id` | FK → grantees |
| `device_fingerprint` | |
| `ip_address` | |
| `performed_at` | |

### `ocr_results`

| Column | Notes |
| --- | --- |
| `id` | PK |
| `document_id` | FK → documents |
| `extracted_text` | |
| `extracted_name` | |
| `extracted_student_id` | |
| `extracted_gwa` | |
| `extracted_semester` | |
| `extracted_school_year` | |
| `name_match` | |
| `id_match` | |
| `semester_match` | |
| `gwa_computed_match` | |
| `institution_name_match` | |
| `ai_semantic_flags` | |
| `processed_at` | |

---

## EVALUATION (5 tables)

### `risk_scores`

| Column | Notes |
| --- | --- |
| `id` | PK |
| `submission_id` | FK → submissions |
| `grantee_id` | FK → grantees |
| `identity_score` | |
| `metadata_score` | |
| `name_id_score` | |
| `semester_score` | |
| `gwa_mismatch_score` | |
| `gwa_trend_score` | |
| `qr_score` | |
| `exif_score` | |
| `duplicate_face_score` | |
| `total_score` | |
| `risk_badge` | |
| `computed_at` | |

### `eligibility_checks`

| Column | Notes |
| --- | --- |
| `id` | PK |
| `submission_id` | FK → submissions |
| `grantee_id` | FK → grantees |
| `gwa_value` | |
| `gwa_threshold` | |
| `gwa_pass` | |
| `failed_count` | |
| `failed_threshold` | |
| `failed_pass` | |
| `dropped_count` | |
| `dropped_threshold` | |
| `dropped_pass` | |
| `overall_result` | |
| `checked_at` | |

### `staff_reviews`

| Column | Notes |
| --- | --- |
| `id` | PK |
| `submission_id` | FK → submissions |
| `reviewer_id` | FK → users |
| `decision` | |
| `rejection_reason` | |
| `id_checklist` | |
| `flag_reason` | |
| `reviewed_at` | |
| `ip_address` | |

### `policy_settings`

| Column | Notes |
| --- | --- |
| `id` | PK |
| `key` | |
| `value` | |
| `description` | |
| `updated_by` | FK → users |
| `updated_at` | |

### `account_disputes`

| Column | Notes |
| --- | --- |
| `id` | PK |
| `grantee_id` | FK → grantees |
| `reported_by` | FK → users |
| `resolved_by` | FK → users |
| `reason` | |
| `status` | |
| `resolution_notes` | |
| `reported_at` | |
| `resolved_at` | |

---

## BILLING (2 tables)

### `billing_reports`

| Column | Notes |
| --- | --- |
| `id` | PK |
| `batch_id` | FK → batches |
| `generated_by` | FK → users |
| `type` | |
| `total_grantees` | |
| `total_amount` | |
| `stipend_per_grantee` | |
| `file_path` | |
| `is_supplemental` | |
| `parent_report_id` | FK → billing_reports |
| `generated_at` | |

### `billing_report_items`

| Column | Notes |
| --- | --- |
| `id` | PK |
| `billing_report_id` | FK → billing_reports |
| `grantee_id` | FK → grantees |
| `full_name` | |
| `student_id` | |
| `program` | |
| `stipend_amount` | |
| `inclusion_status` | |
| `exclusion_reason` | |

---

## SYSTEM (7 tables)

### `notifications`

| Column | Notes |
| --- | --- |
| `id` | PK |
| `type` | |
| `notifiable_type` | Morph |
| `notifiable_id` | FK (morph) |
| `data` | |
| `read_at` | |
| `created_at` | |

### `mass_communications`

| Column | Notes |
| --- | --- |
| `id` | PK |
| `sent_by` | FK → users |
| `subject` | |
| `body` | |
| `target_type` | |
| `target_batch_ids` | |
| `total_recipients` | |
| `dispatched_at` | |

### `activity_logs`

| Column | Notes |
| --- | --- |
| `id` | PK |
| `log_name` | |
| `description` | |
| `subject_type` | Morph |
| `subject_id` | |
| `causer_type` | Morph |
| `causer_id` | |
| `properties` | |
| `ip_address` | |
| `created_at` | |

### `roles`

| Column | Notes |
| --- | --- |
| `id` | PK |
| `name` | |
| `guard_name` | |
| `created_at` | |
| `updated_at` | |

### `model_has_roles`

| Column | Notes |
| --- | --- |
| `role_id` | FK → roles |
| `model_type` | Morph |
| `model_id` | FK (morph) |

Composite identity is typically (`role_id`, `model_id`, `model_type`).

### `jobs`

| Column | Notes |
| --- | --- |
| `id` | PK |
| `queue` | |
| `payload` | |
| `attempts` | |
| `reserved_at` | |
| `available_at` | |
| `created_at` | |

### `failed_jobs`

| Column | Notes |
| --- | --- |
| `id` | PK |
| `uuid` | UK |
| `connection` | |
| `queue` | |
| `payload` | |
| `exception` | |
| `failed_at` | |

---

## Table count

| Domain | Count |
| --- | --- |
| AUTH | 3 |
| GRANTEE | 4 |
| DOCUMENTS | 4 |
| EVALUATION | 5 |
| BILLING | 2 |
| SYSTEM | 7 |
| **Total** | **25** |
