<script setup lang="ts">
import { computed, ref } from "vue";
import {
  IconAlertTriangle,
  IconCheck,
  IconFileSpreadsheet,
  IconMail,
  IconSearch,
  IconUpload,
} from "@tabler/icons-vue";
import DataTable from "@/components/tables/DataTable.vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import AppDialog from "@/components/dialogs/AppDialog.vue";
import { getAuthToken } from "@/auth/session";

type ImportRow = {
  id: number;
  row_number: number;
  student_id: string | null;
  student_number: string | null;
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
};

const batchName = ref("TES Batch");
const academicYear = ref("2026-2027");
const semester = ref("1st Semester");
const selectedFile = ref<File | null>(null);
const preview = ref<ImportPreview | null>(null);
const query = ref("");
const busy = ref(false);
const confirming = ref(false);
const error = ref("");
const confirmDialog = ref(false);
const mailResult = ref<{ sent: number; failed: { email: string; message: string }[] } | null>(null);

const rows = computed(() => {
  const q = query.value.toLowerCase();
  return (preview.value?.rows ?? []).filter((row) =>
    `${row.student_id ?? ""} ${row.student_number ?? ""} ${row.full_name ?? ""} ${row.email ?? ""} ${row.program ?? ""}`
      .toLowerCase()
      .includes(q),
  );
});

function chooseFile(event: Event) {
  const input = event.target as HTMLInputElement;
  selectedFile.value = input.files?.[0] ?? null;
  preview.value = null;
  mailResult.value = null;
}

async function previewImport() {
  if (!selectedFile.value) {
    error.value = "Choose a CSV or XLSX masterlist file first.";
    return;
  }

  busy.value = true;
  error.value = "";
  mailResult.value = null;
  const body = new FormData();
  body.append("file", selectedFile.value);
  body.append("batch_name", batchName.value);
  body.append("academic_year", academicYear.value);
  body.append("semester", semester.value);

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
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "Unable to preview import.";
  } finally {
    busy.value = false;
  }
}

async function confirmImport(close: () => void) {
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
    mailResult.value = payload.mail;
    close();
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "Unable to confirm import.";
  } finally {
    confirming.value = false;
  }
}
</script>

<template>
  <div>
    <PageHeader
      title="Masterlist Import"
      description="Upload the CHED masterlist, preview row errors, then create grantee accounts."
    />

    <section class="mb-4 grid gap-4 rounded-lg border bg-surface p-4 lg:grid-cols-4">
      <label class="block">
        <span class="mb-1.5 block text-xs font-medium">Batch name</span>
        <input v-model="batchName" class="h-9 w-full rounded-md border px-3 text-sm" />
      </label>
      <label class="block">
        <span class="mb-1.5 block text-xs font-medium">Academic year</span>
        <input v-model="academicYear" class="h-9 w-full rounded-md border px-3 text-sm" />
      </label>
      <label class="block">
        <span class="mb-1.5 block text-xs font-medium">Semester</span>
        <input v-model="semester" class="h-9 w-full rounded-md border px-3 text-sm" />
      </label>
      <label class="block">
        <span class="mb-1.5 block text-xs font-medium">CHED file</span>
        <input
          type="file"
          accept=".csv,.xlsx"
          class="block w-full text-xs"
          data-tour="masterlist-upload"
          @change="chooseFile"
        />
      </label>
      <div class="flex items-end lg:col-span-4">
        <button
          :disabled="busy"
          class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-xs font-medium text-white disabled:opacity-60"
          @click="previewImport"
        >
          <IconUpload :size="14" />{{ busy ? "Processing..." : "Preview import" }}
        </button>
      </div>
    </section>

    <p
      v-if="error"
      class="mb-4 flex gap-2 rounded-md border border-danger/30 bg-danger-soft p-3 text-xs text-danger"
    >
      <IconAlertTriangle :size="14" />{{ error }}
    </p>

    <div v-if="preview" class="space-y-4">
      <section class="grid grid-cols-2 gap-3 sm:grid-cols-4" data-tour="masterlist-stats">
        <article
          v-for="item in [
            ['Total rows', preview.total_rows, 'text-text'],
            ['Valid rows', preview.valid_rows, 'text-success'],
            ['Error rows', preview.invalid_rows, 'text-danger'],
            ['Imported', preview.imported_rows, 'text-primary'],
          ]"
          :key="String(item[0])"
          class="rounded-lg border bg-surface p-3"
        >
          <p class="text-xs text-text-muted">{{ item[0] }}</p>
          <p :class="['mt-0.5 text-xl font-semibold tabular-nums', item[2]]">{{ item[1] }}</p>
        </article>
      </section>

      <div
        v-if="preview.invalid_rows"
        class="flex items-start gap-2 rounded-lg border border-warning/30 bg-warning-soft p-3 text-xs"
        data-tour="masterlist-rules"
      >
        <IconAlertTriangle :size="15" class="mt-0.5 shrink-0 text-warning" />
        <div>
          <p class="font-medium text-warning">Rows with errors will be skipped</p>
          <p class="mt-0.5 text-text-muted">
            Fix missing email, duplicate student ID, duplicate student number, or incomplete data
            before confirming if those rows should be included.
          </p>
        </div>
      </div>

      <section class="rounded-lg border bg-surface p-3">
        <div class="relative">
          <IconSearch :size="14" class="absolute left-3 top-1/2 -translate-y-1/2 text-text-soft" />
          <input
            v-model="query"
            class="h-9 w-full rounded-md border pl-9 pr-3 text-xs"
            placeholder="Search preview rows"
          />
        </div>
      </section>

      <DataTable
        :headings="['Row', 'Student ID', 'Name', 'Email', 'Program', 'Year', 'Status']"
      >
        <tr
          v-for="row in rows"
          :key="row.id"
          :class="row.status === 'invalid' ? 'bg-danger-soft/40' : ''"
        >
          <td class="px-3 py-3 font-mono">{{ row.row_number }}</td>
          <td class="px-3 py-3 font-mono">{{ row.student_id || "missing" }}</td>
          <td class="px-3 py-3 font-medium">{{ row.full_name || "missing" }}</td>
          <td class="px-3 py-3 text-text-muted">{{ row.email || "missing" }}</td>
          <td class="px-3 py-3 text-text-muted">{{ row.program || "missing" }}</td>
          <td class="px-3 py-3">{{ row.year_level || "-" }}</td>
          <td class="px-3 py-3">
            <span
              :class="[
                'rounded-full px-2 py-0.5 text-micro',
                row.status === 'valid' ? 'bg-success-soft text-success' : 'bg-danger-soft text-danger',
              ]"
            >
              {{ row.status }}
            </span>
            <p v-if="row.errors.length" class="mt-1 max-w-xs text-micro text-danger">
              {{ row.errors.join(" ") }}
            </p>
          </td>
        </tr>
        <tr v-if="!rows.length">
          <td colspan="7" class="p-8 text-center text-text-muted">No matching rows found.</td>
        </tr>
      </DataTable>

      <section
        v-if="mailResult"
        class="rounded-lg border bg-surface p-4"
      >
        <h2 class="flex items-center gap-2 text-sm font-semibold">
          <IconMail :size="16" class="text-primary" /> Invitation emails
        </h2>
        <p class="mt-1 text-xs text-text-muted">
          {{ mailResult.sent }} activation emails sent. {{ mailResult.failed.length }} failed.
        </p>
      </section>

      <div class="flex justify-end">
        <button
          :disabled="preview.status === 'imported' || preview.valid_rows === 0"
          class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-xs font-medium text-white disabled:opacity-60"
          @click="confirmDialog = true"
        >
          <IconCheck :size="14" />
          {{ preview.status === "imported" ? "Import confirmed" : `Confirm import (${preview.valid_rows} accounts)` }}
        </button>
      </div>
    </div>

    <section
      v-else
      class="flex min-h-64 flex-col items-center justify-center rounded-lg border-2 border-dashed bg-surface p-8 text-center"
    >
      <span class="mb-3 grid size-12 place-items-center rounded-full bg-primary-soft text-primary">
        <IconFileSpreadsheet :size="24" />
      </span>
      <span class="text-sm font-semibold">Upload a CHED CSV or XLSX file</span>
      <span class="mt-1 text-xs text-text-muted">
        Required columns: student ID, name, email, program, year level, and optional student number.
      </span>
    </section>

    <AppDialog
      v-model="confirmDialog"
      title="Confirm masterlist import"
      :description="preview ? `${preview.valid_rows} valid rows will create accounts and receive activation emails.` : ''"
      size="sm"
    >
      <p class="text-sm text-text-muted">
        New accounts will start as unverified. Each grantee receives a one-time activation link and
        temporary password.
      </p>
      <template #footer="{ close }">
        <button class="rounded-md border px-4 py-2 text-xs" @click="close">Cancel</button>
        <button
          class="rounded-md bg-primary px-4 py-2 text-xs text-white disabled:opacity-60"
          :disabled="confirming"
          @click="confirmImport(close)"
        >
          {{ confirming ? "Importing..." : "Confirm import" }}
        </button>
      </template>
    </AppDialog>
  </div>
</template>
