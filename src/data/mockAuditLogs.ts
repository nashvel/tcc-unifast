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

const users = [
  { u: "admin", r: "Admin", ip: "192.168.1.10" },
  { u: "r.santos", r: "Office Head", ip: "192.168.1.05" },
  { u: "j.cruz", r: "UniFAST Staff", ip: "192.168.1.21" },
  { u: "p.tan", r: "UniFAST Staff", ip: "192.168.1.22" },
  { u: "k.aquino", r: "UniFAST Staff", ip: "192.168.1.23" },
  { u: "m.delacruz", r: "Student Grantee", ip: "120.28.45.99" },
];

const events: Array<Omit<AuditLog, "id" | "user" | "role" | "ip" | "timestamp"> & { stamp: string }> = [
  { action: "approve_document", module: "Document Validation", target: "Doc #d2 (TOR — Santos)", before: { status: "pending" }, after: { status: "approved" }, stamp: "2026-06-20 14:21" },
  { action: "create_batch", module: "Batches", target: "Batch AY 2024-2025 Sem 2", stamp: "2026-06-19 09:02" },
  { action: "publish_announcement", module: "Announcements", target: "Submission Deadline Extended", stamp: "2026-06-19 10:00" },
  { action: "flag_suspicious", module: "Document Validation", target: "Doc #d3 (ID — Reyes)", before: { risk: 35 }, after: { risk: 82, status: "suspicious" }, stamp: "2026-06-17 11:15" },
  { action: "import_masterlist", module: "Masterlist", target: "AY 2024-2025 Sem 1 (1,240 rows)", stamp: "2025-08-12 16:40" },
  { action: "activate_account", module: "Auth", target: "Self", stamp: "2025-08-15 21:11" },
  { action: "reject_document", module: "Document Validation", target: "Doc #d6 (Indigency — Villanueva)", before: { status: "pending" }, after: { status: "rejected" }, stamp: "2026-06-15 14:35" },
  { action: "update_eligibility", module: "Eligibility", target: "Grantee #g2 (Santos)", before: { eligibility: "for_evaluation" }, after: { eligibility: "eligible" }, stamp: "2026-06-14 10:11" },
  { action: "edit_grantee", module: "Grantees", target: "Grantee #g7 (Mendoza)", before: { contact: "+639175551111" }, after: { contact: "+639175552222" }, stamp: "2026-06-13 16:30" },
  { action: "request_resubmission", module: "Document Validation", target: "Doc #d4 (PSA — Tan)", before: { status: "pending" }, after: { status: "resubmission" }, stamp: "2026-06-13 11:02" },
  { action: "login", module: "Auth", target: "Self", stamp: "2026-06-13 08:00" },
  { action: "failed_login", module: "Auth", target: "k.aquino", stamp: "2026-06-12 22:45" },
  { action: "create_user", module: "Users & Roles", target: "p.tan (UniFAST Staff)", stamp: "2026-06-10 09:21" },
  { action: "export_report", module: "Reports", target: "Grantee List — AY 2024-2025 Sem 1 (PDF)", stamp: "2026-06-09 14:00" },
  { action: "export_report", module: "Reports", target: "Document Validation — Q2 (Excel)", stamp: "2026-06-09 14:05" },
  { action: "override_eligibility", module: "Eligibility", target: "Grantee #g15", before: { eligibility: "ineligible" }, after: { eligibility: "eligible" }, stamp: "2026-06-08 17:55" },
  { action: "archive_batch", module: "Batches", target: "AY 2022-2023 Sem 2", stamp: "2026-06-05 10:00" },
  { action: "reset_password", module: "Users & Roles", target: "k.aquino", stamp: "2026-06-04 11:00" },
  { action: "approve_document", module: "Document Validation", target: "Doc #d7 (COR — Mendoza)", before: { status: "pending" }, after: { status: "approved" }, stamp: "2026-06-03 09:42" },
  { action: "publish_announcement", module: "Announcements", target: "Resubmission Required", stamp: "2026-06-02 08:30" },
  { action: "edit_settings", module: "Settings", target: "Auto-approve risk threshold", before: { value: 30 }, after: { value: 20 }, stamp: "2026-06-01 16:11" },
  { action: "approve_document", module: "Document Validation", target: "Doc #d8 (TOR — Garcia)", before: { status: "pending" }, after: { status: "approved" }, stamp: "2026-05-31 14:00" },
];

export const mockAuditLogs: AuditLog[] = events.map((e, i) => {
  const u = users[i % users.length];
  return { id: `al${i + 1}`, user: u.u, role: u.r, ip: u.ip, timestamp: e.stamp, action: e.action, module: e.module, target: e.target, before: e.before, after: e.after };
});
