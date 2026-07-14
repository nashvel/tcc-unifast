<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { IconSearch } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import DataTable from "@/components/tables/DataTable.vue";
type Doc = {
  id: number;
  student_name: string;
  student_id: string;
  document_type: string;
  status: string;
  risk_level: string;
  created_at: string;
};
const query = ref("");
const docs = ref<Doc[]>([]);
const loading = ref(true);
onMounted(async () => {
  try {
    const r = await fetch("/api/document-submissions");
    docs.value = (await r.json()).data || [];
  } finally {
    loading.value = false;
  }
});
const rows = computed(() =>
  docs.value.filter((d) =>
    `${d.student_name} ${d.student_id} ${d.document_type}`
      .toLowerCase()
      .includes(query.value.toLowerCase()),
  ),
);
</script>
<template>
  <div>
    <PageHeader
      title="Document Validation Queue"
      description="Review live student submissions and OCR-assisted results."
    />
    <div class="relative mb-3 max-w-xl" data-tour="documents-filters">
      <IconSearch
        :size="14"
        class="absolute left-3 top-1/2 -translate-y-1/2 text-text-soft"
      /><input
        v-model="query"
        class="h-9 w-full rounded-md border pl-9 pr-3 text-xs"
        placeholder="Search grantee or document"
      />
    </div>
    <div data-tour="documents-queue">
      <DataTable :headings="['Grantee', 'Student #', 'Document', 'Submitted', 'Status', 'Risk', '']"
        ><tr v-for="d in rows" :key="d.id">
          <td class="px-3 py-3 font-medium">{{ d.student_name }}</td>
          <td class="px-3 py-3 font-mono">{{ d.student_id }}</td>
          <td class="px-3 py-3 text-text-muted">{{ d.document_type }}</td>
          <td class="px-3 py-3 text-text-muted">{{ new Date(d.created_at).toLocaleString() }}</td>
          <td class="px-3 py-3 capitalize">{{ d.status.replaceAll("_", " ") }}</td>
          <td class="px-3 py-3">
            <span class="rounded-full bg-warning-soft px-2 py-0.5 text-micro text-warning">{{
              d.risk_level
            }}</span>
          </td>
          <td class="px-3 py-3 text-right">
            <RouterLink :to="`/app/documents/${d.id}`" class="text-primary">Review</RouterLink>
          </td>
        </tr>
        <tr v-if="!loading && !rows.length">
          <td colspan="7" class="px-3 py-8 text-center text-text-muted">
            No submissions in the queue.
          </td>
        </tr></DataTable
      >
    </div>
  </div>
</template>
