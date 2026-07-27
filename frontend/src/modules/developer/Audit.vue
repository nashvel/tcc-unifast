<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import {
  IconHistory,
  IconSearch,
  IconRefresh,
  IconLoader,
  IconChevronLeft,
  IconChevronRight,
  IconChevronsLeft,
  IconChevronsRight,
} from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import { apiFetch, isMockMode } from "@/api/client";
import { toast } from "@/composables/useToast";

type AuditLog = {
  id: number;
  timestamp: string;
  actor: string;
  role: string;
  action: string;
  module: string;
  target: string;
  ip: string;
};

type PaginationMeta = {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
};

const mockLogs: AuditLog[] = [
  { id: 1, timestamp: "Jul 12, 2026 10:42 AM", actor: "System Developer", role: "Developer", action: "route_view", module: "Dashboard", target: "/app", ip: "192.168.1.14" },
  { id: 2, timestamp: "Jul 12, 2026 10:31 AM", actor: "System Developer", role: "Developer", action: "config_change", module: "Settings", target: "Updated CORS settings", ip: "192.168.1.14" },
  { id: 3, timestamp: "Jul 12, 2026 10:15 AM", actor: "Office Administrator", role: "Admin", action: "user_create", module: "Users", target: "Created staff@unifast.gov.ph", ip: "192.168.1.10" },
  { id: 4, timestamp: "Jul 12, 2026 09:55 AM", actor: "UniFAST Staff", role: "Staff", action: "document_review", module: "Documents", target: "Approved submission #1234", ip: "192.168.1.22" },
];

const logs = ref<AuditLog[]>([]);
const loading = ref(false);
const search = ref("");
const page = ref(1);
const perPage = ref(15);
const meta = ref<PaginationMeta>({
  current_page: 1,
  per_page: 15,
  total: 0,
  last_page: 1,
});
const errorMessage = ref("");

async function loadLogs() {
  loading.value = true;
  errorMessage.value = "";
  try {
    const params = new URLSearchParams({
      page: String(page.value),
      per_page: String(perPage.value),
    });
    if (search.value.trim()) {
      params.set("search", search.value.trim());
    }

    const res = await apiFetch<{ data: any[]; meta?: PaginationMeta }>(`/api/audit-logs?${params.toString()}`);
    if (res.data && res.data.length > 0) {
      logs.value = res.data.map((item) => ({
        id: item.id,
        timestamp: item.created_at ? new Date(item.created_at).toLocaleString() : item.timestamp || "Just now",
        actor: item.actor || "System Developer",
        role: item.role || "Developer",
        action: item.action || "system_event",
        module: item.module || "System",
        target: item.target || "System operation",
        ip: item.ip_address || item.ip || "127.0.0.1",
      }));
      if (res.meta) {
        meta.value = res.meta;
      } else {
        meta.value = {
          current_page: page.value,
          per_page: perPage.value,
          total: res.data.length,
          last_page: 1,
        };
      }
    } else {
      logs.value = isMockMode ? mockLogs : [];
      meta.value = {
        current_page: 1,
        per_page: perPage.value,
        total: isMockMode ? mockLogs.length : 0,
        last_page: 1,
      };
    }
  } catch (err: any) {
    if (isMockMode) {
      logs.value = mockLogs;
      meta.value = {
        current_page: 1,
        per_page: perPage.value,
        total: mockLogs.length,
        last_page: 1,
      };
    } else {
      errorMessage.value = err?.message || "Failed to load audit trail from API server.";
      toast.error(errorMessage.value);
      logs.value = [];
      meta.value = { current_page: 1, per_page: perPage.value, total: 0, last_page: 1 };
    }
  } finally {
    loading.value = false;
  }
}

let searchDebounce: any = null;
watch(search, () => {
  clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => {
    page.value = 1;
    loadLogs();
  }, 300);
});

watch([page, perPage], () => {
  loadLogs();
});

function goToPage(newPage: number) {
  if (newPage >= 1 && newPage <= meta.value.last_page) {
    page.value = newPage;
  }
}

const actionColors: Record<string, string> = {
  config_change: "bg-white/10 text-white",
  rbac_change: "bg-blue-100 text-blue-700",
  api_deploy: "bg-green-100 text-green-700",
  bug_fix: "bg-orange-100 text-orange-700",
  user_create: "bg-info-soft text-info",
  document_review: "bg-warning-soft text-warning",
  batch_activate: "bg-success-soft text-success",
  route_view: "bg-surface-muted text-text-muted",
};

function formatAction(action: string) {
  return action.replace(/_/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());
}

const startItem = computed(() => (meta.value.total === 0 ? 0 : (meta.value.current_page - 1) * meta.value.per_page + 1));
const endItem = computed(() => Math.min(meta.value.current_page * meta.value.per_page, meta.value.total));

onMounted(loadLogs);
</script>

<template>
  <div class="space-y-4">
    <PageHeader
      title="Developer Audit Trail"
      description="System activity and change log for developers with server-side pagination."
    >
      <template #actions>
        <button class="inline-flex h-9 items-center gap-1.5 rounded-md border border-border px-3 text-xs text-text hover:bg-surface-muted transition-colors" @click="loadLogs">
          <IconRefresh :size="14" :class="loading ? 'animate-spin' : ''" /> Refresh
        </button>
      </template>
    </PageHeader>

    <div v-if="errorMessage" class="rounded-lg border border-red-500/30 bg-red-950/40 p-4 text-xs text-red-200">
      {{ errorMessage }}
    </div>

    <!-- Search and Per Page Controls -->
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div class="relative max-w-md flex-1">
        <IconSearch :size="14" class="absolute left-3 top-1/2 -translate-y-1/2 text-text-soft" />
        <input
          v-model="search"
          class="h-9 w-full rounded-md border border-border pl-9 pr-3 text-xs bg-surface text-text"
          placeholder="Search by actor, action, module, or target..."
        />
      </div>

      <div class="flex items-center gap-2 text-xs text-text-muted">
        <span>Show</span>
        <select
          v-model="perPage"
          class="h-9 rounded-md border border-border bg-surface px-2.5 text-xs text-text"
        >
          <option :value="10">10</option>
          <option :value="15">15</option>
          <option :value="25">25</option>
          <option :value="50">50</option>
          <option :value="100">100</option>
        </select>
        <span>entries</span>
      </div>
    </div>

    <!-- Audit Logs Table -->
    <div class="overflow-x-auto rounded-lg border border-border bg-surface">
      <table class="w-full text-xs">
        <thead>
          <tr class="border-b border-border bg-surface-muted text-left text-text-muted">
            <th class="px-3 py-2.5 font-medium">Timestamp</th>
            <th class="px-3 py-2.5 font-medium">Actor</th>
            <th class="px-3 py-2.5 font-medium">Action</th>
            <th class="px-3 py-2.5 font-medium">Module</th>
            <th class="px-3 py-2.5 font-medium">Target</th>
            <th class="px-3 py-2.5 font-medium">IP Address</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="6" class="p-8 text-center text-text-muted">
              <IconLoader :size="18" class="animate-spin inline mr-2" /> Loading audit trail...
            </td>
          </tr>
          <tr v-else-if="logs.length === 0">
            <td colspan="6" class="p-8 text-center text-text-muted">
              {{ errorMessage ? "Unable to load audit logs from API server." : "No audit logs found." }}
            </td>
          </tr>
          <tr v-for="log in logs" :key="log.id" class="border-b border-border/50 last:border-0 hover:bg-surface-muted/50 transition-colors">
            <td class="px-3 py-2.5 text-text-muted whitespace-nowrap font-mono text-2xs">{{ log.timestamp }}</td>
            <td class="px-3 py-2.5">
              <p class="font-medium text-text">{{ log.actor }}</p>
              <p class="text-2xs text-text-muted">{{ log.role }}</p>
            </td>
            <td class="px-3 py-2.5">
              <span :class="['rounded-full px-2 py-0.5 text-2xs font-semibold', actionColors[log.action] || 'bg-surface-muted text-text-muted']">
                {{ formatAction(log.action) }}
              </span>
            </td>
            <td class="px-3 py-2.5 text-text-muted font-mono text-2xs">{{ log.module }}</td>
            <td class="px-3 py-2.5 max-w-[220px] truncate text-text" :title="log.target">{{ log.target }}</td>
            <td class="px-3 py-2.5 font-mono text-2xs text-text-muted">{{ log.ip }}</td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination Footer -->
      <div v-if="meta.total > 0" class="flex flex-wrap items-center justify-between border-t border-border px-4 py-3 text-xs text-text-muted">
        <div>
          Showing <span class="font-medium text-text">{{ startItem }}</span> to
          <span class="font-medium text-text">{{ endItem }}</span> of
          <span class="font-medium text-text">{{ meta.total.toLocaleString() }}</span> audit events
        </div>

        <div class="flex items-center gap-1">
          <button
            class="grid size-8 place-items-center rounded border border-border bg-surface text-text hover:bg-surface-muted disabled:opacity-40 disabled:hover:bg-surface"
            :disabled="meta.current_page <= 1 || loading"
            title="First Page"
            @click="goToPage(1)"
          >
            <IconChevronsLeft :size="14" />
          </button>

          <button
            class="grid size-8 place-items-center rounded border border-border bg-surface text-text hover:bg-surface-muted disabled:opacity-40 disabled:hover:bg-surface"
            :disabled="meta.current_page <= 1 || loading"
            title="Previous Page"
            @click="goToPage(meta.current_page - 1)"
          >
            <IconChevronLeft :size="14" />
          </button>

          <span class="px-2 font-mono text-2xs">
            Page {{ meta.current_page }} of {{ meta.last_page }}
          </span>

          <button
            class="grid size-8 place-items-center rounded border border-border bg-surface text-text hover:bg-surface-muted disabled:opacity-40 disabled:hover:bg-surface"
            :disabled="meta.current_page >= meta.last_page || loading"
            title="Next Page"
            @click="goToPage(meta.current_page + 1)"
          >
            <IconChevronRight :size="14" />
          </button>

          <button
            class="grid size-8 place-items-center rounded border border-border bg-surface text-text hover:bg-surface-muted disabled:opacity-40 disabled:hover:bg-surface"
            :disabled="meta.current_page >= meta.last_page || loading"
            title="Last Page"
            @click="goToPage(meta.last_page)"
          >
            <IconChevronsRight :size="14" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
