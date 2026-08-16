<script setup lang="ts">
/**
 * Activation Seeder — Admin UI
 *
 * Allows admins to create activation-ready grantees without CLI.
 * Each submission creates:
 *  - a Batch (or reuses an existing one)
 *  - a User + Grantee + MasterlistRow (all linked)
 *  - a fresh ActivationToken (old unused tokens are invalidated)
 *
 * The resulting activation URL is shown and can be copied or opened directly.
 */
import { ref, computed, reactive } from "vue";
import { useQuery, useMutation } from "@tanstack/vue-query";
import {
  IconSeedling,
  IconLink,
  IconCopy,
  IconExternalLink,
  IconCheck,
  IconAlertCircle,
  IconRefresh,
  IconUserPlus,
  IconChevronDown,
} from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import { apiFetch } from "@/api";

// ── Types ─────────────────────────────────────────────────────────────────────

type BatchOption = {
  id: number;
  name: string;
  academic_year: string;
  semester: string;
  status: string;
  is_active: boolean;
};

type SeededResult = {
  user_id: number;
  grantee_id: number;
  batch_id: number;
  batch_name: string;
  student_id: string;
  full_name: string;
  email: string;
  program: string;
  plain_token: string;
  activation_url: string;
  expires_at: string;
  reset_kyc: boolean;
};

// ── Form state ────────────────────────────────────────────────────────────────

const batchMode = ref<"existing" | "new">("existing");

const form = reactive({
  batch_id: null as number | null,
  batch_name: "",
  academic_year: "",
  semester: "1st Semester",
  student_id: "",
  student_number: "",
  full_name: "",
  email: "",
  program: "BSIT",
  year_level: "1",
  reset_kyc: false,
});

const errors = reactive<Record<string, string>>({});
const results = ref<SeededResult[]>([]);
const copiedIdx = ref<number | null>(null);

// ── Batch list query ──────────────────────────────────────────────────────────

const batchesQuery = useQuery({
  queryKey: ["activation-seeder-batches"],
  queryFn: () => apiFetch<{ data: BatchOption[] }>("/api/activation-seeder/batches"),
});

const batchOptions = computed(() => batchesQuery.data.value?.data ?? []);

// ── Seed mutation ─────────────────────────────────────────────────────────────

const seedMutation = useMutation({
  mutationFn: (payload: Record<string, unknown>) =>
    apiFetch<{ data: SeededResult }>("/api/activation-seeder", {
      method: "POST",
      body: JSON.stringify(payload),
    }),
  onSuccess: (res) => {
    results.value.unshift(res.data);
    // clear form for next entry, keep batch selection
    form.student_id = "";
    form.student_number = "";
    form.full_name = "";
    form.email = "";
    form.reset_kyc = false;
    Object.keys(errors).forEach((k) => delete (errors as Record<string, string>)[k]);
  },
  onError: (err: any) => {
    const detail = err?.response?.data?.errors ?? {};
    Object.keys(errors).forEach((k) => delete (errors as Record<string, string>)[k]);
    Object.assign(errors, detail);
  },
});

// ── Validation ────────────────────────────────────────────────────────────────

function validate(): boolean {
  Object.keys(errors).forEach((k) => delete (errors as Record<string, string>)[k]);

  if (batchMode.value === "existing" && !form.batch_id) {
    errors.batch_id = "Select an existing batch.";
  }
  if (batchMode.value === "new") {
    if (!form.batch_name.trim()) errors.batch_name = "Batch name is required.";
    if (!form.academic_year.trim()) errors.academic_year = "Academic year is required (e.g. 2026-2027).";
    if (!form.semester.trim()) errors.semester = "Semester is required.";
  }
  if (!form.student_id.trim()) errors.student_id = "Student ID is required.";
  if (!form.student_number.trim()) errors.student_number = "Student number is required.";
  if (!form.full_name.trim()) errors.full_name = "Full name is required.";
  if (!form.email.trim()) errors.email = "Email is required.";
  else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) errors.email = "Must be a valid email.";
  if (!form.program.trim()) errors.program = "Program is required.";

  return Object.keys(errors).length === 0;
}

// ── Submit ────────────────────────────────────────────────────────────────────

function onSubmit() {
  if (!validate()) return;

  const payload: Record<string, unknown> = {
    student_id:     form.student_id.trim(),
    student_number: form.student_number.trim(),
    full_name:      form.full_name.trim(),
    email:          form.email.trim().toLowerCase(),
    program:        form.program.trim(),
    year_level:     form.year_level || "1",
    reset_kyc:      form.reset_kyc,
  };

  if (batchMode.value === "existing") {
    payload.batch_id = form.batch_id;
  } else {
    payload.batch_name    = form.batch_name.trim();
    payload.academic_year = form.academic_year.trim();
    payload.semester      = form.semester.trim();
  }

  seedMutation.mutate(payload);
}

// ── Helpers ───────────────────────────────────────────────────────────────────

async function copyUrl(url: string, idx: number) {
  await navigator.clipboard.writeText(url);
  copiedIdx.value = idx;
  setTimeout(() => (copiedIdx.value = null), 2000);
}

function formatExpiry(iso: string): string {
  return new Date(iso).toLocaleDateString("en-PH", {
    year: "numeric", month: "short", day: "numeric",
  });
}
</script>

<template>
  <div class="mx-auto max-w-4xl space-y-6 p-6">
    <PageHeader
      title="Activation Link Seeder"
      subtitle="Create activation-ready grantees without the CLI. Each entry creates the batch record, masterlist row, and a fresh activation URL."
    />

    <!-- ── Form Card ─────────────────────────────────────────────────────── -->
    <form
      id="activation-seeder-form"
      class="rounded-xl border bg-surface shadow-sm"
      @submit.prevent="onSubmit"
    >
      <!-- Header -->
      <div class="flex items-center gap-3 border-b px-6 py-4">
        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10">
          <IconSeedling class="text-primary" :size="18" />
        </div>
        <div>
          <h2 class="text-sm font-semibold text-text">New Grantee Activation</h2>
          <p class="text-xs text-text-muted">All fields required unless marked optional</p>
        </div>
      </div>

      <div class="space-y-5 p-6">

        <!-- ── Batch Section ──────────────────────────────────────────────── -->
        <fieldset class="space-y-3">
          <legend class="text-xs font-semibold uppercase tracking-wide text-text-muted">Batch</legend>

          <!-- Mode toggle -->
          <div class="flex overflow-hidden rounded-lg border text-sm">
            <button
              type="button"
              class="flex-1 py-2 text-center transition"
              :class="batchMode === 'existing'
                ? 'bg-primary text-white font-medium'
                : 'bg-surface text-text-muted hover:bg-surface-muted'"
              @click="batchMode = 'existing'"
            >
              Use Existing Batch
            </button>
            <button
              type="button"
              class="flex-1 py-2 text-center transition"
              :class="batchMode === 'new'
                ? 'bg-primary text-white font-medium'
                : 'bg-surface text-text-muted hover:bg-surface-muted'"
              @click="batchMode = 'new'"
            >
              Create New Batch
            </button>
          </div>

          <!-- Existing batch picker -->
          <div v-if="batchMode === 'existing'">
            <label class="mb-1 block text-xs font-medium text-text">Batch</label>
            <div class="relative">
              <select
                id="batch-select"
                v-model="form.batch_id"
                class="h-10 w-full appearance-none rounded-lg border bg-surface px-3 pr-8 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20"
                :class="errors.batch_id ? 'border-danger' : ''"
              >
                <option :value="null" disabled>— Select batch —</option>
                <option
                  v-for="b in batchOptions"
                  :key="b.id"
                  :value="b.id"
                >
                  {{ b.name }} ({{ b.academic_year }} {{ b.semester }})
                  {{ b.is_active ? "✓ Active" : "" }}
                </option>
              </select>
              <IconChevronDown class="pointer-events-none absolute right-2.5 top-2.5 text-text-muted" :size="16" />
            </div>
            <p v-if="errors.batch_id" class="mt-1 text-xs text-danger">{{ errors.batch_id }}</p>
            <p v-if="batchesQuery.isLoading.value" class="mt-1 text-xs text-text-muted">Loading batches…</p>
          </div>

          <!-- New batch fields -->
          <div v-else class="grid gap-3 sm:grid-cols-3">
            <div class="sm:col-span-3">
              <label class="mb-1 block text-xs font-medium text-text">Batch Name</label>
              <input
                id="batch-name"
                v-model="form.batch_name"
                type="text"
                class="h-10 w-full rounded-lg border bg-surface px-3 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20"
                :class="errors.batch_name ? 'border-danger' : ''"
                placeholder="TES AY 2026-2027 1st Semester"
              />
              <p v-if="errors.batch_name" class="mt-1 text-xs text-danger">{{ errors.batch_name }}</p>
            </div>
            <div class="sm:col-span-1">
              <label class="mb-1 block text-xs font-medium text-text">Academic Year</label>
              <input
                id="academic-year"
                v-model="form.academic_year"
                type="text"
                class="h-10 w-full rounded-lg border bg-surface px-3 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20"
                :class="errors.academic_year ? 'border-danger' : ''"
                placeholder="2026-2027"
              />
              <p v-if="errors.academic_year" class="mt-1 text-xs text-danger">{{ errors.academic_year }}</p>
            </div>
            <div class="sm:col-span-2">
              <label class="mb-1 block text-xs font-medium text-text">Semester</label>
              <div class="relative">
                <select
                  id="semester"
                  v-model="form.semester"
                  class="h-10 w-full appearance-none rounded-lg border bg-surface px-3 pr-8 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20"
                >
                  <option>1st Semester</option>
                  <option>2nd Semester</option>
                  <option>Summer</option>
                </select>
                <IconChevronDown class="pointer-events-none absolute right-2.5 top-2.5 text-text-muted" :size="16" />
              </div>
            </div>
          </div>
        </fieldset>

        <div class="border-t" />

        <!-- ── Grantee Fields ──────────────────────────────────────────────── -->
        <fieldset class="space-y-3">
          <legend class="text-xs font-semibold uppercase tracking-wide text-text-muted">Grantee Info</legend>

          <div class="grid gap-3 sm:grid-cols-2">
            <div>
              <label class="mb-1 block text-xs font-medium text-text">Student ID</label>
              <input
                id="student-id"
                v-model="form.student_id"
                type="text"
                class="h-10 w-full rounded-lg border bg-surface px-3 text-sm font-mono focus:border-primary focus:ring-2 focus:ring-primary/20"
                :class="errors.student_id ? 'border-danger' : ''"
                placeholder="20232131"
              />
              <p v-if="errors.student_id" class="mt-1 text-xs text-danger">{{ errors.student_id }}</p>
            </div>

            <div>
              <label class="mb-1 block text-xs font-medium text-text">Student Number</label>
              <input
                id="student-number"
                v-model="form.student_number"
                type="text"
                class="h-10 w-full rounded-lg border bg-surface px-3 text-sm font-mono focus:border-primary focus:ring-2 focus:ring-primary/20"
                :class="errors.student_number ? 'border-danger' : ''"
                placeholder="20232131"
              />
              <p v-if="errors.student_number" class="mt-1 text-xs text-danger">{{ errors.student_number }}</p>
            </div>

            <div class="sm:col-span-2">
              <label class="mb-1 block text-xs font-medium text-text">Full Name</label>
              <input
                id="full-name"
                v-model="form.full_name"
                type="text"
                class="h-10 w-full rounded-lg border bg-surface px-3 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20"
                :class="errors.full_name ? 'border-danger' : ''"
                placeholder="Juan dela Cruz"
              />
              <p v-if="errors.full_name" class="mt-1 text-xs text-danger">{{ errors.full_name }}</p>
            </div>

            <div class="sm:col-span-2">
              <label class="mb-1 block text-xs font-medium text-text">Email</label>
              <input
                id="email"
                v-model="form.email"
                type="email"
                class="h-10 w-full rounded-lg border bg-surface px-3 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20"
                :class="errors.email ? 'border-danger' : ''"
                placeholder="juan.delacruz@tcc.edu.ph"
              />
              <p v-if="errors.email" class="mt-1 text-xs text-danger">{{ errors.email }}</p>
            </div>

            <div>
              <label class="mb-1 block text-xs font-medium text-text">Program</label>
              <div class="relative">
                <select
                  id="program"
                  v-model="form.program"
                  class="h-10 w-full appearance-none rounded-lg border bg-surface px-3 pr-8 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20"
                  :class="errors.program ? 'border-danger' : ''"
                >
                  <option>BSIT</option>
                  <option>BSCS</option>
                  <option>BSED</option>
                  <option>BSBA</option>
                  <option>BSCRIM</option>
                  <option>BSN</option>
                  <option>BSHM</option>
                  <option>BSMT</option>
                  <option>BSECE</option>
                </select>
                <IconChevronDown class="pointer-events-none absolute right-2.5 top-2.5 text-text-muted" :size="16" />
              </div>
              <p v-if="errors.program" class="mt-1 text-xs text-danger">{{ errors.program }}</p>
            </div>

            <div>
              <label class="mb-1 block text-xs font-medium text-text">Year Level</label>
              <div class="relative">
                <select
                  id="year-level"
                  v-model="form.year_level"
                  class="h-10 w-full appearance-none rounded-lg border bg-surface px-3 pr-8 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20"
                >
                  <option value="1">1st Year</option>
                  <option value="2">2nd Year</option>
                  <option value="3">3rd Year</option>
                  <option value="4">4th Year</option>
                </select>
                <IconChevronDown class="pointer-events-none absolute right-2.5 top-2.5 text-text-muted" :size="16" />
              </div>
            </div>
          </div>
        </fieldset>

        <div class="border-t" />

        <!-- ── Options ────────────────────────────────────────────────────── -->
        <div class="flex items-start gap-3">
          <input
            id="reset-kyc"
            v-model="form.reset_kyc"
            type="checkbox"
            class="mt-0.5 h-4 w-4 rounded border-border accent-primary"
          />
          <label for="reset-kyc" class="text-sm text-text">
            <span class="font-medium">Reset KYC &amp; identity data</span>
            <span class="block text-xs text-text-muted">
              Wipes existing KYC profile and identity scan so the grantee restarts onboarding from scratch.
            </span>
          </label>
        </div>

        <!-- Error summary -->
        <div
          v-if="seedMutation.isError.value"
          class="flex items-start gap-2 rounded-lg border border-danger/30 bg-danger-soft px-4 py-3 text-sm text-danger"
        >
          <IconAlertCircle :size="16" class="mt-0.5 shrink-0" />
          <div>
            <p class="font-medium">Seeding failed</p>
            <p class="text-xs">
              {{ (seedMutation.error.value as any)?.response?.data?.message ?? "Check the fields above and try again." }}
            </p>
          </div>
        </div>

        <!-- Submit -->
        <div class="flex justify-end">
          <button
            id="submit-seed-btn"
            type="submit"
            class="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow hover:bg-primary-hover disabled:opacity-50 transition"
            :disabled="seedMutation.isPending.value"
          >
            <IconRefresh v-if="seedMutation.isPending.value" :size="16" class="animate-spin" />
            <IconUserPlus v-else :size="16" />
            {{ seedMutation.isPending.value ? "Seeding…" : "Generate Activation Link" }}
          </button>
        </div>
      </div>
    </form>

    <!-- ── Results ──────────────────────────────────────────────────────── -->
    <section v-if="results.length > 0" class="space-y-3">
      <h2 class="text-sm font-semibold text-text">
        Generated Links
        <span class="ml-1 rounded-full bg-primary/10 px-2 py-0.5 text-xs text-primary">
          {{ results.length }}
        </span>
      </h2>

      <div
        v-for="(r, idx) in results"
        :key="r.plain_token"
        class="rounded-xl border bg-surface p-4 shadow-sm"
      >
        <div class="flex flex-wrap items-start justify-between gap-2">
          <div>
            <p class="text-sm font-semibold text-text">{{ r.full_name }}</p>
            <p class="text-xs text-text-muted">
              {{ r.student_id }} · {{ r.program }} · {{ r.email }}
            </p>
            <p class="mt-1 text-xs text-text-muted">
              Batch: <span class="font-medium text-text">{{ r.batch_name }}</span>
              &nbsp;·&nbsp; Grantee ID: {{ r.grantee_id }}
              &nbsp;·&nbsp; Expires: {{ formatExpiry(r.expires_at) }}
            </p>
            <span
              v-if="r.reset_kyc"
              class="mt-1 inline-block rounded-full bg-warning-soft px-2 py-0.5 text-2xs font-medium text-warning"
            >
              KYC reset
            </span>
          </div>

          <div class="flex shrink-0 items-center gap-2">
            <button
              :id="`copy-url-${idx}`"
              type="button"
              class="inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium transition hover:bg-surface-muted"
              :class="copiedIdx === idx ? 'text-success border-success/40 bg-success/5' : ''"
              @click="copyUrl(r.activation_url, idx)"
            >
              <IconCheck v-if="copiedIdx === idx" :size="13" />
              <IconCopy v-else :size="13" />
              {{ copiedIdx === idx ? "Copied!" : "Copy Link" }}
            </button>

            <a
              :href="r.activation_url"
              target="_blank"
              rel="noopener noreferrer"
              class="inline-flex items-center gap-1.5 rounded-lg bg-primary/10 px-3 py-1.5 text-xs font-medium text-primary transition hover:bg-primary/20"
            >
              <IconExternalLink :size="13" />
              Open
            </a>
          </div>
        </div>

        <!-- URL pill -->
        <div class="mt-3 flex items-center gap-2 overflow-hidden rounded-lg border bg-surface-muted px-3 py-2">
          <IconLink :size="13" class="shrink-0 text-text-muted" />
          <code class="flex-1 truncate text-2xs text-text-muted select-all">{{ r.activation_url }}</code>
        </div>
      </div>
    </section>
  </div>
</template>
