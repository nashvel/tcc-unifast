<script setup lang="ts">
import { computed, ref } from "vue";
import { useQuery } from "@tanstack/vue-query";
import { IconBellRinging, IconEye, IconSend } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import DataTable from "@/components/tables/DataTable.vue";
import AppDialog from "@/components/dialogs/AppDialog.vue";
import { apiFetch, ApiError } from "@/lib/api";
import { queryKeys } from "@/lib/queryClient";
import { toast } from "vue-sonner";

type EligibilityStatus = "Eligible" | "Needs update" | "Not eligible";

type EligibilityRecord = {
  id: number;
  studentNo: string;
  name: string;
  batch: string;
  batch_id: number | null;
  passed: string;
  status: EligibilityStatus;
  missing: string;
  notice: string;
};

type EligibilityListResponse = {
  data: EligibilityRecord[];
  meta: {
    summary: {
      checked: number;
      eligible: number;
      needs_update: number;
      not_eligible: number;
    };
    batches: string[];
  };
};

const selected = ref<EligibilityRecord | null>(null);
const noticeOpen = ref(false);
const noticeMessage = ref("");
const sending = ref(false);
const batchFilter = ref("All batches");
const statusFilter = ref("All statuses");

const eligibilityQuery = useQuery({
  queryKey: queryKeys.eligibility(),
  queryFn: () => apiFetch<EligibilityListResponse>("/api/eligibility?per_page=100"),
});

const rows = computed(() => eligibilityQuery.data.value?.data ?? []);

const batchOptions = computed(() => [
  "All batches",
  ...(eligibilityQuery.data.value?.meta.batches ??
    Array.from(new Set(rows.value.map((row) => row.batch)))),
]);

const statusOptions: Array<"All statuses" | EligibilityStatus> = [
  "All statuses",
  "Eligible",
  "Needs update",
  "Not eligible",
];

const filteredRows = computed(() =>
  rows.value.filter((row) => {
    const batchOk = batchFilter.value === "All batches" || row.batch === batchFilter.value;
    const statusOk = statusFilter.value === "All statuses" || row.status === statusFilter.value;
    return batchOk && statusOk;
  }),
);

const summary = computed(() => {
  const meta = eligibilityQuery.data.value?.meta.summary;
  const source = filteredRows.value.length === rows.value.length && meta
    ? null
    : filteredRows.value;
  if (!source && meta) {
    return [
      ["Checked", String(meta.checked)],
      ["Eligible", String(meta.eligible)],
      ["Need updates", String(meta.needs_update)],
      ["Not eligible", String(meta.not_eligible)],
    ];
  }
  const list = source ?? rows.value;
  return [
    ["Checked", String(list.length)],
    ["Eligible", String(list.filter((r) => r.status === "Eligible").length)],
    ["Need updates", String(list.filter((r) => r.status === "Needs update").length)],
    ["Not eligible", String(list.filter((r) => r.status === "Not eligible").length)],
  ];
});

function badgeClass(status: EligibilityStatus) {
  if (status === "Not eligible") return "bg-danger-soft text-danger";
  if (status === "Needs update") return "bg-warning-soft text-warning";
  return "bg-success-soft text-success";
}

function openNotice(row: EligibilityRecord) {
  selected.value = row;
  noticeMessage.value = `Hello ${row.name}, your TES batch submission needs attention: ${row.missing} Please update your requirements or visit the scholarship office for assistance.`;
  noticeOpen.value = true;
}

async function sendNotice() {
  if (!selected.value) return;
  sending.value = true;
  try {
    await apiFetch(`/api/eligibility/${selected.value.id}/notify`, {
      method: "POST",
      body: JSON.stringify({ message: noticeMessage.value }),
    });
    toast.success("Notice sent to student portal");
    noticeOpen.value = false;
    await eligibilityQuery.refetch();
  } catch (error) {
    toast.error(error instanceof ApiError ? error.message : "Unable to send notice.");
  } finally {
    sending.value = false;
  }
}
</script>

<template>
  <div>
    <PageHeader
      title="Submission eligibility"
      description="Check grantee batch submissions and Settings retention rules (max failed subjects). Citizenship and income are not re-screened."
    />

    <section class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
      <article v-for="item in summary" :key="item[0]" class="rounded-lg border bg-surface p-4">
        <p class="text-xs text-text-muted">{{ item[0] }}</p>
        <p class="mt-1 text-xl font-semibold">{{ item[1] }}</p>
      </article>
    </section>

    <section
      class="mb-4 grid grid-cols-1 gap-2 rounded-lg border bg-surface p-3 md:grid-cols-2"
      data-tour="eligibility-filters"
    >
      <label class="block">
        <span class="mb-1.5 block text-2xs uppercase text-text-muted">Batch</span>
        <select v-model="batchFilter" class="h-9 w-full rounded-md border bg-surface px-3 text-xs">
          <option v-for="batch in batchOptions" :key="batch" :value="batch">{{ batch }}</option>
        </select>
      </label>
      <label class="block">
        <span class="mb-1.5 block text-2xs uppercase text-text-muted">Status</span>
        <select v-model="statusFilter" class="h-9 w-full rounded-md border bg-surface px-3 text-xs">
          <option v-for="status in statusOptions" :key="status" :value="status">{{ status }}</option>
        </select>
      </label>
    </section>

    <div data-tour="eligibility-table">
      <p v-if="eligibilityQuery.isLoading.value" class="mb-3 text-xs text-text-muted">
        Loading eligibility…
      </p>
      <p v-else-if="eligibilityQuery.isError.value" class="mb-3 text-xs text-danger">
        Unable to load eligibility.
        <button class="ml-2 text-primary underline" @click="eligibilityQuery.refetch()">
          Retry
        </button>
      </p>
      <DataTable
        :headings="[
          'Student #',
          'Grantee',
          'Batch',
          'Checks passed',
          'Status',
          'Notice status',
          '',
        ]"
      >
        <tr v-for="row in filteredRows" :key="row.id">
          <td class="px-3 py-3 font-mono">{{ row.studentNo }}</td>
          <td class="px-3 py-3 font-medium">{{ row.name }}</td>
          <td class="px-3 py-3 text-xs text-text-muted">{{ row.batch }}</td>
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
        <tr v-if="!eligibilityQuery.isLoading.value && filteredRows.length === 0">
          <td colspan="7" class="px-3 py-8 text-center text-xs text-text-muted">
            No grantees match the selected batch and status filters. Submit a vault package with OCR
            running to populate results.
          </td>
        </tr>
      </DataTable>
    </div>

    <AppDialog
      v-model="noticeOpen"
      title="Notify grantee"
      :description="
        selected
          ? `Send a submission eligibility notice to ${selected.name}.`
          : 'Send a submission eligibility notice.'
      "
    >
      <div v-if="selected" class="space-y-4">
        <article class="rounded-lg border bg-surface-muted/50 p-4">
          <div class="flex items-start gap-3">
            <IconBellRinging :size="18" class="mt-0.5 text-primary" />
            <div>
              <p class="text-sm font-semibold">{{ selected.name }}</p>
              <p class="mt-1 text-xs text-text-muted">
                {{ selected.studentNo }} · {{ selected.batch }}
              </p>
              <p class="mt-2 text-xs leading-5 text-text-muted">{{ selected.missing }}</p>
            </div>
          </div>
        </article>

        <label class="block">
          <span class="text-xs font-medium">Notification message</span>
          <textarea
            v-model="noticeMessage"
            class="mt-1 min-h-28 w-full rounded-md border bg-surface p-3 text-xs leading-5"
          />
        </label>
      </div>

      <template #footer="{ close }">
        <button class="h-9 rounded-md border px-3 text-xs" @click="close">Cancel</button>
        <button
          class="h-9 rounded-md bg-primary px-3 text-xs font-medium text-white disabled:opacity-60"
          :disabled="sending"
          @click="sendNotice"
        >
          {{ sending ? "Sending…" : "Send notice" }}
        </button>
      </template>
    </AppDialog>
  </div>
</template>
