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
  const body = JSON.stringify(payload);
  await fetch("/api/audit-events", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body,
    keepalive: body.length < 60000,
  }).catch(() => {});
}
