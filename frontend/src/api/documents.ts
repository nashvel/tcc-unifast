import { apiFetch, buildQuery } from "./client";
import type {
  DocSubmission,
  DocSubmissionDetail,
  DocSubmissionPackage,
  PaginatedResponse,
  ListQuery,
} from "./types";

export async function listDocuments(
  params: ListQuery = {},
): Promise<PaginatedResponse<DocSubmission>> {
  return apiFetch<PaginatedResponse<DocSubmission>>(
    `/api/document-submissions${buildQuery(params)}`,
  );
}

export async function listDocumentPackages(
  params: ListQuery = {},
): Promise<PaginatedResponse<DocSubmissionPackage>> {
  return apiFetch<PaginatedResponse<DocSubmissionPackage>>(
    `/api/document-submission-packages${buildQuery(params)}`,
  );
}

export async function getDocumentPackage(
  granteeId: string | number,
  batchId: string | number,
): Promise<DocSubmissionPackage> {
  const payload = await apiFetch<{ data: DocSubmissionPackage }>(
    `/api/document-submission-packages/${granteeId}/${batchId}`,
  );
  return payload.data;
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
