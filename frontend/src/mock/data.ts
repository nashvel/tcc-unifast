import type {
  Batch,
  BatchDetail,
  GranteeRow,
  GranteeDetail,
  AcademicRecord,
  AcademicRecordDetail,
  DocSubmission,
  DocSubmissionDetail,
  AuditLog,
  StudentNotification,
  SubmissionWindow,
} from "@/api/types";

export const mockUser = {
  id: 1,
  name: "System Developer",
  email: "admin@unifast.gov.ph",
  role: "developer" as const,
  student_id: null,
  account_status: "active" as const,
};

export const mockBatches: Batch[] = [
  {
    id: 1,
    name: "TES Batch 01 - AY 2026-2027",
    academic_year: "AY 2026-2027",
    semester: "1st Semester",
    submission_deadline: "2026-08-15T23:59:00Z",
    is_active: true,
    window_status: "active",
    grantees_count: 248,
  },
  {
    id: 2,
    name: "TES Batch 02 - AY 2026-2027",
    academic_year: "AY 2026-2027",
    semester: "2nd Semester",
    submission_deadline: null,
    is_active: false,
    window_status: "draft",
    grantees_count: 0,
  },
  {
    id: 3,
    name: "TES Batch 03 - AY 2025-2026",
    academic_year: "AY 2025-2026",
    semester: "1st Semester",
    submission_deadline: "2025-12-15T23:59:00Z",
    is_active: false,
    window_status: "closed",
    grantees_count: 312,
  },
];

export const mockBatchDetail: BatchDetail = {
  ...mockBatches[0],
  grantees: [
    { id: 1, student_id: "2024-001", student_number: "2024-10001", full_name: "Maria Clara Dela Cruz", email: "mc.delacruz@tcc.edu.ph", program: "BS Information Technology", status: "active", account_status: "active" },
    { id: 2, student_id: "2024-002", student_number: "2024-10002", full_name: "Juan Carlos Reyes", email: "jc.reyes@tcc.edu.ph", program: "BS Business Administration", status: "active", account_status: "active" },
    { id: 3, student_id: "2024-003", student_number: "2024-10003", full_name: "Ana Santos Garcia", email: "as.garcia@tcc.edu.ph", program: "BS Education", status: "active", account_status: "pending_kyc" },
  ],
};

export const mockGrantees: GranteeRow[] = [
  { id: 1, student_number: "2024-10001", student_id: "2024-001", name: "Maria Clara Dela Cruz", program: "BS Information Technology", batch: "TES Batch 01", account: "active", submission: "docs_submitted", eligibility: "eligible", risk: "low" },
  { id: 2, student_number: "2024-10002", student_id: "2024-002", name: "Juan Carlos Reyes", program: "BS Business Administration", batch: "TES Batch 01", account: "active", submission: "not_submitted", eligibility: "pending", risk: "medium" },
  { id: 3, student_number: "2024-10003", student_id: "2024-003", name: "Ana Santos Garcia", program: "BS Education", batch: "TES Batch 01", account: "pending_kyc", submission: "not_submitted", eligibility: "pending", risk: "low" },
  { id: 4, student_number: "2024-10004", student_id: "2024-004", name: "Pedro Miguel Torres", program: "BS Computer Science", batch: "TES Batch 01", account: "active", submission: "docs_submitted", eligibility: "eligible", risk: "low" },
  { id: 5, student_number: "2024-10005", student_id: "2024-005", name: "Sofia Reyes Lim", program: "BS Nursing", batch: "TES Batch 01", account: "blocked", submission: "not_submitted", eligibility: "ineligible", risk: "high" },
];

export const mockGranteeDetail: GranteeDetail = {
  id: 1,
  student_number: "2024-10001",
  student_id: "2024-001",
  name: "Maria Clara Dela Cruz",
  email: "mc.delacruz@tcc.edu.ph",
  program: "BS Information Technology",
  batch: "TES Batch 01",
  account: "active",
  submission: "docs_submitted",
  eligibility: "eligible",
  risk: "low",
  contact: "+63 917 123 4567",
  year_level: "2nd Year",
  university: "Tagoloan Community College",
  gwa: "1.75",
  birthdate: "2003-05-14",
};

export const mockAcademicRecords: AcademicRecord[] = [
  { id: 1, student_number: "2024-10001", student_id: "2024-001", name: "Maria Clara Dela Cruz", program: "BS Information Technology", year_level: "2nd Year", latest_gwa: "1.75", approved_submissions: 2, total_submissions: 3, remarks: { passed: 18, failed: 0, dropped: 1 } },
  { id: 2, student_number: "2024-10002", student_id: "2024-002", name: "Juan Carlos Reyes", program: "BS Business Administration", year_level: "3rd Year", latest_gwa: "2.10", approved_submissions: 1, total_submissions: 2, remarks: { passed: 15, failed: 1, dropped: 0 } },
  { id: 3, student_number: "2024-10003", student_id: "2024-003", name: "Ana Santos Garcia", program: "BS Education", year_level: "1st Year", latest_gwa: "1.50", approved_submissions: 1, total_submissions: 1, remarks: { passed: 20, failed: 0, dropped: 0 } },
];

export const mockAcademicDetail: AcademicRecordDetail = {
  ...mockAcademicRecords[0],
  semesters: [
    {
      id: 1,
      term: "1st Semester AY 2025-2026",
      gwa: "1.80",
      units_taken: 21,
      units_passed: 21,
      courses: [
        { id: 1, code: "IT 201", title: "Data Structures", units: 3, grade: "1.5", remark: "Passed" },
        { id: 2, code: "IT 202", title: "Algorithms", units: 3, grade: "1.75", remark: "Passed" },
        { id: 3, code: "MATH 201", title: "Linear Algebra", units: 3, grade: "2.0", remark: "Passed" },
        { id: 4, code: "IT 203", title: "Web Development", units: 3, grade: "1.25", remark: "Passed" },
        { id: 5, code: "ENG 201", title: "Technical Writing", units: 3, grade: "1.5", remark: "Passed" },
        { id: 6, code: "PE 201", title: "Physical Fitness", units: 2, grade: "1.0", remark: "Passed" },
        { id: 7, code: "NSTP 201", title: "Civic Welfare Training", units: 3, grade: "1.5", remark: "Passed" },
        { id: 8, code: "HUM 201", title: "Art Appreciation", units: 3, grade: "1.75", remark: "Passed" },
      ],
    },
    {
      id: 2,
      term: "2nd Semester AY 2025-2026",
      gwa: "1.70",
      units_taken: 18,
      units_passed: 18,
      courses: [
        { id: 9, code: "IT 211", title: "Database Systems", units: 3, grade: "1.5", remark: "Passed" },
        { id: 10, code: "IT 212", title: "Operating Systems", units: 3, grade: "1.75", remark: "Passed" },
        { id: 11, code: "IT 213", title: "Software Engineering", units: 3, grade: "1.25", remark: "Passed" },
        { id: 12, code: "MATH 202", title: "Discrete Mathematics", units: 3, grade: "2.0", remark: "Passed" },
        { id: 13, code: "IT 214", title: "Computer Networks", units: 3, grade: "1.5", remark: "Passed" },
        { id: 14, code: "ETHICS", title: "Ethics", units: 3, grade: "1.75", remark: "Passed" },
      ],
    },
  ],
};

export const mockDocuments: DocSubmission[] = [
  { id: 1, student_name: "Maria Clara Dela Cruz", student_id: "2024-001", document_type: "School ID", slot_key: "school_id", status: "approved", risk_level: "low", identity_review_required: false, created_at: "2026-07-10T08:30:00Z" },
  { id: 2, student_name: "Juan Carlos Reyes", student_id: "2024-002", document_type: "Course History", slot_key: "course_history", status: "pending_review", risk_level: "low", identity_review_required: false, created_at: "2026-07-11T10:15:00Z" },
  { id: 3, student_name: "Ana Santos Garcia", student_id: "2024-003", document_type: "Grade Slip", slot_key: "grade_slip", status: "resubmission", risk_level: "medium", identity_review_required: true, created_at: "2026-07-09T14:45:00Z" },
  { id: 4, student_name: "Pedro Miguel Torres", student_id: "2024-004", document_type: "School ID", slot_key: "school_id", status: "pending_review", risk_level: "low", identity_review_required: false, created_at: "2026-07-12T09:00:00Z" },
];

export const mockDocumentDetail: DocSubmissionDetail = {
  ...mockDocuments[0],
  original_name: "student_id_front.jpg",
  secondary_original_name: "student_id_back.jpg",
  file_url: "https://via.placeholder.com/400x300?text=ID+Front",
  secondary_file_url: "https://via.placeholder.com/400x300?text=ID+Back",
  mime_type: "image/jpeg",
  secondary_mime_type: "image/jpeg",
  face_quality_score: 0.92,
  identity_review_required: false,
  identity_review_reason: null,
  identity_check: {
    result: "match",
    distance: 0.35,
    confidence_score: 87.5,
    manual_review_required: false,
    challenge_sequence: ["blink", "turn_left", "turn_right"],
    checked_at: "2026-07-10T08:35:00Z",
  },
  extracted_text: "TAGOLOAN COMMUNITY COLLEGE\nStudent ID: 2024-001\nName: MARIA CLARA DELA CRUZ\nProgram: BS Information Technology\nValid Until: 2027-05-31",
  ocr_confidence: 94.2,
  metadata_payload: { source: "ocr", model_version: "2.1" },
  review_notes: "ID verified. Face match passed.",
};

export const mockAuditLogs: AuditLog[] = [
  { id: 1, created_at: "2026-07-12T09:42:00Z", actor: "System Developer", role: "Developer", action: "route_view", module: "Dashboard", target: "/app", ip_address: "192.168.1.14" },
  { id: 2, created_at: "2026-07-12T09:31:00Z", actor: "UniFAST Staff", role: "Staff", action: "ui_click", module: "Documents", target: "Review button", ip_address: "192.168.1.22" },
  { id: 3, created_at: "2026-07-12T09:10:00Z", actor: "Office Administrator", role: "Admin", action: "route_view", module: "Batches", target: "/app/batches", ip_address: "192.168.1.10" },
  { id: 4, created_at: "2026-07-12T08:55:00Z", actor: "System Developer", role: "Developer", action: "auth_login", module: "Authentication", target: "admin@unifast.gov.ph", ip_address: "192.168.1.14" },
];

export const mockNotifications: StudentNotification[] = [
  { id: 1, title: "Submission window opened", body: "The submission window for TES Batch 01 is now open.", type: "window_opened", read: false, time: "2 hours ago" },
  { id: 2, title: "Document approved", body: "Your School ID has been approved by staff.", type: "success", read: false, time: "1 day ago" },
  { id: 3, title: "Deadline extended", body: "The submission deadline has been extended to Aug 15, 2026.", type: "info", read: true, time: "3 days ago" },
];

export const mockSubmissionWindow: SubmissionWindow = {
  open: true,
  status: "active",
  message: "Submission window is open",
  batch: {
    id: 1,
    name: "TES Batch 01",
    academic_year: "AY 2026-2027",
    semester: "1st Semester",
    submission_deadline: "2026-08-15T23:59:00Z",
    window_status: "active",
  },
};
