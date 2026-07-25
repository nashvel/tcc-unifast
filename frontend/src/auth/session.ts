import { reactive } from "vue";
import { fetchCurrentUser } from "@/api/auth";

export type AuthUser = {
  id: number;
  name: string;
  email: string;
  role: "admin" | "head" | "staff" | "student";
  student_id: string | null;
  account_status?: "active" | "unverified" | "pending_kyc" | "blocked";
  kyc_status?: string | null;
};

const TOKEN_KEY = "unifast_auth_token";

export const authSession = reactive<{ user: AuthUser | null; loaded: boolean }>({
  user: null,
  loaded: false,
});

export function getAuthToken(): string | null {
  if (typeof localStorage === "undefined") return null;
  return localStorage.getItem(TOKEN_KEY);
}

export function setAuthToken(token: string) {
  localStorage.setItem(TOKEN_KEY, token);
}

export function clearAuthToken() {
  localStorage.removeItem(TOKEN_KEY);
}

/** @deprecated Use getAuthToken() instead. Kept for backward compatibility. */
export const csrfToken = getAuthToken;

export async function loadAuthUser() {
  if (!getAuthToken()) {
    authSession.loaded = true;
    return null;
  }
  authSession.user = await fetchCurrentUser();
  authSession.loaded = true;
  return authSession.user;
}
