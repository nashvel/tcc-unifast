import { apiFetch, buildQuery } from "./client";
import type { AcademicRecord, AcademicRecordDetail, PaginatedResponse, ListQuery } from "./types";

export async function listAcademicRecords(
  params: ListQuery = {},
): Promise<PaginatedResponse<AcademicRecord>> {
  return apiFetch<PaginatedResponse<AcademicRecord>>(
    `/api/academic-records${buildQuery(params)}`,
  );
}

export async function getAcademicRecord(id: string | number): Promise<AcademicRecordDetail> {
  const payload = await apiFetch<{ data: AcademicRecordDetail }>(`/api/academic-records/${id}`);
  return payload.data;
}
