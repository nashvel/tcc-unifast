<script setup lang="ts">
import { computed, ref } from "vue";
import { IconBellRinging, IconEye, IconSearch, IconSend } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import DataTable from "@/components/tables/DataTable.vue";
import AppDialog from "@/components/dialogs/AppDialog.vue";

type AcademicRecord = {
  id: number;
  studentNo: string;
  name: string;
  program: string;
  gwa: string;
  standing: string;
  risk: "Clear" | "Watchlist" | "At risk";
  reason: string;
  notified: string;
};

const query = ref("");
const notifyOpen = ref(false);
const selected = ref<AcademicRecord | null>(null);

const records: AcademicRecord[] = [
  {
    id: 1,
    studentNo: "2024-00182",
    name: "Maria Angela Santos",
    program: "BS Information Technology",
    gwa: "1.42",
    standing: "Regular",
    risk: "Clear",
    reason: "GWA and unit load are within expected range.",
    notified: "No notice needed",
  },
  {
    id: 2,
    studentNo: "2024-00194",
    name: "John Paul Ramirez",
    program: "BS Business Administration",
    gwa: "1.88",
    standing: "Regular",
    risk: "Clear",
    reason: "Academic standing remains stable.",
    notified: "No notice needed",
  },
  {
    id: 3,
    studentNo: "2024-00207",
    name: "Nicole Anne Flores",
    program: "BS Education",
    gwa: "2.31",
    standing: "Regular",
    risk: "Watchlist",
    reason: "GWA is close to the monitoring threshold.",
    notified: "Reminder sent",
  },
  {
    id: 4,
    studentNo: "2024-00231",
    name: "Christian Dela Cruz",
    program: "BS Criminology",
    gwa: "2.76",
    standing: "Probation",
    risk: "At risk",
    reason: "Probation standing and GWA require student follow-up.",
    notified: "Needs notice",
  },
];

const filtered = computed(() =>
  records.filter((record) =>
    `${record.studentNo} ${record.name} ${record.program} ${record.risk}`
      .toLowerCase()
      .includes(query.value.toLowerCase()),
  ),
);

function badgeClass(risk: AcademicRecord["risk"]) {
  if (risk === "At risk") return "bg-danger-soft text-danger";
  if (risk === "Watchlist") return "bg-warning-soft text-warning";
  return "bg-success-soft text-success";
}

function openNotify(record: AcademicRecord) {
  selected.value = record;
  notifyOpen.value = true;
}
</script>

<template>
  <div>
    <PageHeader
      title="Academic Records"
      description="Monitor academic standing and notify students who may be at risk."
    />

    <div class="relative mb-3 max-w-lg">
      <IconSearch :size="14" class="absolute left-3 top-1/2 -translate-y-1/2 text-text-soft" />
      <input
        v-model="query"
        class="h-9 w-full rounded-md border pl-9 pr-3 text-xs"
        placeholder="Search academic records or risk status"
      />
    </div>

    <DataTable
      :headings="[
        'Student #',
        'Grantee',
        'Program',
        'GWA',
        'Standing',
        'Risk status',
        'Notification',
        '',
      ]"
    >
      <tr v-for="record in filtered" :key="record.id">
        <td class="px-3 py-3 font-mono">{{ record.studentNo }}</td>
        <td class="px-3 py-3 font-medium">{{ record.name }}</td>
        <td class="px-3 py-3 text-text-muted">{{ record.program }}</td>
        <td class="px-3 py-3">{{ record.gwa }}</td>
        <td class="px-3 py-3">{{ record.standing }}</td>
        <td class="px-3 py-3">
          <span class="rounded-full px-2 py-0.5 text-micro" :class="badgeClass(record.risk)">
            {{ record.risk }}
          </span>
        </td>
        <td class="px-3 py-3 text-xs text-text-muted">{{ record.notified }}</td>
        <td class="px-3 py-3">
          <div class="flex justify-end gap-3">
            <button
              v-if="record.risk !== 'Clear'"
              class="inline-flex items-center gap-1 text-primary"
              @click="openNotify(record)"
            >
              <IconSend :size="14" /> Notify
            </button>
            <RouterLink
              :to="`/app/academic/${record.id}`"
              class="inline-flex items-center gap-1 text-text-muted hover:text-primary"
            >
              <IconEye :size="14" /> View
            </RouterLink>
          </div>
        </td>
      </tr>
    </DataTable>

    <AppDialog
      v-model="notifyOpen"
      title="Notify at-risk student"
      :description="
        selected
          ? `Prepare an academic risk notice for ${selected.name}.`
          : 'Prepare an academic risk notice.'
      "
    >
      <div v-if="selected" class="space-y-4">
        <article class="rounded-lg border bg-surface-muted/50 p-4">
          <div class="flex items-start gap-3">
            <IconBellRinging :size="18" class="mt-0.5 text-primary" />
            <div>
              <p class="text-sm font-semibold">{{ selected.name }}</p>
              <p class="mt-1 text-xs text-text-muted">
                {{ selected.studentNo }} · {{ selected.program }}
              </p>
              <p class="mt-2 text-xs leading-5 text-text-muted">{{ selected.reason }}</p>
            </div>
          </div>
        </article>

        <label class="block">
          <span class="text-xs font-medium">Mock notification message</span>
          <textarea
            class="mt-1 min-h-28 w-full rounded-md border bg-surface p-3 text-xs leading-5"
            :value="`Hello ${selected.name}, our office noticed that your current academic standing may put your TES status at risk. Please visit the scholarship office or reply to this notice so we can guide you on the next steps.`"
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
