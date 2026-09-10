import { apiFetch, ensureCsrfCookie } from "@/api/client";

export const sisKeys = { all: ["sis-sso"] as const, capabilities: ["auth-capabilities"] as const };
export type SisCapabilities = { sis: { available: boolean; display_name: string | null; maintenance_message: string | null } };
export type SisConnection = {
  id?: number; display_name?: string; issuer?: string; client_id?: string; student_id_claim?: string;
  state: string; callback_uri: string; has_client_secret: boolean; validated_at?: string | null;
  jwks_refreshed_at?: string | null; pilot_successes?: number; pilot_failures?: number; pending_reviews: number; error_code?: string | null;
};
export type PilotStudent = { id: number; name: string; student_id: string; email: string; pilot_id: number | null; successes: number | null; failures: number | null };
export type IdentityDetails = { student_id: string; name: string; email: string };
export type IdentityReview = { id: number; local_claims: IdentityDetails; provider_claims: IdentityDetails; reason: string; status: string; created_at: string; reviewed_at: string | null; decision_notes: string | null };
export type Page<T> = { data: T[]; meta: { current_page: number; last_page: number; total: number } };

export async function sisMutation<T>(path: string, method: string, body?: object): Promise<T> {
  await ensureCsrfCookie();
  return apiFetch<T>(path, { method, body: body ? JSON.stringify(body) : undefined });
}

export async function startSis(remember: boolean, pilotToken?: string): Promise<void> {
  const path = pilotToken ? `/api/auth/sis/pilot/${encodeURIComponent(pilotToken)}/redirect` : "/api/auth/sis/redirect";
  const result = await sisMutation<{ authorization_url: string }>(path, "POST", { remember_me: remember });
  const target = new URL(result.authorization_url);
  if (target.protocol !== "https:") throw new Error("Invalid SIS authorization URL");
  window.location.assign(target.href);
}
