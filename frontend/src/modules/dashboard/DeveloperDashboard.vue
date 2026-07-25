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
  Users,
} from "lucide-vue-next";

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
  { label: "RBAC Settings", path: "/app/developer/rbac", icon: Shield, color: "from-violet-500/20 to-violet-500/5 text-violet-400" },
  { label: "API Docs", path: "/app/developer/api-docs", icon: Code, color: "from-blue-500/20 to-blue-500/5 text-blue-400" },
  { label: "Flow Charts", path: "/app/developer/flow-chart", icon: GitBranch, color: "from-emerald-500/20 to-emerald-500/5 text-emerald-400" },
  { label: "Support", path: "/app/developer/support", icon: AlertTriangle, color: "from-orange-500/20 to-orange-500/5 text-orange-400" },
  { label: "Collaborators", path: "/app/developer/collaborators", icon: Users, color: "from-cyan-500/20 to-cyan-500/5 text-cyan-400" },
  { label: "Audit Trail", path: "/app/developer/audit", icon: Terminal, color: "from-rose-500/20 to-rose-500/5 text-rose-400" },
]);

const uptime = computed(() => {
  const hours = 720;
  const downtime = 0.14;
  return ((hours - downtime) / hours * 100).toFixed(3);
});

const statusColor: Record<string, string> = {
  healthy: "text-emerald-400",
  degraded: "text-amber-400",
  down: "text-red-400",
};

const statusDot: Record<string, string> = {
  healthy: "bg-emerald-400",
  degraded: "bg-amber-400",
  down: "bg-red-400",
};

const methodColors: Record<string, string> = {
  GET: "bg-emerald-500/20 text-emerald-400",
  POST: "bg-blue-500/20 text-blue-400",
  PUT: "bg-amber-500/20 text-amber-400",
  DELETE: "bg-red-500/20 text-red-400",
};

const levelColors: Record<string, string> = {
  error: "bg-red-500/20 text-red-400",
  warn: "bg-amber-500/20 text-amber-400",
  info: "bg-blue-500/20 text-blue-400",
};
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-lg font-bold text-white">Developer Dashboard</h1>
        <p class="mt-0.5 text-xs text-slate-400">System health, API performance, and deployment status.</p>
      </div>
      <button class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-[#1e293b] bg-white/5 px-3 text-xs text-slate-300 hover:bg-white/10">
        <RefreshCw :size="14" /> Refresh
      </button>
    </div>

    <!-- Quick Actions -->
    <section class="grid grid-cols-3 gap-2 sm:grid-cols-6">
      <RouterLink
        v-for="action in quickActions"
        :key="action.path"
        :to="action.path"
        class="group flex flex-col items-center gap-2.5 rounded-xl border border-[#1e293b] bg-[#0f172a] p-4 text-center transition-all hover:border-[#334155] hover:bg-[#1e293b]"
      >
        <span :class="['grid size-10 place-items-center rounded-lg bg-gradient-to-b', action.color]">
          <component :is="action.icon" :size="18" />
        </span>
        <span class="text-2xs font-medium text-slate-400 group-hover:text-slate-200">{{ action.label }}</span>
      </RouterLink>
    </section>

    <!-- System Health -->
    <section>
      <h2 class="mb-3 text-sm font-semibold text-white">System Health</h2>
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div
          v-for="service in systemHealth"
          :key="service.name"
          class="rounded-xl border border-[#1e293b] bg-[#0f172a] p-4"
        >
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2.5">
              <div class="grid size-8 place-items-center rounded-lg bg-white/5">
                <component :is="service.icon" :size="14" class="text-slate-400" />
              </div>
              <span class="text-xs font-medium text-slate-200">{{ service.name }}</span>
            </div>
            <div class="flex items-center gap-1.5">
              <span :class="['size-1.5 rounded-full', statusDot[service.status]]" />
              <span :class="['text-2xs font-medium', statusColor[service.status]]">
                {{ service.status }}
              </span>
            </div>
          </div>
          <div class="mt-3 grid grid-cols-2 gap-2 text-2xs">
            <div>
              <p class="text-slate-500">Latency</p>
              <p class="font-medium text-slate-200">{{ service.latency }}</p>
            </div>
            <div>
              <p class="text-slate-500">Uptime</p>
              <p class="font-medium text-slate-200">{{ service.uptime }}</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- API Performance -->
    <section>
      <div class="mb-3 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-white">API Performance</h2>
        <span class="text-2xs text-slate-500">Last 24 hours</span>
      </div>
      <div class="overflow-x-auto rounded-xl border border-[#1e293b] bg-[#0f172a]">
        <table class="w-full text-xs">
          <thead>
            <tr class="border-b border-[#1e293b] text-left">
              <th class="px-3 py-2.5 font-medium text-slate-500">Endpoint</th>
              <th class="px-3 py-2.5 font-medium text-slate-500">P50</th>
              <th class="px-3 py-2.5 font-medium text-slate-500">P95</th>
              <th class="px-3 py-2.5 font-medium text-slate-500">P99</th>
              <th class="px-3 py-2.5 font-medium text-slate-500">Calls</th>
              <th class="px-3 py-2.5 font-medium text-slate-500">Errors</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="api in apiMetrics" :key="api.endpoint" class="border-b border-[#1e293b]/50 last:border-0 hover:bg-white/[0.02]">
              <td class="px-3 py-2.5">
                <div class="flex items-center gap-2">
                  <span :class="['rounded px-1.5 py-0.5 text-2xs font-bold', methodColors[api.method]]">
                    {{ api.method }}
                  </span>
                  <code class="text-2xs font-mono text-slate-300">{{ api.endpoint }}</code>
                </div>
              </td>
              <td class="px-3 py-2.5 font-mono text-2xs text-slate-300">{{ api.p50 }}</td>
              <td class="px-3 py-2.5 font-mono text-2xs text-slate-300">{{ api.p95 }}</td>
              <td class="px-3 py-2.5 font-mono text-2xs text-slate-300">{{ api.p99 }}</td>
              <td class="px-3 py-2.5 text-2xs text-slate-400">{{ api.calls }}</td>
              <td class="px-3 py-2.5">
                <span
                  :class="[
                    'text-2xs font-medium',
                    parseFloat(api.errors) > 1 ? 'text-red-400' : parseFloat(api.errors) > 0.1 ? 'text-amber-400' : 'text-emerald-400',
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
        <h2 class="mb-3 text-sm font-semibold text-white">Recent Deployments</h2>
        <div class="space-y-2">
          <div
            v-for="deploy in recentDeployments"
            :key="deploy.version"
            class="flex items-center gap-3 rounded-xl border border-[#1e293b] bg-[#0f172a] p-3.5"
          >
            <span
              :class="[
                'grid size-8 place-items-center rounded-lg',
                deploy.status === 'success' ? 'bg-emerald-500/10' : 'bg-red-500/10',
              ]"
            >
              <Check v-if="deploy.status === 'success'" :size="14" class="text-emerald-400" />
              <AlertTriangle v-else :size="14" class="text-red-400" />
            </span>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2">
                <span class="text-xs font-semibold text-white">{{ deploy.version }}</span>
                <code class="text-2xs text-slate-500">{{ deploy.commit }}</code>
              </div>
              <p class="text-2xs text-slate-400">{{ deploy.author }} · {{ deploy.time }}</p>
            </div>
            <span
              :class="[
                'rounded-full px-2 py-0.5 text-2xs font-semibold',
                deploy.status === 'success' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400',
              ]"
            >
              {{ deploy.status }}
            </span>
          </div>
        </div>
      </section>

      <!-- Error Log -->
      <section>
        <h2 class="mb-3 text-sm font-semibold text-white">Recent Errors</h2>
        <div class="space-y-2">
          <div
            v-for="(error, idx) in errorLog"
            :key="idx"
            class="rounded-xl border border-[#1e293b] bg-[#0f172a] p-3.5"
          >
            <div class="flex items-center gap-2">
              <span :class="['rounded-full px-2 py-0.5 text-2xs font-semibold', levelColors[error.level]]">
                {{ error.level }}
              </span>
              <span class="text-2xs text-slate-500">{{ error.time }}</span>
              <span class="text-2xs text-slate-500">· {{ error.service }}</span>
            </div>
            <p class="mt-1.5 text-xs text-slate-300">{{ error.message }}</p>
          </div>
        </div>
      </section>
    </div>

    <!-- System Info -->
    <section class="rounded-xl border border-[#1e293b] bg-[#0f172a] p-5">
      <h2 class="mb-4 text-sm font-semibold text-white">System Information</h2>
      <div class="grid gap-4 text-xs sm:grid-cols-2 lg:grid-cols-4">
        <div>
          <p class="text-slate-500">Framework</p>
          <p class="mt-0.5 font-medium text-slate-200">Laravel 13.19 + Vue 3.5</p>
        </div>
        <div>
          <p class="text-slate-500">Node Version</p>
          <p class="mt-0.5 font-medium text-slate-200">Node.js 20 LTS</p>
        </div>
        <div>
          <p class="text-slate-500">Database</p>
          <p class="mt-0.5 font-medium text-slate-200">SQLite (dev) / MySQL (prod)</p>
        </div>
        <div>
          <p class="text-slate-500">30-Day Uptime</p>
          <p class="mt-0.5 font-medium text-emerald-400">{{ uptime }}%</p>
        </div>
        <div>
          <p class="text-slate-500">Auth Method</p>
          <p class="mt-0.5 font-medium text-slate-200">Laravel Sanctum Tokens</p>
        </div>
        <div>
          <p class="text-slate-500">Frontend Build</p>
          <p class="mt-0.5 font-medium text-slate-200">Vite 7.3 + TypeScript</p>
        </div>
        <div>
          <p class="text-slate-500">Cache Driver</p>
          <p class="mt-0.5 font-medium text-slate-200">File / Redis (prod)</p>
        </div>
        <div>
          <p class="text-slate-500">Queue Driver</p>
          <p class="mt-0.5 font-medium text-slate-200">Sync / Database (prod)</p>
        </div>
      </div>
    </section>
  </div>
</template>
