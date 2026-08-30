import { apiFetch } from "../client";
import type { VaultResponse, VaultDocument } from "../types";

export async function getRequirementVault(): Promise<VaultResponse> {
  return apiFetch<VaultResponse>("/api/student/requirement-vault");
}

export async function uploadVaultDocument(formData: FormData): Promise<{ data: VaultDocument }> {
  return apiFetch("/api/student/requirement-vault/document", {
    method: "POST",
    body: formData,
  });
}

export async function confirmSubmission(): Promise<{
  grantee: { submission_status: string };
}> {
  return apiFetch("/api/student/requirement-vault/confirm", {
    method: "POST",
  });
}
