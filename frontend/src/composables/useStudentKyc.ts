import { reactive, ref } from "vue";
import { apiFetch } from "@/api";
import { authSession } from "@/auth/session";
import type { KycResponse } from "@/api";

export function useStudentKyc() {
  const loading = ref(true);
  const busy = ref(false);
  const error = ref("");
  const mismatches = ref<Record<string, string>>({});
  const reference = ref<KycResponse["reference"] | null>(null);
  const form = reactive({
    full_name: "",
    student_id: "",
    program: "",
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
          reference: KycResponse["reference"];
          profile: Record<string, string> | null;
          mismatches: Record<string, string>;
        };
      }>("/api/student/kyc");
      reference.value = payload.data.reference;
      mismatches.value = payload.data.mismatches || {};
      form.full_name = payload.data.profile?.full_name || payload.data.reference.full_name || "";
      form.student_id = payload.data.profile?.student_id || payload.data.reference.student_id || "";
      form.program = payload.data.profile?.program || payload.data.reference.program || "";
      form.birthdate = payload.data.profile?.birthdate || "";
      form.contact = payload.data.profile?.contact || "";
      form.address = payload.data.profile?.address || "";
      form.guardian_name = payload.data.profile?.guardian_name || "";
      form.household_income = payload.data.profile?.household_income || "";
    } catch (exception) {
      error.value = exception instanceof Error ? exception.message : "Unable to load KYC profile.";
    } finally {
      loading.value = false;
    }
  }

  async function submit() {
    busy.value = true;
    error.value = "";
    mismatches.value = {};
    try {
      const payload = await apiFetch<{
        data: { status: string; account_status: string; mismatches?: Record<string, string> };
      }>("/api/student/kyc", {
        method: "POST",
        body: JSON.stringify({
          ...form,
          household_income: form.household_income === "" ? null : Number(form.household_income),
        }),
      });
      mismatches.value = payload.data.mismatches || {};
      if (Object.keys(mismatches.value).length > 0) {
        throw new Error("KYC details did not match the CHED masterlist.");
      }
      authSession.user = authSession.user
        ? { ...authSession.user, account_status: payload.data.account_status as "active" | "unverified" | "pending_kyc" | "blocked", kyc_status: payload.data.status }
        : null;
      return true;
    } catch (exception) {
      error.value = exception instanceof Error ? exception.message : "Unable to submit KYC profile.";
      return false;
    } finally {
      busy.value = false;
    }
  }

  return { loading, busy, error, mismatches, reference, form, loadKyc, submit };
}
