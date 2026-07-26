import { apiFetch } from "./client";
import type { AuditLog, PaginatedResponse } from "./types";

export async function listAuditLogs(): Promise<PaginatedResponse<AuditLog>> {
  return apiFetch<PaginatedResponse<AuditLog>>("/api/audit-logs");
}

export async function emitAuditEvent(payload: {
  action: string;
  module: string;
  target?: string;
  context?: Record<string, unknown>;
}): Promise<void> {
  try {
    await apiFetch("/api/audit-events", {
      method: "POST",
      body: JSON.stringify(payload),
    });
  } catch {
    // Audit logging must never break the UI
  }
}
