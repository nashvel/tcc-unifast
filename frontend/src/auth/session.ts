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
    /** Identity proven, password not yet chosen. */
    | "identity_verified"
    /** Face match rejected — recoverable, restarts the funnel. */
    | "identity_rejected"
    | "blocked";
  kyc_status?: string | null;
  has_security_pin?: boolean;
  onboarding_next_step?:
    | "sso_review"
    | "blocked"
    | "kyc"
    | "id_scan"
    | "liveness"
    | "face_review"
    | "credentials"
    | "done";
  onboarding_path?: string;
};

export const authSession = reactive<{ user: AuthUser | null; loaded: boolean }>({
  user: null,
  loaded: false,
});

export function clearAuthSession() {
  authSession.user = null;
}

export async function loadAuthUser() {
  authSession.user = await fetchCurrentUser();
  authSession.loaded = true;
  return authSession.user;
}
