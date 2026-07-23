<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { useRoute } from "vue-router";
import {
  IconAlertTriangle,
  IconArrowLeft,
  IconCheck,
  IconChecklist,
  IconFileCheck,
  IconSchool,
} from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import DataTable from "@/components/tables/DataTable.vue";
import CardSkeleton from "@/components/ui/CardSkeleton.vue";
import EmptyState from "@/components/ui/EmptyState.vue";
import StatGridSkeleton from "@/components/ui/StatGridSkeleton.vue";

type CourseRemark = "Passed" | "Failed" | "Dropped";
type AcademicCourse = {
  id: number;
  code: string;
  title: string;
  units: number;
  grade: string | null;
  remark: CourseRemark;
};
type AcademicSemester = {
  id: number;
  term: string;
  gwa: string | null;
  units_taken: number;
  units_passed: number;
  courses: AcademicCourse[];
};
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
  semesters: AcademicSemester[];
};

const route = useRoute();
const record = ref<AcademicRecord | null>(null);
const loading = ref(true);
const error = ref("");

async function load() {
  loading.value = true;
  error.value = "";
  try {
    const response = await fetch(`/api/academic-records/${route.params.id}`, {
      headers: { Accept: "application/json" },
    });
    const payload = await response.json();
    if (!response.ok) throw new Error(payload.message || "Unable to load academic record.");
    record.value = payload.data;
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "Unable to load academic record.";
  } finally {
    loading.value = false;
  }
}

onMounted(load);

const totalUnitsTaken = computed(() =>
  record.value?.semesters.reduce((total, semester) => total + semester.units_taken, 0) ?? 0,
);
const totalUnitsPassed = computed(() =>
  record.value?.semesters.reduce((total, semester) => total + semester.units_passed, 0) ?? 0,
);

function remarkCount(remark: CourseRemark) {
  return record.value?.remarks[remark.toLowerCase() as "passed" | "failed" | "dropped"] ?? 0;
}

function remarkClass(remark: CourseRemark) {
  if (remark === "Failed") return "bg-danger-soft text-danger";
  if (remark === "Dropped") return "bg-warning-soft text-warning";
  return "bg-success-soft text-success";
}

function remarkIcon(remark: CourseRemark) {
  if (remark === "Passed") return IconCheck;
  if (remark === "Failed") return IconAlertTriangle;
  return IconChecklist;
}
</script>

<template>
  <div>
    <RouterLink
      to="/app/academic"
      class="mb-3 inline-flex items-center gap-1 text-xs text-text-muted"
    >
      <IconArrowLeft :size="14" />Academic records
    </RouterLink>

    <PageHeader
      :title="record?.name || 'Academic record'"
      :description="
        record
          ? `${record.student_number || record.student_id} - ${record.program} - ${record.year_level || 'Year level not set'}`
          : 'Loading academic history'
      "
    />

    <div v-if="loading" class="space-y-4">
      <StatGridSkeleton :count="5" />
      <CardSkeleton :lines="6" />
    </div>
    <EmptyState
      v-else-if="error"
      variant="error"
      title="Couldn't load academic record"
      :hint="error"
      @retry="load()"
    />

    <template v-else-if="record">
    <section class="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
      <article class="rounded-lg border bg-surface p-4">
        <IconSchool :size="18" class="text-primary" />
        <p class="mt-3 text-xs text-text-muted">Latest GWA</p>
        <p class="mt-1 text-xl font-semibold tabular-nums">{{ record.latest_gwa || "-" }}</p>
      </article>
      <article class="rounded-lg border bg-surface p-4">
        <IconChecklist :size="18" class="text-primary" />
        <p class="mt-3 text-xs text-text-muted">Units passed</p>
        <p class="mt-1 text-xl font-semibold tabular-nums">
          {{ totalUnitsPassed }} / {{ totalUnitsTaken }}
        </p>
      </article>
      <article
        v-for="remark in (['Passed', 'Failed', 'Dropped'] as CourseRemark[])"
        :key="remark"
        class="rounded-lg border bg-surface p-4"
      >
        <component :is="remarkIcon(remark)" :size="18" class="text-primary" />
        <p class="mt-3 text-xs text-text-muted">{{ remark }} courses</p>
        <p class="mt-1 text-xl font-semibold tabular-nums">
          {{ remarkCount(remark) }}
        </p>
      </article>
    </section>

    <section class="mb-4 rounded-lg border bg-surface p-4">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 class="text-sm font-semibold">Scholarship submission count</h2>
          <p class="mt-1 text-xs text-text-muted">
            Only submissions reviewed as approved are counted for scholarship program history.
          </p>
        </div>
        <div class="rounded-lg bg-success-soft px-4 py-3 text-right text-success">
          <div class="flex items-center justify-end gap-2">
            <IconFileCheck :size="18" />
            <p class="text-2xl font-semibold tabular-nums">{{ record.approved_submissions }}</p>
          </div>
          <p class="text-micro">approved of {{ record.total_submissions }} total submissions</p>
        </div>
      </div>
    </section>

    <section class="space-y-4">
      <article
        v-for="semester in record.semesters"
        :key="semester.term"
        class="overflow-hidden rounded-lg border bg-surface"
      >
        <header class="flex flex-wrap items-center justify-between gap-3 border-b p-4">
          <div>
            <h2 class="text-sm font-semibold">{{ semester.term }}</h2>
            <p class="mt-1 text-xs text-text-muted">
              {{ semester.units_passed }} of {{ semester.units_taken }} units passed
            </p>
          </div>
          <div class="text-right">
            <p class="text-lg font-semibold tabular-nums">{{ semester.gwa || "-" }}</p>
            <p class="text-micro text-text-muted">semester GWA</p>
          </div>
        </header>

        <DataTable :headings="['Course code', 'Course title', 'Units', 'Grade', 'Remarks']">
          <tr v-for="course in semester.courses" :key="`${semester.term}-${course.code}`">
            <td class="px-3 py-3 font-mono">{{ course.code }}</td>
            <td class="px-3 py-3 font-medium">{{ course.title }}</td>
            <td class="px-3 py-3 tabular-nums">{{ course.units }}</td>
            <td class="px-3 py-3 font-semibold tabular-nums">{{ course.grade || "-" }}</td>
            <td class="px-3 py-3">
              <span class="rounded-full px-2 py-0.5 text-micro" :class="remarkClass(course.remark)">
                {{ course.remark }}
              </span>
            </td>
          </tr>
        </DataTable>
      </article>
    </section>
    </template>
  </div>
</template>
