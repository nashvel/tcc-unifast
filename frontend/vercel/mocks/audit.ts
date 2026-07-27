import type { AuditLog } from "@/api/types";

export const mockAuditLogs: AuditLog[] = [
  { id: 1, created_at: "2026-07-12T09:42:00Z", actor: "System Developer", role: "Developer", action: "route_view", module: "Dashboard", target: "/app", ip_address: "192.168.1.14" },
  { id: 2, created_at: "2026-07-12T09:31:00Z", actor: "UniFAST Staff", role: "Staff", action: "ui_click", module: "Documents", target: "Review button", ip_address: "192.168.1.22" },
  { id: 3, created_at: "2026-07-12T09:10:00Z", actor: "Office Administrator", role: "Admin", action: "route_view", module: "Batches", target: "/app/batches", ip_address: "192.168.1.10" },
  { id: 4, created_at: "2026-07-12T08:55:00Z", actor: "System Developer", role: "Developer", action: "auth_login", module: "Authentication", target: "admin@unifast.gov.ph", ip_address: "192.168.1.14" },
];
