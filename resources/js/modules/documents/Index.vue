<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { keepPreviousData, useQuery } from "@tanstack/vue-query";
import { IconSearch } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import DataTable from "@/components/tables/DataTable.vue";
import TablePagination from "@/components/tables/TablePagination.vue";
import TableStates from "@/components/ui/TableStates.vue";
import { apiFetch, buildQuery, type PaginatedResponse } from "@/lib/api";
import { queryKeys } from "@/lib/queryClient";
import { useOnline } from "@/composables/useOnline";

type Doc = {
  id: number;
  student_name: string;
  student_id: string;
  document_type: string;
  slot_key: string | null;
  status: string;
  risk_level: string;
  identity_review_required: boolean;
  created_at: string;
};

const search = ref("");
const debouncedSearch = ref("");
const page = ref(1);
const { online } = useOnline();

let searchTimer: ReturnType<typeof setTimeout> | undefined;
watch(search, (value) => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    debouncedSearch.value = value;
    page.value = 1;
  }, 250);
});

const docsQuery = useQuery({
  queryKey: computed(() =>
    queryKeys.documents({ page: page.value, search: debouncedSearch.value }),
  ),
  queryFn: () =>
    apiFetch<PaginatedResponse<Doc>>(
      `/api/document-submissions${buildQuery({
        page: page.value,
        per_page: 15,
        search: debouncedSearch.value,
      })}`,
    ),
  placeholderData: keepPreviousData,
});

const rows = computed(() => docsQuery.data.value?.data ?? []);
const meta = computed(() => docsQuery.data.value?.meta);
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
      />
      <input
        v-model="search"
        class="h-9 w-full rounded-md border pl-9 pr-3 text-xs"
        placeholder="Search grantee or document"
      />
    </div>
    <div data-tour="documents-queue">
      <DataTable
        :headings="['Grantee', 'Student #', 'Slot', 'Document', 'Submitted', 'Status', 'Risk', '']"
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
          empty-hint="New student uploads will appear here for review."
          @retry="docsQuery.refetch()"
        />
        <template v-else>
          <tr v-for="d in rows" :key="d.id">
            <td class="px-3 py-3 font-medium">{{ d.student_name }}</td>
            <td class="px-3 py-3 font-mono">{{ d.student_id }}</td>
            <td class="px-3 py-3 text-text-muted">
              {{ d.slot_key?.replaceAll("_", " ") || "Legacy" }}
            </td>
            <td class="px-3 py-3 text-text-muted">{{ d.document_type }}</td>
            <td class="px-3 py-3 text-text-muted">
              {{ new Date(d.created_at).toLocaleString() }}
            </td>
            <td class="px-3 py-3 capitalize">{{ d.status.replaceAll("_", " ") }}</td>
            <td class="px-3 py-3">
              <span class="rounded-full bg-warning-soft px-2 py-0.5 text-micro text-warning">
                {{ d.identity_review_required ? "identity review" : d.risk_level }}
              </span>
            </td>
            <td class="px-3 py-3 text-right">
              <RouterLink :to="`/app/documents/${d.id}`" class="text-primary">Review</RouterLink>
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
