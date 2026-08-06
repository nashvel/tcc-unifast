/** Allowed student routes while KYC / identity onboarding is incomplete. */

export function isStudentOnboardingRoute(path: string): boolean {

  if (path === "/student/kyc") return true;

  if (path === "/student/onboarding" || path.startsWith("/student/onboarding/")) return true;

  return false;

}



function needsKyc(accountStatus: string | null | undefined): boolean {

  return accountStatus === "unverified" || accountStatus === "pending_kyc";

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

    case "pending_identity":

      return "/student/onboarding";

    case "blocked":

      return "/locked";

    default:

      return "/student";

  }

}


