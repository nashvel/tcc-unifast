<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
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
import { apiFetch, isMockMode } from "@/api/client";

type HealthItem = {
  name: string;
  status: "healthy" | "degraded" | "down";
  latency: string;
  uptime: string;
};

type ApiMetric = {
  endpoint: string;
  method: string;
  p50: string;
  p95: string;
  calls: string;
  errors: string;
};

type RecentDeploy = {
  version: string;
  status: "success" | "failed";
  commit: string;
  time: string;
  author: string;
};

type LogEntry = {
  time: string;
  level: "error" | "warn" | "info";
  message: string;
  service: string;
};

const systemHealth = ref<HealthItem[]>([]);
const apiMetrics = ref<ApiMetric[]>([]);
const recentDeployments = ref<RecentDeploy[]>([]);
const errorLog = ref<LogEntry[]>([]);
const systemInfo = ref<Record<string, any>>({});
const loading = ref(false);
const errorMessage = ref("");
const measuredPing = ref("0ms");

async function loadTelemetry() {
  loading.value = true;
  errorMessage.value = "";
  const startPing = performance.now();
  try {
    const res = await apiFetch<{
      data: {
        health: HealthItem[];
        endpoints: ApiMetric[];
        system: Record<string, any>;
        logs: LogEntry[];
        deployments: RecentDeploy[];
      };
    }>("/api/system/health");

    const pingDuration = Math.round(performance.now() - startPing);
    measuredPing.value = `${pingDuration}ms`;

    if (res.data) {
      if (res.data.health?.length) {
        systemHealth.value = res.data.health;
        if (systemHealth.value[0]) systemHealth.value[0].latency = measuredPing.value;
      }
      if (res.data.endpoints?.length) apiMetrics.value = res.data.endpoints;
      if (res.data.system) systemInfo.value = res.data.system;
      if (res.data.logs) errorLog.value = res.data.logs;
      if (res.data.deployments) recentDeployments.value = res.data.deployments;
    }
  } catch (err: any) {
    errorMessage.value = err?.message || "Failed to connect to API server. Ensure backend is running.";
    systemHealth.value = [];
    apiMetrics.value = [];
    errorLog.value = [];
  } finally {
    loading.value = false;
  }
}

const quickActions = ref([
  { label: "RBAC", path: "/app/developer/rbac", icon: Shield },
  { label: "API Docs", path: "/app/developer/api-docs", icon: Code },
  { label: "Flow Charts", path: "/app/developer/flow-chart", icon: GitBranch },
  { label: "Support", path: "/app/developer/support", icon: AlertTriangle },
  { label: "Team", path: "/app/developer/collaborators", icon: Users },
  { label: "Audit", path: "/app/developer/audit", icon: Terminal },
]);

onMounted(loadTelemetry);
</script>

<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-lg font-semibold text-[var(--text)]">Developer Dashboard</h1>
        <p class="text-2xs text-[var(--text-muted)]">
          Live system health metrics, real database telemetry, and event stream.
          <span v-if="isMockMode" class="ml-2 rounded bg-amber-500/20 px-1.5 py-0.5 text-amber-400 font-mono">Mock Mode Active</span>
          <span v-else class="ml-2 rounded bg-emerald-500/20 px-1.5 py-0.5 text-emerald-400 font-mono">Real API Mode</span>
        </p>
      </div>
      <button
        class="inline-flex h-8 items-center gap-1.5 rounded-md border border-[var(--border)] bg-[var(--surface)] px-3 text-xs text-[var(--text-muted)] hover:bg-[var(--surface-muted)]"
        @click="loadTelemetry"
      >
        <RefreshCw :size="13" :class="loading ? 'animate-spin' : ''" /> Refresh
      </button>
    </div>

    <!-- Error Alert when API is unreachable -->
    <div v-if="errorMessage" class="flex items-center justify-between rounded-lg border border-red-500/30 bg-red-950/40 p-4 text-red-200">
      <div class="flex items-center gap-3">
        <AlertTriangle :size="20" class="text-red-400 shrink-0" />
        <div>
          <p class="text-xs font-semibold text-red-300">API Connection Failed</p>
          <p class="text-2xs text-red-200/80 mt-0.5">{{ errorMessage }}</p>
        </div>
      </div>
      <button
        class="rounded border border-red-500/40 px-3 py-1 text-2xs text-red-300 hover:bg-red-900/50"
        @click="loadTelemetry"
      >
        Retry Connection
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
    <section v-if="systemHealth.length > 0" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
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
        <p class="mt-0.5 text-2xs text-[var(--text-soft)]">Metrics: {{ service.uptime }}</p>
      </div>
    </section>

    <div class="grid gap-4 lg:grid-cols-[1fr_280px]">
      <!-- API Performance & Database Model Telemetry Table -->
      <section>
        <h2 class="mb-3 text-sm font-semibold text-[var(--text)]">Live Endpoints & Database Telemetry</h2>
        <div class="overflow-x-auto rounded-lg border border-[var(--border)] bg-[var(--surface)]">
          <table class="w-full text-xs">
            <thead>
              <tr class="border-b border-[var(--border)] text-left">
                <th class="px-3 py-2.5 font-medium text-[var(--text-muted)]">Endpoint</th>
                <th class="px-3 py-2.5 font-medium text-[var(--text-muted)]">P50</th>
                <th class="px-3 py-2.5 font-medium text-[var(--text-muted)]">P95</th>
                <th class="px-3 py-2.5 font-medium text-[var(--text-muted)]">Live Database Resources</th>
                <th class="px-3 py-2.5 font-medium text-[var(--text-muted)]">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="apiMetrics.length === 0">
                <td colspan="5" class="p-6 text-center text-text-muted text-xs">
                  {{ loading ? "Connecting to backend telemetry..." : "No live telemetry data available." }}
                </td>
              </tr>
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
                <td class="px-3 py-2.5 text-2xs font-semibold text-[var(--text)]">{{ api.calls }}</td>
                <td class="px-3 py-2.5">
                  <span class="text-2xs font-medium text-[var(--success)]">
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
          <h3 class="mb-3 text-xs font-semibold text-[var(--text)]">Live Environment Details</h3>
          <div class="space-y-2.5 text-xs">
            <div class="flex justify-between"><span class="text-[var(--text-muted)]">Framework</span><span class="text-[var(--text)] font-medium">{{ systemInfo.framework || 'Laravel + Vue 3' }}</span></div>
            <div v-if="systemInfo.php_version" class="flex justify-between"><span class="text-[var(--text-muted)]">PHP Version</span><span class="text-[var(--text)] font-medium">{{ systemInfo.php_version }}</span></div>
            <div class="flex justify-between"><span class="text-[var(--text-muted)]">Auth Engine</span><span class="text-[var(--text)] font-medium">{{ systemInfo.auth || 'Sanctum API Tokens' }}</span></div>
            <div class="flex justify-between"><span class="text-[var(--text-muted)]">Database</span><span class="text-[var(--text)] font-medium">{{ systemInfo.database || 'Connecting...' }}</span></div>
            <div v-if="systemInfo.memory_usage" class="flex justify-between"><span class="text-[var(--text-muted)]">RAM Usage</span><span class="text-[var(--text)] font-medium">{{ systemInfo.memory_usage }}</span></div>
            <div v-if="systemInfo.os" class="flex justify-between"><span class="text-[var(--text-muted)]">Server OS</span><span class="text-[var(--text)] font-medium">{{ systemInfo.os }}</span></div>
          </div>
        </div>

        <div class="rounded-lg border border-[var(--border)] bg-[var(--surface)] p-4">
          <h3 class="mb-3 text-xs font-semibold text-[var(--text)]">Build & Deployments</h3>
          <div class="space-y-2">
            <div v-if="recentDeployments.length === 0" class="text-2xs text-text-muted">No deployment records loaded.</div>
            <div v-for="deploy in recentDeployments" :key="deploy.version" class="flex items-center justify-between text-xs">
              <div class="flex items-center gap-2">
                <span
                  :class="[
                    'size-1.5 rounded-full',
                    deploy.status === 'success' ? 'bg-[var(--success)]' : 'bg-[var(--danger)]',
                  ]"
                />
                <span class="font-medium text-[var(--text)]">{{ deploy.version }}</span>
              </div>
              <span class="text-2xs text-[var(--text-soft)]">{{ deploy.time }}</span>
            </div>
          </div>
        </div>
      </section>
    </div>

    <!-- Live System Activity Stream -->
    <section>
      <h2 class="mb-3 text-sm font-semibold text-[var(--text)]">Live System Activity & Events Stream</h2>
      <div v-if="errorLog.length === 0" class="rounded-lg border border-[var(--border)] bg-[var(--surface)] p-4 text-xs text-[var(--text-muted)]">
        {{ loading ? "Loading activity events..." : "No activity events available." }}
      </div>
      <div v-else class="space-y-2">
        <div
          v-for="(event, idx) in errorLog"
          :key="idx"
          class="flex items-center gap-3 rounded-lg border border-[var(--border)] bg-[var(--surface)] p-3"
        >
          <span
            :class="[
              'rounded-full px-2 py-0.5 text-2xs font-medium',
              event.level === 'error' ? 'bg-[var(--danger-soft)] text-[var(--danger)]' : event.level === 'warn' ? 'bg-[var(--warning-soft)] text-[var(--warning)]' : 'bg-[var(--info-soft)] text-[var(--info)]',
            ]"
          >
            {{ event.level }}
          </span>
          <span class="text-2xs text-[var(--text-soft)] font-mono">{{ event.time }}</span>
          <span class="flex-1 text-xs text-[var(--text)]">{{ event.message }}</span>
          <span class="text-2xs font-mono text-[var(--text-soft)]">{{ event.service }}</span>
        </div>
      </div>
    </section>
  </div>
</template>
