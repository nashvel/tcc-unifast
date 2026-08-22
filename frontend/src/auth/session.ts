import { reactive } from "vue";
import { fetchCurrentUser } from "@/api/auth";

export type AuthUser = {
  id: number;
  name: string;
  email: string;
  role: "developer" | "admin" | "staff" | "student";
  student_id: string | null;
  account_status?:
    | "active"
    | "unverified"
    | "pending_kyc"
    | "pending_identity"
    | "pending_face_review"
    | "blocked";
  kyc_status?: string | null;
  has_security_pin?: boolean;
  onboarding_next_step?: "blocked" | "kyc" | "id_scan" | "liveness" | "face_review" | "done";
  onboarding_path?: string;
};

/** In-memory mock-only session flag (never used for real auth cookies). */
const MOCK_SESSION_KEY = "unifast_mock_session";

export const authSession = reactive<{ user: AuthUser | null; loaded: boolean }>({
  user: null,
  loaded: false,
});

export function hasMockSession(): boolean {
  if (typeof sessionStorage === "undefined") return false;
  return sessionStorage.getItem(MOCK_SESSION_KEY) === "1";
}

export function setMockSession(active: boolean) {
  if (typeof sessionStorage === "undefined") return;
  if (active) sessionStorage.setItem(MOCK_SESSION_KEY, "1");
  else sessionStorage.removeItem(MOCK_SESSION_KEY);
}

export function clearAuthSession() {
  authSession.user = null;
  setMockSession(false);
}

export async function loadAuthUser() {
  authSession.user = await fetchCurrentUser();
  authSession.loaded = true;
  return authSession.user;
}
