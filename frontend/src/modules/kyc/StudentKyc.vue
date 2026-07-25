<script setup lang="ts">
import { onMounted, reactive, ref } from "vue";
import { useRouter } from "vue-router";
import { IconAlertTriangle, IconCheck, IconId, IconSchool, IconUser } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import CardSkeleton from "@/components/ui/CardSkeleton.vue";
import { authSession, csrfToken } from "@/auth/session";
import { toast } from "@/composables/useToast";

type KycResponse = {
  status: string;
  mismatches: Record<string, string>;
  reference: {
    full_name: string;
    student_id: string;
    program: string;
    year_level: string | null;
  };
};

const router = useRouter();
const loading = ref(true);
const busy = ref(false);
const error = ref("");
const mismatches = ref<Record<string, string>>({});
const reference = ref<KycResponse["reference"] | null>(null);
const form = reactive({
  full_name: "",
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
    const response = await fetch("/api/student/kyc", { headers: { Accept: "application/json" } });
    const payload = await response.json();
    if (!response.ok) throw new Error(payload.message || "Unable to load KYC profile.");
    reference.value = payload.data.reference;
    mismatches.value = payload.data.mismatches || {};
    form.full_name = payload.data.profile?.full_name || payload.data.reference.full_name || "";
    form.student_id = payload.data.profile?.student_id || payload.data.reference.student_id || "";
    form.program = payload.data.profile?.program || payload.data.reference.program || "";
    form.year_level = payload.data.profile?.year_level || payload.data.reference.year_level || "";
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
    const response = await fetch("/api/student/kyc", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": csrfToken(),
        Accept: "application/json",
      },
      body: JSON.stringify({
        ...form,
        household_income: form.household_income === "" ? null : Number(form.household_income),
      }),
    });
    const payload = await response.json();
    if (!response.ok) {
      mismatches.value = payload.data?.mismatches || {};
      const validation = payload.errors ? Object.values(payload.errors).flat().join(" ") : "";
      throw new Error(validation || "KYC details did not match the CHED masterlist.");
    }
    authSession.user = authSession.user
      ? { ...authSession.user, account_status: payload.data.account_status, kyc_status: payload.data.status }
      : null;
    await router.push("/student/onboarding/id-scan");
    toast.success("KYC validated — continue with School ID scan");
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "Unable to submit KYC profile.";
    toast.error(error.value);
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <div>
    <PageHeader
      title="KYC Profile Validation"
      description="Complete your profile. Name, student ID, program, and year level must match the CHED masterlist."
    />

    <div v-if="loading" class="space-y-4">
      <CardSkeleton :lines="3" />
      <CardSkeleton :lines="6" />
    </div>

    <form v-else class="space-y-4" @submit.prevent="submit">
      <section v-if="reference" class="rounded-lg border bg-surface p-4">
        <h2 class="flex items-center gap-2 text-sm font-semibold">
          <IconId :size="16" class="text-primary" /> Masterlist reference
        </h2>
        <div class="mt-3 grid gap-3 text-xs md:grid-cols-4">
          <div>
            <p class="text-text-muted">Student ID</p>
            <p class="mt-1 font-mono font-semibold">{{ reference.student_id }}</p>
          </div>
          <div>
            <p class="text-text-muted">Name</p>
            <p class="mt-1 font-semibold">{{ reference.full_name }}</p>
          </div>
          <div>
            <p class="text-text-muted">Program</p>
            <p class="mt-1 font-semibold">{{ reference.program }}</p>
          </div>
          <div>
            <p class="text-text-muted">Year level</p>
            <p class="mt-1 font-semibold">{{ reference.year_level || "Not provided" }}</p>
          </div>
        </div>
      </section>

      <section class="grid gap-4 lg:grid-cols-2">
        <article class="rounded-lg border bg-surface p-4">
          <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold">
            <IconUser :size="16" class="text-primary" /> Personal information
          </h2>
          <div class="grid gap-3">
            <label class="block">
              <span class="mb-1.5 block text-xs font-medium">Full name *</span>
              <input v-model="form.full_name" class="h-9 w-full rounded-md border px-3 text-sm" />
              <p v-if="mismatches.full_name" class="mt-1 text-xs text-danger">
                {{ mismatches.full_name }}
              </p>
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
              <input v-model="form.student_id" class="h-9 w-full rounded-md border px-3 text-sm" />
              <p v-if="mismatches.student_id" class="mt-1 text-xs text-danger">
                {{ mismatches.student_id }}
              </p>
            </label>
            <label class="block">
              <span class="mb-1.5 block text-xs font-medium">Program *</span>
              <input v-model="form.program" class="h-9 w-full rounded-md border px-3 text-sm" />
              <p v-if="mismatches.program" class="mt-1 text-xs text-danger">
                {{ mismatches.program }}
              </p>
            </label>
            <label class="block">
              <span class="mb-1.5 block text-xs font-medium">Year level *</span>
              <input v-model="form.year_level" class="h-9 w-full rounded-md border px-3 text-sm" />
              <p v-if="mismatches.year_level" class="mt-1 text-xs text-danger">
                {{ mismatches.year_level }}
              </p>
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
          <IconCheck :size="15" />{{ busy ? "Validating..." : "Submit and validate KYC" }}
        </button>
      </div>
    </form>
  </div>
</template>
