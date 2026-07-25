import {
  mockUser,
  mockBatches,
  mockBatchDetail,
  mockGrantees,
  mockGranteeDetail,
  mockAcademicRecords,
  mockAcademicDetail,
  mockDocuments,
  mockDocumentDetail,
  mockAuditLogs,
  mockNotifications,
  mockSubmissionWindow,
} from "./data";

type MockResponse = { status: number; body: unknown };

const handlers: Record<string, () => MockResponse> = {
  // Auth
  "GET /api/auth/me": () => ({ status: 200, body: { user: mockUser } }),
  "POST /api/auth/login": () => ({
    status: 200,
    body: { user: mockUser, token: "mock-token-12345" },
  }),
  "POST /api/auth/logout": () => ({ status: 200, body: { message: "Signed out." } }),

  // Batches
  "GET /api/batches": () => ({
    status: 200,
    body: { data: mockBatches, meta: { current_page: 1, last_page: 1, per_page: 24, total: mockBatches.length, from: 1, to: mockBatches.length } },
  }),
  "GET /api/batches/1": () => ({ status: 200, body: { data: mockBatchDetail } }),
  "POST /api/batches": () => ({
    status: 200,
    body: { data: { ...mockBatches[0], id: 99, name: "New Batch" } },
  }),

  // Grantees
  "GET /api/grantees": () => ({
    status: 200,
    body: { data: mockGrantees, meta: { current_page: 1, last_page: 1, per_page: 15, total: mockGrantees.length, from: 1, to: mockGrantees.length } },
  }),
  "GET /api/grantees/1": () => ({ status: 200, body: { data: mockGranteeDetail } }),

  // Academic
  "GET /api/academic-records": () => ({
    status: 200,
    body: { data: mockAcademicRecords, meta: { current_page: 1, last_page: 1, per_page: 15, total: mockAcademicRecords.length, from: 1, to: mockAcademicRecords.length } },
  }),
  "GET /api/academic-records/1": () => ({ status: 200, body: { data: mockAcademicDetail } }),

  // Documents
  "GET /api/document-submissions": () => ({
    status: 200,
    body: { data: mockDocuments, meta: { current_page: 1, last_page: 1, per_page: 15, total: mockDocuments.length, from: 1, to: mockDocuments.length } },
  }),
  "GET /api/document-submissions/1": () => ({ status: 200, body: { data: mockDocumentDetail } }),
  "POST /api/document-submissions/1/review": () => ({
    status: 200,
    body: { data: { ...mockDocumentDetail, status: "approved" } },
  }),

  // Audit
  "GET /api/audit-logs": () => ({
    status: 200,
    body: { data: mockAuditLogs },
  }),
  "POST /api/audit-events": () => ({ status: 200, body: { ok: true } }),

  // Student
  "GET /api/student/kyc": () => ({
    status: 200,
    body: {
      data: {
        status: "verified",
        reference: { full_name: "Maria Clara Dela Cruz", student_id: "2024-001", program: "BS Information Technology", year_level: "2nd Year" },
        profile: { full_name: "Maria Clara Dela Cruz", student_id: "2024-001", program: "BS Information Technology", birthdate: "2003-05-14", contact: "+63 917 123 4567", address: "Tagoloan, Misamis Oriental", guardian_name: "Jose Dela Cruz", household_income: "15000" },
        mismatches: {},
      },
    },
  }),
  "POST /api/student/kyc": () => ({
    status: 200,
    body: { data: { status: "verified", account_status: "active" } },
  }),
  "GET /api/student/submission-window": () => ({
    status: 200,
    body: { data: mockSubmissionWindow },
  }),
  "GET /api/student/requirement-vault": () => ({
    status: 200,
    body: {
      window: { open: true, message: "Submission window is open" },
      grantee: { submission_status: "not_submitted", submitted_at: null },
      slots: {},
      identity_check: null,
    },
  }),
  "GET /api/student/notifications": () => ({
    status: 200,
    body: { data: mockNotifications, meta: { current_page: 1, last_page: 1, per_page: 50, total: mockNotifications.length, from: 1, to: mockNotifications.length } },
  }),
};

export function handleMockRequest(method: string, path: string): MockResponse | null {
  const key = `${method} ${path}`;
  const handler = handlers[key];
  if (handler) return handler();

  // Fallback for parameterized routes
  if (path.match(/^\/api\/batches\/\d+/)) return handlers["GET /api/batches/1"]();
  if (path.match(/^\/api\/grantees\/\d+/)) return handlers["GET /api/grantees/1"]();
  if (path.match(/^\/api\/academic-records\/\d+/)) return handlers["GET /api/academic-records/1"]();
  if (path.match(/^\/api\/document-submissions\/\d+\/review/)) return handlers["POST /api/document-submissions/1/review"]();
  if (path.match(/^\/api\/document-submissions\/\d+/)) return handlers["GET /api/document-submissions/1"]();

  return null;
}
