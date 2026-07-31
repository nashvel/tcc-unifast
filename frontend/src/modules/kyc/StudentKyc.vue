<script setup lang="ts">
import { apiUrl } from "@/api/client";
import { onMounted, reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { IconAlertTriangle, IconCheck, IconSchool, IconUser } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import CardSkeleton from "@/components/ui/CardSkeleton.vue";
import { authSession, getAuthToken } from "@/auth/session";
import { toast } from "@/composables/useToast";
import { withLang } from "@/i18n/routeLang";

type ProgramOption = { id: number; code: string; name: string };

const router = useRouter();
const route = useRoute();
const loading = ref(true);
const busy = ref(false);
const error = ref("");
const programs = ref<ProgramOption[]>([]);
const yearOptions = ref<string[]>(["1", "2", "3", "4"]);
const hintLast4 = ref<string | null>(null);
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

onMounted(loadKyc);

async function loadKyc() {
  try {
    const token = getAuthToken();
    if (!token) {
      throw new Error("Unauthenticated. Activate or sign in again, then retry KYC.");
    }
    const response = await fetch(apiUrl("/api/student/kyc"), {
      headers: { Accept: "application/json", Authorization: `Bearer ${token}` },
    });
    const payload = await response.json();
    if (!response.ok) throw new Error(payload.message || "Unable to load KYC profile.");

    programs.value = payload.data.programs || [];
    yearOptions.value = payload.data.year_level_options || ["1", "2", "3", "4"];
    hintLast4.value = payload.data.hint?.student_id_last4 ?? null;

    const next = payload.data.next_step as string | undefined;
    if (next === "id_scan") {
      await router.replace(withLang("/student/onboarding/id-scan", route.query.lang));
      return;
    }
    if (next === "liveness") {
      await router.replace(withLang("/student/onboarding/liveness", route.query.lang));
      return;
    }
    if (next === "done") {
      await router.replace(withLang("/student", route.query.lang));
      return;
    }

    const profile = payload.data.profile || {};
    form.first_name = profile.first_name || "";
    form.middle_name = profile.middle_name || "";
    form.last_name = profile.last_name || "";
    form.student_id = profile.student_id || "";
    form.program = profile.program || "";
    form.year_level = profile.year_level || "";
    form.birthdate = profile.birthdate || "";
    form.contact = profile.contact || "";
    form.address = profile.address || "";
    form.guardian_name = profile.guardian_name || "";
    form.household_income = profile.household_income ?? "";
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "Unable to load KYC profile.";
    toast.error(error.value);
  } finally {
    loading.value = false;
  }
}

async function submit() {
  busy.value = true;
  error.value = "";

  try {
    const token = getAuthToken();
    if (!token) {
      throw new Error("Unauthenticated. Activate or sign in again, then retry KYC.");
    }
    if (!form.first_name.trim() || !form.last_name.trim() || !form.student_id.trim() || !form.program || !form.year_level) {
      throw new Error("Enter your first name, last name, student ID, program, and year level.");
    }
    const response = await fetch(apiUrl("/api/student/kyc"), {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${token}`,
        Accept: "application/json",
      },
      body: JSON.stringify({
        first_name: form.first_name.trim(),
        middle_name: form.middle_name.trim() || null,
        last_name: form.last_name.trim(),
        student_id: form.student_id.trim(),
        program: form.program,
        year_level: form.year_level,
        birthdate: form.birthdate || null,
        contact: form.contact || null,
        address: form.address || null,
        guardian_name: form.guardian_name || null,
        household_income: form.household_income === "" ? null : Number(form.household_income),
      }),
    });
    const payload = await response.json();
    if (!response.ok) {
      const validation = payload.errors ? Object.values(payload.errors).flat().join(" ") : "";
      throw new Error(validation || payload.message || "Unable to validate KYC profile.");
    }
    authSession.user = authSession.user
      ? {
          ...authSession.user,
          account_status: payload.data.account_status as AuthAccountStatus,
          kyc_status: payload.data.status,
          onboarding_next_step: "id_scan",
          onboarding_path: "/student/onboarding/id-scan",
        }
      : null;
    await router.push(withLang("/student/onboarding/id-scan", route.query.lang));
    toast.success("KYC confirmed — continue with School ID scan");
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "Unable to submit KYC profile.";
    toast.error(error.value);
  } finally {
    busy.value = false;
  }
}

type AuthAccountStatus = "active" | "unverified" | "pending_kyc" | "pending_identity" | "blocked";
</script>

<template>
  <div>
    <PageHeader
      title="KYC Profile Validation"
      description="Type your identity details exactly as they appear on your records. The server cross-checks them against the CHED masterlist after you submit."
    />

    <div v-if="loading" class="space-y-4">
      <CardSkeleton :lines="3" />
      <CardSkeleton :lines="6" />
    </div>

    <form v-else class="space-y-4" @submit.prevent="submit">
      <section
        v-if="hintLast4"
        class="rounded-lg border border-info/30 bg-info-soft px-4 py-3 text-xs text-text-muted"
      >
        Account bound to masterlist student ID ending in
        <span class="font-mono font-semibold text-text">••••{{ hintLast4 }}</span>.
        Contact UniFAST support if this is not your record.
      </section>

      <section class="grid gap-4 lg:grid-cols-2">
        <article class="rounded-lg border bg-surface p-4">
          <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold">
            <IconUser :size="16" class="text-primary" /> Personal information
          </h2>
          <div class="grid gap-3">
            <div class="grid gap-3 sm:grid-cols-2">
              <label class="block">
                <span class="mb-1.5 block text-xs font-medium">First name *</span>
                <input
                  v-model="form.first_name"
                  required
                  autocomplete="given-name"
                  placeholder="First name"
                  class="h-9 w-full rounded-md border px-3 text-sm"
                />
              </label>
              <label class="block">
                <span class="mb-1.5 block text-xs font-medium">Last name *</span>
                <input
                  v-model="form.last_name"
                  required
                  autocomplete="family-name"
                  placeholder="Last name / surname"
                  class="h-9 w-full rounded-md border px-3 text-sm"
                />
              </label>
            </div>
            <label class="block">
              <span class="mb-1.5 block text-xs font-medium">Middle name</span>
              <input
                v-model="form.middle_name"
                autocomplete="additional-name"
                placeholder="Optional"
                class="h-9 w-full rounded-md border px-3 text-sm"
              />
              <span class="mt-1 block text-micro text-text-muted">
                Case-insensitive match. Middle is optional; if you enter one and the masterlist has a middle name, they must match.
              </span>
            </label>
            <label class="block">
              <span class="mb-1.5 block text-xs font-medium">Birthdate</span>
              <input
                v-model="form.birthdate"
                type="date"
                class="h-9 w-full rounded-md border px-3 text-sm"
              />
            </label>
            <label class="block">
              <span class="mb-1.5 block text-xs font-medium">Contact number</span>
              <input v-model="form.contact" class="h-9 w-full rounded-md border px-3 text-sm" />
            </label>
            <label class="block">
              <span class="mb-1.5 block text-xs font-medium">Address</span>
              <textarea v-model="form.address" rows="4" class="w-full rounded-md border p-3 text-sm" />
            </label>
          </div>
        </article>

        <article class="rounded-lg border bg-surface p-4">
          <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold">
            <IconSchool :size="16" class="text-primary" /> Academic and socio-economic data
          </h2>
          <div class="grid gap-3">
            <label class="block">
              <span class="mb-1.5 block text-xs font-medium">Student ID *</span>
              <input
                v-model="form.student_id"
                required
                autocomplete="off"
                placeholder="Type your student ID"
                class="h-9 w-full rounded-md border px-3 font-mono text-sm"
              />
            </label>
            <label class="block">
              <span class="mb-1.5 block text-xs font-medium">Program *</span>
              <select v-model="form.program" required class="h-9 w-full rounded-md border px-3 text-sm">
                <option value="" disabled>Select program</option>
                <option v-for="row in programs" :key="row.id" :value="row.code">
                  {{ row.code }} — {{ row.name }}
                </option>
              </select>
            </label>
            <label class="block">
              <span class="mb-1.5 block text-xs font-medium">Year level *</span>
              <select v-model="form.year_level" required class="h-9 w-full rounded-md border px-3 text-sm">
                <option value="" disabled>Select year level</option>
                <option v-for="year in yearOptions" :key="year" :value="year">Year {{ year }}</option>
              </select>
            </label>
            <label class="block">
              <span class="mb-1.5 block text-xs font-medium">Guardian name</span>
              <input
                v-model="form.guardian_name"
                class="h-9 w-full rounded-md border px-3 text-sm"
              />
            </label>
            <label class="block">
              <span class="mb-1.5 block text-xs font-medium">Monthly household income</span>
              <input
                v-model="form.household_income"
                type="number"
                min="0"
                class="h-9 w-full rounded-md border px-3 text-sm"
              />
            </label>
          </div>
        </article>
      </section>

      <div v-if="error" class="flex gap-2 rounded-md border border-danger/30 bg-danger-soft p-3 text-xs text-danger">
        <IconAlertTriangle :size="14" />{{ error }}
      </div>

      <div class="flex justify-end">
        <button
          :disabled="busy"
          class="inline-flex h-10 items-center gap-2 rounded-md bg-primary px-4 text-sm font-medium text-white disabled:opacity-60"
        >
          <IconCheck :size="15" />{{ busy ? "Validating..." : "Confirm KYC and continue" }}
        </button>
      </div>
    </form>
  </div>
</template>
