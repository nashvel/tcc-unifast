import { reactive } from "vue";

export type AuthUser = {
  id: number;
  name: string;
  email: string;
  role: "admin" | "head" | "staff" | "student";
  student_id: string | null;
  account_status?: "active" | "unverified" | "pending_kyc" | "blocked";
  kyc_status?: string | null;
};
export const authSession = reactive<{ user: AuthUser | null; loaded: boolean }>({
  user: null,
  loaded: false,
});
export const csrfToken = () =>
  typeof document === "undefined"
    ? ""
    : (document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? "");
export async function loadAuthUser() {
  if (typeof fetch === "undefined") {
    authSession.loaded = true;
    return null;
  }
  const response = await fetch("/api/auth/me", { headers: { Accept: "application/json" } });
  authSession.user = response.ok ? (await response.json()).user : null;
  authSession.loaded = true;
  return authSession.user;
}
