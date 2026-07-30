<script setup lang="ts">
import { computed, ref, onMounted } from "vue";
import {
  IconFile,
  IconFileTypePdf,
  IconPhoto,
  IconSearch,
  IconUpload,
  IconFileSpreadsheet,
} from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import { getAuthToken } from "@/auth/session";

type FileItem = {
  id: string;
  name: string;
  category: "document" | "image" | "spreadsheet";
  owner: string;
  size: string;
  size_bytes: number;
  created_at: string;
};

type SummaryStats = {
  total_files: string;
  documents: string;
  images: string;
  storage_used: string;
};

const query = ref("");
const category = ref("all");

const files = ref<FileItem[]>([]);
const summary = ref<SummaryStats>({
  total_files: "0",
  documents: "0",
  images: "0",
  storage_used: "0 KB",
});
const loading = ref(true);

const rows = computed(() =>
  files.value.filter(
    (f) =>
      (category.value === "all" || f.category === category.value) &&
      `${f.name} ${f.owner}`.toLowerCase().includes(query.value.toLowerCase()),
  ),
);

async function loadFiles() {
  loading.value = true;
  try {
    const response = await fetch("/api/files", {
      headers: { Authorization: `Bearer ${getAuthToken()}`, Accept: "application/json" },
    });
    const payload = await response.json();
    if (response.ok && payload.data) {
      files.value = payload.data;
      summary.value = payload.summary;
    }
  } catch (err) {
    console.error("Unable to load files", err);
  } finally {
    loading.value = false;
  }
}

function formatDate(val: string) {
  return new Date(val).toLocaleDateString(undefined, {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
}

onMounted(() => {
  loadFiles();
});
</script>

<template>
  <div>
    <PageHeader title="File Manager" description="Browse and organize uploaded scholarship files."
      ><template #actions
        ><button
          class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-xs text-white"
        >
          <IconUpload :size="14" />Upload file
        </button></template
      ></PageHeader
    >
    <section class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
      <article
        v-for="c in [
          ['All files', summary.total_files],
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
    <section class="mb-3 flex flex-wrap gap-2">
      <div class="relative min-w-64 flex-1">
        <IconSearch
          :size="14"
          class="absolute left-3 top-1/2 -translate-y-1/2 text-text-soft"
        /><input
          v-model="query"
          class="h-9 w-full rounded-md border pl-9 pr-3 text-xs"
          placeholder="Search files or owners"
        />
      </div>
      <select v-model="category" class="h-9 rounded-md border bg-surface px-3 text-xs">
        <option value="all">All categories</option>
        <option value="document">Documents</option>
        <option value="image">Images</option>
        <option value="spreadsheet">Spreadsheets</option>
      </select>
    </section>
    
    <div v-if="loading" class="rounded-lg border p-8 text-center text-sm text-text-muted">
      Loading files...
    </div>
    
    <section v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
      <article v-for="f in rows" :key="f.id" class="rounded-lg border bg-surface p-4">
        <component
          :is="f.category === 'image' ? IconPhoto : f.category === 'spreadsheet' ? IconFileSpreadsheet : f.name.endsWith('.pdf') ? IconFileTypePdf : IconFile"
          :size="28"
          class="text-primary"
        />
        <h2 class="mt-4 truncate text-sm font-semibold" :title="f.name">{{ f.name }}</h2>
        <p class="mt-1 text-xs text-text-muted truncate" :title="f.owner">{{ f.owner || 'System' }}</p>
        <div class="mt-4 flex justify-between border-t pt-3 text-micro text-text-muted">
          <span>{{ f.size }}</span>
          <span>{{ formatDate(f.created_at) }}</span>
        </div>
        <div class="mt-3 flex gap-3 text-xs text-primary">
          <button class="hover:underline">Preview</button>
          <button class="hover:underline">Download</button>
        </div>
      </article>
    </section>
    <p v-if="!loading && !rows.length" class="rounded-lg border p-8 text-center text-sm text-text-muted">
      No files found.
    </p>
  </div>
</template>
