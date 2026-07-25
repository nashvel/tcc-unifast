<script setup lang="ts">
import { computed, ref } from "vue";
import {
  AlertTriangle,
  Check,
  Code,
  Cpu,
  Database,
  GitBranch,
  HardDrive,
  RefreshCw,
  Server,
  Shield,
  Terminal,
  Users,
} from "lucide-vue-next";

const systemHealth = ref([
  { name: "API Server", status: "healthy", latency: "45ms", uptime: "99.98%", icon: Server },
  { name: "Database", status: "healthy", latency: "12ms", uptime: "99.99%", icon: Database },
  { name: "OCR Service", status: "degraded", latency: "2.3s", uptime: "98.5%", icon: Cpu },
  { name: "File Storage", status: "healthy", latency: "89ms", uptime: "99.95%", icon: HardDrive },
]);

const apiMetrics = ref([
  { endpoint: "/api/auth/login", method: "POST", p50: "120ms", p95: "340ms", calls: "12.4k", errors: "0.02%" },
  { endpoint: "/api/batches", method: "GET", p50: "45ms", p95: "120ms", calls: "8.2k", errors: "0.01%" },
  { endpoint: "/api/document-submissions", method: "GET", p50: "89ms", p95: "210ms", calls: "15.6k", errors: "0.03%" },
  { endpoint: "/api/student/requirement-vault", method: "POST", p50: "230ms", p95: "1.2s", calls: "4.8k", errors: "1.2%" },
  { endpoint: "/api/grantees", method: "GET", p50: "67ms", p95: "180ms", calls: "6.1k", errors: "0.01%" },
  { endpoint: "/api/masterlist/imports/preview", method: "POST", p50: "1.8s", p95: "4.2s", calls: "1.2k", errors: "2.1%" },
]);

const recentDeployments = ref([
  { version: "v2.1.0", status: "success", commit: "a3f8c2d", time: "2 hours ago", author: "System Developer" },
  { version: "v2.0.9", status: "success", commit: "b7e1d4f", time: "1 day ago", author: "System Developer" },
  { version: "v2.0.8", status: "failed", commit: "c9a2e5b", time: "2 days ago", author: "System Developer" },
  { version: "v2.0.7", status: "success", commit: "d4f7a8c", time: "3 days ago", author: "System Developer" },
]);

const errorLog = ref([
  { time: "10:42:18", level: "error", message: "Face verification timeout after 30s", service: "ocr-service" },
  { time: "10:38:05", level: "warn", message: "Rate limit exceeded for /api/student/submissions", service: "api-gateway" },
  { time: "10:35:22", level: "error", message: "Database connection pool exhausted", service: "database" },
  { time: "10:30:11", level: "info", message: "Batch 01 activated by admin@unifast.gov.ph", service: "api-server" },
  { time: "10:25:44", level: "warn", message: "OCR service response time > 2s", service: "ocr-service" },
]);

const quickActions = ref([
  { label: "RBAC", path: "/app/developer/rbac", icon: Shield },
  { label: "API Docs", path: "/app/developer/api-docs", icon: Code },
  { label: "Flow Charts", path: "/app/developer/flow-chart", icon: GitBranch },
  { label: "Support", path: "/app/developer/support", icon: AlertTriangle },
  { label: "Team", path: "/app/developer/collaborators", icon: Users },
  { label: "Audit", path: "/app/developer/audit", icon: Terminal },
]);

const uptime = computed(() => {
  const hours = 720;
  const downtime = 0.14;
  return ((hours - downtime) / hours * 100).toFixed(3);
});
</script>

<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <h1 class="text-lg font-semibold text-[var(--text)]">Dashboard</h1>
      <button class="inline-flex h-8 items-center gap-1.5 rounded-md border border-[var(--border)] bg-[var(--surface)] px-3 text-xs text-[var(--text-muted)] hover:bg-[var(--surface-muted)]">
        <RefreshCw :size="13" /> Refresh
      </button>
    </div>

    <!-- Quick Actions -->
    <section class="grid grid-cols-3 gap-2 sm:grid-cols-6">
      <RouterLink
        v-for="action in quickActions"
        :key="action.path"
        :to="action.path"
        class="flex flex-col items-center gap-2 rounded-lg border border-[var(--border)] bg-[var(--surface)] p-4 text-center transition-colors hover:bg-[var(--surface-muted)]"
      >
        <span class="grid size-9 place-items-center rounded-lg bg-[var(--surface-muted)]">
          <component :is="action.icon" :size="16" class="text-[var(--text-muted)]" />
        </span>
        <span class="text-2xs text-[var(--text-muted)]">{{ action.label }}</span>
      </RouterLink>
    </section>

    <!-- System Health Cards -->
    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <div
        v-for="service in systemHealth"
        :key="service.name"
        class="rounded-lg border border-[var(--border)] bg-[var(--surface)] p-4"
      >
        <div class="flex items-center justify-between">
          <span class="text-xs text-[var(--text-muted)]">{{ service.name }}</span>
          <span
            :class="[
              'rounded-full px-2 py-0.5 text-2xs font-medium',
              service.status === 'healthy' ? 'bg-[var(--success-soft)] text-[var(--success)]' : 'bg-[var(--warning-soft)] text-[var(--warning)]',
            ]"
          >
            {{ service.status }}
          </span>
        </div>
        <p class="mt-2 text-xl font-semibold text-[var(--text)]">{{ service.latency }}</p>
        <p class="mt-0.5 text-2xs text-[var(--text-soft)]">Uptime: {{ service.uptime }}</p>
      </div>
    </section>

    <div class="grid gap-4 lg:grid-cols-[1fr_280px]">
      <!-- API Performance Table -->
      <section>
        <h2 class="mb-3 text-sm font-semibold text-[var(--text)]">API Performance</h2>
        <div class="overflow-x-auto rounded-lg border border-[var(--border)] bg-[var(--surface)]">
          <table class="w-full text-xs">
            <thead>
              <tr class="border-b border-[var(--border)] text-left">
                <th class="px-3 py-2.5 font-medium text-[var(--text-muted)]">Endpoint</th>
                <th class="px-3 py-2.5 font-medium text-[var(--text-muted)]">P50</th>
                <th class="px-3 py-2.5 font-medium text-[var(--text-muted)]">P95</th>
                <th class="px-3 py-2.5 font-medium text-[var(--text-muted)]">Calls</th>
                <th class="px-3 py-2.5 font-medium text-[var(--text-muted)]">Errors</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="api in apiMetrics" :key="api.endpoint" class="border-b border-[var(--border)]/50 last:border-0 hover:bg-[var(--surface-muted)]/50">
                <td class="px-3 py-2.5">
                  <div class="flex items-center gap-2">
                    <span
                      :class="[
                        'rounded px-1.5 py-0.5 text-2xs font-bold',
                        api.method === 'GET' ? 'bg-[var(--success-soft)] text-[var(--success)]' : 'bg-[var(--info-soft)] text-[var(--info)]',
                      ]"
                    >
                      {{ api.method }}
                    </span>
                    <code class="text-2xs text-[var(--text)]">{{ api.endpoint }}</code>
                  </div>
                </td>
                <td class="px-3 py-2.5 font-mono text-2xs text-[var(--text)]">{{ api.p50 }}</td>
                <td class="px-3 py-2.5 font-mono text-2xs text-[var(--text)]">{{ api.p95 }}</td>
                <td class="px-3 py-2.5 text-2xs text-[var(--text-muted)]">{{ api.calls }}</td>
                <td class="px-3 py-2.5">
                  <span
                    :class="[
                      'text-2xs font-medium',
                      parseFloat(api.errors) > 1 ? 'text-[var(--danger)]' : parseFloat(api.errors) > 0.1 ? 'text-[var(--warning)]' : 'text-[var(--success)]',
                    ]"
                  >
                    {{ api.errors }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- System & Deployments -->
      <section class="space-y-4">
        <div class="rounded-lg border border-[var(--border)] bg-[var(--surface)] p-4">
          <h3 class="mb-3 text-xs font-semibold text-[var(--text)]">System</h3>
          <div class="space-y-2.5 text-xs">
            <div class="flex justify-between"><span class="text-[var(--text-muted)]">Framework</span><span class="text-[var(--text)]">Laravel 13 + Vue 3</span></div>
            <div class="flex justify-between"><span class="text-[var(--text-muted)]">Auth</span><span class="text-[var(--text)]">Sanctum Tokens</span></div>
            <div class="flex justify-between"><span class="text-[var(--text-muted)]">Database</span><span class="text-[var(--text)]">SQLite / MySQL</span></div>
            <div class="flex justify-between"><span class="text-[var(--text-muted)]">Uptime</span><span class="text-[var(--success)]">{{ uptime }}%</span></div>
          </div>
        </div>

        <div class="rounded-lg border border-[var(--border)] bg-[var(--surface)] p-4">
          <h3 class="mb-3 text-xs font-semibold text-[var(--text)]">Deployments</h3>
          <div class="space-y-2">
            <div v-for="deploy in recentDeployments" :key="deploy.version" class="flex items-center gap-2 text-xs">
              <span
                :class="[
                  'size-1.5 rounded-full',
                  deploy.status === 'success' ? 'bg-[var(--success)]' : 'bg-[var(--danger)]',
                ]"
              />
              <span class="font-medium text-[var(--text)]">{{ deploy.version }}</span>
              <span class="text-[var(--text-soft)]">{{ deploy.time }}</span>
            </div>
          </div>
        </div>
      </section>
    </div>

    <!-- Error Log -->
    <section>
      <h2 class="mb-3 text-sm font-semibold text-[var(--text)]">Recent Errors</h2>
      <div class="space-y-2">
        <div
          v-for="(error, idx) in errorLog"
          :key="idx"
          class="flex items-center gap-3 rounded-lg border border-[var(--border)] bg-[var(--surface)] p-3"
        >
          <span
            :class="[
              'rounded-full px-2 py-0.5 text-2xs font-medium',
              error.level === 'error' ? 'bg-[var(--danger-soft)] text-[var(--danger)]' : error.level === 'warn' ? 'bg-[var(--warning-soft)] text-[var(--warning)]' : 'bg-[var(--info-soft)] text-[var(--info)]',
            ]"
          >
            {{ error.level }}
          </span>
          <span class="text-2xs text-[var(--text-soft)]">{{ error.time }}</span>
          <span class="flex-1 text-xs text-[var(--text)]">{{ error.message }}</span>
          <span class="text-2xs text-[var(--text-soft)]">{{ error.service }}</span>
        </div>
      </div>
    </section>
  </div>
</template>
