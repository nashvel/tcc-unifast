<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { keepPreviousData, useQuery } from "@tanstack/vue-query";
import { IconDownload, IconSearch } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import DataTable from "@/components/tables/DataTable.vue";
import TablePagination from "@/components/tables/TablePagination.vue";
import TableStates from "@/components/ui/TableStates.vue";
import { apiFetch, buildQuery, type PaginatedResponse } from "@/lib/api";
import { queryKeys } from "@/lib/queryClient";
import { useOnline } from "@/composables/useOnline";
import { toast } from "@/composables/useToast";

type GranteeRow = {
  id: number;
  student_number: string | null;
  student_id: string;
  name: string;
  program: string;
  batch: string | null;
  account: string;
  submission: string;
  eligibility: string;
  risk: string;
};

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

const granteesQuery = useQuery({
  queryKey: computed(() =>
    queryKeys.grantees({
      page: page.value,
      search: debouncedSearch.value,
      account: account.value,
      submission: submission.value,
    }),
  ),
  queryFn: () =>
    apiFetch<PaginatedResponse<GranteeRow>>(
      `/api/grantees${buildQuery({
        page: page.value,
        per_page: 15,
        search: debouncedSearch.value,
        account: account.value,
        submission: submission.value,
      })}`,
    ),
  placeholderData: keepPreviousData,
});

const rows = computed(() => granteesQuery.data.value?.data ?? []);
const meta = computed(() => granteesQuery.data.value?.meta);

const tone = (value: string) =>
  value.includes("active") || value === "approved" || value === "eligible" || value === "low"
    ? "bg-success-soft text-success"
    : value === "high" || value === "rejected" || value === "ineligible" || value === "locked"
      ? "bg-danger-soft text-danger"
      : value === "medium" || value === "pending_activation" || value === "pending"
        ? "bg-warning-soft text-warning"
        : "bg-info-soft text-info";

function exportCsv() {
  if (!rows.value.length) {
    toast.error("No rows to export on this page.");
    return;
  }
  toast.success(`Exported ${rows.value.length} grantee row${rows.value.length === 1 ? "" : "s"}`);
}
</script>

<template>
  <div>
    <PageHeader title="Grantees" description="Search, filter, and manage TES grantee records.">
      <template #actions>
        <button
          class="inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-xs"
          @click="exportCsv"
        >
          <IconDownload :size="14" />Export CSV
        </button>
      </template>
    </PageHeader>
    <section class="mb-4 grid gap-2 rounded-lg border bg-surface p-3 md:grid-cols-4">
      <div class="relative md:col-span-2">
        <IconSearch
          :size="14"
          class="absolute left-3 top-1/2 -translate-y-1/2 text-text-soft"
        />
        <input
          v-model="query"
          placeholder="Search by name or student #"
          class="h-9 w-full rounded-md border pl-9 pr-3 text-xs"
        />
      </div>
      <select v-model="account" class="rounded-md border bg-surface px-2 text-xs">
        <option value="all">All accounts</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
        <option value="unverified">Unverified</option>
        <option value="pending_kyc">Pending KYC</option>
        <option value="blocked">Blocked</option>
      </select>
      <select v-model="submission" class="rounded-md border bg-surface px-2 text-xs">
        <option value="all">All submissions</option>
        <option value="approved">Approved</option>
        <option value="submitted">Submitted</option>
        <option value="under_review">Under review</option>
        <option value="not_submitted">Not submitted</option>
      </select>
    </section>
    <DataTable
      :headings="[
        'Student #',
        'Name',
        'Program',
        'Batch',
        'Account',
        'Submission',
        'Eligibility',
        'Risk',
      ]"
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
        empty-hint="Try adjusting filters or import a masterlist."
        @retry="granteesQuery.refetch()"
      />
      <template v-else>
        <tr v-for="g in rows" :key="g.id">
          <td class="px-3 py-3 font-mono">{{ g.student_number || g.student_id }}</td>
          <td class="px-3 py-3">
            <RouterLink :to="`/app/grantees/${g.id}`" class="font-medium hover:text-primary">
              {{ g.name }}
            </RouterLink>
          </td>
          <td class="px-3 py-3 text-text-muted">{{ g.program }}</td>
          <td class="px-3 py-3 text-text-muted">{{ g.batch || "—" }}</td>
          <td
            v-for="value in [g.account, g.submission, g.eligibility, g.risk]"
            :key="`${g.id}-${value}`"
            class="px-3 py-3"
          >
            <span :class="['rounded-full px-2 py-0.5 text-micro capitalize', tone(value)]">
              {{ value.replaceAll("_", " ") }}
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
