import { reactive, ref } from "vue";
import { apiFetch } from "@/api";
import { authSession } from "@/auth/session";

export function useStudentKyc() {
  const loading = ref(true);
  const busy = ref(false);
  const error = ref("");
  const form = reactive({
    first_name: "",
    middle_name: "",
    last_name: "",
    student_id: "",
    program: "",
    year_level: "",
    birthdate: "",
    contact: "",
    address: "",
    guardian_name: "",
    household_income: "",
  });

  async function loadKyc() {
    loading.value = true;
    error.value = "";
    try {
      const payload = await apiFetch<{
        data: {
          profile: Record<string, string | number | null> | null;
          mismatches: Record<string, string>;
        };
      }>("/api/student/kyc");
      const profile = payload.data.profile || {};
      form.first_name = String(profile.first_name || "");
      form.middle_name = String(profile.middle_name || "");
      form.last_name = String(profile.last_name || "");
      form.student_id = String(profile.student_id || "");
      form.program = String(profile.program || "");
      form.year_level = String(profile.year_level || "");
      form.birthdate = String(profile.birthdate || "");
      form.contact = String(profile.contact || "");
      form.address = String(profile.address || "");
      form.guardian_name = String(profile.guardian_name || "");
      form.household_income = profile.household_income == null ? "" : String(profile.household_income);
    } catch (exception) {
      error.value = exception instanceof Error ? exception.message : "Unable to load KYC profile.";
    } finally {
      loading.value = false;
    }
  }

  async function submit() {
    busy.value = true;
    error.value = "";
    try {
      const payload = await apiFetch<{
        data: { status: string; account_status: string; mismatches?: Record<string, string> };
      }>("/api/student/kyc", {
        method: "POST",
        body: JSON.stringify({
          first_name: form.first_name.trim(),
          middle_name: form.middle_name.trim() || null,
          last_name: form.last_name.trim(),
          student_id: form.student_id.trim(),
          program: form.program,
          year_level: form.year_level || null,
          birthdate: form.birthdate || null,
          contact: form.contact || null,
          address: form.address || null,
          guardian_name: form.guardian_name || null,
          household_income: form.household_income === "" ? null : Number(form.household_income),
        }),
      });
      authSession.user = authSession.user
        ? {
            ...authSession.user,
            account_status: payload.data.account_status as
              | "active"
              | "unverified"
              | "pending_kyc"
              | "pending_identity"
              | "pending_face_review"
              | "blocked",
            kyc_status: payload.data.status,
            onboarding_next_step: "id_scan",
            onboarding_path: "/student/onboarding/id-scan",
          }
        : null;
      return true;
    } catch (exception) {
      error.value = exception instanceof Error ? exception.message : "Unable to submit KYC profile.";
      return false;
    } finally {
      busy.value = false;
    }
  }

  return { loading, busy, error, form, loadKyc, submit };
}
