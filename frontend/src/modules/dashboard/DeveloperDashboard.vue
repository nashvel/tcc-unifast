<script setup lang="ts">
import { computed, ref, onMounted } from "vue";
import { useRoute } from "vue-router";
import {
  Activity,
  AlertTriangle,
  ArrowUpRight,
  Check,
  Clock,
  Code,
  Cpu,
  Database,
  GitBranch,
  Globe,
  HardDrive,
  Layers,
  Lock,
  RefreshCw,
  Server,
  Shield,
  Terminal,
  Wifi,
  Zap,
} from "lucide-vue-next";
import PageHeader from "@/components/ui/PageHeader.vue";

const route = useRoute();

const systemHealth = ref([
  { name: "API Server", status: "healthy", latency: "45ms", uptime: "99.98%", icon: Server },
  { name: "Database", status: "healthy", latency: "12ms", uptime: "99.99%", icon: Database },
  { name: "OCR Service", status: "degraded", latency: "2.3s", uptime: "98.5%", icon: Cpu },
  { name: "File Storage", status: "healthy", latency: "89ms", uptime: "99.95%", icon: HardDrive },
]);

const apiMetrics = ref([
  { endpoint: "/api/auth/login", method: "POST", p50: "120ms", p95: "340ms", p99: "890ms", calls: "12.4k", errors: "0.02%" },
  { endpoint: "/api/batches", method: "GET", p50: "45ms", p95: "120ms", p99: "250ms", calls: "8.2k", errors: "0.01%" },
  { endpoint: "/api/document-submissions", method: "GET", p50: "89ms", p95: "210ms", p99: "450ms", calls: "15.6k", errors: "0.03%" },
  { endpoint: "/api/student/requirement-vault", method: "POST", p50: "230ms", p95: "1.2s", p99: "3.4s", calls: "4.8k", errors: "1.2%" },
  { endpoint: "/api/grantees", method: "GET", p50: "67ms", p95: "180ms", p99: "320ms", calls: "6.1k", errors: "0.01%" },
  { endpoint: "/api/masterlist/imports/preview", method: "POST", p50: "1.8s", p95: "4.2s", p99: "8.1s", calls: "1.2k", errors: "2.1%" },
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
  { label: "RBAC Settings", path: "/app/developer/rbac", icon: Shield, color: "bg-purple-100 text-purple-600" },
  { label: "API Docs", path: "/app/developer/api-docs", icon: Code, color: "bg-blue-100 text-blue-600" },
  { label: "Flow Charts", path: "/app/developer/flow-chart", icon: GitBranch, color: "bg-green-100 text-green-600" },
  { label: "Support", path: "/app/developer/support", icon: AlertTriangle, color: "bg-orange-100 text-orange-600" },
  { label: "Collaborators", path: "/app/developer/collaborators", icon: Users, color: "bg-cyan-100 text-cyan-600" },
  { label: "Audit Trail", path: "/app/developer/audit", icon: Terminal, color: "bg-red-100 text-red-600" },
]);

import { Users } from "lucide-vue-next";

const uptime = computed(() => {
  const hours = 720;
  const downtime = 0.14;
  return ((hours - downtime) / hours * 100).toFixed(3);
});

const statusColor: Record<string, string> = {
  healthy: "text-success",
  degraded: "text-warning",
  down: "text-danger",
};

const methodColors: Record<string, string> = {
  GET: "bg-green-100 text-green-700",
  POST: "bg-blue-100 text-blue-700",
  PUT: "bg-yellow-100 text-yellow-700",
  DELETE: "bg-red-100 text-red-700",
};

const levelColors: Record<string, string> = {
  error: "bg-danger-soft text-danger",
  warn: "bg-warning-soft text-warning",
  info: "bg-info-soft text-info",
};
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      title="Developer Dashboard"
      description="System health, API performance, and deployment status."
    >
      <template #actions>
        <button class="inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-xs">
          <RefreshCw :size="14" /> Refresh
        </button>
      </template>
    </PageHeader>

    <!-- Quick Actions -->
    <section class="grid grid-cols-3 gap-2 sm:grid-cols-6">
      <RouterLink
        v-for="action in quickActions"
        :key="action.path"
        :to="action.path"
        class="flex flex-col items-center gap-2 rounded-lg border bg-surface p-3 text-center transition hover:shadow-sm"
      >
        <span :class="['grid size-10 place-items-center rounded-lg', action.color]">
          <component :is="action.icon" :size="18" />
        </span>
        <span class="text-2xs font-medium text-text-muted">{{ action.label }}</span>
      </RouterLink>
    </section>

    <!-- System Health -->
    <section>
      <h2 class="mb-3 text-sm font-semibold">System Health</h2>
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div
          v-for="service in systemHealth"
          :key="service.name"
          class="rounded-lg border bg-surface p-4"
        >
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <component :is="service.icon" :size="16" class="text-text-muted" />
              <span class="text-xs font-medium">{{ service.name }}</span>
            </div>
            <span :class="['text-2xs font-semibold', statusColor[service.status]]">
              {{ service.status }}
            </span>
          </div>
          <div class="mt-3 grid grid-cols-2 gap-2 text-2xs text-text-muted">
            <div>
              <p class="text-text-soft">Latency</p>
              <p class="font-medium text-text">{{ service.latency }}</p>
            </div>
            <div>
              <p class="text-text-soft">Uptime</p>
              <p class="font-medium text-text">{{ service.uptime }}</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- API Performance -->
    <section>
      <div class="mb-3 flex items-center justify-between">
        <h2 class="text-sm font-semibold">API Performance</h2>
        <span class="text-2xs text-text-muted">Last 24 hours</span>
      </div>
      <div class="overflow-x-auto rounded-lg border bg-surface">
        <table class="w-full text-xs">
          <thead>
            <tr class="border-b bg-surface-muted text-left text-text-muted">
              <th class="px-3 py-2 font-medium">Endpoint</th>
              <th class="px-3 py-2 font-medium">P50</th>
              <th class="px-3 py-2 font-medium">P95</th>
              <th class="px-3 py-2 font-medium">P99</th>
              <th class="px-3 py-2 font-medium">Calls</th>
              <th class="px-3 py-2 font-medium">Errors</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="api in apiMetrics" :key="api.endpoint" class="border-b last:border-0 hover:bg-surface-muted/50">
              <td class="px-3 py-2">
                <div class="flex items-center gap-2">
                  <span :class="['rounded px-1.5 py-0.5 text-2xs font-bold', methodColors[api.method]]">
                    {{ api.method }}
                  </span>
                  <code class="text-2xs font-mono">{{ api.endpoint }}</code>
                </div>
              </td>
              <td class="px-3 py-2 font-mono text-2xs">{{ api.p50 }}</td>
              <td class="px-3 py-2 font-mono text-2xs">{{ api.p95 }}</td>
              <td class="px-3 py-2 font-mono text-2xs">{{ api.p99 }}</td>
              <td class="px-3 py-2 text-2xs">{{ api.calls }}</td>
              <td class="px-3 py-2">
                <span
                  :class="[
                    'text-2xs font-medium',
                    parseFloat(api.errors) > 1 ? 'text-danger' : parseFloat(api.errors) > 0.1 ? 'text-warning' : 'text-success',
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

    <div class="grid gap-4 lg:grid-cols-2">
      <!-- Recent Deployments -->
      <section>
        <h2 class="mb-3 text-sm font-semibold">Recent Deployments</h2>
        <div class="space-y-2">
          <div
            v-for="deploy in recentDeployments"
            :key="deploy.version"
            class="flex items-center gap-3 rounded-lg border bg-surface p-3"
          >
            <span
              :class="[
                'grid size-8 place-items-center rounded-full',
                deploy.status === 'success' ? 'bg-success-soft' : 'bg-danger-soft',
              ]"
            >
              <Check v-if="deploy.status === 'success'" :size="14" class="text-success" />
              <AlertTriangle v-else :size="14" class="text-danger" />
            </span>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2">
                <span class="text-xs font-semibold">{{ deploy.version }}</span>
                <code class="text-2xs text-text-muted">{{ deploy.commit }}</code>
              </div>
              <p class="text-2xs text-text-muted">{{ deploy.author }} · {{ deploy.time }}</p>
            </div>
            <span
              :class="[
                'rounded-full px-2 py-0.5 text-2xs font-semibold',
                deploy.status === 'success' ? 'bg-success-soft text-success' : 'bg-danger-soft text-danger',
              ]"
            >
              {{ deploy.status }}
            </span>
          </div>
        </div>
      </section>

      <!-- Error Log -->
      <section>
        <h2 class="mb-3 text-sm font-semibold">Recent Errors</h2>
        <div class="space-y-2">
          <div
            v-for="(error, idx) in errorLog"
            :key="idx"
            class="rounded-lg border bg-surface p-3"
          >
            <div class="flex items-center gap-2">
              <span :class="['rounded-full px-2 py-0.5 text-2xs font-semibold', levelColors[error.level]]">
                {{ error.level }}
              </span>
              <span class="text-2xs text-text-muted">{{ error.time }}</span>
              <span class="text-2xs text-text-muted">· {{ error.service }}</span>
            </div>
            <p class="mt-1 text-xs text-text">{{ error.message }}</p>
          </div>
        </div>
      </section>
    </div>

    <!-- System Info -->
    <section class="rounded-lg border bg-surface p-4">
      <h2 class="mb-3 text-sm font-semibold">System Information</h2>
      <div class="grid gap-4 text-xs sm:grid-cols-2 lg:grid-cols-4">
        <div>
          <p class="text-text-muted">Framework</p>
          <p class="mt-0.5 font-medium">Laravel 13.19 + Vue 3.5</p>
        </div>
        <div>
          <p class="text-text-muted">Node Version</p>
          <p class="mt-0.5 font-medium">Node.js 20 LTS</p>
        </div>
        <div>
          <p class="text-text-muted">Database</p>
          <p class="mt-0.5 font-medium">SQLite (dev) / MySQL (prod)</p>
        </div>
        <div>
          <p class="text-text-muted">30-Day Uptime</p>
          <p class="mt-0.5 font-medium text-success">{{ uptime }}%</p>
        </div>
        <div>
          <p class="text-text-muted">Auth Method</p>
          <p class="mt-0.5 font-medium">Laravel Sanctum Tokens</p>
        </div>
        <div>
          <p class="text-text-muted">Frontend Build</p>
          <p class="mt-0.5 font-medium">Vite 7.3 + TypeScript</p>
        </div>
        <div>
          <p class="text-text-muted">Cache Driver</p>
          <p class="mt-0.5 font-medium">File / Redis (prod)</p>
        </div>
        <div>
          <p class="text-text-muted">Queue Driver</p>
          <p class="mt-0.5 font-medium">Sync / Database (prod)</p>
        </div>
      </div>
    </section>
  </div>
</template>
