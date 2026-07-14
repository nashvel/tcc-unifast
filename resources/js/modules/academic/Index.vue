<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { IconEye, IconSearch } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import DataTable from "@/components/tables/DataTable.vue";

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
const remarkFilter = ref<"All" | CourseRemark>("All");
const records = ref<AcademicRecord[]>([]);
const loading = ref(true);
const error = ref("");

onMounted(async () => {
  try {
    const response = await fetch("/api/academic-records", { headers: { Accept: "application/json" } });
    const payload = await response.json();
    if (!response.ok) throw new Error(payload.message || "Unable to load academic records.");
    records.value = payload.data || [];
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "Unable to load academic records.";
  } finally {
    loading.value = false;
  }
});

const filtered = computed(() =>
  records.value.filter((record) => {
    const matchesQuery = `${record.student_number ?? ""} ${record.student_id} ${record.name} ${record.program}`
      .toLowerCase()
      .includes(query.value.toLowerCase());
    const matchesRemark =
      remarkFilter.value === "All" || remarkCount(record, remarkFilter.value) > 0;
    return matchesQuery && matchesRemark;
  }),
);

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

    <p v-if="error" class="mb-3 rounded-md border border-danger/30 bg-danger-soft p-3 text-xs text-danger">
      {{ error }}
    </p>

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
      <tr v-if="loading">
        <td colspan="7" class="px-3 py-8 text-center text-text-muted">Loading academic records...</td>
      </tr>
      <tr v-for="record in filtered" :key="record.id">
        <td class="px-3 py-3 font-mono">{{ record.student_number || record.student_id }}</td>
        <td class="px-3 py-3">
          <p class="font-medium">{{ record.name }}</p>
          <p class="mt-0.5 text-micro text-text-muted">{{ record.year_level || "Year level not set" }}</p>
        </td>
        <td class="px-3 py-3 text-text-muted">{{ record.program }}</td>
        <td class="px-3 py-3 font-semibold tabular-nums">{{ record.latest_gwa || "-" }}</td>
        <td class="px-3 py-3">
          <div class="flex flex-wrap gap-1.5">
            <span
              v-for="remark in (['Passed', 'Failed', 'Dropped'] as CourseRemark[])"
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
      <tr v-if="!loading && !filtered.length">
        <td colspan="7" class="px-3 py-8 text-center text-text-muted">No academic records found.</td>
      </tr>
    </DataTable>
  </div>
</template>
