<script setup lang="ts">
import { ref, watch } from "vue";
import { IconDownload, IconSearch } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import DataTable from "@/components/tables/DataTable.vue";
import TablePagination from "@/components/tables/TablePagination.vue";
import TableStates from "@/components/ui/TableStates.vue";
import { useGranteeList } from "@/composables/useGrantees";
import { useOnline } from "@/composables/useOnline";
import { toast } from "@/composables/useToast";

const query = ref("");
const debouncedSearch = ref("");
const account = ref("all");
const submission = ref("all");
const page = ref(1);
const { online } = useOnline();

let searchTimer: ReturnType<typeof setTimeout> | undefined;
watch([query, account, submission], () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    debouncedSearch.value = query.value;
    page.value = 1;
  }, 250);
});

const { rows, meta, query: granteesQuery } = useGranteeList({
  page: () => page.value,
  search: () => debouncedSearch.value,
  account: () => account.value,
  submission: () => submission.value,
});
</script>

<template>
  <div>
    <PageHeader title="Grantees" description="View and filter all active scholarship grantees." />
    <div class="mb-4 grid gap-2 md:grid-cols-[1fr_120px_120px_120px]">
      <div class="relative">
        <IconSearch :size="14" class="absolute left-3 top-1/2 -translate-y-1/2 text-text-soft" />
        <input
          v-model="query"
          class="h-9 w-full rounded-md border pl-9 pr-3 text-xs"
          placeholder="Search by name, ID, or program"
        />
      </div>
      <select v-model="account" class="h-9 rounded-md border bg-surface px-3 text-xs">
        <option value="all">All accounts</option>
        <option value="active">Active</option>
        <option value="unverified">Unverified</option>
        <option value="pending_kyc">Pending KYC</option>
        <option value="blocked">Blocked</option>
      </select>
      <select v-model="submission" class="h-9 rounded-md border bg-surface px-3 text-xs">
        <option value="all">All submissions</option>
        <option value="not_submitted">Not submitted</option>
        <option value="docs_submitted">Docs submitted</option>
      </select>
      <button class="inline-flex h-9 items-center justify-center gap-1 rounded-md border text-xs">
        <IconDownload :size="14" /> Export
      </button>
    </div>

    <DataTable
      :headings="['Student #', 'Name', 'Program', 'Batch', 'Account', 'Submission', 'Eligibility', 'Risk']"
    >
      <TableStates
        v-if="
          granteesQuery.isLoading.value ||
          granteesQuery.isError.value ||
          (!online && !rows.length) ||
          (!granteesQuery.isLoading.value && !rows.length)
        "
        :col-span="8"
        :is-loading="granteesQuery.isLoading.value"
        :is-fetching="granteesQuery.isFetching.value"
        :is-error="granteesQuery.isError.value"
        :error="granteesQuery.error.value"
        :is-offline="!online && !rows.length"
        :is-empty="!granteesQuery.isLoading.value && !granteesQuery.isError.value && !rows.length"
        empty-title="No grantees found"
        empty-hint="Try a different search or filter."
        @retry="granteesQuery.refetch()"
      />
      <template v-else>
        <tr v-for="row in rows" :key="row.id">
          <td class="px-3 py-3 font-mono">{{ row.student_number || row.student_id }}</td>
          <td class="px-3 py-3 font-medium">
            <RouterLink :to="`/app/grantees/${row.id}`" class="text-primary">{{ row.name }}</RouterLink>
          </td>
          <td class="px-3 py-3 text-text-muted">{{ row.program }}</td>
          <td class="px-3 py-3 text-text-muted">{{ row.batch || "\u2014" }}</td>
          <td class="px-3 py-3 capitalize">{{ row.account.replaceAll("_", " ") }}</td>
          <td class="px-3 py-3 capitalize">{{ row.submission.replaceAll("_", " ") }}</td>
          <td class="px-3 py-3 capitalize">{{ row.eligibility }}</td>
          <td class="px-3 py-3">
            <span class="rounded-full bg-warning-soft px-2 py-0.5 text-micro text-warning">
              {{ row.risk }}
            </span>
          </td>
        </tr>
      </template>
      <template v-if="meta" #footer>
        <TablePagination
          :meta="meta"
          :busy="granteesQuery.isFetching.value"
          @update:page="page = $event"
        />
      </template>
    </DataTable>
  </div>
</template>
