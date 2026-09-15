<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useQuery, useQueryClient } from "@tanstack/vue-query";
import { useRoute, useRouter } from "vue-router";
import {
  IconAlertTriangle,
  IconDatabase,
  IconInfoCircle,
  IconSearch,
  IconShieldCheck,
} from "@tabler/icons-vue";
import { apiFetch } from "@/api/client";
import AppDialog from "@/components/dialogs/AppDialog.vue";
import { toast } from "@/composables/useToast";
import PageHeader from "@/components/ui/PageHeader.vue";
import DataTable from "@/components/tables/DataTable.vue";

const route = useRoute();
const router = useRouter();
const queryClient = useQueryClient();

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
type Finding = {
  id: number;
  title: string;
  category: string;
  severity: string;
  status: string;
  description?: string | null;
  created_at: string;
  related_user?: { name: string } | null;
};
const findingsQuery = useQuery({ queryKey: ["security-findings"], queryFn: () => apiFetch<{ data: { data: Finding[] } }>("/api/security/findings") });
const findings = computed(() => findingsQuery.data.value?.data.data ?? []);
const search = ref("");
const status = ref("all");
const selectedFinding = ref<Finding | null>(null);
const updatingFinding = ref(false);
const filteredFindings = computed(() => findings.value.filter((finding) => {
  const matchesStatus = status.value === "all" || finding.status === status.value;
  const matchesSearch = `${finding.title} ${finding.category} ${finding.severity}`
    .toLowerCase()
    .includes(search.value.toLowerCase());
  return matchesStatus && matchesSearch;
}));
const stats = computed(() => [
  ["Open", findings.value.filter((finding) => finding.status === "open").length, IconAlertTriangle],
  ["Resolved", findings.value.filter((finding) => finding.status === "resolved").length, IconShieldCheck],
  ["Ignored", findings.value.filter((finding) => finding.status === "ignored").length, IconInfoCircle],
  ["Total", findings.value.length, IconShieldCheck],
]);

async function updateFindingStatus(status: "resolved" | "ignored") {
  if (!selectedFinding.value) return;
  updatingFinding.value = true;
  try {
    await apiFetch(`/api/security/findings/${selectedFinding.value.id}`, { method: "PATCH", body: JSON.stringify({ status }) });
    await queryClient.invalidateQueries({ queryKey: ["security-findings"] });
    selectedFinding.value = null;
    toast.success(`Finding marked ${status}.`);
  } catch (error) {
    toast.error(error instanceof Error ? error.message : "Unable to update finding.");
  } finally {
    updatingFinding.value = false;
  }
}

// ── Memory tab ─────────────────────────────────────────────
const query = ref("");
const filteredRecords = computed(() =>
  findings.value.filter((finding) =>
    `${finding.title} ${finding.category} ${finding.status} ${finding.related_user?.name ?? ""}`
      .toLowerCase()
      .includes(query.value.toLowerCase()),
  ),
);
</script>

<template>
  <div>
    <PageHeader
      title="Security"
      description="Monitor security findings and retained detection signals."
    >
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
      <div class="mb-4 grid gap-2 rounded-lg border bg-surface p-3 md:grid-cols-5">
        <select v-model="status" class="h-9 rounded-md border bg-surface px-3 text-xs">
          <option value="all">All states</option>
          <option value="open">Open</option>
          <option value="resolved">Resolved</option>
          <option value="ignored">Ignored</option>
        </select>
        <input
          v-model="search"
          placeholder="Search findings…"
          class="h-9 rounded-md border px-3 text-xs md:col-span-2"
        />
      </div>
      <DataTable :headings="['Severity', 'Finding', 'Scanner', 'State', 'Detected', '']">
        <tr v-if="findingsQuery.isLoading.value"><td colspan="6" class="p-8 text-center text-text-muted">Loading findings…</td></tr>
        <tr v-else-if="findingsQuery.isError.value"><td colspan="6" class="p-8 text-center text-danger">Unable to load security findings.</td></tr>
        <tr v-for="finding in filteredFindings" :key="finding.id">
          <td class="px-3 py-3 text-warning">
            <IconAlertTriangle :size="12" class="mr-1 inline" />{{ finding.severity }}
          </td>
          <td class="px-3 py-3 font-medium">{{ finding.title }}</td>
          <td class="px-3 py-3 text-text-muted">{{ finding.category }}</td>
          <td class="px-3 py-3 text-success">{{ finding.status }}</td>
          <td class="px-3 py-3 text-text-muted">{{ new Date(finding.created_at).toLocaleDateString() }}</td>
          <td class="px-3 py-3 text-right"><button class="text-primary hover:underline" @click="selectedFinding = finding">View</button></td>
        </tr>
        <tr v-if="!findingsQuery.isLoading.value && !findingsQuery.isError.value && !filteredFindings.length"><td colspan="6" class="p-8 text-center text-text-muted">No security findings match the current filters.</td></tr>
      </DataTable>
    </template>

    <!-- ── Memory Tab ── -->
    <template v-else-if="activeTab === 'memory'">
      <section class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <article
          v-for="item in [
            ['Security findings', findings.length],
            ['Open', findings.filter((finding) => finding.status === 'open').length],
            ['Resolved', findings.filter((finding) => finding.status === 'resolved').length],
            ['Ignored', findings.filter((finding) => finding.status === 'ignored').length],
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
      <DataTable :headings="['Signal ID', 'Summary', 'Category', 'Subject', 'Last observed', 'Status']">
        <tr v-if="findingsQuery.isLoading.value"><td colspan="6" class="p-8 text-center text-text-muted">Loading retained signals…</td></tr>
        <tr v-else-if="findingsQuery.isError.value"><td colspan="6" class="p-8 text-center text-danger">Unable to load retained signals.</td></tr>
        <tr v-for="record in filteredRecords" :key="record.id">
          <td class="px-3 py-3 font-mono">SEC-{{ String(record.id).padStart(6, '0') }}</td>
          <td class="px-3 py-3 font-medium">{{ record.title }}</td>
          <td class="px-3 py-3 text-text-muted">{{ record.category }}</td>
          <td class="px-3 py-3">{{ record.related_user?.name ?? '—' }}</td>
          <td class="px-3 py-3 text-text-muted">{{ new Date(record.created_at).toLocaleDateString() }}</td>
          <td class="px-3 py-3">
            <span class="inline-flex items-center gap-1 rounded-full bg-success-soft px-2 py-0.5 text-micro text-success">
              <IconShieldCheck :size="11" />{{ record.status }}
            </span>
          </td>
        </tr>
        <tr v-if="!findingsQuery.isLoading.value && !findingsQuery.isError.value && !filteredRecords.length">
          <td colspan="6" class="p-8 text-center text-text-muted">No security memory records found.</td>
        </tr>
      </DataTable>
    </template>

    <AppDialog :model-value="!!selectedFinding" title="Security Finding" @update:model-value="selectedFinding = null">
      <dl v-if="selectedFinding" class="space-y-3 text-sm">
        <div><dt class="text-xs text-text-muted">Title</dt><dd class="font-medium">{{ selectedFinding.title }}</dd></div>
        <div class="grid grid-cols-2 gap-3"><div><dt class="text-xs text-text-muted">Severity</dt><dd>{{ selectedFinding.severity }}</dd></div><div><dt class="text-xs text-text-muted">Status</dt><dd>{{ selectedFinding.status }}</dd></div></div>
        <div><dt class="text-xs text-text-muted">Category</dt><dd>{{ selectedFinding.category }}</dd></div>
        <div><dt class="text-xs text-text-muted">Description</dt><dd class="whitespace-pre-wrap">{{ selectedFinding.description || 'No description was provided.' }}</dd></div>
        <div><dt class="text-xs text-text-muted">Related user</dt><dd>{{ selectedFinding.related_user?.name ?? '—' }}</dd></div>
      </dl>
      <template #footer>
        <div class="flex justify-end gap-2">
          <button v-if="selectedFinding?.status === 'open'" :disabled="updatingFinding" class="rounded-md border px-3 py-2 text-xs hover:bg-surface-muted disabled:opacity-50" @click="updateFindingStatus('ignored')">Ignore</button>
          <button v-if="selectedFinding?.status === 'open'" :disabled="updatingFinding" class="rounded-md bg-primary px-3 py-2 text-xs text-white disabled:opacity-50" @click="updateFindingStatus('resolved')">{{ updatingFinding ? 'Updating…' : 'Resolve' }}</button>
        </div>
      </template>
    </AppDialog>
  </div>
</template>
