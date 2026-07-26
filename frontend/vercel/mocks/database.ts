export const mockTables = [
  { name: "users", columns: 10, rows: 4, column_names: ["id", "name", "email", "role", "student_id", "account_status", "password", "created_at", "updated_at", "email_verified_at"] },
  { name: "roles", columns: 5, rows: 4, column_names: ["id", "name", "description", "color", "is_system"] },
  { name: "permissions", columns: 4, rows: 16, column_names: ["id", "name", "description", "category"] },
  { name: "batches", columns: 8, rows: 3, column_names: ["id", "name", "academic_year", "semester", "submission_deadline", "is_active", "window_status", "grantees_count"] },
  { name: "grantees", columns: 12, rows: 5, column_names: ["id", "user_id", "student_id", "student_number", "full_name", "email", "program", "batch_id", "status", "account_status", "created_at", "updated_at"] },
  { name: "document_submissions", columns: 15, rows: 4, column_names: ["id", "student_id", "student_name", "document_type", "slot_key", "status", "risk_level", "created_at", "updated_at", "original_name", "file_url", "mime_type", "face_quality_score", "identity_review_required", "review_notes"] },
  { name: "academic_records", columns: 8, rows: 3, column_names: ["id", "student_id", "student_number", "grantee_name", "program", "year_level", "latest_gwa", "approved_submissions"] },
  { name: "audit_logs", columns: 9, rows: 4, column_names: ["id", "actor", "role", "action", "module", "target", "ip_address", "context", "created_at"] },
  { name: "kyc_profiles", columns: 10, rows: 2, column_names: ["id", "user_id", "full_name", "student_id", "program", "birthdate", "contact", "address", "status", "created_at"] },
  { name: "faqs", columns: 7, rows: 6, column_names: ["id", "question", "answer", "category", "sort_order", "is_active", "created_at"] },
  { name: "terms", columns: 6, rows: 1, column_names: ["id", "title", "content", "version", "is_active", "created_at"] },
];

export const mockDbStats = {
  tables: [
    { table: "users", rows: 4, columns: 10 },
    { table: "roles", rows: 4, columns: 5 },
    { table: "permissions", rows: 16, columns: 4 },
    { table: "batches", rows: 3, columns: 8 },
    { table: "grantees", rows: 5, columns: 12 },
    { table: "document_submissions", rows: 4, columns: 15 },
    { table: "academic_records", rows: 3, columns: 8 },
    { table: "audit_logs", rows: 4, columns: 9 },
    { table: "kyc_profiles", rows: 2, columns: 10 },
    { table: "faqs", rows: 6, columns: 7 },
    { table: "terms", rows: 1, columns: 6 },
  ],
  summary: { total_tables: 11, total_rows: 48, database: "sqlite" },
};

export const mockUserTable = {
  name: "users",
  columns: [
    { name: "id", type: "integer", nullable: false, default: null, primary: true },
    { name: "name", type: "varchar", nullable: false, default: null, primary: false },
    { name: "email", type: "varchar", nullable: false, default: null, primary: false },
    { name: "role", type: "varchar", nullable: false, default: "student", primary: false },
    { name: "student_id", type: "varchar", nullable: true, default: null, primary: false },
    { name: "account_status", type: "varchar", nullable: false, default: "active", primary: false },
    { name: "password", type: "varchar", nullable: false, default: null, primary: false },
    { name: "created_at", type: "datetime", nullable: true, default: null, primary: false },
    { name: "updated_at", type: "datetime", nullable: true, default: null, primary: false },
    { name: "email_verified_at", type: "datetime", nullable: true, default: null, primary: false },
  ],
  indexes: [{ name: "primary", unique: true }],
  row_count: 4,
};

export const mockUserRows = [
  { id: 1, name: "System Developer", email: "admin@unifast.gov.ph", role: "developer", student_id: null, account_status: "active", created_at: "2026-07-01T00:00:00Z" },
  { id: 2, name: "Office Administrator", email: "head@unifast.gov.ph", role: "admin", student_id: null, account_status: "active", created_at: "2026-07-01T00:00:00Z" },
  { id: 3, name: "UniFAST Staff", email: "staff@unifast.gov.ph", role: "staff", student_id: null, account_status: "active", created_at: "2026-07-01T00:00:00Z" },
  { id: 4, name: "Maria Angela Santos", email: "student@tcc.edu.ph", role: "student", student_id: "2024-00182", account_status: "active", created_at: "2026-07-01T00:00:00Z" },
];

export const mockTerms = {
  id: 1,
  title: "Terms and Conditions",
  content: '<h3>1. Acceptance of Terms</h3><p>By accessing and using the UniFAST TES Portal, you agree to be bound by these Terms and Conditions.</p><h3>2. Eligibility</h3><p>Only students enrolled at Tagoloan Community College who are confirmed TES grantees are eligible to use this portal.</p><h3>3. Account Responsibility</h3><p>You are responsible for maintaining the confidentiality of your account credentials and for all activities under your account.</p><h3>4. Document Submission</h3><p>All documents submitted must be authentic and accurate. Submission of falsified documents may result in account termination.</p><h3>5. Privacy</h3><p>Your personal information is handled in accordance with the Data Privacy Act of 2012 (RA 10173).</p>',
  version: "1.0",
  is_active: true,
};

export const mockFaqs = [
  { id: 1, question: "How do I activate my account?", answer: "Check your email for the activation link sent after registration. Click the link and follow the prompts to set your password.", category: "account", sort_order: 1, is_active: true },
  { id: 2, question: "What documents do I need to submit?", answer: "You need to submit: (1) School ID - front and back photos, (2) Course History PDF, and (3) Grade Slip PDF.", category: "documents", sort_order: 2, is_active: true },
  { id: 3, question: "What is the identity verification process?", answer: "After uploading your School ID, you will be asked to complete a live face verification. The system compares your live face with the photo on your School ID.", category: "verification", sort_order: 3, is_active: true },
  { id: 4, question: "How do I check my submission status?", answer: "Go to the Submission Status page from the student sidebar.", category: "general", sort_order: 4, is_active: true },
  { id: 5, question: "What if my document is rejected?", answer: "If your document is rejected, you will receive a notification with the reason. You can re-upload a corrected version.", category: "documents", sort_order: 5, is_active: true },
  { id: 6, question: "How do I update my KYC information?", answer: "Go to the KYC Profile Validation page from the student sidebar.", category: "account", sort_order: 6, is_active: true },
];
