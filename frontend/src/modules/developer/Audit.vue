<script setup lang="ts">
import { computed, ref } from "vue";
import { IconHistory, IconSearch, IconRefresh } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";

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

const logs = ref<AuditLog[]>([
  { id: 1, timestamp: "Jul 12, 2026 10:42 AM", actor: "System Developer", role: "Developer", action: "route_view", module: "Dashboard", target: "/app", ip: "192.168.1.14" },
  { id: 2, timestamp: "Jul 12, 2026 10:31 AM", actor: "System Developer", role: "Developer", action: "config_change", module: "Settings", target: "Updated CORS settings", ip: "192.168.1.14" },
  { id: 3, timestamp: "Jul 12, 2026 10:15 AM", actor: "Office Administrator", role: "Admin", action: "user_create", module: "Users", target: "Created staff@unifast.gov.ph", ip: "192.168.1.10" },
  { id: 4, timestamp: "Jul 12, 2026 09:55 AM", actor: "UniFAST Staff", role: "Staff", action: "document_review", module: "Documents", target: "Approved submission #1234", ip: "192.168.1.22" },
  { id: 5, timestamp: "Jul 12, 2026 09:30 AM", actor: "System Developer", role: "Developer", action: "api_deploy", module: "API", target: "Deployed v2.1.0", ip: "192.168.1.14" },
  { id: 6, timestamp: "Jul 11, 2026 04:15 PM", actor: "System Developer", role: "Developer", action: "rbac_change", module: "RBAC", target: "Updated staff permissions", ip: "192.168.1.14" },
  { id: 7, timestamp: "Jul 11, 2026 02:30 PM", actor: "Office Administrator", role: "Admin", action: "batch_activate", module: "Batches", target: "Activated Batch 01", ip: "192.168.1.10" },
  { id: 8, timestamp: "Jul 11, 2026 11:00 AM", actor: "System Developer", role: "Developer", action: "bug_fix", module: "System", target: "Fixed face verification crash", ip: "192.168.1.14" },
]);

const search = ref("");
const filteredLogs = computed(() =>
  logs.value.filter(
    (l) =>
      !search.value ||
      l.actor.toLowerCase().includes(search.value.toLowerCase()) ||
      l.action.toLowerCase().includes(search.value.toLowerCase()) ||
      l.module.toLowerCase().includes(search.value.toLowerCase()),
  ),
);

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
</script>

<template>
  <div>
    <PageHeader
      title="Developer Audit Trail"
      description="System activity and change log for developers."
    >
      <template #actions>
        <button class="inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-xs" @click="logs.unshift(logs[0])">
          <IconRefresh :size="14" /> Refresh
        </button>
      </template>
    </PageHeader>

    <div class="mb-4">
      <div class="relative max-w-md">
        <IconSearch :size="14" class="absolute left-3 top-1/2 -translate-y-1/2 text-text-soft" />
        <input
          v-model="search"
          class="h-9 w-full rounded-md border pl-9 pr-3 text-xs"
          placeholder="Search logs..."
        />
      </div>
    </div>

    <div class="overflow-x-auto rounded-lg border bg-surface">
      <table class="w-full text-xs">
        <thead>
          <tr class="border-b bg-surface-muted text-left text-text-muted">
            <th class="px-3 py-2 font-medium">Timestamp</th>
            <th class="px-3 py-2 font-medium">Actor</th>
            <th class="px-3 py-2 font-medium">Action</th>
            <th class="px-3 py-2 font-medium">Module</th>
            <th class="px-3 py-2 font-medium">Target</th>
            <th class="px-3 py-2 font-medium">IP</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="log in filteredLogs" :key="log.id" class="border-b last:border-0 hover:bg-surface-muted/50">
            <td class="px-3 py-2 text-text-muted">{{ log.timestamp }}</td>
            <td class="px-3 py-2">
              <p class="font-medium">{{ log.actor }}</p>
              <p class="text-2xs text-text-muted">{{ log.role }}</p>
            </td>
            <td class="px-3 py-2">
              <span :class="['rounded-full px-2 py-0.5 text-2xs font-semibold', actionColors[log.action] || 'bg-surface-muted text-text-muted']">
                {{ formatAction(log.action) }}
              </span>
            </td>
            <td class="px-3 py-2 text-text-muted">{{ log.module }}</td>
            <td class="px-3 py-2 max-w-[200px] truncate">{{ log.target }}</td>
            <td class="px-3 py-2 font-mono text-text-muted">{{ log.ip }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
