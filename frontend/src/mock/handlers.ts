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
  "GET /api/audit-logs": () => ({
    status: 200,
    body: {
      data: mockAuditLogs,
      meta: { current_page: 1, per_page: 15, total: mockAuditLogs.length, last_page: 1 },
    },
  }),
  "POST /api/audit-events": () => ({ status: 200, body: { ok: true } }),

  // Terms & Conditions
  "GET /api/terms": () => ({ status: 200, body: { data: [mockTerms] } }),
  "GET /api/terms/active": () => ({ status: 200, body: { data: mockTerms } }),

  // FAQ
  "GET /api/faqs": () => ({ status: 200, body: { data: mockFaqs } }),
  "GET /api/faqs/all": () => ({ status: 200, body: { data: mockFaqs } }),

  // Database
  "GET /api/database/tables": () => ({
    status: 200,
    body: {
      data: mockTables,
      summary: {
        total_tables: mockTables.length,
        total_rows: mockTables.reduce((acc, t) => acc + t.rows, 0),
        database: "SQLITE (tcc_unifast.sqlite)",
        largest_table: "batches (12 rows)",
      },
    },
  }),
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

  // Developer System Health Telemetry
  "GET /api/system/health": () => ({
    status: 200,
    body: {
      data: {
        health: [
          { name: "API Server", status: "healthy", latency: "14ms", uptime: "99.99%" },
          { name: "Database Engine (SQLITE)", status: "healthy", latency: "1.2ms", uptime: "99.99%" },
          { name: "OCR Service Engine", status: "healthy", latency: "185ms", uptime: "99.8%" },
          { name: "File Storage", status: "healthy", latency: "0.8ms", uptime: "45.2 GB free" },
        ],
        kpis: [
          { title: "System Accounts", value: `${mockUsers.length}`, change: `${mockUsers.length} Active`, trend: "up", subtitle: "Registered Accounts" },
          { title: "Academic Batches", value: `${mockBatches.length}`, change: `${mockBatches.length} Open`, trend: "up", subtitle: "System Batches" },
          { title: "Document Vault", value: `${mockDocuments.length}`, change: `${mockDocuments.length} Approved`, trend: "up", subtitle: "Uploaded Documents" },
          { title: "Support Tickets", value: "3", change: "1 Pending", trend: "up", subtitle: "Developer Queue" },
        ],
        endpoints: [
          { endpoint: "/api/auth/login", method: "POST", p50: "45ms", p95: "110ms", calls: `${mockUsers.length} users`, errors: "0.00%" },
          { endpoint: "/api/batches", method: "GET", p50: "18ms", p95: "55ms", calls: `${mockBatches.length} batches`, errors: "0.00%" },
          { endpoint: "/api/document-submissions", method: "GET", p50: "32ms", p95: "95ms", calls: `${mockDocuments.length} submissions`, errors: "0.01%" },
          { endpoint: "/api/grantees", method: "GET", p50: "24ms", p95: "80ms", calls: `${mockGrantees.length} grantees`, errors: "0.00%" },
          { endpoint: "/api/academic-records", method: "GET", p50: "28ms", p95: "85ms", calls: `${mockAcademicRecords.length} records`, errors: "0.00%" },
          { endpoint: "/api/audit-logs", method: "GET", p50: "12ms", p95: "35ms", calls: `${mockAuditLogs.length} logs`, errors: "0.00%" },
        ],
        system: {
          framework: "Laravel 11 + Vue 3",
          php_version: "PHP 8.3.6",
          auth: "Sanctum API Tokens",
          database: "SQLITE (tcc_unifast.sqlite)",
          users_count: mockUsers.length,
          batches_count: mockBatches.length,
          submissions_count: mockDocuments.length,
          audit_events_count: mockAuditLogs.length,
          memory_usage: "18.4 MB",
          os: "Linux",
        },
        logs: mockAuditLogs.slice(0, 6).map((log) => ({
          time: log.created_at ? log.created_at.split("T")[1]?.slice(0, 8) || "10:42:18" : "10:42:18",
          level: "info",
          message: `${log.actor} (${log.role}) — ${log.action} in ${log.module}: ${log.target || "System operation"}`,
          service: log.module.toLowerCase(),
        })),
        deployments: [
          { version: "v2.1.0 (Current Release)", status: "success", commit: "main", time: "Jul 26, 2026 18:30", author: "System Developer" },
          { version: "v2.0.9 (Security Patch)", status: "success", commit: "a3f8c2d", time: "Jul 25, 2026 14:15", author: "System Developer" },
        ],
      },
    },
  }),

  // Collaborators
  "GET /api/collaborators": () => ({
    status: 200,
    body: {
      data: [
        { id: "1", name: "System Developer", email: "admin@unifast.gov.ph", role: "developer", access: ["*"], status: "active", invitedAt: "Jul 1, 2026" },
        { id: "2", name: "Office Administrator", email: "head@unifast.gov.ph", role: "admin", access: ["users", "batches", "settings", "audit"], status: "active", invitedAt: "Jul 1, 2026" },
        { id: "3", name: "Dev Assistant", email: "dev2@unifast.gov.ph", role: "staff", access: ["documents", "grantees", "academic"], status: "pending", invitedAt: "Jul 12, 2026" },
      ],
      summary: {
        total_members: 3,
        active_members: 2,
        pending_invites: 1,
        developers: 1,
      },
    },
  }),
  "POST /api/collaborators/invite": (body) => {
    const parsed = body ? JSON.parse(body) : {};
    return {
      status: 201,
      body: {
        data: {
          id: String(Date.now()),
          name: (parsed.email || "colleague@unifast.gov.ph").split("@")[0],
          email: parsed.email || "colleague@unifast.gov.ph",
          role: parsed.role || "staff",
          access: parsed.access || [],
          status: "pending",
          invitedAt: "Just now",
        },
      },
    };
  },

  // Support Tickets
  "GET /api/support-tickets": () => ({
    status: 200,
    body: {
      data: [
        { id: 1, ticket_id: "TK-001", title: "Face verification timeout after 30s", category: "bug", priority: "High", status: "Open", reporter: "Maria Santos", assignee: "System Developer", createdAt: "Jul 12, 2026", replies: [], description: "Face verification API times out on weak mobile connections." },
        { id: 2, ticket_id: "TK-002", title: "Request: CSV export for audit trail", category: "feature", priority: "Normal", status: "In Progress", reporter: "Office Administrator", assignee: "System Developer", createdAt: "Jul 11, 2026", replies: [], description: "Admin requested CSV export capability for developer audit logs." },
        { id: 3, ticket_id: "TK-003", title: "OCR mismatch on non-standard font transcripts", category: "bug", priority: "Normal", status: "Waiting", reporter: "UniFAST Staff", assignee: "System Developer", createdAt: "Jul 10, 2026", replies: [], description: "Special characters on course names cause low confidence score." },
      ],
    },
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
