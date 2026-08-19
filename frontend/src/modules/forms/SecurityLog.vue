<script setup lang="ts">
import { ref, computed } from "vue";
import { useRoute } from "vue-router";
import {
  IconShield, IconChevronLeft, IconChevronRight, IconX, IconAlertTriangle,
} from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import TableSkeleton from "@/components/ui/TableSkeleton.vue";
import EmptyState from "@/components/ui/EmptyState.vue";
import { useFormSecurityLogs } from "@/composables/useForms";
import type { FormSecurityLog } from "@/api/types";

const route = useRoute();
const formId = computed(() => String(route.params.id));

const page = ref(1);
const eventType = ref("all");
const dateFrom = ref("");
const dateTo = ref("");

const params = computed(() => ({
  page: page.value,
  event_type: eventType.value !== "all" ? eventType.value : undefined,
  date_from: dateFrom.value || undefined,
  date_to: dateTo.value || undefined,
}));

const { data, isLoading, isError, refetch } = useFormSecurityLogs(formId, params);
const logs = computed(() => data.value?.data ?? []);
const meta = computed(() => data.value?.meta);

// Detail modal
const detail = ref<FormSecurityLog | null>(null);

const EVENT_COLORS: Record<string, string> = {
  honeypot_triggered: "bg-warning-soft text-warning",
  rate_limit_hit: "bg-warning-soft text-warning",
  unauthorized_access: "bg-danger-soft text-danger",
  token_enumeration_attempt: "bg-danger-soft text-danger",
  xss_attempt: "bg-danger-soft text-danger",
  sql_injection_attempt: "bg-danger-soft text-danger",
  duplicate_submission_attempt: "bg-surface-muted text-text-muted",
  invalid_field_submission: "bg-surface-muted text-text-muted",
};

function eventColor(type: string) {
  return EVENT_COLORS[type] ?? "bg-surface-muted text-text-muted";
}

function formatDate(iso: string | null) {
  if (!iso) return "—";
  return new Date(iso).toLocaleString();
}
</script>

<template>
  <div>
    <PageHeader title="Security Log" description="Security events recorded for this form.">
      <template #icon>
        <IconShield class="text-warning" :size="20" />
      </template>
    </PageHeader>

    <!-- Filters -->
    <div class="mb-4 flex flex-wrap gap-2">
      <select v-model="eventType" class="h-9 rounded-md border bg-surface px-3 text-sm">
        <option value="all">All events</option>
        <option value="honeypot_triggered">Honeypot</option>
        <option value="xss_attempt">XSS Attempt</option>
        <option value="sql_injection_attempt">SQL Injection</option>
        <option value="unauthorized_access">Unauthorized Access</option>
        <option value="token_enumeration_attempt">Token Enumeration</option>
        <option value="rate_limit_hit">Rate Limit</option>
        <option value="duplicate_submission_attempt">Duplicate Submission</option>
        <option value="invalid_field_submission">Invalid Field</option>
      </select>
      <input v-model="dateFrom" type="date" class="h-9 rounded-md border bg-surface px-3 text-sm" />
      <input v-model="dateTo" type="date" class="h-9 rounded-md border bg-surface px-3 text-sm" />
    </div>

    <div class="rounded-lg border bg-surface overflow-hidden">
      <TableSkeleton v-if="isLoading" :cols="5" :rows="10" />
      <EmptyState v-else-if="isError" variant="error" title="Could not load security logs" @retry="refetch()" />
      <template v-else>
        <table class="w-full text-sm">
          <thead class="border-b bg-surface-muted text-xs text-text-muted">
            <tr>
              <th class="px-4 py-3 text-left font-medium">Event</th>
              <th class="px-4 py-3 text-left font-medium">IP Address</th>
              <th class="px-4 py-3 text-left font-medium">User ID</th>
              <th class="px-4 py-3 text-left font-medium">Time</th>
              <th class="px-4 py-3 text-right font-medium">Payload</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr v-if="!logs.length">
              <td colspan="5" class="py-12 text-center text-sm text-text-muted">
                No security events recorded.
              </td>
            </tr>
            <tr v-for="log in logs" :key="log.id" class="hover:bg-surface-muted/40 transition">
              <td class="px-4 py-3">
                <span :class="['inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-micro font-semibold', eventColor(log.event_type)]">
                  <IconAlertTriangle :size="10" />
                  {{ log.event_type.replace(/_/g, ' ') }}
                </span>
              </td>
              <td class="px-4 py-3 font-mono text-xs">{{ log.ip_address }}</td>
              <td class="px-4 py-3 text-text-muted">{{ log.user_id ?? 'Anonymous' }}</td>
              <td class="px-4 py-3 text-xs text-text-muted">{{ formatDate(log.created_at) }}</td>
              <td class="px-4 py-3 text-right">
                <button
                  v-if="log.payload"
                  :id="`btn-log-payload-${log.id}`"
                  class="inline-flex h-6 items-center gap-1 rounded border px-2 text-micro hover:bg-surface-muted"
                  @click="detail = log"
                >
                  View
                </button>
                <span v-else class="text-micro text-text-muted">—</span>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div v-if="meta && meta.last_page > 1" class="flex items-center justify-between border-t px-4 py-3 text-xs text-text-muted">
          <span>Page {{ meta.current_page }} of {{ meta.last_page }}</span>
          <div class="flex gap-2">
            <button class="rounded border px-2 py-1 disabled:opacity-40" :disabled="meta.current_page <= 1" @click="page--">
              <IconChevronLeft :size="12" />
            </button>
            <button class="rounded border px-2 py-1 disabled:opacity-40" :disabled="meta.current_page >= meta.last_page" @click="page++">
              <IconChevronRight :size="12" />
            </button>
          </div>
        </div>
      </template>
    </div>

    <!-- Payload detail modal -->
    <div v-if="detail" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="detail = null">
      <div class="w-full max-w-md rounded-xl border bg-surface shadow-xl">
        <div class="flex items-center justify-between border-b px-5 py-4">
          <h2 class="text-sm font-semibold">Event Payload</h2>
          <button class="grid size-7 place-items-center rounded hover:bg-surface-muted" @click="detail = null">
            <IconX :size="14" />
          </button>
        </div>
        <div class="px-5 py-4">
          <pre class="rounded-lg bg-surface-muted p-3 text-xs overflow-auto max-h-64">{{ JSON.stringify(detail.payload, null, 2) }}</pre>
        </div>
      </div>
    </div>
  </div>
</template>
