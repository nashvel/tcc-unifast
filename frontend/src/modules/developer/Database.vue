<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { IconDatabase, IconSearch, IconRefresh, IconTable, IconCode, IconLoader } from "@tabler/icons-vue";
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

type QueryResult = {
  data: Record<string, unknown>[];
  meta: { count: number; elapsed_ms: number };
};

type DbStats = {
  tables: { table: string; rows: number; columns: number }[];
  summary: { total_tables: number; total_rows: number; database: string };
};

const tables = ref<TableInfo[]>([]);
const selectedTable = ref<string | null>(null);
const tableDetail = ref<TableDetail | null>(null);
const rows = ref<Record<string, unknown>[]>([]);
const rowMeta = ref({ current_page: 1, per_page: 25, total: 0, last_page: 1 });
const search = ref("");
const sort = ref("id");
const direction = ref<"asc" | "desc">("asc");
const stats = ref<DbStats | null>(null);

const loading = ref(false);
const loadingRows = ref(false);
const error = ref("");

const sqlQuery = ref("");
const queryResults = ref<QueryResult | null>(null);
const queryLoading = ref(false);
const queryError = ref("");
const activeTab = ref<"tables" | "query">("tables");

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
  loadingRows.value = true;
  error.value = "";
  try {
    const [detailPayload, rowsPayload] = await Promise.all([
      apiFetch<{ data: TableDetail }>(`/api/database/tables/${name}`),
      apiFetch<{ data: Record<string, unknown>[]; meta: typeof rowMeta.value }>(
        `/api/database/tables/${name}/rows?page=1&per_page=25&sort=id&direction=asc`
      ),
    ]);
    tableDetail.value = detailPayload.data;
    rows.value = rowsPayload.data;
    rowMeta.value = rowsPayload.meta;
    sort.value = "id";
    direction.value = "asc";
    search.value = "";
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "Failed to load table.";
  } finally {
    loadingRows.value = false;
  }
}

async function loadRows() {
  if (!selectedTable.value) return;
  loadingRows.value = true;
  try {
    const payload = await apiFetch<{ data: Record<string, unknown>[]; meta: typeof rowMeta.value }>(
      `/api/database/tables/${selectedTable.value}/rows?page=${rowMeta.value.current_page}&per_page=${rowMeta.value.per_page}&search=${search.value}&sort=${sort.value}&direction=${direction.value}`
    );
    rows.value = payload.data;
    rowMeta.value = payload.meta;
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "Failed to load rows.";
  } finally {
    loadingRows.value = false;
  }
}

async function runQuery() {
  if (!sqlQuery.value.trim()) return;
  queryLoading.value = true;
  queryError.value = "";
  queryResults.value = null;
  try {
    queryResults.value = await apiFetch<QueryResult>("/api/database/query", {
      method: "POST",
      body: JSON.stringify({ sql: sqlQuery.value }),
    });
  } catch (exception) {
    queryError.value = exception instanceof Error ? exception.message : "Query failed.";
  } finally {
    queryLoading.value = false;
  }
}

function toggleSort(column: string) {
  if (sort.value === column) {
    direction.value = direction.value === "asc" ? "desc" : "asc";
  } else {
    sort.value = column;
    direction.value = "asc";
  }
  loadRows();
}

watch(search, () => {
  rowMeta.value.current_page = 1;
  loadRows();
});

onMounted(() => {
  loadTables();
  loadStats();
});
</script>

<template>
  <div>
    <PageHeader
      title="Database Viewer"
      description="Browse tables, view data, and run queries."
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

    <!-- Tabs -->
    <div class="mb-4 flex gap-1 border-b border-[var(--border)]">
      <button
        :class="[
          'px-4 py-2 text-xs font-medium border-b-2 transition-colors',
          activeTab === 'tables'
            ? 'border-white text-white'
            : 'border-transparent text-[var(--text-muted)] hover:text-[var(--text)]',
        ]"
        @click="activeTab = 'tables'"
      >
        <IconTable :size="14" class="mr-1 inline" /> Tables
      </button>
      <button
        :class="[
          'px-4 py-2 text-xs font-medium border-b-2 transition-colors',
          activeTab === 'query'
            ? 'border-white text-white'
            : 'border-transparent text-[var(--text-muted)] hover:text-[var(--text)]',
        ]"
        @click="activeTab = 'query'"
      >
        <IconCode :size="14" class="mr-1 inline" /> Query
      </button>
    </div>

    <!-- Tables Tab -->
    <div v-if="activeTab === 'tables'" class="grid gap-4 lg:grid-cols-[240px_1fr]">
      <!-- Table List -->
      <section class="rounded-lg border border-[var(--border)] bg-[var(--surface)] p-3">
        <div class="relative mb-2">
          <IconSearch :size="14" class="absolute left-2.5 top-1/2 -translate-y-1/2 text-[var(--text-soft)]" />
          <input
            v-model="search"
            class="h-8 w-full rounded-md border border-[var(--border)] bg-[var(--bg)] pl-8 pr-2 text-xs text-[var(--text)]"
            placeholder="Search tables..."
          />
        </div>
        <div class="max-h-[500px] overflow-y-auto space-y-0.5">
          <button
            v-for="table in tables.filter(t => !search || t.name.includes(search.toLowerCase()))"
            :key="table.name"
            :class="[
              'flex w-full items-center justify-between rounded-md px-2.5 py-1.5 text-left text-xs transition-colors',
              selectedTable === table.name
                ? 'bg-[var(--surface-muted)] text-[var(--text)]'
                : 'text-[var(--text-muted)] hover:bg-[var(--surface-muted)] hover:text-[var(--text)]',
            ]"
            @click="selectTable(table.name)"
          >
            <span class="flex items-center gap-1.5">
              <IconDatabase :size="12" class="text-[var(--text-soft)]" />
              {{ table.name }}
            </span>
            <span class="text-2xs text-[var(--text-soft)]">{{ table.rows }}</span>
          </button>
        </div>
      </section>

      <!-- Table Data -->
      <section>
        <div v-if="!selectedTable" class="flex items-center justify-center rounded-lg border border-dashed border-[var(--border)] bg-[var(--surface)] p-12">
          <p class="text-sm text-[var(--text-muted)]">Select a table to view its data</p>
        </div>

        <template v-else>
          <!-- Table Info -->
          <div v-if="tableDetail" class="mb-3 flex flex-wrap items-center gap-3 text-xs">
            <span class="font-medium text-[var(--text)]">{{ tableDetail.name }}</span>
            <span class="text-[var(--text-muted)]">{{ tableDetail.columns.length }} columns</span>
            <span class="text-[var(--text-muted)]">{{ tableDetail.row_count.toLocaleString() }} rows</span>
          </div>

          <!-- Data Table -->
          <div class="overflow-x-auto rounded-lg border border-[var(--border)] bg-[var(--surface)]">
            <div v-if="loadingRows" class="flex items-center justify-center p-8">
              <IconLoader :size="20" class="animate-spin text-[var(--text-soft)]" />
            </div>
            <table v-else-if="rows.length" class="w-full text-xs">
              <thead>
                <tr class="border-b border-[var(--border)] text-left">
                  <th
                    v-for="col in tableDetail?.columns"
                    :key="col.name"
                    class="cursor-pointer px-3 py-2 font-medium text-[var(--text-muted)] hover:text-[var(--text)]"
                    @click="toggleSort(col.name)"
                  >
                    <span class="flex items-center gap-1">
                      {{ col.name }}
                      <span v-if="sort === col.name" class="text-white">
                        {{ direction === "asc" ? "\u2191" : "\u2193" }}
                      </span>
                    </span>
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, idx) in rows" :key="idx" class="border-b border-[var(--border)]/50 last:border-0 hover:bg-[var(--surface-muted)]/50">
                  <td
                    v-for="col in tableDetail?.columns"
                    :key="col.name"
                    class="px-3 py-2 text-[var(--text)] max-w-[200px] truncate"
                    :title="String(row[col.name] ?? '')"
                  >
                    <span v-if="row[col.name] === null" class="text-[var(--text-soft)] italic">NULL</span>
                    <span v-else-if="typeof row[col.name] === 'boolean'" :class="row[col.name] ? 'text-[var(--success)]' : 'text-[var(--danger)]'">
                      {{ row[col.name] }}
                    </span>
                    <span v-else-if="typeof row[col.name] === 'number'" class="font-mono">
                      {{ row[col.name] }}
                    </span>
                    <span v-else>{{ row[col.name] }}</span>
                  </td>
                </tr>
              </tbody>
            </table>
            <p v-else class="p-8 text-center text-xs text-[var(--text-muted)]">No rows found</p>
          </div>

          <!-- Pagination -->
          <div v-if="rowMeta.last_page > 1" class="mt-3 flex items-center justify-between text-xs text-[var(--text-muted)]">
            <span>Page {{ rowMeta.current_page }} of {{ rowMeta.last_page }} ({{ rowMeta.total }} total)</span>
            <div class="flex gap-2">
              <button
                class="rounded-md border border-[var(--border)] px-2 py-1 disabled:opacity-40"
                :disabled="rowMeta.current_page <= 1"
                @click="rowMeta.current_page--; loadRows();"
              >
                Prev
              </button>
              <button
                class="rounded-md border border-[var(--border)] px-2 py-1 disabled:opacity-40"
                :disabled="rowMeta.current_page >= rowMeta.last_page"
                @click="rowMeta.current_page++; loadRows();"
              >
                Next
              </button>
            </div>
          </div>
        </template>
      </section>
    </div>

    <!-- Query Tab -->
    <div v-if="activeTab === 'query'">
      <div class="rounded-lg border border-[var(--border)] bg-[var(--surface)] p-4">
        <h3 class="mb-2 text-xs font-semibold text-[var(--text)]">SQL Query</h3>
        <p class="mb-3 text-2xs text-[var(--text-soft)]">Only SELECT queries are allowed. No INSERT, UPDATE, DELETE, or DROP.</p>
        <textarea
          v-model="sqlQuery"
          class="w-full rounded-md border border-[var(--border)] bg-[var(--bg)] p-3 font-mono text-xs text-[var(--text)] placeholder-[var(--text-soft)]"
          rows="4"
          placeholder="SELECT * FROM users LIMIT 10;"
          @keydown.ctrl.enter="runQuery"
          @keydown.meta.enter="runQuery"
        />
        <div class="mt-2 flex items-center justify-between">
          <span class="text-2xs text-[var(--text-soft)]">Press Ctrl+Enter to run</span>
          <button
            class="inline-flex h-8 items-center gap-1.5 rounded-md bg-white px-3 text-xs text-black disabled:opacity-50"
            :disabled="queryLoading || !sqlQuery.trim()"
            @click="runQuery"
          >
            <IconLoader v-if="queryLoading" :size="14" class="animate-spin" />
            <IconCode v-else :size="14" />
            Run Query
          </button>
        </div>
      </div>

      <!-- Query Error -->
      <div v-if="queryError" class="mt-3 rounded-lg border border-[var(--danger)]/30 bg-[var(--danger-soft)] p-3 text-xs text-[var(--danger)]">
        {{ queryError }}
      </div>

      <!-- Query Results -->
      <div v-if="queryResults" class="mt-3">
        <p class="mb-2 text-xs text-[var(--text-muted)]">
          {{ queryResults.meta.count }} rows in {{ queryResults.meta.elapsed_ms }}ms
        </p>
        <div class="overflow-x-auto rounded-lg border border-[var(--border)] bg-[var(--surface)]">
          <table v-if="queryResults.data.length" class="w-full text-xs">
            <thead>
              <tr class="border-b border-[var(--border)] text-left">
                <th
                  v-for="key in Object.keys(queryResults.data[0])"
                  :key="key"
                  class="px-3 py-2 font-medium text-[var(--text-muted)]"
                >
                  {{ key }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, idx) in queryResults.data" :key="idx" class="border-b border-[var(--border)]/50 last:border-0">
                <td
                  v-for="key in Object.keys(row)"
                  :key="key"
                  class="px-3 py-2 text-[var(--text)] max-w-[200px] truncate"
                  :title="String(row[key] ?? '')"
                >
                  <span v-if="row[key] === null" class="text-[var(--text-soft)] italic">NULL</span>
                  <span v-else>{{ row[key] }}</span>
                </td>
              </tr>
            </tbody>
          </table>
          <p v-else class="p-8 text-center text-xs text-[var(--text-muted)]">No results</p>
        </div>
      </div>
    </div>
  </div>
</template>
