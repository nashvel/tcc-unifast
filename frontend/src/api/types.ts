export type PaginationMeta = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
};

export type PaginatedResponse<T> = {
  data: T[];
  meta: PaginationMeta;
};

export type ListQuery = {
  page?: number;
  per_page?: number;
  search?: string;
  sort?: string;
  direction?: "asc" | "desc";
  [key: string]: string | number | boolean | undefined | null;
};

export type Batch = {
  id: number;
  name: string;
  academic_year: string;
  semester: string;
  submission_deadline: string | null;
  is_active: boolean;
  window_status: "draft" | "active" | "closed" | "expired";
  grantees_count: number;
};

export type BatchDetail = Batch & {
  grantees: BatchGrantee[];
};

export type SocialMediaPostStatus = "draft" | "queued" | "sent_to_n8n" | "scheduled" | "failed" | "published";

export type SocialMediaPost = {
  id: number;
  title: string;
  message: string;
  channel: "facebook";
  campaign: string | null;
  status: SocialMediaPostStatus;
  approval_mode: "approval_required" | "pre_approved";
  scheduled_for: string | null;
  submitted_at: string | null;
  published_at: string | null;
  n8n_request_id: string | null;
  n8n_status: string | null;
  error_message: string | null;
  external_post_id: string | null;
  external_permalink: string | null;
  created_at: string;
  updated_at: string;
  batch: Pick<Batch, "id" | "name" | "academic_year" | "semester" | "submission_deadline"> | null;
  creator: { id: number; name: string | null; email: string } | null;
};

export type SocialMediaIntegrationStatus = {
  n8n_configured: boolean;
  n8n_reachable: boolean;
  facebook_confirmed: boolean;
  state:
    | "not_configured"
    | "ready_for_first_post"
    | "draft_saved"
    | "awaiting_approval"
    | "awaiting_facebook_callback"
    | "failed"
    | "connected";
  page: {
    id?: string | null;
    name?: string | null;
    url?: string | null;
    picture_url?: string | null;
    cover_url?: string | null;
    followers_count?: number | null;
    fan_count?: number | null;
  } | null;
  latest_post: {
    id: number;
    status: SocialMediaPost["status"];
    approval_mode: SocialMediaPost["approval_mode"];
    n8n_status: string | null;
    error_message: string | null;
    updated_at: string;
  } | null;
  counts: {
    total: number;
    drafts: number;
    processing: number;
    published: number;
    failed: number;
  };
  last_activity_at: string | null;
  last_published_at: string | null;
};

export type SocialMediaPostTemplate = {
  title: string;
  message: string;
  channel: "facebook";
  campaign: string;
  batch_id: number | null;
  approval_mode: "approval_required" | "pre_approved";
  scheduled_for: string | null;
  facts: {
    portal_url: string;
    support_email: string;
    deadline: string | null;
    deadline_label: string;
    grantees_count: number;
    window_status: string | null;
    generated_at: string;
  };
  batch: (Pick<Batch, "id" | "name" | "academic_year" | "semester" | "submission_deadline" | "is_active" | "window_status" | "grantees_count">) | null;
};

export type BatchGrantee = {
  id: number;
  student_id: string;
  student_number: string | null;
  full_name: string;
  email: string;
  program: string;
  status: string;
  account_status: string | null;
};

export type GranteeRow = {
  id: number;
  student_number: string | null;
  student_id: string;
  name: string;
  program: string;
  batch: string | null;
  account: string;
  submission: string;
  eligibility: string;
  risk: string;
};

export type GranteeDetail = {
  id: number;
  student_number: string | null;
  student_id: string;
  name: string;
  email: string;
  program: string;
  batch: string | null;
  account: string;
  submission: string;
  eligibility: string;
  risk: string;
  contact: string | null;
  year_level: string | null;
  university: string;
  gwa: string | null;
  birthdate: string | null;
};

export type CourseRemark = "Passed" | "Failed" | "Dropped";

export type AcademicRecord = {
  id: number;
  student_number: string | null;
  student_id: string;
  name: string;
  program: string;
  year_level: string | null;
  latest_gwa: string | null;
  approved_submissions: number;
  total_submissions: number;
  remarks: { passed: number; failed: number; dropped: number };
};

export type AcademicRecordDetail = AcademicRecord & {
  semesters: AcademicSemester[];
};

export type AcademicSemester = {
  id: number;
  term: string;
  gwa: string | null;
  units_taken: number;
  units_passed: number;
  courses: AcademicCourse[];
};

export type AcademicCourse = {
  id: number;
  code: string;
  title: string;
  units: number;
  grade: string | null;
  remark: CourseRemark;
};

export type DocSubmission = {
  id: number;
  student_name: string;
  student_id: string;
  document_type: string;
  slot_key: string | null;
  status: string;
  risk_level: string;
  identity_review_required: boolean;
  created_at: string;
  grantee_id?: number | null;
  batch_id?: number | null;
};

export type DocPackageDocument = {
  id: number;
  slot_key: string | null;
  document_type: string;
  tab_label: string;
  status: string;
  risk_level: string;
  identity_review_required: boolean;
};

export type DocSubmissionPackage = {
  grantee_id: number;
  batch_id: number;
  batch_name: string | null;
  student_name: string;
  student_id: string;
  status: string;
  risk_level: string;
  identity_review_required: boolean;
  submitted_at: string | null;
  slots_expected: number;
  slots_submitted: number;
  slots_reviewed: number;
  progress: string;
  documents: DocPackageDocument[];
};

export type OcrCourseRow = {
  code?: string | null;
  description?: string | null;
  units?: string | null;
  grade?: string | null;
  instructor?: string | null;
  remarks?: string | null;
  fail_reason?: string | null;
  counts_as_fail?: boolean;
  academic_term?: string | null;
  program_code?: string | null;
  program_raw?: string | null;
  year_level?: string | null;
  pass_grade?: number | null;
};

export type OcrTermBlock = {
  academic_term?: string | null;
  program_raw?: string | null;
  program_code?: string | null;
  year_level?: string | null;
  enrollment_status?: string | null;
  pass_grade?: number | null;
  program_matched?: boolean;
  failed_count?: number;
  blank_count?: number;
  pending_count?: number;
  dropped_count?: number;
  retention_count?: number;
  courses?: OcrCourseRow[];
};

export type DocSubmissionDetail = DocSubmission & {
  original_name: string;
  secondary_original_name: string | null;
  file_url: string;
  secondary_file_url: string | null;
  file_preview_url?: string | null;
  secondary_file_preview_url?: string | null;
  mime_type: string;
  secondary_mime_type: string | null;
  face_quality_score: number | null;
  identity_review_reason: string | null;
  identity_check: {
    result: string;
    distance: number;
    confidence_score: number | null;
    manual_review_required: boolean;
    challenge_sequence: string[];
    checked_at: string;
  } | null;
  extracted_text: string | null;
  ocr_confidence: number | null;
  ocr_payload?: {
    engine?: string;
    method?: string;
    result?: {
      combined_text?: string;
      formatted_table_text?: string | null;
      courses?: OcrCourseRow[];
      terms?: OcrTermBlock[];
      grade_summary?: {
        blank_count?: number;
        pending_count?: number;
        failed_count?: number;
        numeric_failed_count?: number;
        dropped_count?: number;
        retention_count?: number;
        pass_grade?: number;
        program_code?: string | null;
        document_type?: string | null;
        blanks_count_as_fails?: boolean;
        blanks_count_as_dropped?: boolean;
        pending_term_window?: number;
        term_count?: number;
      };
    };
  } | null;
  metadata_payload: Record<string, unknown> | null;
  review_notes: string | null;
};

export type ImportRow = {
  id: number;
  row_number: number;
  student_id: string | null;
  student_number: string | null;
  full_name: string | null;
  email: string | null;
  program: string | null;
  year_level: string | null;
  status: "valid" | "invalid";
  errors: string[];
};

export type ImportPreview = {
  id: number;
  status: string;
  total_rows: number;
  valid_rows: number;
  invalid_rows: number;
  imported_rows: number;
  rows: ImportRow[];
};

export type AuditLog = {
  id: number;
  created_at: string;
  actor: string;
  role: string;
  action: string;
  module: string;
  target: string;
  ip_address: string;
};

export type KycResponse = {
  status: string;
  mismatches: Record<string, string>;
  hint: { student_id_last4: string };
  programs: Array<{ id: number; code: string; name: string }>;
  year_level_options: string[];
  profile?: {
    first_name?: string | null;
    middle_name?: string | null;
    last_name?: string | null;
    full_name?: string | null;
    student_id?: string | null;
    program?: string | null;
    year_level?: string | null;
    birthdate?: string | null;
    contact?: string | null;
    address?: string | null;
    guardian_name?: string | null;
    household_income?: string | number | null;
    status?: string | null;
  };
  next_step?: string;
};

export type VaultDocument = {
  id: number;
  slot_key: string;
  document_type: string;
  original_name: string;
  secondary_original_name: string | null;
  status: string;
  risk_level: string;
  face_quality_score: number | null;
  identity_review_required: boolean;
  identity_review_reason: string | null;
  review_notes: string | null;
  face_descriptor: number[] | null;
};

export type IdentityCheck = {
  id: number;
  result: "match" | "no_match";
  distance: number;
  confidence_score: number | null;
  manual_review_required: boolean;
  checked_at: string;
};

export type VaultResponse = {
  window: { open: boolean; message: string };
  grantee: { submission_status: string; submitted_at: string | null } | null;
  slots: Record<string, VaultDocument>;
  identity_check: IdentityCheck | null;
};

export type StudentNotification = {
  id: number;
  title: string;
  body: string;
  time: string;
  type: string;
  read: boolean;
};

export type StudentSubmission = {
  id: number;
  document_type: string;
  original_name: string;
  created_at: string;
  status: string;
  review_notes: string | null;
  ocr_confidence: number | null;
};

export type SubmissionWindow = {
  open: boolean;
  status: "active" | "expired" | "closed" | "draft" | "unassigned";
  message: string;
  batch: null | {
    id: number;
    name: string;
    academic_year: string;
    semester: string;
    submission_deadline: string | null;
    window_status: string;
  };
};
