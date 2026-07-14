<script setup lang="ts">
import { onMounted, ref } from "vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import DataTable from "@/components/tables/DataTable.vue";
type Log = {
  id: number;
  created_at: string;
  actor: string;
  role: string;
  action: string;
  module: string;
  target: string;
  ip_address: string;
};
const logs = ref<Log[]>([]);
async function loadLogs() {
  const r = await fetch("/api/audit-logs");
  logs.value = (await r.json()).data || [];
}
onMounted(loadLogs);
</script>
<template>
  <div>
    <PageHeader
      title="Audit Trail"
      description="Live authentication, navigation, document, and UI click events."
    />
    <div class="mb-3 flex justify-end">
      <button class="h-8 rounded-md border px-3 text-xs text-text-muted" @click="loadLogs">
        Refresh logs
      </button>
    </div>
    <DataTable :headings="['Timestamp', 'User', 'Role', 'Action', 'Module', 'Target', 'IP']"
      ><tr v-for="log in logs" :key="log.id">
        <td class="px-3 py-3 text-text-muted">{{ new Date(log.created_at).toLocaleString() }}</td>
        <td class="px-3 py-3 font-medium">{{ log.actor }}</td>
        <td class="px-3 py-3 text-text-muted">{{ log.role }}</td>
        <td class="px-3 py-3">{{ log.action.replaceAll("_", " ") }}</td>
        <td class="px-3 py-3 text-text-muted">{{ log.module }}</td>
        <td class="px-3 py-3 text-text-muted">{{ log.target }}</td>
        <td class="px-3 py-3 font-mono text-text-muted">{{ log.ip_address }}</td>
      </tr>
      <tr v-if="!logs.length">
        <td colspan="7" class="px-3 py-8 text-center text-text-muted">No audit events yet.</td>
      </tr></DataTable
    >
  </div>
</template>
