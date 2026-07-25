import { apiFetch } from "../client";

export async function getStudentKyc(): Promise<{
  data: {
    reference: { full_name: string; student_id: string; program: string; year_level: string | null };
    profile: Record<string, string> | null;
    mismatches: Record<string, string>;
  };
}> {
  return apiFetch("/api/student/kyc");
}

export async function submitStudentKyc(data: {
  full_name: string;
  student_id: string;
  program: string;
  birthdate: string;
  contact: string;
  address: string;
  guardian_name: string;
  household_income: number | null;
}): Promise<{
  data: { status: string; account_status: string; mismatches?: Record<string, string> };
}> {
  return apiFetch("/api/student/kyc", {
    method: "POST",
    body: JSON.stringify(data),
  });
}
