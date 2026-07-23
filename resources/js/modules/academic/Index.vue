<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { keepPreviousData, useQuery } from "@tanstack/vue-query";
import { IconEye, IconSearch } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import DataTable from "@/components/tables/DataTable.vue";
import TablePagination from "@/components/tables/TablePagination.vue";
import TableStates from "@/components/ui/TableStates.vue";
import { apiFetch, buildQuery, type PaginatedResponse } from "@/lib/api";
import { queryKeys } from "@/lib/queryClient";
import { useOnline } from "@/composables/useOnline";

type CourseRemark = "Passed" | "Failed" | "Dropped";
type AcademicRecord = {
  id: number;
  student_number: string | null;
  student_id: string;
  name: string;
  program: string;
  year_level: string | null;
  latest_gwa: string | null;
  approved_submissions: number;
  total_submissions: number;
  remarks: { passed: number; failed: number; dropped: number };
};

const query = ref("");
const debouncedSearch = ref("");
const remarkFilter = ref<"All" | CourseRemark>("All");
const page = ref(1);
const { online } = useOnline();

let searchTimer: ReturnType<typeof setTimeout> | undefined;
watch(query, (value) => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    debouncedSearch.value = value;
    page.value = 1;
  }, 250);
});

const recordsQuery = useQuery({
  queryKey: computed(() =>
    queryKeys.academic({ page: page.value, search: debouncedSearch.value }),
  ),
  queryFn: () =>
    apiFetch<PaginatedResponse<AcademicRecord>>(
      `/api/academic-records${buildQuery({
        page: page.value,
        per_page: 15,
        search: debouncedSearch.value,
      })}`,
    ),
  placeholderData: keepPreviousData,
});

const filtered = computed(() =>
  (recordsQuery.data.value?.data ?? []).filter((record) => {
    if (remarkFilter.value === "All") return true;
    return remarkCount(record, remarkFilter.value) > 0;
  }),
);
const meta = computed(() => recordsQuery.data.value?.meta);

function remarkCount(record: AcademicRecord, remark: CourseRemark) {
  return record.remarks[remark.toLowerCase() as "passed" | "failed" | "dropped"] ?? 0;
}

function remarkClass(remark: CourseRemark) {
  if (remark === "Failed") return "bg-danger-soft text-danger";
  if (remark === "Dropped") return "bg-warning-soft text-warning";
  return "bg-success-soft text-success";
}
</script>

<template>
  <div>
    <PageHeader
      title="Academic Records"
      description="Review each grantee's course history, grades, semester remarks, and approved scholarship submissions."
    />

    <div class="mb-4 grid gap-2 md:grid-cols-[1fr_180px]">
      <div class="relative">
        <IconSearch :size="14" class="absolute left-3 top-1/2 -translate-y-1/2 text-text-soft" />
        <input
          v-model="query"
          class="h-9 w-full rounded-md border pl-9 pr-3 text-xs"
          placeholder="Search by student number, name, or program"
        />
      </div>
      <select v-model="remarkFilter" class="h-9 rounded-md border bg-surface px-3 text-xs">
        <option>All</option>
        <option>Passed</option>
        <option>Failed</option>
        <option>Dropped</option>
      </select>
    </div>

    <DataTable
      :headings="[
        'Student #',
        'Grantee',
        'Program',
        'Latest GWA',
        'Course remarks',
        'Approved submissions',
        '',
      ]"
    >
      <TableStates
        v-if="
          recordsQuery.isLoading.value ||
          recordsQuery.isError.value ||
          (!online && !filtered.length) ||
          (!recordsQuery.isLoading.value && !filtered.length)
        "
        :col-span="7"
        :is-loading="recordsQuery.isLoading.value"
        :is-fetching="recordsQuery.isFetching.value"
        :is-error="recordsQuery.isError.value"
        :error="recordsQuery.error.value"
        :is-offline="!online && !filtered.length"
        :is-empty="!recordsQuery.isLoading.value && !recordsQuery.isError.value && !filtered.length"
        empty-title="No academic records found"
        empty-hint="Try a different search or remark filter."
        @retry="recordsQuery.refetch()"
      />
      <template v-else>
        <tr v-for="record in filtered" :key="record.id">
          <td class="px-3 py-3 font-mono">{{ record.student_number || record.student_id }}</td>
          <td class="px-3 py-3">
            <p class="font-medium">{{ record.name }}</p>
            <p class="mt-0.5 text-micro text-text-muted">
              {{ record.year_level || "Year level not set" }}
            </p>
          </td>
          <td class="px-3 py-3 text-text-muted">{{ record.program }}</td>
          <td class="px-3 py-3 font-semibold tabular-nums">{{ record.latest_gwa || "-" }}</td>
          <td class="px-3 py-3">
            <div class="flex flex-wrap gap-1.5">
              <span
                v-for="remark in ['Passed', 'Failed', 'Dropped'] as CourseRemark[]"
                :key="remark"
                class="rounded-full px-2 py-0.5 text-micro"
                :class="remarkClass(remark)"
              >
                {{ remarkCount(record, remark) }} {{ remark }}
              </span>
            </div>
          </td>
          <td class="px-3 py-3">
            <p class="font-semibold tabular-nums">{{ record.approved_submissions }}</p>
            <p class="mt-0.5 text-micro text-text-muted">
              only approved count / {{ record.total_submissions }} total
            </p>
          </td>
          <td class="px-3 py-3 text-right">
            <RouterLink
              :to="`/app/academic/${record.id}`"
              class="inline-flex items-center gap-1 text-primary"
            >
              <IconEye :size="14" /> View history
            </RouterLink>
          </td>
        </tr>
      </template>
      <template v-if="meta" #footer>
        <TablePagination
          :meta="meta"
          :busy="recordsQuery.isFetching.value"
          @update:page="page = $event"
        />
      </template>
    </DataTable>
  </div>
</template>
