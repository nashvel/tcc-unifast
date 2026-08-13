<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from "vue";
import { RouterLink } from "vue-router";
import {
  IconChevronDown,
  IconChevronRight,
  IconDownload,
  IconEye,
  IconFile,
  IconFileSpreadsheet,
  IconFileTypePdf,
  IconPhoto,
  IconSearch,
  IconX,
} from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import EmptyState from "@/components/ui/EmptyState.vue";
import CardSkeleton from "@/components/ui/CardSkeleton.vue";
import TablePagination from "@/components/tables/TablePagination.vue";
import { apiFetch, apiFetchBlob } from "@/api/client";
import { toast } from "@/composables/useToast";

type Tab = "requirements" | "imports";

type RequirementFile = {
  kind: "requirement";
  id: string;
  submission_id: number;
  batch_id: number | null;
  batch_name: string | null;
  grantee_id: number | null;
  student_id: string | null;
  student_name: string | null;
  slot_key: string | null;
  document_type: string | null;
  status: string | null;
  name: string;
  mime_type: string | null;
  category: "document" | "image";
  size: string;
  size_bytes: number;
  created_at: string;
  preview_url: string | null;
  download_url: string | null;
  package_path: string | null;
};

type ImportFile = {
  kind: "import";
  id: string;
  import_id: number;
  batch_id: number | null;
  batch_name: string | null;
  name: string;
  category: "spreadsheet";
  owner: string;
  status: string | null;
  size: string;
  size_bytes: number;
  created_at: string;
  preview_url: string | null;
  download_url: string | null;
};

type SummaryStats = {
  total_files: string;
  documents: string;
  images: string;
  storage_used: string;
};

type BatchOption = { id: number; name: string };

type Meta = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

type StudentGroup = {
  key: string;
  student_id: string;
  student_name: string;
  grantee_id: number | null;
  files: RequirementFile[];
};

type BatchGroup = {
  key: string;
  batch_id: number | null;
  batch_name: string;
  students: StudentGroup[];
};

const tab = ref<Tab>("requirements");
const query = ref("");
const debouncedSearch = ref("");
const batchId = ref<string>("");
const page = ref(1);

const requirementFiles = ref<RequirementFile[]>([]);
const importFiles = ref<ImportFile[]>([]);
const summary = ref<SummaryStats>({
  total_files: "0",
  documents: "0",
  images: "0",
  storage_used: "0 KB",
});
const batches = ref<BatchOption[]>([]);
const meta = ref<Meta>({ current_page: 1, last_page: 1, per_page: 50, total: 0 });
const loading = ref(true);
const error = ref("");

const expandedBatches = ref<Record<string, boolean>>({});
const expandedStudents = ref<Record<string, boolean>>({});

const previewOpen = ref(false);
const previewTitle = ref("");
const previewUrl = ref<string | null>(null);
const previewMime = ref<string | null>(null);
const previewBusy = ref(false);
const previewError = ref("");
let previewObjectUrl: string | null = null;

let searchTimer: ReturnType<typeof setTimeout> | undefined;
watch(query, (value) => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    debouncedSearch.value = value;
    page.value = 1;
  }, 250);
});

watch([tab, debouncedSearch, batchId, page], () => {
  void loadFiles();
}, { immediate: true });

const batchGroups = computed((): BatchGroup[] => {
  const byBatch = new Map<string, BatchGroup>();
  for (const file of requirementFiles.value) {
    const batchKey = String(file.batch_id ?? "none");
    let batch = byBatch.get(batchKey);
    if (!batch) {
      batch = {
        key: batchKey,
        batch_id: file.batch_id,
        batch_name: file.batch_name || "Unassigned batch",
        students: [],
      };
      byBatch.set(batchKey, batch);
    }
    const studentKey = `${batchKey}:${file.student_id || file.grantee_id || file.student_name || "unknown"}`;
    let student = batch.students.find((s) => s.key === studentKey);
    if (!student) {
      student = {
        key: studentKey,
        student_id: file.student_id || "—",
        student_name: file.student_name || "Unknown student",
        grantee_id: file.grantee_id,
        files: [],
      };
      batch.students.push(student);
    }
    student.files.push(file);
  }
  return Array.from(byBatch.values());
});

function ensureExpandedDefaults(groups: BatchGroup[]) {
  for (const batch of groups) {
    if (expandedBatches.value[batch.key] === undefined) {
      expandedBatches.value[batch.key] = true;
    }
    for (const student of batch.students) {
      if (expandedStudents.value[student.key] === undefined) {
        expandedStudents.value[student.key] = true;
      }
    }
  }
}

watch(batchGroups, (groups) => ensureExpandedDefaults(groups), { immediate: true });

async function loadFiles() {
  loading.value = true;
  error.value = "";
  try {
    const params = new URLSearchParams({
      tab: tab.value,
      page: String(page.value),
      per_page: "50",
    });
    if (debouncedSearch.value.trim()) params.set("search", debouncedSearch.value.trim());
    if (batchId.value) params.set("batch_id", batchId.value);

    const payload = await apiFetch<{
      summary?: typeof summary.value;
      batches?: typeof batches.value;
      meta?: typeof meta.value;
      data?: RequirementFile[] | ImportFile[];
      message?: string;
    }>(`/api/files?${params}`);

    summary.value = payload.summary || summary.value;
    batches.value = payload.batches || [];
    meta.value = payload.meta || { current_page: 1, last_page: 1, per_page: 50, total: 0 };

    if (tab.value === "requirements") {
      requirementFiles.value = (payload.data || []) as RequirementFile[];
      importFiles.value = [];
    } else {
      importFiles.value = (payload.data || []) as ImportFile[];
      requirementFiles.value = [];
    }
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "Unable to load files.";
    requirementFiles.value = [];
    importFiles.value = [];
  } finally {
    loading.value = false;
  }
}

function setTab(next: Tab) {
  if (tab.value === next) return;
  tab.value = next;
  page.value = 1;
}

function toggleBatch(key: string) {
  expandedBatches.value[key] = !expandedBatches.value[key];
}

function toggleStudent(key: string) {
  expandedStudents.value[key] = !expandedStudents.value[key];
}

function formatDate(val: string) {
  return new Date(val).toLocaleDateString(undefined, {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
}

function fileIcon(file: { category: string; name: string; mime_type?: string | null }) {
  if (file.category === "image" || (file.mime_type || "").startsWith("image/")) return IconPhoto;
  if (file.category === "spreadsheet") return IconFileSpreadsheet;
  if ((file.name || "").toLowerCase().endsWith(".pdf") || file.mime_type === "application/pdf") {
    return IconFileTypePdf;
  }
  return IconFile;
}

function revokePreview() {
  if (previewObjectUrl) {
    URL.revokeObjectURL(previewObjectUrl);
    previewObjectUrl = null;
  }
  previewUrl.value = null;
}

async function fetchAuthBlob(url: string): Promise<{ objectUrl: string; mime: string | null }> {
  const response = await apiFetchBlob(url, {
    headers: { Accept: "*/*" },
  });
  if (!response.ok) {
    throw new Error("Unable to load file.");
  }
  const mime = response.headers.get("Content-Type");
  const blob = await response.blob();
  return { objectUrl: URL.createObjectURL(blob), mime };
}

async function openPreview(file: RequirementFile | ImportFile) {
  if (!file.preview_url && file.kind === "import") {
    toast.info("CSV preview is not available — use Download.");
    return;
  }
  if (!file.preview_url) {
    toast.error("Preview URL missing for this file.");
    return;
  }

  previewBusy.value = true;
  previewError.value = "";
  previewTitle.value = file.name;
  previewOpen.value = true;
  previewMime.value = "mime_type" in file ? file.mime_type : null;
  revokePreview();

  try {
    const { objectUrl, mime } = await fetchAuthBlob(file.preview_url);
    previewObjectUrl = objectUrl;
    previewUrl.value = objectUrl;
    if (mime) previewMime.value = mime;
  } catch (exception) {
    previewError.value = exception instanceof Error ? exception.message : "Unable to load preview.";
  } finally {
    previewBusy.value = false;
  }
}

async function downloadFile(file: RequirementFile | ImportFile) {
  if (!file.download_url) {
    toast.error("Download URL missing for this file.");
    return;
  }
  try {
    const { objectUrl } = await fetchAuthBlob(file.download_url);
    const anchor = document.createElement("a");
    anchor.href = objectUrl;
    anchor.download = file.name || "download";
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    URL.revokeObjectURL(objectUrl);
  } catch (exception) {
    toast.error(exception instanceof Error ? exception.message : "Download failed.");
  }
}

function closePreview() {
  previewOpen.value = false;
  previewError.value = "";
  revokePreview();
}

onBeforeUnmount(() => {
  clearTimeout(searchTimer);
  revokePreview();
});
</script>

<template>
  <div>
    <PageHeader
      title="File Manager"
      description="Browse requirement files by batch and student. Masterlist imports are listed separately."
    />

    <section class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
      <article
        v-for="c in [
          ['Files', summary.total_files],
          ['Documents', summary.documents],
          ['Images', summary.images],
          ['Storage used', summary.storage_used],
        ]"
        :key="c[0]"
        class="rounded-lg border bg-surface p-4"
      >
        <p class="text-xs text-text-muted">{{ c[0] }}</p>
        <p class="mt-1 text-xl font-semibold tabular-nums">{{ c[1] }}</p>
      </article>
    </section>

    <div class="mb-3 flex flex-wrap gap-2 border-b pb-3">
      <button
        type="button"
        class="rounded-md px-3 py-1.5 text-xs font-medium"
        :class="tab === 'requirements' ? 'bg-primary text-white' : 'border bg-surface text-text-muted'"
        @click="setTab('requirements')"
      >
        Requirement files
      </button>
      <button
        type="button"
        class="rounded-md px-3 py-1.5 text-xs font-medium"
        :class="tab === 'imports' ? 'bg-primary text-white' : 'border bg-surface text-text-muted'"
        @click="setTab('imports')"
      >
        Masterlist imports
      </button>
    </div>

    <section class="mb-3 flex flex-wrap gap-2">
      <div class="relative min-w-64 flex-1">
        <IconSearch :size="14" class="absolute left-3 top-1/2 -translate-y-1/2 text-text-soft" />
        <input
          v-model="query"
          class="h-9 w-full rounded-md border pl-9 pr-3 text-xs"
          :placeholder="tab === 'requirements' ? 'Search student, ID, or file name' : 'Search import name or uploader'"
        />
      </div>
      <select v-model="batchId" class="h-9 rounded-md border bg-surface px-3 text-xs" @change="page = 1">
        <option value="">All batches</option>
        <option v-for="b in batches" :key="b.id" :value="String(b.id)">{{ b.name }}</option>
      </select>
    </section>

    <CardSkeleton v-if="loading" :lines="6" class-name="rounded-lg p-4" />

    <EmptyState
      v-else-if="error"
      variant="error"
      title="Unable to load files"
      :hint="error"
      @retry="loadFiles"
    />

    <EmptyState
      v-else-if="tab === 'requirements' && !batchGroups.length"
      title="No requirement files"
      hint="Uploaded School ID, Grade Slip, and other vault documents will appear here grouped by batch and student."
    />

    <EmptyState
      v-else-if="tab === 'imports' && !importFiles.length"
      title="No masterlist imports"
      hint="CSV imports from the Masterlist module will appear in this tab."
    />

    <section v-else-if="tab === 'requirements'" class="space-y-3">
      <article v-for="batch in batchGroups" :key="batch.key" class="overflow-hidden rounded-lg border bg-surface">
        <button
          type="button"
          class="flex w-full items-center gap-2 px-4 py-3 text-left text-sm font-semibold hover:bg-surface-muted"
          @click="toggleBatch(batch.key)"
        >
          <IconChevronDown v-if="expandedBatches[batch.key]" :size="16" />
          <IconChevronRight v-else :size="16" />
          <span>{{ batch.batch_name }}</span>
          <span class="text-xs font-normal text-text-muted">
            · {{ batch.students.length }} student{{ batch.students.length === 1 ? "" : "s" }}
          </span>
        </button>

        <div v-if="expandedBatches[batch.key]" class="border-t">
          <div v-for="student in batch.students" :key="student.key" class="border-b last:border-b-0">
            <button
              type="button"
              class="flex w-full items-center gap-2 bg-surface-muted/40 px-4 py-2.5 text-left text-xs font-medium hover:bg-surface-muted"
              @click="toggleStudent(student.key)"
            >
              <IconChevronDown v-if="expandedStudents[student.key]" :size="14" />
              <IconChevronRight v-else :size="14" />
              <span>{{ student.student_name }}</span>
              <span class="font-normal text-text-muted">({{ student.student_id }})</span>
              <span class="font-normal text-text-muted">· {{ student.files.length }} file{{ student.files.length === 1 ? "" : "s" }}</span>
            </button>

            <ul v-if="expandedStudents[student.key]" class="divide-y">
              <li
                v-for="file in student.files"
                :key="file.id"
                class="flex flex-wrap items-center gap-3 px-4 py-3 sm:flex-nowrap"
              >
                <component :is="fileIcon(file)" :size="22" class="shrink-0 text-primary" />
                <div class="min-w-0 flex-1">
                  <p class="truncate text-sm font-medium" :title="file.name">
                    {{ file.document_type || file.slot_key || file.name }}
                  </p>
                  <p class="truncate text-micro text-text-muted" :title="file.name">
                    {{ file.name }} · {{ file.size }} · {{ formatDate(file.created_at) }}
                    <span v-if="file.status"> · {{ file.status }}</span>
                  </p>
                </div>
                <div class="flex flex-wrap items-center gap-2 text-xs">
                  <button
                    type="button"
                    class="inline-flex items-center gap-1 rounded-md border px-2 py-1 hover:bg-surface-muted"
                    @click="openPreview(file)"
                  >
                    <IconEye :size="14" /> Preview
                  </button>
                  <button
                    type="button"
                    class="inline-flex items-center gap-1 rounded-md border px-2 py-1 hover:bg-surface-muted"
                    @click="downloadFile(file)"
                  >
                    <IconDownload :size="14" /> Download
                  </button>
                  <RouterLink
                    v-if="file.package_path"
                    :to="file.package_path"
                    class="inline-flex items-center rounded-md border px-2 py-1 hover:bg-surface-muted"
                  >
                    Open validation
                  </RouterLink>
                </div>
              </li>
            </ul>
          </div>
        </div>
      </article>
    </section>

    <section v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
      <article v-for="file in importFiles" :key="file.id" class="rounded-lg border bg-surface p-4">
        <IconFileSpreadsheet :size="28" class="text-primary" />
        <h2 class="mt-3 truncate text-sm font-semibold" :title="file.name">{{ file.name }}</h2>
        <p class="mt-1 text-xs text-text-muted">
          {{ file.owner }}
          <span v-if="file.batch_name"> · {{ file.batch_name }}</span>
        </p>
        <div class="mt-3 flex justify-between border-t pt-3 text-micro text-text-muted">
          <span>{{ file.size }}</span>
          <span>{{ formatDate(file.created_at) }}</span>
        </div>
        <div class="mt-3 flex gap-2 text-xs">
          <button
            type="button"
            class="inline-flex items-center gap-1 rounded-md border px-2 py-1 hover:bg-surface-muted"
            @click="downloadFile(file)"
          >
            <IconDownload :size="14" /> Download
          </button>
        </div>
      </article>
    </section>

    <TablePagination
      v-if="!loading && !error && meta.last_page > 1"
      class="mt-4 rounded-lg border bg-surface"
      :meta="{
        current_page: meta.current_page,
        last_page: meta.last_page,
        per_page: meta.per_page,
        total: meta.total,
        from: meta.total ? (meta.current_page - 1) * meta.per_page + 1 : 0,
        to: Math.min(meta.current_page * meta.per_page, meta.total),
      }"
      :busy="loading"
      @update:page="(p) => (page = p)"
    />

    <div
      v-if="previewOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
      @click.self="closePreview"
    >
      <div class="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-lg border bg-surface shadow-lg">
        <div class="flex items-center justify-between gap-3 border-b px-4 py-3">
          <p class="truncate text-sm font-semibold">{{ previewTitle }}</p>
          <button type="button" class="rounded-md border p-1.5 hover:bg-surface-muted" @click="closePreview">
            <IconX :size="16" />
          </button>
        </div>
        <div class="min-h-48 flex-1 overflow-auto p-4">
          <p v-if="previewBusy" class="text-sm text-text-muted">Loading preview…</p>
          <p v-else-if="previewError" class="text-sm text-danger">{{ previewError }}</p>
          <img
            v-else-if="previewUrl && (previewMime || '').startsWith('image/')"
            :src="previewUrl"
            :alt="previewTitle"
            class="mx-auto max-h-[70vh] max-w-full rounded-md object-contain"
          />
          <iframe
            v-else-if="previewUrl && ((previewMime || '').includes('pdf') || previewTitle.toLowerCase().endsWith('.pdf'))"
            :src="previewUrl"
            class="h-[70vh] w-full rounded-md border"
            title="File preview"
          />
          <p v-else-if="previewUrl" class="text-sm text-text-muted">
            Preview not available for this file type.
            <button type="button" class="text-primary underline" @click="closePreview">Close</button>
            and use Download instead.
          </p>
        </div>
      </div>
    </div>
  </div>
</template>
