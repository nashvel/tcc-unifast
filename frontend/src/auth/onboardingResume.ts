/** Allowed student routes while KYC / identity onboarding is incomplete. */

export function isStudentOnboardingRoute(path: string): boolean {

  if (path === "/student/kyc") return true;

  // Covers /student/onboarding/set-password, the terminal credential step.
  if (path === "/student/onboarding" || path.startsWith("/student/onboarding/")) return true;

  return false;

}



function needsKyc(accountStatus: string | null | undefined): boolean {

  return (
    accountStatus === "unverified" ||
    accountStatus === "pending_kyc" ||
    // A rejected face match restarts the funnel — recoverable, not locked out.
    accountStatus === "identity_rejected"
  );

}



export function studentHomePath(user: {

  role?: string;

  account_status?: string | null;

  onboarding_path?: string | null;

  onboarding_next_step?: string | null;

}): string {

  if (user.role !== "student") return "/app";

  // Never trust a stale onboarding_path while KYC is still required.

  if (needsKyc(user.account_status)) return "/student/kyc";

  if (user.onboarding_next_step === "kyc") return "/student/kyc";

  if (user.onboarding_path) return user.onboarding_path;

  switch (user.onboarding_next_step ?? user.account_status) {

    case "kyc":

    case "pending_kyc":

    case "unverified":

      return "/student/kyc";

    case "id_scan":

      return "/student/onboarding/id-scan";

    case "liveness":

      return "/student/onboarding/liveness";

    case "face_review":

    case "pending_face_review":

      return "/student/onboarding/pending-review";

    // Identity proven, password not yet chosen (identity-first activation).

    case "credentials":

    case "identity_verified":

      return "/student/onboarding/set-password";

    case "pending_identity":

      return "/student/onboarding";

    case "blocked":

      return "/locked";

    default:

      return "/student";

  }

}


