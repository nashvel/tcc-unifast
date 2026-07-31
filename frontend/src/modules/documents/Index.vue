<script setup lang="ts">
import { ref, watch } from "vue";
import { IconSearch } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import DataTable from "@/components/tables/DataTable.vue";
import TablePagination from "@/components/tables/TablePagination.vue";
import TableStates from "@/components/ui/TableStates.vue";
import { useDocumentPackageList } from "@/composables/useDocuments";
import { useOnline } from "@/composables/useOnline";

const search = ref("");
const debouncedSearch = ref("");
const page = ref(1);
const { online } = useOnline();

function packageStatusClass(status: string) {
  if (status === "pending_review" || status === "under_review" || status === "docs_submitted") {
    return "inline-flex rounded-full bg-info-soft px-2 py-0.5 text-micro font-semibold capitalize text-info";
  }
  if (status === "resubmission" || status === "resubmission_requested") {
    return "inline-flex rounded-full bg-warning-soft px-2 py-0.5 text-micro font-semibold capitalize text-warning";
  }
  if (status === "approved" || status === "verified") {
    return "inline-flex rounded-full bg-success-soft px-2 py-0.5 text-micro font-semibold capitalize text-success";
  }
  if (status === "rejected") {
    return "inline-flex rounded-full bg-danger-soft px-2 py-0.5 text-micro font-semibold capitalize text-danger";
  }
  return "inline-flex rounded-full bg-surface-muted px-2 py-0.5 text-micro font-medium capitalize text-text";
}

let searchTimer: ReturnType<typeof setTimeout> | undefined;
watch(search, (value) => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    debouncedSearch.value = value;
    page.value = 1;
  }, 250);
});

const { rows, meta, query: docsQuery } = useDocumentPackageList({
  page: () => page.value,
  search: () => debouncedSearch.value,
});
</script>

<template>
  <div>
    <PageHeader
      title="Document Validation Queue"
      description="Review live student submission packages and OCR-assisted results."
    />
    <div class="relative mb-3 max-w-xl" data-tour="documents-filters">
      <IconSearch
        :size="14"
        class="absolute left-3 top-1/2 -translate-y-1/2 text-text-soft"
      />
      <input
        v-model="search"
        class="h-9 w-full rounded-md border pl-9 pr-3 text-xs"
        placeholder="Search grantee or student ID"
      />
    </div>
    <div data-tour="documents-queue">
      <DataTable
        :headings="['Grantee', 'Student #', 'Batch', 'Progress', 'Submitted', 'Status', 'Risk', '']"
      >
        <TableStates
          v-if="
            docsQuery.isLoading.value ||
            docsQuery.isError.value ||
            (!online && !rows.length) ||
            (!docsQuery.isLoading.value && !rows.length)
          "
          :col-span="8"
          :is-loading="docsQuery.isLoading.value"
          :is-fetching="docsQuery.isFetching.value"
          :is-error="docsQuery.isError.value"
          :error="docsQuery.error.value"
          :is-offline="!online && !rows.length"
          :is-empty="!docsQuery.isLoading.value && !docsQuery.isError.value && !rows.length"
          empty-title="No submissions in the queue"
          empty-hint="New student packages will appear here for review."
          @retry="docsQuery.refetch()"
        />
        <template v-else>
          <tr v-for="d in rows" :key="`${d.grantee_id}-${d.batch_id}`">
            <td class="px-3 py-3 font-medium">{{ d.student_name }}</td>
            <td class="px-3 py-3 font-mono">{{ d.student_id }}</td>
            <td class="px-3 py-3 text-text-muted">{{ d.batch_name || `Batch #${d.batch_id}` }}</td>
            <td class="px-3 py-3 text-text-muted">{{ d.progress }}</td>
            <td class="px-3 py-3 text-text-muted">
              {{ d.submitted_at ? new Date(d.submitted_at).toLocaleString() : "—" }}
            </td>
            <td class="px-3 py-3">
              <span :class="packageStatusClass(d.status)">{{ d.status.replaceAll("_", " ") }}</span>
            </td>
            <td class="px-3 py-3">
              <span class="rounded-full bg-warning-soft px-2 py-0.5 text-micro text-warning">
                {{ d.identity_review_required ? "identity review" : d.risk_level }}
              </span>
            </td>
            <td class="px-3 py-3 text-right">
              <RouterLink
                :to="`/app/documents/package/${d.grantee_id}/${d.batch_id}`"
                class="text-primary"
              >
                View
              </RouterLink>
            </td>
          </tr>
        </template>
        <template v-if="meta" #footer>
          <TablePagination
            :meta="meta"
            :busy="docsQuery.isFetching.value"
            @update:page="page = $event"
          />
        </template>
      </DataTable>
    </div>
  </div>
</template>
