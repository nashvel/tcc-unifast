import { apiFetch, buildQuery } from "./client";
import type { GranteeRow, GranteeDetail, PaginatedResponse, ListQuery } from "./types";

export async function listGrantees(
  params: ListQuery = {},
): Promise<PaginatedResponse<GranteeRow>> {
  return apiFetch<PaginatedResponse<GranteeRow>>(`/api/grantees${buildQuery(params)}`);
}

export async function getGrantee(id: string | number): Promise<GranteeDetail> {
  const payload = await apiFetch<{ data: GranteeDetail }>(`/api/grantees/${id}`);
  return payload.data;
}
