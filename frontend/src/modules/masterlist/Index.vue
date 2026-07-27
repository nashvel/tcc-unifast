<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import {
  IconAlertTriangle,
  IconCheck,
  IconFileSpreadsheet,
  IconSearch,
  IconUpload,
  IconTrash,
} from "@tabler/icons-vue";
import DataTable from "@/components/tables/DataTable.vue";
import TablePagination from "@/components/tables/TablePagination.vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import AppDialog from "@/components/dialogs/AppDialog.vue";
import { getAuthToken } from "@/auth/session";
import {
  apiFetch,
  buildQuery,
  type PaginatedResponse,
  type PaginationMeta,
} from "@/api";

type Batch = {
  id: number;
  name: string;
  academic_year: string;
  semester: string;
  submission_deadline: string | null;
};

type ImportSummary = {
  id: number;
  status: string;
  original_name: string | null;
  total_rows: number;
  valid_rows: number;
  invalid_rows: number;
  imported_rows: number;
  created_at: string | null;
  batch: {
    id: number;
    name: string;
    academic_year: string;
    semester: string;
  } | null;
};

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

type ImportDetail = {
  id: number;
  status: string;
  original_name?: string | null;
  total_rows: number;
  valid_rows: number;
  invalid_rows: number;
  imported_rows: number;
  rows: ImportRow[];
  batch: {
    id: number;
    name: string;
    academic_year?: string;
    semester?: string;
    submission_deadline?: string | null;
  } | null;
};

const batches = ref<Batch[]>([]);
const filterBatchId = ref<number | null>(null);
const uploadBatchId = ref<number | null>(null);
const imports = ref<ImportSummary[]>([]);
const importsMeta = ref<PaginationMeta | null>(null);
const importsPage = ref(1);
const selectedImportId = ref<number | null>(null);
const selectedFile = ref<File | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const preview = ref<ImportDetail | null>(null);
const query = ref("");
const loadingList = ref(false);
const loadingDetail = ref(false);
const busy = ref(false);
const confirming = ref(false);
const error = ref("");
const confirmDialog = ref(false);
const mailResult = ref<{ sent: number; failed: { email: string; message: string }[] } | null>(null);

const deadlineBatches = computed(() =>
  batches.value.filter((batch) => Boolean(batch.submission_deadline)),
);

const rows = computed(() => {
  const q = query.value.toLowerCase();
  return (preview.value?.rows ?? []).filter((row) =>
    `${row.student_id ?? ""} ${row.student_number ?? ""} ${row.full_name ?? ""} ${row.email ?? ""} ${row.program ?? ""}`
      .toLowerCase()
      .includes(q),
  );
});

onMounted(async () => {
  try {
    const payload = await apiFetch<PaginatedResponse<Batch>>("/api/batches?per_page=50");
    batches.value = payload.data;
    const firstDeadline = deadlineBatches.value[0]?.id ?? null;
    uploadBatchId.value = firstDeadline;
  } catch {
    batches.value = [];
  }
  await loadImports();
});

watch(filterBatchId, () => {
  if (importsPage.value !== 1) {
    importsPage.value = 1;
  } else {
    void loadImports();
  }
});

watch(importsPage, () => {
  void loadImports();
});

async function loadImports() {
  loadingList.value = true;
  error.value = "";
  try {
    const payload = await apiFetch<PaginatedResponse<ImportSummary>>(
      `/api/masterlist/imports${buildQuery({
        page: importsPage.value,
        per_page: 15,
        batch_id: filterBatchId.value,
      })}`,
    );
    imports.value = payload.data;
    importsMeta.value = payload.meta;
  } catch (exception) {
    imports.value = [];
    importsMeta.value = null;
    error.value = exception instanceof Error ? exception.message : "Unable to load masterlist records.";
  } finally {
    loadingList.value = false;
  }
}

function openFilePicker() {
  fileInput.value?.click();
}

async function selectImport(importId: number) {
  selectedImportId.value = importId;
  selectedFile.value = null;
  mailResult.value = null;
  loadingDetail.value = true;
  error.value = "";
  try {
    const payload = await apiFetch<{ data: ImportDetail }>(`/api/masterlist/imports/${importId}`);
    preview.value = payload.data;
    if (payload.data.batch?.id) {
      uploadBatchId.value = payload.data.batch.id;
    }
  } catch (exception) {
    preview.value = null;
    error.value = exception instanceof Error ? exception.message : "Unable to load import detail.";
  } finally {
    loadingDetail.value = false;
  }
}

function chooseFile(event: Event) {
  const input = event.target as HTMLInputElement;
  selectedFile.value = input.files?.[0] ?? null;
  preview.value = null;
  selectedImportId.value = null;
  mailResult.value = null;
}

async function previewImport() {
  if (!uploadBatchId.value) {
    error.value = "Select a batch with a submission deadline first.";
    return;
  }
  if (!selectedFile.value) {
    error.value = "Choose a CSV or XLSX masterlist file first.";
    return;
  }

  busy.value = true;
  error.value = "";
  mailResult.value = null;
  const body = new FormData();
  body.append("file", selectedFile.value);
  body.append("batch_id", String(uploadBatchId.value));

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
    selectedImportId.value = payload.data.id;
    await loadImports();
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
    selectedFile.value = null;
    await loadImports();
    close();
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "Unable to confirm import.";
  } finally {
    confirming.value = false;
  }
}

const deleteDialogOpen = ref(false);
const deletingImport = ref<ImportItem | null>(null);

function openDeleteDialog(item: ImportItem) {
  deletingImport.value = item;
  deleteDialogOpen.value = true;
}

async function deleteImport() {
  if (!deletingImport.value) return;
  busy.value = true;
  try {
    const response = await fetch(`/api/masterlist/imports/${deletingImport.value.id}`, {
      method: "DELETE",
      headers: { Authorization: `Bearer ${getAuthToken()}`, Accept: "application/json" },
    });
    const payload = await response.json();
    if (!response.ok) throw new Error(payload.message || "Unable to delete import.");
    
    // Clear preview if we deleted the currently selected one
    if (selectedImportId.value === deletingImport.value.id) {
      selectedImportId.value = null;
      preview.value = null;
    }
    
    await loadImports();
    deleteDialogOpen.value = false;
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "Unable to delete import.";
  } finally {
    busy.value = false;
  }
}

function formatDate(value: string | null) {
  if (!value) return "—";
  return new Date(value).toLocaleString();
}
</script>

<template>
  <div>
    <PageHeader
      title="Masterlist"
      description="Records uploaded from Onboarding Center appear here. Upload a new file to update."
    />

    <section class="mb-4 rounded-lg border bg-surface p-4">
      <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
        <div>
          <h2 class="text-sm font-semibold">Stored imports</h2>
          <p class="mt-0.5 text-xs text-text-muted">
            Click a row to preview rows. New uploads from
            <RouterLink to="/app/onboarding" class="text-primary hover:underline">Onboarding Center</RouterLink>
            show up in this list.
          </p>
        </div>
        <label class="block min-w-52">
          <span class="mb-1.5 block text-xs font-medium">Filter by batch</span>
          <select v-model="filterBatchId" class="h-9 w-full rounded-md border px-3 text-sm">
            <option :value="null">All batches</option>
            <option v-for="batch in batches" :key="batch.id" :value="batch.id">
              {{ batch.name }} · {{ batch.academic_year }} · {{ batch.semester }}
            </option>
          </select>
        </label>
      </div>

      <DataTable :headings="['File', 'Batch', 'Status', 'Rows', 'Imported', 'Uploaded', '']">
        <tr
          v-for="item in imports"
          :key="item.id"
          :class="[
            'group cursor-pointer hover:bg-primary/5',
            selectedImportId === item.id ? 'bg-primary/5' : '',
          ]"
          @click="selectImport(item.id)"
        >
          <td class="px-3 py-3 font-medium">{{ item.original_name || `Import #${item.id}` }}</td>
          <td class="px-3 py-3 text-text-muted">
            <template v-if="item.batch">
              {{ item.batch.name }} · {{ item.batch.academic_year }} · {{ item.batch.semester }}
            </template>
            <template v-else>—</template>
          </td>
          <td class="px-3 py-3 capitalize">{{ item.status }}</td>
          <td class="px-3 py-3 tabular-nums">
            {{ item.valid_rows }}/{{ item.total_rows }}
            <span v-if="item.invalid_rows" class="text-danger">({{ item.invalid_rows }} err)</span>
          </td>
          <td class="px-3 py-3 tabular-nums">{{ item.imported_rows }}</td>
          <td class="px-3 py-3 text-text-muted">{{ formatDate(item.created_at) }}</td>
          <td class="px-3 py-3 text-right">
            <button
              v-if="item.status !== 'completed'"
              class="text-text-muted hover:text-danger opacity-0 group-hover:opacity-100 transition-opacity"
              title="Delete import"
              @click.stop="openDeleteDialog(item)"
            >
              <IconTrash :size="16" />
            </button>
          </td>
        </tr>
        <tr v-if="!loadingList && !imports.length">
          <td colspan="6" class="p-8 text-center text-text-muted">
            No masterlist records yet. Upload from Onboarding Center or use the form below.
          </td>
        </tr>
        <tr v-if="loadingList">
          <td colspan="6" class="p-8 text-center text-text-muted">Loading records…</td>
        </tr>
        <template v-if="importsMeta" #footer>
          <TablePagination
            :meta="importsMeta"
            :busy="loadingList"
            @update:page="importsPage = $event"
          />
        </template>
      </DataTable>
    </section>

    <section class="mb-4 grid gap-4 rounded-lg border bg-surface p-4 lg:grid-cols-2">
      <div class="lg:col-span-2">
        <h2 class="text-sm font-semibold">Upload updated masterlist</h2>
        <p class="mt-0.5 text-xs text-text-muted">
          Choose a batch and upload a new CHED file when the masterlist needs updating. Preview, then confirm
          to create/update grantee accounts.
        </p>
      </div>
      
      <div v-if="!deadlineBatches.length" class="lg:col-span-2 flex items-center gap-3 rounded-md border border-warning/30 bg-warning-soft p-4">
        <IconAlertTriangle :size="20" class="text-warning" />
        <div>
          <p class="text-sm font-medium text-warning">No active batch detected</p>
          <p class="mt-0.5 text-xs text-warning/80">You must <RouterLink to="/app/batches?create=true" class="underline font-semibold hover:text-warning-dark">create a batch</RouterLink> or activate a submission deadline before you can upload a masterlist.</p>
        </div>
      </div>

      <template v-else>
        <label class="block">
          <span class="mb-1.5 block text-xs font-medium">Batch (with deadline)</span>
          <select v-model.number="uploadBatchId" class="h-9 w-full rounded-md border px-3 text-sm">
            <option :value="null" disabled>Select a batch</option>
            <option v-for="batch in deadlineBatches" :key="batch.id" :value="batch.id">
              {{ batch.name }} · {{ batch.academic_year }} · {{ batch.semester }}
            </option>
          </select>
        </label>

        <div class="flex items-end gap-2">
          <input
            ref="fileInput"
            type="file"
            accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel"
            class="hidden"
            @change="chooseFile"
          />
          <button
            class="flex h-9 w-full items-center justify-center gap-2 rounded-md border bg-surface px-4 text-xs font-medium hover:bg-surface-muted"
            data-tour="masterlist-upload"
            @click="openFilePicker"
          >
            <IconFileSpreadsheet :size="14" /> Upload Excel or CSV file
          </button>
        </div>

        <div v-if="selectedFile" class="lg:col-span-2 flex items-center justify-between rounded-md border bg-surface-muted px-3 py-2">
          <span class="text-xs font-medium">{{ selectedFile.name }}</span>
          <button
            class="flex items-center gap-1 rounded bg-primary px-3 py-1.5 text-xs font-medium text-white disabled:opacity-50"
            :disabled="busy || !uploadBatchId"
            @click="previewImport"
          >
            <IconUpload :size="14" />{{ busy ? "Processing..." : "Preview import" }}
          </button>
        </div>
      </template>
    </section>

    <p
      v-if="error"
      class="mb-4 flex gap-2 rounded-md border border-danger/30 bg-danger-soft p-3 text-xs text-danger"
    >
      <IconAlertTriangle :size="14" />{{ error }}
    </p>

    <p v-if="loadingDetail" class="mb-4 text-xs text-text-muted">Loading import detail…</p>

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



      <div v-if="preview.status !== 'imported'" class="flex justify-end">
        <button
          :disabled="preview.valid_rows === 0"
          class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-xs font-medium text-white disabled:opacity-60"
          @click="confirmDialog = true"
        >
          <IconCheck :size="14" />
          Confirm import ({{ preview.valid_rows }} accounts)
        </button>
      </div>
      <p v-else class="text-right text-xs text-text-muted">This import is confirmed.</p>
    </div>

    <section
      v-else-if="!loadingDetail"
      class="flex min-h-48 flex-col items-center justify-center rounded-lg border-2 border-dashed bg-surface p-8 text-center"
    >
      <span class="mb-3 grid size-12 place-items-center rounded-full bg-primary-soft text-primary">
        <IconFileSpreadsheet :size="24" />
      </span>
      <span class="text-sm font-semibold">Select a stored import or upload a file</span>
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

    <AppDialog
      v-model="deleteDialogOpen"
      title="Delete import record"
      description="Are you sure you want to delete this masterlist import? This action cannot be undone."
    >
      <div class="flex justify-end gap-2 pt-4">
        <button
          type="button"
          class="rounded-md border px-4 py-2 text-xs font-medium hover:bg-surface-muted"
          @click="deleteDialogOpen = false"
        >
          Cancel
        </button>
        <button
          type="button"
          class="rounded-md bg-danger px-4 py-2 text-xs font-medium text-white disabled:opacity-50"
          :disabled="busy"
          @click="deleteImport"
        >
          {{ busy ? "Deleting..." : "Delete record" }}
        </button>
      </div>
    </AppDialog>
  </div>
</template>
