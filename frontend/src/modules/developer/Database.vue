<script setup lang="ts">
import { onMounted, ref } from "vue";
import { IconDatabase, IconSearch, IconRefresh, IconTable, IconCode, IconLoader, IconKey } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import { apiFetch } from "@/api/client";

type TableInfo = {
  name: string;
  columns: number;
  rows: number;
  column_names: string[];
};

type TableDetail = {
  name: string;
  columns: { name: string; type: string; nullable: boolean; default: string | null; primary: boolean }[];
  indexes: { name: string; unique: boolean }[];
  row_count: number;
};

type DbStats = {
  tables: { table: string; rows: number; columns: number }[];
  summary: { total_tables: number; total_rows: number; database: string };
};

const tables = ref<TableInfo[]>([]);
const selectedTable = ref<string | null>(null);
const tableDetail = ref<TableDetail | null>(null);
const search = ref("");
const stats = ref<DbStats | null>(null);
const loading = ref(false);
const loadingDetail = ref(false);
const error = ref("");

async function loadTables() {
  loading.value = true;
  error.value = "";
  try {
    const payload = await apiFetch<{ data: TableInfo[] }>("/api/database/tables");
    tables.value = payload.data;
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "Failed to load tables.";
  } finally {
    loading.value = false;
  }
}

async function loadStats() {
  try {
    const payload = await apiFetch<{ data: DbStats }>("/api/database/stats");
    stats.value = payload.data;
  } catch {}
}

async function selectTable(name: string) {
  selectedTable.value = name;
  loadingDetail.value = true;
  error.value = "";
  try {
    const payload = await apiFetch<{ data: TableDetail }>(`/api/database/tables/${name}`);
    tableDetail.value = payload.data;
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "Failed to load table.";
  } finally {
    loadingDetail.value = false;
  }
}

onMounted(() => {
  loadTables();
  loadStats();
});
</script>

<template>
  <div>
    <PageHeader
      title="Database Viewer"
      description="Browse tables and view their structure."
    >
      <template #actions>
        <button
          class="inline-flex h-8 items-center gap-1.5 rounded-md border border-[var(--border)] bg-[var(--surface)] px-3 text-xs text-[var(--text-muted)] hover:bg-[var(--surface-muted)]"
          @click="loadTables(); loadStats();"
        >
          <IconRefresh :size="14" /> Refresh
        </button>
      </template>
    </PageHeader>

    <!-- Stats Summary -->
    <section v-if="stats" class="mb-4 grid gap-3 sm:grid-cols-3">
      <div class="rounded-lg border border-[var(--border)] bg-[var(--surface)] p-4">
        <p class="text-xs text-[var(--text-muted)]">Tables</p>
        <p class="mt-1 text-xl font-semibold text-[var(--text)]">{{ stats.summary.total_tables }}</p>
      </div>
      <div class="rounded-lg border border-[var(--border)] bg-[var(--surface)] p-4">
        <p class="text-xs text-[var(--text-muted)]">Total Rows</p>
        <p class="mt-1 text-xl font-semibold text-[var(--text)]">{{ stats.summary.total_rows.toLocaleString() }}</p>
      </div>
      <div class="rounded-lg border border-[var(--border)] bg-[var(--surface)] p-4">
        <p class="text-xs text-[var(--text-muted)]">Database</p>
        <p class="mt-1 text-xl font-semibold text-[var(--text)]">{{ stats.summary.database }}</p>
      </div>
    </section>

    <!-- Search -->
    <div class="mb-4">
      <div class="relative max-w-md">
        <IconSearch :size="14" class="absolute left-3 top-1/2 -translate-y-1/2 text-[var(--text-soft)]" />
        <input
          v-model="search"
          class="h-9 w-full rounded-md border border-[var(--border)] bg-[var(--surface)] pl-9 pr-3 text-xs text-[var(--text)]"
          placeholder="Search tables..."
        />
      </div>
    </div>

    <!-- Tables Grid -->
    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
      <div
        v-for="table in tables.filter(t => !search || t.name.includes(search.toLowerCase()))"
        :key="table.name"
        :class="[
          'rounded-lg border p-4 cursor-pointer transition-all',
          selectedTable === table.name
            ? 'border-[var(--primary)] bg-[var(--primary-soft)]'
            : 'border-[var(--border)] bg-[var(--surface)] hover:border-[var(--border-strong)]',
        ]"
        @click="selectTable(table.name)"
      >
        <div class="flex items-center gap-2">
          <IconTable :size="16" class="text-[var(--text-soft)]" />
          <span class="text-sm font-medium text-[var(--text)]">{{ table.name }}</span>
        </div>
        <div class="mt-2 flex gap-3 text-2xs text-[var(--text-muted)]">
          <span>{{ table.columns }} columns</span>
          <span>{{ table.rows.toLocaleString() }} rows</span>
        </div>
      </div>
    </div>

    <!-- Table Detail -->
    <div v-if="selectedTable" class="mt-6">
      <div v-if="loadingDetail" class="flex items-center justify-center p-8">
        <IconLoader :size="20" class="animate-spin text-[var(--text-soft)]" />
      </div>
      <template v-else-if="tableDetail">
        <div class="mb-3 flex items-center gap-3">
          <h2 class="text-sm font-semibold text-[var(--text)]">{{ tableDetail.name }}</h2>
          <span class="text-2xs text-[var(--text-muted)]">{{ tableDetail.columns.length }} columns, {{ tableDetail.row_count.toLocaleString() }} rows</span>
        </div>

        <!-- Columns Table -->
        <div class="overflow-x-auto rounded-lg border border-[var(--border)] bg-[var(--surface)]">
          <table class="w-full text-xs">
            <thead>
              <tr class="border-b border-[var(--border)] text-left">
                <th class="px-3 py-2 font-medium text-[var(--text-muted)]">Column</th>
                <th class="px-3 py-2 font-medium text-[var(--text-muted)]">Type</th>
                <th class="px-3 py-2 font-medium text-[var(--text-muted)]">Nullable</th>
                <th class="px-3 py-2 font-medium text-[var(--text-muted)]">Default</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="col in tableDetail.columns" :key="col.name" class="border-b border-[var(--border)]/50 last:border-0 hover:bg-[var(--surface-muted)]/50">
                <td class="px-3 py-2">
                  <span class="flex items-center gap-1.5 font-medium text-[var(--text)]">
                    <IconKey v-if="col.primary" :size="12" class="text-[var(--warning)]" />
                    {{ col.name }}
                  </span>
                </td>
                <td class="px-3 py-2 font-mono text-2xs text-[var(--text-muted)]">{{ col.type }}</td>
                <td class="px-3 py-2">
                  <span :class="col.nullable ? 'text-[var(--warning)]' : 'text-[var(--text-soft)]'">
                    {{ col.nullable ? "YES" : "NO" }}
                  </span>
                </td>
                <td class="px-3 py-2 text-2xs text-[var(--text-soft)]">
                  {{ col.default ?? "NULL" }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </div>
  </div>
</template>
