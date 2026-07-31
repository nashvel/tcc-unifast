import { apiFetch } from "../client";

export async function getStudentKyc(): Promise<{
  data: {
    hint: { student_id_last4: string };
    profile: Record<string, string | number | null> | null;
    mismatches: Record<string, string>;
    programs: Array<{ id: number; code: string; name: string }>;
    year_level_options: string[];
    next_step: string;
  };
}> {
  return apiFetch("/api/student/kyc");
}

export async function submitStudentKyc(data: {
  first_name: string;
  middle_name?: string | null;
  last_name: string;
  student_id: string;
  program: string;
  year_level: string;
  birthdate?: string | null;
  contact?: string | null;
  address?: string | null;
  guardian_name?: string | null;
  household_income?: number | null;
}): Promise<{
  data: { status: string; account_status: string; mismatches?: Record<string, string>; next_step?: string };
}> {
  return apiFetch("/api/student/kyc", {
    method: "POST",
    body: JSON.stringify(data),
  });
}
