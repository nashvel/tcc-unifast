<script setup lang="ts">
import { onMounted } from "vue";
import { useRouter } from "vue-router";
import { IconAlertTriangle, IconCheck, IconId, IconSchool, IconUser } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import CardSkeleton from "@/components/ui/CardSkeleton.vue";
import { useStudentKyc } from "@/composables/useStudentKyc";
import { toast } from "@/composables/useToast";

const router = useRouter();
const { loading, busy, error, mismatches, reference, form, loadKyc, submit } = useStudentKyc();

onMounted(loadKyc);

async function handleSubmit() {
  const success = await submit();
  if (success) {
    await router.push("/student");
    toast.success("KYC profile submitted");
  } else {
    toast.error(error.value);
  }
}
</script>

<template>
  <div>
    <PageHeader
      title="KYC Profile Validation"
      description="Complete your profile. Your name, student ID, and program must match the CHED masterlist."
    />

    <div v-if="loading" class="space-y-4">
      <CardSkeleton :lines="3" />
      <CardSkeleton :lines="6" />
    </div>

    <form v-else class="space-y-4" @submit.prevent="handleSubmit">
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
