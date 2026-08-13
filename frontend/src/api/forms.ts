import { apiFetch, apiFetchBlob, buildQuery } from "./client";
import type {
  Form,
  FormDetail,
  FormField,
  FormResponse,
  FormResponseDetail,
  FormSecurityLog,
  FormSchema,
  AssignedForm,
  PaginatedResponse,
  ListQuery,
} from "./types";

// ─────────────────────────────────────────────────────────
// Admin: Form CRUD
// ─────────────────────────────────────────────────────────

export type FormCreatePayload = {
  title: string;
  description?: string | null;
  target_role: "grantee" | "staff" | "all";
  visibility: "public" | "private";
  batch_id?: number | null;
  is_active?: boolean;
  max_submissions?: number | null;
  closes_at?: string | null;
};

export async function listForms(params: ListQuery = {}): Promise<PaginatedResponse<Form>> {
  return apiFetch<PaginatedResponse<Form>>(`/api/forms${buildQuery(params)}`);
}

export async function getForm(id: string | number): Promise<FormDetail> {
  const payload = await apiFetch<{ data: FormDetail }>(`/api/forms/${id}`);
  return payload.data;
}

export async function createForm(data: FormCreatePayload): Promise<{ data: Form }> {
  return apiFetch<{ data: Form }>("/api/forms", {
    method: "POST",
    body: JSON.stringify(data),
  });
}

export async function updateForm(id: string | number, data: Partial<FormCreatePayload>): Promise<{ data: FormDetail }> {
  return apiFetch<{ data: FormDetail }>(`/api/forms/${id}`, {
    method: "PUT",
    body: JSON.stringify(data),
  });
}

export async function deleteForm(id: string | number): Promise<{ message: string }> {
  return apiFetch<{ message: string }>(`/api/forms/${id}`, { method: "DELETE" });
}

export async function toggleForm(id: string | number): Promise<{ data: Form }> {
  return apiFetch<{ data: Form }>(`/api/forms/${id}/toggle`, { method: "PATCH" });
}

export async function regenerateFormToken(id: string | number): Promise<{ data: { public_token: string } }> {
  return apiFetch<{ data: { public_token: string } }>(`/api/forms/${id}/regenerate-token`, {
    method: "PATCH",
  });
}

// ─────────────────────────────────────────────────────────
// Admin: Field management
// ─────────────────────────────────────────────────────────

export type FieldCreatePayload = {
  label: string;
  field_name: string;
  field_type: string;
  placeholder?: string | null;
  options?: string[] | null;
  is_required?: boolean;
  min_value?: string | null;
  max_value?: string | null;
  min_length?: number | null;
  max_length?: number | null;
  accepted_types?: string | null;
  max_file_size?: number | null;
};

export async function addField(formId: string | number, data: FieldCreatePayload): Promise<{ data: FormField }> {
  return apiFetch<{ data: FormField }>(`/api/forms/${formId}/fields`, {
    method: "POST",
    body: JSON.stringify(data),
  });
}

export async function updateField(
  formId: string | number,
  fieldId: string | number,
  data: Partial<FieldCreatePayload>,
): Promise<{ data: FormField }> {
  return apiFetch<{ data: FormField }>(`/api/forms/${formId}/fields/${fieldId}`, {
    method: "PUT",
    body: JSON.stringify(data),
  });
}

export async function deleteField(
  formId: string | number,
  fieldId: string | number,
): Promise<{ message: string }> {
  return apiFetch<{ message: string }>(`/api/forms/${formId}/fields/${fieldId}`, {
    method: "DELETE",
  });
}

export async function reorderFields(
  formId: string | number,
  order: Record<number, number>,
): Promise<{ message: string }> {
  return apiFetch<{ message: string }>(`/api/forms/${formId}/fields/reorder`, {
    method: "PATCH",
    body: JSON.stringify({ order }),
  });
}

// ─────────────────────────────────────────────────────────
// Admin + Staff: Responses
// ─────────────────────────────────────────────────────────

export async function listFormResponses(
  formId: string | number,
  params: ListQuery = {},
): Promise<PaginatedResponse<FormResponse>> {
  return apiFetch<PaginatedResponse<FormResponse>>(
    `/api/forms/${formId}/responses${buildQuery(params)}`,
  );
}

export async function getFormResponse(
  formId: string | number,
  responseId: string | number,
): Promise<FormResponseDetail> {
  const payload = await apiFetch<{ data: FormResponseDetail }>(
    `/api/forms/${formId}/responses/${responseId}`,
  );
  return payload.data;
}

export async function exportFormResponses(formId: string | number): Promise<Response> {
  return apiFetchBlob(`/api/forms/${formId}/responses/export`);
}

// ─────────────────────────────────────────────────────────
// Admin: Security logs
// ─────────────────────────────────────────────────────────

export async function getSecurityLogs(
  formId: string | number,
  params: ListQuery = {},
): Promise<PaginatedResponse<FormSecurityLog>> {
  return apiFetch<PaginatedResponse<FormSecurityLog>>(
    `/api/forms/${formId}/security-logs${buildQuery(params)}`,
  );
}

// ─────────────────────────────────────────────────────────
// Grantee portal
// ─────────────────────────────────────────────────────────

export async function getAssignedForms(): Promise<{ data: AssignedForm[] }> {
  return apiFetch<{ data: AssignedForm[] }>("/api/forms/assigned");
}

export async function getFormSchema(id: string | number): Promise<{ data: FormSchema }> {
  return apiFetch<{ data: FormSchema }>(`/api/forms/${id}/schema`);
}

export async function submitFormResponse(
  id: string | number,
  data: FormData | Record<string, unknown>,
): Promise<{ success: boolean; message: string }> {
  const isFormData = data instanceof FormData;
  return apiFetch<{ success: boolean; message: string }>(`/api/forms/${id}/responses`, {
    method: "POST",
    body: isFormData ? data : JSON.stringify(data),
    ...(isFormData ? {} : {}),
  });
}

// ─────────────────────────────────────────────────────────
// Public (no auth)
// ─────────────────────────────────────────────────────────

export async function getPublicForm(token: string): Promise<{ data: FormSchema }> {
  return apiFetch<{ data: FormSchema }>(`/api/forms/public/${token}`);
}

export async function submitPublicFormResponse(
  token: string,
  data: FormData | Record<string, unknown>,
): Promise<{ success: boolean; message: string }> {
  const isFormData = data instanceof FormData;
  return apiFetch<{ success: boolean; message: string }>(
    `/api/forms/public/${token}/responses`,
    {
      method: "POST",
      body: isFormData ? data : JSON.stringify(data),
    },
  );
}
