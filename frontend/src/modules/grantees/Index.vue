<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { IconAdjustmentsHorizontal, IconDownload, IconFilter, IconSearch } from "@tabler/icons-vue";
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
const showAdvanced = ref(false);
const smartPill = ref<"all" | "needs_review" | "awaiting_docs" | "pending_kyc" | "unverified">("all");
const { online } = useOnline();

function setPill(pill: typeof smartPill.value) {
  smartPill.value = pill;
  page.value = 1;
  if (pill === "all") {
    account.value = "all";
    submission.value = "all";
  } else if (pill === "needs_review") {
    account.value = "active";
    submission.value = "docs_submitted";
  } else if (pill === "awaiting_docs") {
    account.value = "all";
    submission.value = "not_submitted";
  } else if (pill === "pending_kyc") {
    account.value = "pending_kyc";
    submission.value = "all";
  } else if (pill === "unverified") {
    account.value = "unverified";
    submission.value = "all";
  }
}

// Sync smart pill if dropdowns are touched manually
watch([account, submission], () => {
  if (account.value === "all" && submission.value === "all") smartPill.value = "all";
  else if (account.value === "active" && submission.value === "docs_submitted") smartPill.value = "needs_review";
  else if (account.value === "all" && submission.value === "not_submitted") smartPill.value = "awaiting_docs";
  else if (account.value === "pending_kyc" && submission.value === "all") smartPill.value = "pending_kyc";
  else if (account.value === "unverified" && submission.value === "all") smartPill.value = "unverified";
  else smartPill.value = "all";
});

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
  <div class="space-y-4">
    <PageHeader title="Grantees" description="View and filter all active scholarship grantees." />

    <!-- Hick's Law Task-Oriented Smart Filter Pills -->
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border/60 pb-3">
      <div class="flex flex-wrap items-center gap-1.5" role="tablist" aria-label="Grantee Status Filters">
        <button
          type="button"
          :class="[
            'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium transition',
            smartPill === 'all'
              ? 'bg-primary text-white shadow-xs'
              : 'border border-border/80 bg-surface text-text-muted hover:bg-surface-muted hover:text-text',
          ]"
          @click="setPill('all')"
        >
          All Grantees
        </button>
        <button
          type="button"
          :class="[
            'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium transition',
            smartPill === 'needs_review'
              ? 'bg-primary text-white shadow-xs'
              : 'border border-border/80 bg-surface text-text-muted hover:bg-surface-muted hover:text-text',
          ]"
          @click="setPill('needs_review')"
        >
          <span class="size-2 rounded-full bg-emerald-500" />
          Needs Review (Docs Submitted)
        </button>
        <button
          type="button"
          :class="[
            'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium transition',
            smartPill === 'awaiting_docs'
              ? 'bg-primary text-white shadow-xs'
              : 'border border-border/80 bg-surface text-text-muted hover:bg-surface-muted hover:text-text',
          ]"
          @click="setPill('awaiting_docs')"
        >
          <span class="size-2 rounded-full bg-amber-500" />
          Awaiting Docs
        </button>
        <button
          type="button"
          :class="[
            'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium transition',
            smartPill === 'pending_kyc'
              ? 'bg-primary text-white shadow-xs'
              : 'border border-border/80 bg-surface text-text-muted hover:bg-surface-muted hover:text-text',
          ]"
          @click="setPill('pending_kyc')"
        >
          Pending KYC
        </button>
        <button
          type="button"
          :class="[
            'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium transition',
            smartPill === 'unverified'
              ? 'bg-primary text-white shadow-xs'
              : 'border border-border/80 bg-surface text-text-muted hover:bg-surface-muted hover:text-text',
          ]"
          @click="setPill('unverified')"
        >
          Unverified
        </button>
      </div>

      <button
        type="button"
        class="inline-flex items-center gap-1 text-xs font-medium text-text-muted hover:text-primary transition"
        @click="showAdvanced = !showAdvanced"
      >
        <IconAdjustmentsHorizontal :size="14" />
        {{ showAdvanced ? "Hide Advanced Filters" : "Advanced Filters" }}
      </button>
    </div>

    <!-- Search & Filter Controls -->
    <div class="grid gap-2" :class="showAdvanced ? 'md:grid-cols-[1fr_140px_140px_110px]' : 'md:grid-cols-[1fr_110px]'">
      <div class="relative">
        <IconSearch :size="14" class="absolute left-3 top-1/2 -translate-y-1/2 text-text-soft" />
        <input
          v-model="query"
          class="h-9 w-full rounded-md border pl-9 pr-3 text-xs"
          placeholder="Search by name, ID, or program"
        />
      </div>

      <template v-if="showAdvanced">
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
      </template>

      <button class="inline-flex h-9 items-center justify-center gap-1 rounded-md border text-xs hover:bg-surface-muted transition">
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
