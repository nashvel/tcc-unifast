<script setup lang="ts">
import { apiUrl } from "@/api/client";
import { computed, onMounted, ref, watch } from "vue";
import {
  IconAlertTriangle,
  IconCheck,
  IconFileSpreadsheet,
  IconSearch,
  IconUpload,
  IconTrash,
  IconTableColumn,
  IconCircleCheck,
  IconCircleX,
} from "@tabler/icons-vue";
import DataTable from "@/components/tables/DataTable.vue";
import TablePagination from "@/components/tables/TablePagination.vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import AppDialog from "@/components/dialogs/AppDialog.vue";
import Skeleton from "@/components/ui/Skeleton.vue";
import TableSkeleton from "@/components/ui/TableSkeleton.vue";
import CardSkeleton from "@/components/ui/CardSkeleton.vue";
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
  detection_info?: {
    table_index: number | null;
    raw_headers: string[];
    matched_columns: Record<string, string>;
    unmatched_headers: string[];
    row_count: number;
  } | null;
};

const batches = ref<Batch[]>([]);
const uploadBatchId = ref<number | null>(null);
const loadingBatches = ref(true);
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

const selectedUploadBatch = computed(() =>
  uploadBatchId.value ? batches.value.find((b) => b.id === uploadBatchId.value) ?? null : null,
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
  loadingBatches.value = true;
  try {
    const payload = await apiFetch<PaginatedResponse<Batch>>("/api/batches?per_page=50");
    batches.value = payload.data;
    const firstDeadline = deadlineBatches.value[0]?.id ?? null;
    uploadBatchId.value = firstDeadline;
  } catch {
    batches.value = [];
  } finally {
    loadingBatches.value = false;
  }
  await loadImports();
});

watch(uploadBatchId, () => {
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
        batch_id: uploadBatchId.value,
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

function downloadTemplate() {
  const headers = "student_id,name,email,program,year_level,student_number\n";
  const sampleRow = "2026-001,Juan Dela Cruz,juan@email.com,BSIT,2,SN-1234\n";
  const blob = new Blob([headers + sampleRow], { type: "text/csv" });
  const url = window.URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.style.display = "none";
  a.href = url;
  a.download = "masterlist_template.csv";
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
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
  error.value = "";
}

async function previewImport() {
  if (!uploadBatchId.value) {
    error.value = "Select a batch with a submission deadline first.";
    return;
  }
  if (!selectedFile.value) {
    error.value = "Choose a CSV, XLSX, PDF, or DOCX masterlist file first.";
    return;
  }

  busy.value = true;
  error.value = "";
  mailResult.value = null;
  const body = new FormData();
  body.append("file", selectedFile.value);
  body.append("batch_id", String(uploadBatchId.value));

  try {
    const response = await fetch(apiUrl("/api/masterlist/imports/preview"), {
      method: "POST",
      headers: { Accept: "application/json" },
      credentials: "include",
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
    const response = await fetch(apiUrl(`/api/masterlist/imports/${preview.value.id}/confirm`), {
      method: "POST",
      headers: { Accept: "application/json" },
      credentials: "include",
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
const deletingImport = ref<ImportSummary | null>(null);

function openDeleteDialog(item: ImportSummary) {
  deletingImport.value = item;
  deleteDialogOpen.value = true;
}

async function deleteImport() {
  if (!deletingImport.value) return;
  busy.value = true;
  try {
    const response = await fetch(apiUrl(`/api/masterlist/imports/${deletingImport.value.id}`), {
      method: "DELETE",
      headers: { Accept: "application/json" },
      credentials: "include",
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
      description="Upload a new file to add or update grantees. Stored imports appear below."
    />

    <!-- ══ UPLOAD SECTION (top) ══ -->
    <section class="mb-6 grid gap-6 rounded-lg border bg-surface p-6">
      
      <!-- Progress Indicator -->
      <div class="flex items-center justify-between text-xs font-semibold text-text-muted">
        <span class="flex items-center gap-1.5" :class="{ 'text-primary': !uploadBatchId }">
          <span class="grid size-5 place-items-center rounded-full bg-border text-text">1</span>
          Select Batch
        </span>
        <span class="h-px flex-1 bg-border/60 mx-4"></span>
        <span class="flex items-center gap-1.5" :class="{ 'text-primary': uploadBatchId && !preview && !selectedFile }">
          <span class="grid size-5 place-items-center rounded-full bg-border text-text">2</span>
          Upload
        </span>
        <span class="h-px flex-1 bg-border/60 mx-4"></span>
        <span class="flex items-center gap-1.5" :class="{ 'text-primary': selectedFile || preview }">
          <span class="grid size-5 place-items-center rounded-full bg-border text-text">3</span>
          Review
        </span>
        <span class="h-px flex-1 bg-border/60 mx-4"></span>
        <span class="flex items-center gap-1.5">
          <span class="grid size-5 place-items-center rounded-full bg-border text-text">4</span>
          Import
        </span>
      </div>

      <!-- Skeleton state while batches are loading -->
      <template v-if="loadingBatches">
        <!-- Row 1 Skeleton: Batch selector & Deadline -->
        <div class="grid gap-4 md:grid-cols-[1fr_auto]">
          <div class="space-y-1.5">
            <Skeleton class-name="h-3.5 w-36" />
            <Skeleton class-name="h-10 w-full rounded-md" />
          </div>
          <div class="flex flex-col justify-center rounded-md border bg-surface-muted px-4 py-2 min-w-[200px] space-y-1.5">
            <Skeleton class-name="h-2.5 w-24" />
            <Skeleton class-name="h-4 w-32" />
          </div>
        </div>

        <!-- Row 2 Skeleton: Upload Zone & Instructions -->
        <div class="grid gap-6 md:grid-cols-[2fr_1fr]">
          <div class="space-y-1.5">
            <Skeleton class-name="h-3.5 w-36" />
            <div class="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-border bg-surface-muted p-8 text-center space-y-3">
              <Skeleton class-name="size-12 rounded-full" />
              <Skeleton class-name="h-4 w-48" />
              <Skeleton class-name="h-3 w-32" />
              <div class="mt-2 flex gap-2">
                <Skeleton class-name="h-6 w-20 rounded" />
                <Skeleton class-name="h-6 w-16 rounded" />
                <Skeleton class-name="h-6 w-16 rounded" />
                <Skeleton class-name="h-6 w-20 rounded" />
              </div>
            </div>
          </div>
          <div class="flex flex-col justify-center gap-4">
            <CardSkeleton :lines="4" class-name="bg-surface-muted border-border" />
            <Skeleton class-name="h-9 w-full rounded-md" />
          </div>
        </div>
      </template>

      <!-- Warning if loaded and no batches with active submission deadline -->
      <div v-else-if="!deadlineBatches.length" class="flex items-center gap-3 rounded-md border border-warning/30 bg-warning-soft p-4">
        <IconAlertTriangle :size="20" class="text-warning shrink-0" />
        <div>
          <p class="text-sm font-medium text-warning">
            {{ batches.length ? "No active batch with submission deadline" : "No active batch detected" }}
          </p>
          <p class="mt-0.5 text-xs text-warning/80">
            You must
            <RouterLink to="/app/batches?create=true" class="underline font-semibold hover:text-warning-dark">
              {{ batches.length ? "set a submission deadline on a batch" : "create a batch" }}
            </RouterLink>
            before you can upload a masterlist.
          </p>
        </div>
      </div>

      <template v-else>
        <!-- Row 1: Batch selection & Deadline -->
        <div class="grid gap-4 md:grid-cols-[1fr_auto]">
          <label class="block">
            <span class="mb-1.5 block text-xs font-medium">1. Select Target Batch</span>
            <select v-model.number="uploadBatchId" class="h-10 w-full rounded-md border px-3 text-sm focus:border-primary focus:ring-1 focus:ring-primary">
              <option :value="null" disabled>Select a batch</option>
              <option v-for="batch in deadlineBatches" :key="batch.id" :value="batch.id">
                {{ batch.name }} · {{ batch.academic_year }} · {{ batch.semester }}
              </option>
            </select>
          </label>

          <div v-if="selectedUploadBatch?.submission_deadline" class="flex flex-col justify-center rounded-md border bg-surface-muted px-4 py-1.5 min-w-[200px]">
            <span class="text-2xs font-semibold uppercase tracking-wider text-text-muted">Batch Deadline</span>
            <div class="mt-0.5 flex items-center gap-2">
              <span class="text-sm font-semibold text-text">{{ formatDate(selectedUploadBatch.submission_deadline) }}</span>
            </div>
          </div>
        </div>

        <!-- Row 2: Upload Zone -->
        <div class="grid gap-6 md:grid-cols-[2fr_1fr]">
          
          <!-- Dropzone Area -->
          <div>
            <span class="mb-1.5 block text-xs font-medium">2. Upload Masterlist</span>
            <div
              class="relative flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-border bg-surface-muted p-8 text-center transition-colors hover:border-primary hover:bg-primary/5"
              @click="openFilePicker"
            >
              <input
                ref="fileInput"
                type="file"
                accept=".csv,.xlsx,.xls,.pdf,.docx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                class="hidden"
                @change="chooseFile"
              />
              <span class="mb-3 grid size-12 place-items-center rounded-full bg-primary/10 text-primary">
                <IconFileSpreadsheet :size="24" />
              </span>
              <p class="text-base font-semibold text-text">Drag & drop your masterlist here</p>
              <p class="mt-1 text-sm text-text-muted">or <span class="text-primary hover:underline">browse files</span></p>
              
              <div class="mt-4 flex flex-wrap justify-center gap-2 text-xs text-text-muted">
                <span class="rounded bg-surface px-2 py-1 border shadow-sm">Excel (.xlsx)</span>
                <span class="rounded bg-surface px-2 py-1 border shadow-sm">CSV (.csv)</span>
                <span class="rounded bg-surface px-2 py-1 border shadow-sm">PDF (.pdf)</span>
                <span class="rounded bg-surface px-2 py-1 border shadow-sm">Word (.docx)</span>
              </div>
            </div>
          </div>

          <!-- Requirements & Template -->
          <div class="flex flex-col justify-center gap-4">
            <div class="rounded-lg border bg-surface-muted p-4">
              <p class="text-xs font-semibold text-text">Required Columns</p>
              <ul class="mt-2 space-y-1.5 text-xs text-text-muted">
                <li class="flex items-center gap-1.5"><IconCheck :size="14" class="text-success" /> student_id</li>
                <li class="flex items-center gap-1.5"><IconCheck :size="14" class="text-success" /> name</li>
                <li class="flex items-center gap-1.5"><IconCheck :size="14" class="text-success" /> email</li>
                <li class="flex items-center gap-1.5"><IconCheck :size="14" class="text-success" /> program</li>
                <li class="flex items-center gap-1.5"><IconCheck :size="14" class="text-success" /> year_level</li>
                <li class="flex items-center gap-1.5 text-text-soft"><span class="ml-1 text-2xs border rounded-full px-1">O</span> student_number (optional)</li>
              </ul>
            </div>
            
            <button
              class="flex w-full items-center justify-center gap-2 rounded-md border bg-surface py-2 text-xs font-semibold text-text shadow-sm hover:bg-surface-muted"
              @click="downloadTemplate"
            >
              <IconFileSpreadsheet :size="16" /> Download Template
            </button>
          </div>
        </div>

        <!-- Selected File Banner -->
        <div v-if="selectedFile" class="flex items-center justify-between rounded-md border border-primary/20 bg-primary/5 px-4 py-3">
          <div class="flex items-center gap-3">
            <IconFileSpreadsheet :size="20" class="text-primary" />
            <div>
              <p class="text-sm font-semibold text-text">{{ selectedFile.name }}</p>
              <p class="text-xs text-text-muted">{{ (selectedFile.size / 1024).toFixed(1) }} KB</p>
            </div>
          </div>
          <button
            class="flex items-center gap-1.5 rounded-md bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-primary-dark disabled:opacity-50"
            :disabled="busy || !uploadBatchId"
            @click="previewImport"
          >
            <IconUpload :size="16" />{{ busy ? "Processing..." : "3. Preview Import" }}
          </button>
        </div>

        <!-- Detection Info Card (DOCX/PDF only) -->
        <div
          v-if="preview?.detection_info"
          class="rounded-lg border border-blue-200 bg-blue-50 p-4"
        >
          <div class="mb-2 flex items-center gap-2">
            <IconTableColumn :size="16" class="text-blue-600" />
            <p class="text-xs font-semibold text-blue-800">
              Table Detection Result
              <span class="ml-1 rounded bg-blue-100 px-1.5 py-0.5 text-2xs font-normal">
                Table {{ (preview.detection_info.table_index ?? 0) + 1 }} selected
              </span>
            </p>
          </div>

          <!-- Raw headers row -->
          <div class="mb-2">
            <p class="mb-1 text-2xs font-semibold uppercase tracking-wide text-blue-600">Columns found in file</p>
            <div class="flex flex-wrap gap-1">
              <span
                v-for="h in preview.detection_info.raw_headers"
                :key="h"
                class="inline-flex items-center gap-1 rounded border px-1.5 py-0.5 text-2xs"
                :class="Object.values(preview.detection_info.matched_columns).includes(h)
                  ? 'border-green-300 bg-green-50 text-green-800'
                  : 'border-border bg-surface-muted text-text-muted'"
              >
                <IconCircleCheck v-if="Object.values(preview.detection_info.matched_columns).includes(h)" :size="10" class="text-green-600" />
                <IconCircleX v-else :size="10" class="text-text-muted" />
                {{ h }}
              </span>
            </div>
          </div>

          <!-- Matched columns -->
          <div class="mb-2">
            <p class="mb-1 text-2xs font-semibold uppercase tracking-wide text-blue-600">Mapped to system fields</p>
            <div class="flex flex-wrap gap-1">
              <span
                v-for="(rawCol, field) in preview.detection_info.matched_columns"
                :key="field"
                class="rounded bg-green-100 px-1.5 py-0.5 text-2xs text-green-800"
              >
                {{ rawCol }} → <strong>{{ field }}</strong>
              </span>
            </div>
          </div>

          <!-- Unmatched warning -->
          <div v-if="preview.detection_info.unmatched_headers.length" class="flex items-start gap-1.5 rounded border border-warning/30 bg-warning-soft p-2">
            <IconAlertTriangle :size="12" class="mt-0.5 flex-shrink-0 text-warning" />
            <p class="text-2xs text-warning">
              <strong>Ignored columns</strong> (not mapped to any system field):
              {{ preview.detection_info.unmatched_headers.join(', ') }}
              — add aliases in <code>CHED_FORMAT.md</code> if needed.
            </p>
          </div>
        </div>
      </template>
      <hr class="col-span-full my-2 border-t border-border/60" />

      <!-- ══ IMPORT HISTORY LIST (below) ══ -->
      <div class="col-span-full">
        <div>
          <h2 class="text-sm font-semibold">Import History</h2>
          <p class="mt-0.5 text-xs text-text-muted">
            Click a row to preview rows. New uploads from
            <RouterLink to="/app/onboarding" class="text-primary hover:underline">Onboarding Center</RouterLink>
            show up in this list.
          </p>
        </div>

      <DataTable :headings="['File', 'Status', 'Records', 'Imported', 'Uploaded', 'Actions']">
        <TableSkeleton v-if="loadingList" :cols="6" :rows="4" />
        <template v-else>
          <tr
            v-for="item in imports"
            :key="item.id"
            :class="[
              'group hover:bg-primary/5 transition-colors',
              selectedImportId === item.id ? 'bg-primary/5' : '',
            ]"
          >
            <td class="px-3 py-3 font-medium">{{ item.original_name || `Import #${item.id}` }}</td>
            <td class="px-3 py-3">
              <span v-if="item.status === 'imported' || item.status === 'completed'" class="inline-flex rounded-full bg-success-soft px-2 py-0.5 text-xs font-semibold text-success">
                ✓ Imported
              </span>
              <span v-else class="inline-flex rounded-full bg-surface-muted px-2 py-0.5 text-xs font-semibold text-text-muted capitalize">
                {{ item.status }}
              </span>
            </td>
            <td class="px-3 py-3">
              <span v-if="item.invalid_rows > 0" class="inline-flex items-center gap-1 rounded-full bg-warning-soft px-2 py-0.5 text-xs font-semibold text-warning">
                ⚠ {{ item.invalid_rows }} errors
              </span>
              <span v-else class="inline-flex rounded-full bg-surface-muted px-2 py-0.5 text-xs font-semibold text-text-muted">
                {{ item.valid_rows }} valid
              </span>
            </td>
            <td class="px-3 py-3 tabular-nums">{{ item.imported_rows }}</td>
            <td class="px-3 py-3 text-text-muted">{{ formatDate(item.created_at) }}</td>
            <td class="px-3 py-3 text-right">
              <div class="flex items-center justify-end gap-2">
                <button
                  class="text-xs font-semibold text-primary hover:underline"
                  @click="selectImport(item.id)"
                >
                  View
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="!imports.length">
            <td colspan="6" class="p-8 text-center text-text-muted">
              No masterlist records yet. Upload from Onboarding Center or use the form above.
            </td>
          </tr>
        </template>
        <template v-if="importsMeta" #footer>
          <TablePagination
            :meta="importsMeta"
            :busy="loadingList"
            @update:page="importsPage = $event"
          />
        </template>
      </DataTable>
      </div>
    </section>


    <p
      v-if="error"
      class="mb-4 flex gap-2 rounded-md border border-danger/30 bg-danger-soft p-3 text-xs text-danger"
    >
      <IconAlertTriangle :size="14" />{{ error }}
    </p>

    <!-- Detail Skeleton while loading a selected import -->
    <div v-if="loadingDetail" class="space-y-4">
      <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <CardSkeleton v-for="i in 4" :key="i" :lines="1" />
      </div>
      <CardSkeleton :lines="5" />
    </div>

    <div v-else-if="preview" class="space-y-4">
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
          class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-xs font-medium text-white shadow-sm disabled:opacity-60"
          @click="confirmDialog = true"
        >
          <IconCheck :size="14" />
          4. Confirm Import ({{ preview.valid_rows }} accounts)
        </button>
      </div>
      <p v-else class="text-right text-xs font-semibold text-success">✓ This import is confirmed.</p>
    </div>

    <section
      v-else-if="!loadingDetail"
      class="flex flex-col items-center justify-center rounded-lg border bg-surface-muted py-6 text-center"
    >
      <span class="text-sm font-semibold text-text-muted">Select an import history record to view details</span>
    </section>

    <AppDialog
      v-model="confirmDialog"
      title="Ready to import records"
      :description="preview ? `You are about to import ${preview.valid_rows} records into the system.` : ''"
      size="sm"
    >
      <div v-if="preview" class="space-y-3 rounded-lg border bg-surface p-4 text-sm">
        <p class="flex items-center gap-2 text-success">
          <IconCheck :size="16" /> {{ preview.valid_rows }} new grantees will be created
        </p>
        <p v-if="preview.invalid_rows" class="flex items-center gap-2 text-warning">
          <IconAlertTriangle :size="16" /> {{ preview.invalid_rows }} records will be skipped due to errors
        </p>
      </div>
      
      <p class="mt-4 text-xs text-text-muted">
        New accounts will start as unverified. Each grantee receives a one-time activation link and temporary password via email.
      </p>
      <template #footer="{ close }">
        <button class="rounded-md border px-4 py-2 text-xs font-medium hover:bg-surface-muted" @click="close">Cancel</button>
        <button
          class="rounded-md bg-primary px-4 py-2 text-xs font-medium text-white shadow-sm disabled:opacity-60"
          :disabled="confirming"
          @click="confirmImport(close)"
        >
          {{ confirming ? "Importing..." : "Confirm Import" }}
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
