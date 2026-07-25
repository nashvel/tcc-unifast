import { apiFetch } from "../client";
import type { VaultResponse, VaultDocument, IdentityCheck } from "../types";

export async function getRequirementVault(): Promise<VaultResponse> {
  return apiFetch<VaultResponse>("/api/student/requirement-vault");
}

export async function uploadSchoolId(formData: FormData): Promise<{ data: VaultDocument }> {
  return apiFetch("/api/student/requirement-vault/id", {
    method: "POST",
    body: formData,
  });
}

export async function uploadVaultDocument(formData: FormData): Promise<{ data: VaultDocument }> {
  return apiFetch("/api/student/requirement-vault/document", {
    method: "POST",
    body: formData,
  });
}

export async function submitIdentityCheck(data: {
  challenge_sequence: string[];
  result: string;
  distance: number;
  confidence_score: number;
  consent_accepted: boolean;
}): Promise<{ data: IdentityCheck }> {
  return apiFetch("/api/student/requirement-vault/identity-check", {
    method: "POST",
    body: JSON.stringify(data),
  });
}

export async function confirmSubmission(): Promise<{
  grantee: { submission_status: string };
}> {
  return apiFetch("/api/student/requirement-vault/confirm", {
    method: "POST",
  });
}
