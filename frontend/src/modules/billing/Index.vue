<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { IconDownload, IconFileInvoice, IconRefresh } from "@tabler/icons-vue";
import DataTable from "@/components/tables/DataTable.vue";
import TablePagination from "@/components/tables/TablePagination.vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import { apiFetch, buildQuery, type PaginatedResponse, type PaginationMeta } from "@/lib/api";
import { toast } from "@/composables/useToast";

type Batch = {
  id: number;
  name: string;
  academic_year: string;
  semester: string;
};

type BillingReportRow = {
  id: number;
  type: string;
  batch_id: number;
  batch: Batch | null;
  generated_by: string | null;
  total_grantees: number;
  total_amount: number;
  stipend_per_grantee: number;
  generated_at: string | null;
};

const batches = ref<Batch[]>([]);
const selectedBatchId = ref<number | null>(null);
const filterBatchId = ref<number | null>(null);
const reports = ref<BillingReportRow[]>([]);
const meta = ref<PaginationMeta | null>(null);
const page = ref(1);
const loading = ref(false);
const generating = ref(false);

const selectedBatchLabel = computed(() => {
  const batch = batches.value.find((row) => row.id === selectedBatchId.value);
  return batch ? `${batch.name} · ${batch.academic_year} · ${batch.semester}` : "";
});

onMounted(async () => {
  try {
    const payload = await apiFetch<PaginatedResponse<Batch>>("/api/batches?per_page=50");
    batches.value = payload.data;
    selectedBatchId.value = payload.data[0]?.id ?? null;
  } catch {
    batches.value = [];
  }
  await loadReports();
});

watch(filterBatchId, () => {
  if (page.value !== 1) page.value = 1;
  else void loadReports();
});

watch(page, () => {
  void loadReports();
});

async function loadReports() {
  loading.value = true;
  try {
    const payload = await apiFetch<PaginatedResponse<BillingReportRow>>(
      `/api/billing-reports${buildQuery({
        page: page.value,
        per_page: 15,
        batch_id: filterBatchId.value,
      })}`,
    );
    reports.value = payload.data;
    meta.value = payload.meta;
  } catch (error) {
    reports.value = [];
    meta.value = null;
    toast.error(error instanceof Error ? error.message : "Failed to load billing reports.");
  } finally {
    loading.value = false;
  }
}

async function generateReport() {
  if (!selectedBatchId.value) {
    toast.warning("Select a batch first.");
    return;
  }
  generating.value = true;
  try {
    await apiFetch<{ data: BillingReportRow }>("/api/billing-reports", {
      method: "POST",
      body: JSON.stringify({ batch_id: selectedBatchId.value }),
    });
    toast.success("Call-for-billing report generated.", {
      description: selectedBatchLabel.value,
    });
    page.value = 1;
    await loadReports();
  } catch (error) {
    toast.error(error instanceof Error ? error.message : "Failed to generate report.");
  } finally {
    generating.value = false;
  }
}

function formatMoney(value: number) {
  return new Intl.NumberFormat("en-PH", {
    style: "currency",
    currency: "PHP",
    minimumFractionDigits: 2,
  }).format(value);
}

function formatDate(value: string | null) {
  if (!value) return "—";
  return new Date(value).toLocaleString();
}

function downloadReport(report: BillingReportRow) {
  window.open(`/api/billing-reports/${report.id}/download`, "_blank");
}
</script>

<template>
  <div>
    <PageHeader
      title="Billing / Call-for-Billing"
      description="Generate a CHED call-for-billing PDF for verified grantees only (PHP 10,000 stipend per semester)."
    />

    <section class="mb-4 rounded-lg border bg-surface p-4">
      <div class="grid grid-cols-1 gap-3 md:grid-cols-[1fr_auto] md:items-end">
        <label class="block">
          <span class="mb-1.5 block text-2xs uppercase text-text-muted">Batch</span>
          <select
            v-model="selectedBatchId"
            class="h-9 w-full rounded-md border bg-surface px-3 text-xs"
          >
            <option :value="null" disabled>Select batch</option>
            <option v-for="batch in batches" :key="batch.id" :value="batch.id">
              {{ batch.name }} · {{ batch.academic_year }} · {{ batch.semester }}
            </option>
          </select>
        </label>
        <button
          type="button"
          class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-primary px-4 text-xs font-medium text-white disabled:opacity-60"
          :disabled="generating || !selectedBatchId"
          @click="generateReport"
        >
          <IconFileInvoice :size="16" />
          {{ generating ? "Generating…" : "Generate call-for-billing" }}
        </button>
      </div>
      <p class="mt-2 text-xs text-text-muted">
        Only grantees with Verified status are included. Pending and non-compliant grantees are excluded.
      </p>
    </section>

    <section class="mb-3 flex flex-wrap items-end justify-between gap-3">
      <label class="block min-w-56">
        <span class="mb-1.5 block text-2xs uppercase text-text-muted">Filter past reports</span>
        <select v-model="filterBatchId" class="h-9 w-full rounded-md border bg-surface px-3 text-xs">
          <option :value="null">All batches</option>
          <option v-for="batch in batches" :key="batch.id" :value="batch.id">
            {{ batch.name }}
          </option>
        </select>
      </label>
      <button
        type="button"
        class="inline-flex h-9 items-center gap-2 rounded-md border px-3 text-xs text-text-muted hover:text-primary"
        :disabled="loading"
        @click="loadReports"
      >
        <IconRefresh :size="14" /> Refresh
      </button>
    </section>

    <DataTable
      :headings="['Report', 'Batch', 'Verified', 'Total amount', 'Generated', '']"
    >
      <tr v-if="loading">
        <td colspan="6" class="px-3 py-8 text-center text-xs text-text-muted">Loading reports…</td>
      </tr>
      <tr v-for="report in reports" :key="report.id">
        <td class="px-3 py-3 font-medium">#{{ report.id }}</td>
        <td class="px-3 py-3 text-xs text-text-muted">
          {{ report.batch?.name ?? "—" }}
        </td>
        <td class="px-3 py-3">{{ report.total_grantees }}</td>
        <td class="px-3 py-3">{{ formatMoney(report.total_amount) }}</td>
        <td class="px-3 py-3 text-xs text-text-muted">
          <div>{{ formatDate(report.generated_at) }}</div>
          <div>{{ report.generated_by ?? "—" }}</div>
        </td>
        <td class="px-3 py-3">
          <button
            type="button"
            class="inline-flex items-center gap-1 text-primary"
            @click="downloadReport(report)"
          >
            <IconDownload :size="14" /> Download PDF
          </button>
        </td>
      </tr>
      <tr v-if="!loading && reports.length === 0">
        <td colspan="6" class="px-3 py-8 text-center text-xs text-text-muted">
          No call-for-billing reports yet. Select a batch and generate one.
        </td>
      </tr>
    </DataTable>

    <TablePagination
      v-if="meta && meta.last_page > 1"
      class="mt-3"
      :meta="meta"
      @update:page="(value) => (page = value)"
    />
  </div>
</template>
