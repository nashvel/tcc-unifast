<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import {
  IconAlertTriangle,
  IconDatabase,
  IconDownload,
  IconInfoCircle,
  IconSearch,
  IconShieldCheck,
  IconTrash,
} from "@tabler/icons-vue";
import { findings } from "@/constants/mockAdmin";
import PageHeader from "@/components/ui/PageHeader.vue";
import DataTable from "@/components/tables/DataTable.vue";

const route = useRoute();
const router = useRouter();

// ── Tab state ──────────────────────────────────────────────
type Tab = "findings" | "memory";
const activeTab = ref<Tab>((route.query.tab as Tab) === "memory" ? "memory" : "findings");

watch(
  () => route.query.tab,
  (val) => {
    if (val === "memory" || val === "findings") {
      activeTab.value = val;
    } else if (!val) {
      activeTab.value = "findings";
    }
  }
);

function selectTab(tab: Tab) {
  activeTab.value = tab;
  router.replace({
    query: {
      ...route.query,
      tab: tab === "findings" ? undefined : tab,
    },
  });
}

const tabs: { key: Tab; label: string }[] = [
  { key: "findings", label: "Security Findings" },
  { key: "memory",   label: "Security Memory" },
];

// ── Findings tab ───────────────────────────────────────────
const stats = [
  ["Open", 0, IconAlertTriangle],
  ["Fixed", 3, IconShieldCheck],
  ["Ignored", 0, IconInfoCircle],
  ["Scanners", 3, IconShieldCheck],
];

// ── Memory tab ─────────────────────────────────────────────
const query = ref("");
const records = ref([
  ["SEC-2026-0184", "Repeated failed login pattern",  "Authentication", "System Developer", "July 11, 2026", "Active"],
  ["SEC-2026-0172", "Document hash duplicate",         "File integrity",  "Maria Santos",    "July 9, 2026",  "Retained"],
  ["SEC-2026-0158", "Unusual export volume",           "Data access",    "Staff Account",   "July 5, 2026",  "Reviewed"],
]);
const filteredRecords = computed(() =>
  records.value.filter((r) => r.join(" ").toLowerCase().includes(query.value.toLowerCase())),
);
</script>

<template>
  <div>
    <PageHeader
      title="Security"
      description="Monitor security findings and retained detection signals."
    >
      <template #actions>
        <button class="inline-flex h-9 items-center gap-1 rounded-md border px-3 text-xs">
          <IconDownload :size="14" /> Export CSV
        </button>
      </template>
    </PageHeader>

    <!-- Tabs -->
    <div class="mb-5 flex gap-1 border-b">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        class="px-4 py-2 text-sm font-medium transition-colors"
        :class="
          activeTab === tab.key
            ? 'border-b-2 border-primary text-primary'
            : 'text-text-muted hover:text-text'
        "
        @click="selectTab(tab.key)"
      >
        {{ tab.label }}
      </button>
    </div>

    <!-- ── Findings Tab ── -->
    <template v-if="activeTab === 'findings'">
      <div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-4">
        <article
          v-for="stat in stats"
          :key="stat[0] as string"
          class="flex items-center gap-3 rounded-lg border bg-surface p-3"
        >
          <span class="grid h-9 w-9 place-items-center rounded-md bg-surface-muted text-primary">
            <component :is="stat[2]" :size="18" />
          </span>
          <div>
            <p class="text-micro uppercase text-text-muted">{{ stat[0] }}</p>
            <p class="text-lg font-semibold">{{ stat[1] }}</p>
          </div>
        </article>
      </div>
      <div class="mb-4 grid grid-cols-2 gap-2 rounded-lg border bg-surface p-3 md:grid-cols-5">
        <select
          v-for="value in ['All scanners', 'Any state', 'Any severity']"
          :key="value"
          class="h-9 rounded-md border bg-surface px-3 text-xs"
        >
          <option>{{ value }}</option>
        </select>
        <input
          placeholder="Search findings…"
          class="h-9 rounded-md border px-3 text-xs md:col-span-2"
        />
      </div>
      <DataTable :headings="['Severity', 'Finding', 'Scanner', 'State', 'Detected', '']">
        <tr v-for="finding in findings" :key="finding[1] as string">
          <td class="px-3 py-3 text-warning">
            <IconAlertTriangle :size="12" class="mr-1 inline" />{{ finding[0] }}
          </td>
          <td class="px-3 py-3 font-medium">{{ finding[1] }}</td>
          <td class="px-3 py-3 text-text-muted">{{ finding[2] }}</td>
          <td class="px-3 py-3 text-success">{{ finding[3] }}</td>
          <td class="px-3 py-3 text-text-muted">{{ finding[4] }}</td>
          <td class="px-3 py-3 text-primary">View</td>
        </tr>
      </DataTable>
    </template>

    <!-- ── Memory Tab ── -->
    <template v-else-if="activeTab === 'memory'">
      <section class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <article
          v-for="item in [
            ['Retained signals', '184'],
            ['Active patterns', '12'],
            ['Reviewed', '159'],
            ['Retention', '365 days'],
          ]"
          :key="item[0]"
          class="rounded-lg border bg-surface p-4"
        >
          <IconDatabase :size="17" class="text-primary" />
          <p class="mt-3 text-xs text-text-muted">{{ item[0] }}</p>
          <p class="mt-1 text-lg font-semibold">{{ item[1] }}</p>
        </article>
      </section>
      <div class="relative mb-3 max-w-xl">
        <IconSearch :size="14" class="absolute left-3 top-1/2 -translate-y-1/2 text-text-soft" />
        <input
          v-model="query"
          class="h-9 w-full rounded-md border pl-9 pr-3 text-xs"
          placeholder="Search retained security context"
        />
      </div>
      <DataTable :headings="['Signal ID', 'Summary', 'Category', 'Subject', 'Last observed', 'Status', '']">
        <tr v-for="record in filteredRecords" :key="record[0]">
          <td class="px-3 py-3 font-mono">{{ record[0] }}</td>
          <td class="px-3 py-3 font-medium">{{ record[1] }}</td>
          <td class="px-3 py-3 text-text-muted">{{ record[2] }}</td>
          <td class="px-3 py-3">{{ record[3] }}</td>
          <td class="px-3 py-3 text-text-muted">{{ record[4] }}</td>
          <td class="px-3 py-3">
            <span class="inline-flex items-center gap-1 rounded-full bg-success-soft px-2 py-0.5 text-micro text-success">
              <IconShieldCheck :size="11" />{{ record[5] }}
            </span>
          </td>
          <td class="px-3 py-3 text-right">
            <button class="text-text-soft hover:text-danger" aria-label="Remove retained signal">
              <IconTrash :size="14" />
            </button>
          </td>
        </tr>
        <tr v-if="!filteredRecords.length">
          <td colspan="7" class="p-8 text-center text-text-muted">No security memory records found.</td>
        </tr>
      </DataTable>
    </template>
  </div>
</template>
