import {
  mockUsers,
  roleFromEmail,
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
  mockVault,
  mockTables,
  mockDbStats,
  mockUserRows,
  mockTerms,
  mockFaqs,
} from "../../vercel/mocks";

type MockResponse = { status: number; body: unknown };
type MockHandler = (body?: string) => MockResponse;

function makeUser(email: string) {
  const match = roleFromEmail[email] || roleFromEmail["admin@unifast.gov.ph"];
  return {
    id: 1,
    name: match.name,
    email,
    role: match.role,
    student_id: match.role === "student" ? "2024-001" : null,
    account_status: "active" as const,
  };
}

const handlers: Record<string, MockHandler> = {
  // Auth
  "GET /api/auth/me": () => {
    const token = typeof localStorage !== "undefined" ? localStorage.getItem("unifast_auth_token") : null;
    if (!token) return { status: 401, body: { message: "Unauthenticated." } };
    return { status: 200, body: { user: makeUser("admin@unifast.gov.ph") } };
  },
  "GET /api/auth/captcha": () => {
    // Return a simple SVG image as a base64 data URL for mock mode
    const mockCode = "MOCK01";
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="180" height="50">
      <rect width="180" height="50" fill="#1e293b"/>
      <text x="50%" y="55%" dominant-baseline="middle" text-anchor="middle"
        font-family="monospace" font-size="20" font-weight="bold" fill="#e2e8f0"
        letter-spacing="6">${mockCode}</text>
      <line x1="10" y1="15" x2="170" y2="35" stroke="#475569" stroke-width="1"/>
      <line x1="20" y1="40" x2="160" y2="10" stroke="#475569" stroke-width="1"/>
    </svg>`;
    const b64 = btoa(unescape(encodeURIComponent(svg)));
    return {
      status: 200,
      body: { image: `data:image/svg+xml;base64,${b64}`, key: "mock-captcha-key" },
    };
  },
  "POST /api/auth/login": (body) => {
    const parsed = body ? JSON.parse(body) : {};
    const user = makeUser(parsed.email || "admin@unifast.gov.ph");
    return { status: 200, body: { user, token: "mock-token-12345" } };
  },
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
  "GET /api/audit-logs": () => ({ status: 200, body: { data: mockAuditLogs } }),
  "POST /api/audit-events": () => ({ status: 200, body: { ok: true } }),

  // Terms & Conditions
  "GET /api/terms": () => ({ status: 200, body: { data: [mockTerms] } }),
  "GET /api/terms/active": () => ({ status: 200, body: { data: mockTerms } }),

  // FAQ
  "GET /api/faqs": () => ({ status: 200, body: { data: mockFaqs } }),
  "GET /api/faqs/all": () => ({ status: 200, body: { data: mockFaqs } }),

  // Database
  "GET /api/database/tables": () => ({ status: 200, body: { data: mockTables } }),
  "GET /api/database/stats": () => ({ status: 200, body: { data: mockDbStats } }),

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
  "GET /api/student/requirement-vault": () => ({ status: 200, body: mockVault }),
  "GET /api/student/notifications": () => ({
    status: 200,
    body: { data: mockNotifications, meta: { current_page: 1, last_page: 1, per_page: 50, total: mockNotifications.length, from: 1, to: mockNotifications.length } },
  }),
};

export function handleMockRequest(method: string, path: string, body?: string): MockResponse | null {
  const key = `${method} ${path}`;
  const handler = handlers[key];
  if (handler) return handler(body);

  // Database table detail - generate dynamically from mockTables
  const tableMatch = path.match(/^\/api\/database\/tables\/(\w+)$/);
  if (tableMatch && method === "GET") {
    const tableName = tableMatch[1];
    const tableInfo = mockTables.find((t) => t.name === tableName);
    if (tableInfo) {
      return {
        status: 200,
        body: {
          data: {
            name: tableInfo.name,
            columns: tableInfo.column_names.map((name, i) => ({
              name,
              type: i === 0 ? "bigint" : "varchar",
              nullable: i > 0 && Math.random() > 0.7,
              default: i === 0 ? null : null,
              primary: i === 0,
            })),
            indexes: [{ name: "primary", unique: true }],
            row_count: tableInfo.rows,
          },
        },
      };
    }
  }

  // Database table rows
  const rowsMatch = path.match(/^\/api\/database\/tables\/(\w+)\/rows$/);
  if (rowsMatch && method === "GET") {
    return {
      status: 200,
      body: { data: mockUserRows, meta: { current_page: 1, per_page: 25, total: 4, last_page: 1 } },
    };
  }

  // Fallback for parameterized routes
  if (path.match(/^\/api\/batches\/\d+/)) return handlers["GET /api/batches/1"]?.();
  if (path.match(/^\/api\/grantees\/\d+/)) return handlers["GET /api/grantees/1"]?.();
  if (path.match(/^\/api\/academic-records\/\d+/)) return handlers["GET /api/academic-records/1"]?.();
  if (path.match(/^\/api\/document-submissions\/\d+\/review/)) return handlers["POST /api/document-submissions/1/review"]?.();
  if (path.match(/^\/api\/document-submissions\/\d+/)) return handlers["GET /api/document-submissions/1"]?.();
  if (path.match(/^\/api\/terms\/\d+/)) return handlers["GET /api/terms/active"]?.();
  if (path.match(/^\/api\/faqs\/\d+/)) return handlers["GET /api/faqs/all"]?.();

  return null;
}
