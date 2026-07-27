<script setup lang="ts">
import { computed, ref } from "vue";
import { useQuery, useQueryClient } from "@tanstack/vue-query";
import {
  IconAlertTriangle,
  IconCheck,
  IconFileSpreadsheet,
  IconPower,
  IconUpload,
} from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import DataTable from "@/components/tables/DataTable.vue";
import AppDialog from "@/components/dialogs/AppDialog.vue";
import { apiFetch } from "@/api/client";
import type { PaginatedResponse } from "@/api/types";
import { queryKeys } from "@/api/queryKeys";
import { getAuthToken } from "@/auth/session";
import { toast } from "@/composables/useToast";

type Batch = {
  id: number;
  name: string;
  academic_year: string;
  semester: string;
  submission_deadline: string | null;
  is_active: boolean;
  window_status: "draft" | "active" | "closed" | "expired";
  grantees_count: number;
};

type ImportRow = {
  id: number;
  row_number: number;
  student_id: string | null;
  full_name: string | null;
  email: string | null;
  program: string | null;
  year_level: string | null;
  status: "valid" | "invalid";
  errors: string[];
};

type ImportPreview = {
  id: number;
  status: string;
  total_rows: number;
  valid_rows: number;
  invalid_rows: number;
  imported_rows: number;
  rows: ImportRow[];
  batch: { id: number; name: string } | null;
};

const step = ref(1);
const batchDialog = ref(false);
const selectedBatchId = ref<number | null>(null);
const selectedFile = ref<File | null>(null);
const preview = ref<ImportPreview | null>(null);
const busy = ref(false);
const confirming = ref(false);
const activating = ref(false);
const error = ref("");
const form = ref({
  name: "",
  academic_year: "AY 2026-2027",
  semester: "1st Semester",
  submission_deadline: "",
});

const queryClient = useQueryClient();

const batchesQuery = useQuery({
  queryKey: computed(() => [...queryKeys.batches, { page: 1, onboarding: true }]),
  queryFn: () => apiFetch<PaginatedResponse<Batch>>("/api/batches?page=1&per_page=50"),
});

const batches = computed(() => batchesQuery.data.value?.data ?? []);
const selectedBatch = computed(
  () => batches.value.find((batch) => batch.id === selectedBatchId.value) ?? null,
);

async function createBatch() {
  busy.value = true;
  error.value = "";
  try {
    const payload = await apiFetch<{ data: Batch }>("/api/batches", {
      method: "POST",
      body: JSON.stringify(form.value),
    });
    await queryClient.invalidateQueries({ queryKey: queryKeys.batches });
    selectedBatchId.value = payload.data.id;
    batchDialog.value = false;
    form.value = {
      name: "",
      academic_year: "AY 2026-2027",
      semester: "1st Semester",
      submission_deadline: "",
    };
    step.value = 2;
    toast.success("Batch created");
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "Unable to create batch.";
    toast.error(error.value);
  } finally {
    busy.value = false;
  }
}

function chooseFile(event: Event) {
  const input = event.target as HTMLInputElement;
  selectedFile.value = input.files?.[0] ?? null;
  preview.value = null;
}

async function previewImport() {
  if (!selectedBatchId.value) {
    error.value = "Select or create a batch with a submission deadline first.";
    return;
  }
  if (!selectedFile.value) {
    error.value = "Choose a CHED Excel/CSV masterlist file first.";
    return;
  }

  busy.value = true;
  error.value = "";
  const body = new FormData();
  body.append("file", selectedFile.value);
  body.append("batch_id", String(selectedBatchId.value));

  try {
    const response = await fetch("/api/masterlist/imports/preview", {
      method: "POST",
      headers: { Authorization: `Bearer ${getAuthToken()}`, Accept: "application/json" },
      body,
    });
    const payload = await response.json();
    if (!response.ok) {
      const validation = payload.errors ? Object.values(payload.errors).flat().join(" ") : "";
      throw new Error(validation || payload.message || "Unable to preview import.");
    }
    preview.value = payload.data;
    step.value = 3;
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "Unable to preview import.";
    toast.error(error.value);
  } finally {
    busy.value = false;
  }
}

async function confirmImport() {
  if (!preview.value) return;
  confirming.value = true;
  error.value = "";
  try {
    const response = await fetch(`/api/masterlist/imports/${preview.value.id}/confirm`, {
      method: "POST",
      headers: { Authorization: `Bearer ${getAuthToken()}`, Accept: "application/json" },
    });
    const payload = await response.json();
    if (!response.ok && response.status !== 207) {
      throw new Error(payload.message || "Unable to confirm import.");
    }
    preview.value = payload.data;
    toast.success(
      `Imported ${payload.data.imported_rows} grantees. Invites sent: ${payload.mail?.sent ?? 0}.`,
    );
    await queryClient.invalidateQueries({ queryKey: queryKeys.batches });
    step.value = 4;
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "Unable to confirm import.";
    toast.error(error.value);
  } finally {
    confirming.value = false;
  }
}

async function activateBatch() {
  if (!selectedBatchId.value) return;
  activating.value = true;
  error.value = "";
  try {
    await apiFetch(`/api/batches/${selectedBatchId.value}/activate`, { method: "POST" });
    await queryClient.invalidateQueries({ queryKey: queryKeys.batches });
    toast.success("Batch activated — only one submission window is active.");
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "Unable to activate batch.";
    toast.error(error.value);
  } finally {
    activating.value = false;
  }
}

function selectExistingBatch(id: number) {
  selectedBatchId.value = id;
  preview.value = null;
  step.value = 2;
}
</script>

<template>
  <div>
    <PageHeader
      title="Onboarding Center"
      description="Create a batch, import the CHED masterlist, send invites, then open one active submission window."
    />

    <ol class="mb-4 grid gap-2 md:grid-cols-4">
      <li
        v-for="item in [
          [1, 'Create / select batch'],
          [2, 'Upload Excel'],
          [3, 'Preview & confirm'],
          [4, 'Activate window'],
        ]"
        :key="item[0]"
        :class="[
          'rounded-lg border px-3 py-2 text-xs',
          step === item[0] ? 'border-primary bg-primary/5 font-semibold' : 'bg-surface text-text-muted',
        ]"
      >
        Step {{ item[0] }}: {{ item[1] }}
      </li>
    </ol>

    <div v-if="error" class="mb-4 flex gap-2 rounded-md border border-danger/30 bg-danger-soft p-3 text-xs text-danger">
      <IconAlertTriangle :size="14" />{{ error }}
    </div>

    <section class="mb-4 rounded-lg border bg-surface p-4">
      <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-sm font-semibold">1. Batch</h2>
        <button
          class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-xs text-white"
          @click="batchDialog = true"
        >
          New batch
        </button>
      </div>
      <p class="mb-3 text-xs text-text-muted">
        Only one batch can be active at a time. Import requires an existing batch with a deadline.
      </p>
      <div class="grid gap-2 md:grid-cols-2 xl:grid-cols-3">
        <button
          v-for="batch in batches"
          :key="batch.id"
          type="button"
          :class="[
            'rounded-md border p-3 text-left text-xs',
            selectedBatchId === batch.id ? 'border-primary bg-primary/5' : 'hover:border-primary/40',
          ]"
          @click="selectExistingBatch(batch.id)"
        >
          <p class="font-semibold">{{ batch.name }}</p>
          <p class="mt-1 text-text-muted">
            {{ batch.academic_year }} · {{ batch.semester }} · {{ batch.window_status }}
          </p>
          <p class="mt-1 text-text-muted">
            Deadline:
            {{
              batch.submission_deadline
                ? new Date(batch.submission_deadline).toLocaleString()
                : "missing"
            }}
          </p>
        </button>
      </div>
    </section>

    <section class="mb-4 rounded-lg border bg-surface p-4">
      <h2 class="mb-3 text-sm font-semibold">2. Upload CHED masterlist</h2>
      <label class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-md border border-dashed p-6 text-xs text-text-muted">
        <IconUpload :size="18" />
        <span>{{ selectedFile?.name || "Choose CSV or XLSX file" }}</span>
        <input type="file" accept=".csv,.xlsx,.xls" class="hidden" @change="chooseFile" />
      </label>
      <button
        class="mt-3 inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-xs disabled:opacity-60"
        :disabled="busy || !selectedBatchId || !selectedFile"
        @click="previewImport"
      >
        <IconFileSpreadsheet :size="14" />{{ busy ? "Parsing..." : "Preview import" }}
      </button>
    </section>

    <section v-if="preview" class="mb-4 rounded-lg border bg-surface p-4">
      <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-sm font-semibold">3. Preview</h2>
        <p class="text-xs text-text-muted">
          {{ preview.valid_rows }} valid · {{ preview.invalid_rows }} errors · batch
          {{ preview.batch?.name }}
        </p>
      </div>
      <DataTable
        :headings="['Row', 'Student ID', 'Name', 'Email', 'Program', 'Year', 'Status', 'Errors']"
      >
        <tr
          v-for="row in preview.rows"
          :key="row.id"
          :class="row.status === 'invalid' ? 'bg-danger-soft/60' : ''"
        >
          <td class="px-3 py-2">{{ row.row_number }}</td>
          <td class="px-3 py-2 font-mono">{{ row.student_id }}</td>
          <td class="px-3 py-2">{{ row.full_name }}</td>
          <td class="px-3 py-2">{{ row.email }}</td>
          <td class="px-3 py-2">{{ row.program }}</td>
          <td class="px-3 py-2">{{ row.year_level }}</td>
          <td class="px-3 py-2 capitalize">{{ row.status }}</td>
          <td class="px-3 py-2 text-danger">{{ row.errors.join(" ") }}</td>
        </tr>
      </DataTable>
      <button
        class="mt-3 inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-xs text-white disabled:opacity-60"
        :disabled="confirming || preview.valid_rows < 1 || preview.status === 'imported'"
        @click="confirmImport"
      >
        <IconCheck :size="14" />
        {{
          preview.status === "imported"
            ? "Already imported"
            : confirming
              ? "Importing..."
              : "Confirm import & send invites"
        }}
      </button>
    </section>

    <section class="rounded-lg border bg-surface p-4">
      <h2 class="mb-2 text-sm font-semibold">4. Activate submission window</h2>
      <p class="mb-3 text-xs text-text-muted">
        Activating opens the window for grantees in
        <strong>{{ selectedBatch?.name || "the selected batch" }}</strong>
        and notifies them. Any other active batch is closed automatically.
      </p>
      <button
        class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-xs text-white disabled:opacity-60"
        :disabled="activating || !selectedBatchId || selectedBatch?.window_status === 'active'"
        @click="activateBatch"
      >
        <IconPower :size="14" />
        {{
          selectedBatch?.window_status === "active"
            ? "Already active"
            : activating
              ? "Activating..."
              : "Activate batch"
        }}
      </button>
    </section>

    <AppDialog
      v-model="batchDialog"
      title="Create batch"
      description="Name, academic year, semester, and submission deadline are required."
      size="sm"
    >
      <div class="grid gap-3">
        <label class="block text-xs font-medium"
          >Batch name
          <input v-model="form.name" class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm" />
        </label>
        <label class="block text-xs font-medium"
          >Academic year
          <input
            v-model="form.academic_year"
            class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm"
          />
        </label>
        <label class="block text-xs font-medium"
          >Semester
          <input v-model="form.semester" class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm" />
        </label>
        <label class="block text-xs font-medium"
          >Submission deadline
          <input
            v-model="form.submission_deadline"
            type="datetime-local"
            class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm"
          />
        </label>
      </div>
      <template #footer="{ close }">
        <button class="rounded-md border px-4 py-2 text-xs" @click="close">Cancel</button>
        <button
          class="rounded-md bg-primary px-4 py-2 text-xs text-white disabled:opacity-60"
          :disabled="busy || !form.name || !form.submission_deadline"
          @click="createBatch"
        >
          {{ busy ? "Saving..." : "Create batch" }}
        </button>
      </template>
    </AppDialog>
  </div>
</template>
