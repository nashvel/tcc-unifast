import { apiFetch, buildQuery } from "./client";
import type { DocSubmission, DocSubmissionDetail, PaginatedResponse, ListQuery } from "./types";

export async function listDocuments(
  params: ListQuery = {},
): Promise<PaginatedResponse<DocSubmission>> {
  return apiFetch<PaginatedResponse<DocSubmission>>(
    `/api/document-submissions${buildQuery(params)}`,
  );
}

export async function getDocument(id: string | number): Promise<DocSubmissionDetail> {
  const payload = await apiFetch<{ data: DocSubmissionDetail }>(
    `/api/document-submissions/${id}`,
  );
  return payload.data;
}

export async function reviewDocument(
  id: string | number,
  decision: string,
  notes: string,
): Promise<{ data: DocSubmissionDetail }> {
  return apiFetch(`/api/document-submissions/${id}/review`, {
    method: "POST",
    body: JSON.stringify({ decision, notes }),
  });
}
