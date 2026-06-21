export interface AuditLog {
  id: string;
  user: string;
  role: string;
  action: string;
  module: string;
  target: string;
  ip: string;
  timestamp: string;
  before?: Record<string, unknown>;
  after?: Record<string, unknown>;
}

export const mockAuditLogs: AuditLog[] = [
  { id: "al1", user: "j.cruz", role: "UniFAST Staff", action: "approve_document", module: "Document Validation", target: "Doc #d2 (TOR — Santos)", ip: "192.168.1.21", timestamp: "2026-06-20 14:21", before: { status: "pending" }, after: { status: "approved" } },
  { id: "al2", user: "admin", role: "Admin", action: "create_batch", module: "Batches", target: "Batch AY 2024-2025 Sem 2", ip: "192.168.1.10", timestamp: "2026-06-19 09:02" },
  { id: "al3", user: "r.santos", role: "Office Head", action: "publish_announcement", module: "Announcements", target: "Submission Deadline Extended", ip: "192.168.1.05", timestamp: "2026-06-19 10:00" },
  { id: "al4", user: "j.cruz", role: "UniFAST Staff", action: "flag_suspicious", module: "Document Validation", target: "Doc #d3 (ID — Reyes)", ip: "192.168.1.21", timestamp: "2026-06-17 11:15", before: { risk: 35 }, after: { risk: 82, status: "suspicious" } },
  { id: "al5", user: "admin", role: "Admin", action: "import_masterlist", module: "Masterlist", target: "AY 2024-2025 Sem 1 (1,240 rows)", ip: "192.168.1.10", timestamp: "2025-08-12 16:40" },
  { id: "al6", user: "m.delacruz", role: "Student Grantee", action: "activate_account", module: "Auth", target: "Self", ip: "120.28.45.99", timestamp: "2025-08-15 21:11" },
  { id: "al7", user: "j.cruz", role: "UniFAST Staff", action: "reject_document", module: "Document Validation", target: "Doc #d6 (Indigency — Villanueva)", ip: "192.168.1.21", timestamp: "2026-06-15 14:35", before: { status: "pending" }, after: { status: "rejected" } },
];
