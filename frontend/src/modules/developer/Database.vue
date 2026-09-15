<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import {
  IconDatabase,
  IconTable,
  IconRefresh,
  IconSearch,
  IconChevronRight,
  IconLoader,
  IconServer,
  IconTrendingUp,
} from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import { apiFetch } from "@/api/client";
import { toast } from "@/composables/useToast";

type TableItem = {
  name: string;
  rows: number;
  columns: number;
  column_names: string[];
};

type Column = { name: string; type: string; nullable: boolean; default: string | null; primary: boolean };
type TableDetail = { name: string; columns: Column[]; row_count: number };

type DbSummary = {
  total_tables: number;
  total_rows: number;
  database: string;
  largest_table: string;
};

const tables = ref<TableItem[]>([]);
const summary = ref<DbSummary | null>(null);
const selectedTableName = ref<string | null>(null);
const selectedTable = computed(() => tables.value.find((table) => table.name === selectedTableName.value) ?? null);
const selectedTableDetail = ref<TableDetail | null>(null);
const loading = ref(false);
const detailLoading = ref(false);
const search = ref("");
const errorMessage = ref("");

async function fetchTables() {
  loading.value = true;
  errorMessage.value = "";
  try {
    const res = await apiFetch<{ data: TableItem[]; summary?: DbSummary }>("/api/database/tables");
    tables.value = res.data ?? [];
    summary.value = res.summary ?? null;
  } catch (err: any) {
    errorMessage.value = err?.message || "Failed to inspect database structure from server.";
    toast.error(errorMessage.value);
    tables.value = [];
    summary.value = null;
  } finally {
    loading.value = false;
    if (tables.value.length > 0 && !selectedTableName.value) {
      await inspectTable(tables.value[0].name);
    }
  }
}

async function inspectTable(tableName: string) {
  selectedTableName.value = tableName;
  selectedTableDetail.value = null;
  detailLoading.value = true;
  try {
    const response = await apiFetch<{ data: TableDetail }>(`/api/database/tables/${tableName}`);
    selectedTableDetail.value = response.data;
  } catch (err: any) {
    errorMessage.value = err?.message || `Failed to inspect ${tableName}.`;
    toast.error(errorMessage.value);
  } finally {
    detailLoading.value = false;
  }
}

const filteredTables = computed(() => {
  const seen = new Set<string>();
  return tables.value.filter((t) => {
    if (!t || !t.name || seen.has(t.name)) return false;
    seen.add(t.name);
    return !search.value || t.name.toLowerCase().includes(search.value.toLowerCase());
  });
});

onMounted(fetchTables);
</script>

<template>
  <div class="space-y-5">
    <PageHeader
      title="Database Schema Inspector"
      description="Inspect active database tables, column structures, and live database KPIs."
    >
      <template #actions>
        <button class="inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-xs" @click="fetchTables">
          <IconRefresh :size="14" :class="loading ? 'animate-spin' : ''" /> Refresh Tables
        </button>
      </template>
    </PageHeader>

    <div v-if="errorMessage" class="rounded-lg border border-red-500/30 bg-red-950/40 p-4 text-xs text-red-200">
      {{ errorMessage }}
    </div>

    <!-- Database KPI Summary Cards -->
    <section v-if="summary" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <div class="rounded-lg border bg-surface p-4 shadow-sm">
        <div class="flex items-center justify-between">
          <span class="text-xs font-medium text-text-muted">Total Tables</span>
          <span class="grid size-7 place-items-center rounded-md bg-surface-muted text-text-muted">
            <IconTable :size="15" />
          </span>
        </div>
        <p class="mt-2 text-2xl font-bold font-mono tracking-tight text-text">{{ summary.total_tables }}</p>
        <p class="mt-1 text-2xs text-text-muted">Active Database Schemas</p>
      </div>

      <div class="rounded-lg border bg-surface p-4 shadow-sm">
        <div class="flex items-center justify-between">
          <span class="text-xs font-medium text-text-muted">Total Stored Rows</span>
          <span class="grid size-7 place-items-center rounded-md bg-surface-muted text-text-muted">
            <IconDatabase :size="15" />
          </span>
        </div>
        <p class="mt-2 text-2xl font-bold font-mono tracking-tight text-text">{{ summary.total_rows.toLocaleString() }}</p>
        <p class="mt-1 text-2xs text-text-muted">Database Records</p>
      </div>

      <div class="rounded-lg border bg-surface p-4 shadow-sm">
        <div class="flex items-center justify-between">
          <span class="text-xs font-medium text-text-muted">Database Engine</span>
          <span class="grid size-7 place-items-center rounded-md bg-surface-muted text-text-muted">
            <IconServer :size="15" />
          </span>
        </div>
        <p class="mt-2 text-xl font-bold font-mono tracking-tight text-text truncate" :title="summary.database">{{ summary.database }}</p>
        <p class="mt-1 text-2xs text-text-muted">Active Connection Driver</p>
      </div>

      <div class="rounded-lg border bg-surface p-4 shadow-sm">
        <div class="flex items-center justify-between">
          <span class="text-xs font-medium text-text-muted">Largest Table</span>
          <span class="grid size-7 place-items-center rounded-md bg-surface-muted text-text-muted">
            <IconTrendingUp :size="15" />
          </span>
        </div>
        <p class="mt-2 text-sm font-bold font-mono tracking-tight text-text truncate" :title="summary.largest_table">{{ summary.largest_table }}</p>
        <p class="mt-1 text-2xs text-text-muted">Most Populated Table</p>
      </div>
    </section>

    <div class="mb-2">
      <div class="relative max-w-md">
        <IconSearch :size="14" class="absolute left-3 top-1/2 -translate-y-1/2 text-text-soft" />
        <input
          v-model="search"
          class="h-9 w-full rounded-md border pl-9 pr-3 text-xs"
          placeholder="Filter table name..."
        />
      </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[280px_1fr]">
      <!-- Table list sidebar -->
      <div class="space-y-1 rounded-lg border bg-surface p-2">
        <div v-if="loading" class="p-4 text-center text-xs text-text-muted">
          <IconLoader :size="16" class="animate-spin inline mr-2" /> Inspecting database...
        </div>
        <div v-else-if="filteredTables.length === 0" class="p-4 text-center text-xs text-text-muted">
          {{ errorMessage ? "Unable to connect to database API." : "No tables found." }}
        </div>
        <button
          v-for="t in filteredTables"
          :key="t.name"
          :class="[
            'w-full text-left px-3 py-2 rounded-md text-xs flex items-center justify-between font-mono transition',
            selectedTable?.name === t.name ? 'bg-[var(--primary-soft)] text-[var(--primary)] font-bold border-l-2 border-[var(--primary)]' : 'hover:bg-[var(--surface-muted)] text-[var(--text)]',
          ]"
          @click="inspectTable(t.name)"
        >
          <div class="flex items-center gap-2 truncate">
            <IconTable :size="14" />
            <span class="truncate">{{ t.name }}</span>
          </div>
          <span class="text-2xs opacity-75 shrink-0">{{ t.rows }} rows</span>
        </button>
      </div>

      <!-- Selected Table Inspector -->
      <div v-if="selectedTable" class="rounded-lg border bg-surface p-5 space-y-4">
        <div class="flex items-center justify-between border-b pb-3">
          <div>
            <h2 class="text-base font-bold font-mono text-text">`{{ selectedTable.name }}`</h2>
            <p class="text-2xs text-text-muted mt-0.5">
              Live schema metadata from the connected database.
            </p>
          </div>
          <span class="rounded bg-surface-muted px-2 py-1 text-2xs font-mono text-text">
            {{ selectedTableDetail?.columns.length ?? selectedTable.columns }} columns, {{ selectedTableDetail?.row_count ?? selectedTable.rows }} total rows
          </span>
        </div>

        <div>
          <h3 class="text-xs font-semibold text-text mb-2">Column Schemas</h3>
          <div class="overflow-x-auto rounded border">
            <table class="w-full text-xs">
              <thead>
                <tr class="border-b bg-surface-muted text-left text-text-muted font-mono">
                  <th class="px-3 py-2 font-medium">#</th>
                  <th class="px-3 py-2 font-medium">Column Name</th>
                  <th class="px-3 py-2 font-medium">Type</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="detailLoading"><td colspan="3" class="px-3 py-6 text-center text-text-muted">Loading column metadata…</td></tr>
                <tr
                  v-for="(col, i) in selectedTableDetail?.columns ?? []"
                  :key="col.name"
                  class="border-b last:border-0 hover:bg-surface-muted/50 font-mono"
                >
                  <td class="px-3 py-2 text-text-muted">{{ i + 1 }}</td>
                  <td class="px-3 py-2 font-semibold text-text">{{ col.name }}</td>
                  <td class="px-3 py-2 text-text-muted">
                    {{ col.type }}{{ col.primary ? ' PRIMARY' : '' }}{{ col.nullable ? ' NULL' : ' NOT NULL' }}
                  </td>
                </tr>
                <tr v-if="!detailLoading && !selectedTableDetail"><td colspan="3" class="px-3 py-6 text-center text-text-muted">Column metadata is unavailable.</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div v-else class="flex items-center justify-center rounded-lg border border-dashed p-12 text-xs text-text-muted">
        Select a table to inspect column properties and schema metrics.
      </div>
    </div>
  </div>
</template>
