import { apiFetch, buildQuery } from "./client";
import type { Batch, BatchDetail, PaginatedResponse, ListQuery } from "./types";

export async function listBatches(params: ListQuery = {}): Promise<PaginatedResponse<Batch>> {
  return apiFetch<PaginatedResponse<Batch>>(`/api/batches${buildQuery(params)}`);
}

export async function getBatch(id: string | number): Promise<BatchDetail> {
  const payload = await apiFetch<{ data: BatchDetail }>(`/api/batches/${id}`);
  return payload.data;
}

export async function createBatch(data: {
  name: string;
  academic_year: string;
  semester: string;
  submission_deadline: string;
}): Promise<{ data: Batch }> {
  return apiFetch<{ data: Batch }>("/api/batches", {
    method: "POST",
    body: JSON.stringify(data),
  });
}

export async function activateBatch(
  id: string | number,
): Promise<{ data: Partial<Batch>; mail?: { sent: number; failed: unknown[] } }> {
  return apiFetch(`/api/batches/${id}/activate`, { method: "POST" });
}

export async function deactivateBatch(
  id: string | number,
): Promise<{ data: Partial<Batch>; mail?: { sent: number; failed: unknown[] } }> {
  return apiFetch(`/api/batches/${id}/deactivate`, { method: "POST" });
}

export async function extendBatchDeadline(
  id: string | number,
  submission_deadline: string,
): Promise<{ data: Partial<Batch>; mail?: { sent: number; failed: unknown[] } }> {
  return apiFetch(`/api/batches/${id}/extend-deadline`, {
    method: "POST",
    body: JSON.stringify({ submission_deadline }),
  });
}
