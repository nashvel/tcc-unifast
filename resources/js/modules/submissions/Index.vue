<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { IconSearch } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import DataTable from "@/components/tables/DataTable.vue";
type Submission = {
  id: number;
  document_type: string;
  original_name: string;
  created_at: string;
  status: string;
  review_notes: string | null;
  ocr_confidence: number | null;
};
const query = ref("");
const status = ref("all");
const loading = ref(true);
const submissions = ref<Submission[]>([]);
onMounted(async () => {
  try {
    const r = await fetch("/api/document-submissions?student_view=1");
    submissions.value = (await r.json()).data || [];
  } finally {
    loading.value = false;
  }
});
const filtered = computed(() =>
  submissions.value.filter(
    (s) =>
      (!query.value ||
        `${s.document_type} ${s.original_name}`
          .toLowerCase()
          .includes(query.value.toLowerCase())) &&
      (status.value === "all" || s.status === status.value),
  ),
);
const tones: Record<string, string> = {
  approved: "bg-success-soft text-success",
  pending_review: "bg-info-soft text-info",
  processing: "bg-warning-soft text-warning",
  ocr_failed: "bg-danger-soft text-danger",
  rejected: "bg-danger-soft text-danger",
  resubmission: "bg-warning-soft text-warning",
};
</script>
<template>
  <div>
    <PageHeader
      title="Submission Status"
      description="Live status and history of your uploaded documents."
    />
    <div class="mb-4 grid gap-2 rounded-lg border bg-surface p-3 md:grid-cols-4">
      <div class="relative md:col-span-3">
        <IconSearch
          :size="14"
          class="absolute left-3 top-1/2 -translate-y-1/2 text-text-soft"
        /><input
          v-model="query"
          placeholder="Search by document or file name"
          class="h-9 w-full rounded-md border pl-9 pr-3 text-xs"
        />
      </div>
      <select v-model="status" class="h-9 rounded-md border bg-surface px-3 text-xs">
        <option value="all">All statuses</option>
        <option value="pending_review">Pending review</option>
        <option value="approved">Approved</option>
        <option value="rejected">Rejected</option>
        <option value="resubmission">Resubmission</option>
      </select>
    </div>
    <DataTable :headings="['Document', 'File', 'Uploaded', 'Status', 'OCR', 'Remarks']"
      ><tr v-for="item in filtered" :key="item.id">
        <td class="px-3 py-3 font-medium">{{ item.document_type }}</td>
        <td class="px-3 py-3 font-mono text-text-muted">{{ item.original_name }}</td>
        <td class="px-3 py-3 text-text-muted">{{ new Date(item.created_at).toLocaleString() }}</td>
        <td class="px-3 py-3">
          <span
            :class="[
              'rounded-full px-2 py-0.5 capitalize',
              tones[item.status] || 'bg-surface-muted',
            ]"
            >{{ item.status.replaceAll("_", " ") }}</span
          >
        </td>
        <td class="px-3 py-3 text-text-muted">
          {{ item.ocr_confidence === null ? "—" : `${item.ocr_confidence.toFixed(1)}%` }}
        </td>
        <td class="px-3 py-3 text-text-muted">
          {{ item.review_notes || "Awaiting staff review." }}
        </td>
      </tr>
      <tr v-if="!loading && !filtered.length">
        <td colspan="6" class="px-3 py-8 text-center text-text-muted">No submissions yet.</td>
      </tr></DataTable
    >
  </div>
</template>
