<script setup lang="ts">
import { computed, ref } from "vue";
import { IconBellRinging, IconEye, IconSend } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import DataTable from "@/components/tables/DataTable.vue";
import AppDialog from "@/components/dialogs/AppDialog.vue";

type EligibilityRecord = {
  id: number;
  studentNo: string;
  name: string;
  passed: string;
  status: "Eligible" | "Needs update" | "Not eligible";
  missing: string;
  notice: string;
};

const selected = ref<EligibilityRecord | null>(null);
const noticeOpen = ref(false);

const rows: EligibilityRecord[] = [
  {
    id: 1,
    studentNo: "2024-00182",
    name: "Maria Angela Santos",
    passed: "5 / 5",
    status: "Eligible",
    missing: "No missing eligibility requirements.",
    notice: "No notice needed",
  },
  {
    id: 2,
    studentNo: "2024-00194",
    name: "John Paul Ramirez",
    passed: "4 / 5",
    status: "Needs update",
    missing: "Update household income certification.",
    notice: "Needs reminder",
  },
  {
    id: 3,
    studentNo: "2024-00207",
    name: "Nicole Anne Flores",
    passed: "5 / 5",
    status: "Eligible",
    missing: "No missing eligibility requirements.",
    notice: "No notice needed",
  },
  {
    id: 4,
    studentNo: "2024-00231",
    name: "Christian Dela Cruz",
    passed: "3 / 5",
    status: "Not eligible",
    missing: "Academic retention and duplicate assistance checks need attention.",
    notice: "Needs notice",
  },
];

const summary = computed(() => [
  ["Checked", "2,486"],
  ["Eligible", "2,184"],
  ["Need updates", "184"],
  ["Not eligible", "118"],
]);

function badgeClass(status: EligibilityRecord["status"]) {
  if (status === "Not eligible") return "bg-danger-soft text-danger";
  if (status === "Needs update") return "bg-warning-soft text-warning";
  return "bg-success-soft text-success";
}

function openNotice(row: EligibilityRecord) {
  selected.value = row;
  noticeOpen.value = true;
}
</script>

<template>
  <div>
    <PageHeader
      title="Eligibility Status"
      description="Monitor eligibility results and notify students who need to update requirements."
    />

    <section class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4" data-tour="eligibility-filters">
      <article v-for="item in summary" :key="item[0]" class="rounded-lg border bg-surface p-4">
        <p class="text-xs text-text-muted">{{ item[0] }}</p>
        <p class="mt-1 text-xl font-semibold">{{ item[1] }}</p>
      </article>
    </section>

    <div data-tour="eligibility-table">
      <DataTable
        :headings="[
          'Student #',
          'Grantee',
          'Criteria passed',
          'Eligibility status',
          'Notice status',
          '',
        ]"
      >
        <tr v-for="row in rows" :key="row.id">
          <td class="px-3 py-3 font-mono">{{ row.studentNo }}</td>
          <td class="px-3 py-3 font-medium">{{ row.name }}</td>
          <td class="px-3 py-3">{{ row.passed }}</td>
          <td class="px-3 py-3">
            <span class="rounded-full px-2 py-0.5 text-micro" :class="badgeClass(row.status)">
              {{ row.status }}
            </span>
          </td>
          <td class="px-3 py-3 text-xs text-text-muted">{{ row.notice }}</td>
          <td class="px-3 py-3">
            <div class="flex justify-end gap-3">
              <button
                v-if="row.status !== 'Eligible'"
                class="inline-flex items-center gap-1 text-primary"
                @click="openNotice(row)"
              >
                <IconSend :size="14" /> Notify
              </button>
              <RouterLink
                :to="`/app/eligibility/${row.id}`"
                class="inline-flex items-center gap-1 text-text-muted hover:text-primary"
              >
                <IconEye :size="14" /> View
              </RouterLink>
            </div>
          </td>
        </tr>
      </DataTable>
    </div>

    <AppDialog
      v-model="noticeOpen"
      title="Notify student"
      :description="
        selected
          ? `Send an eligibility update notice to ${selected.name}.`
          : 'Send an eligibility update notice.'
      "
    >
      <div v-if="selected" class="space-y-4">
        <article class="rounded-lg border bg-surface-muted/50 p-4">
          <div class="flex items-start gap-3">
            <IconBellRinging :size="18" class="mt-0.5 text-primary" />
            <div>
              <p class="text-sm font-semibold">{{ selected.name }}</p>
              <p class="mt-1 text-xs text-text-muted">{{ selected.studentNo }}</p>
              <p class="mt-2 text-xs leading-5 text-text-muted">{{ selected.missing }}</p>
            </div>
          </div>
        </article>

        <label class="block">
          <span class="text-xs font-medium">Mock notification message</span>
          <textarea
            class="mt-1 min-h-28 w-full rounded-md border bg-surface p-3 text-xs leading-5"
            :value="`Hello ${selected.name}, your TES eligibility record needs attention: ${selected.missing} Please update your requirements or visit the scholarship office for assistance.`"
          />
        </label>
      </div>

      <template #footer="{ close }">
        <button class="h-9 rounded-md border px-3 text-xs" @click="close">Cancel</button>
        <button
          class="h-9 rounded-md bg-primary px-3 text-xs font-medium text-white"
          @click="close"
        >
          Send mocked notice
        </button>
      </template>
    </AppDialog>
  </div>
</template>
