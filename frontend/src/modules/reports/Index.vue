<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import {
  IconChecklist,
  IconFileCheck,
  IconFileInvoice,
  IconFolders,
  IconHistory,
  IconReportAnalytics,
  IconReportMoney,
  IconSchool,
  IconUsersGroup,
} from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import BillingIndex from "@/modules/billing/Index.vue";
import DistributionIndex from "@/modules/distribution/Index.vue";

const route = useRoute();
const router = useRouter();

type TabKey = "overview" | "billing" | "distribution";

const tabs: { key: TabKey; label: string; icon: any }[] = [
  { key: "overview", label: "Operational Reports", icon: IconReportAnalytics },
  { key: "billing", label: "Call-for-Billing", icon: IconFileInvoice },
  { key: "distribution", label: "Distribution Summary", icon: IconReportMoney },
];

const activeTab = ref<TabKey>(
  (route.query.tab as TabKey) && ["overview", "billing", "distribution"].includes(route.query.tab as string)
    ? (route.query.tab as TabKey)
    : "overview"
);

watch(
  () => route.query.tab,
  (newTab) => {
    if (newTab && ["overview", "billing", "distribution"].includes(newTab as string)) {
      activeTab.value = newTab as TabKey;
    } else if (!newTab) {
      activeTab.value = "overview";
    }
  }
);

function switchTab(key: TabKey) {
  activeTab.value = key;
  router.replace({
    query: {
      ...route.query,
      tab: key === "overview" ? undefined : key,
    },
  });
}

const reports = [
  ["Grantee List", IconUsersGroup, "Complete grantee roster with personal & academic details."],
  ["Batch Report", IconFolders, "Per-batch summary, progress, and outcomes."],
  ["Document Validation", IconFileCheck, "Validation outcomes by document type and risk."],
  ["Academic Tracking", IconSchool, "GWA trends, retention, and at-risk grantees."],
  ["Eligibility Report", IconChecklist, "Eligible/ineligible distribution and reasons."],
  ["Audit Trail", IconHistory, "Filtered audit logs export."],
  ["Office Report", IconReportAnalytics, "Consolidated UniFAST Office performance metrics."],
];
</script>

<template>
  <div>
    <PageHeader
      title="Reports & Disbursals"
      description="Generate, monitor, and export operational, billing, and distribution reports in one consolidated hub."
    />

    <!-- Tabs (Hick's Law consolidation) -->
    <div class="mb-5 flex flex-wrap gap-1 border-b">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        type="button"
        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium transition-colors"
        :class="
          activeTab === tab.key
            ? 'border-b-2 border-primary text-primary'
            : 'text-text-muted hover:text-text'
        "
        @click="switchTab(tab.key)"
      >
        <component :is="tab.icon" :size="16" />
        {{ tab.label }}
      </button>
    </div>

    <!-- ── Operational Reports Tab ── -->
    <section v-if="activeTab === 'overview'" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
      <RouterLink
        v-for="report in reports"
        :key="report[0] as string"
        to="/app/reports/generate"
        class="rounded-lg border bg-surface p-4 transition hover:border-primary/40 hover:bg-primary-soft/10"
      >
        <div class="mb-2 grid h-9 w-9 place-items-center rounded-md bg-primary-soft text-primary">
          <component :is="report[1]" :size="18" />
        </div>
        <p class="text-sm font-semibold">{{ report[0] }}</p>
        <p class="mt-1 text-xs text-text-muted">{{ report[2] }}</p>
      </RouterLink>
    </section>

    <!-- ── Call-for-Billing Tab ── -->
    <section v-else-if="activeTab === 'billing'">
      <BillingIndex :hide-header="true" />
    </section>

    <!-- ── Distribution Summary Tab ── -->
    <section v-else-if="activeTab === 'distribution'">
      <DistributionIndex :hide-header="true" />
    </section>
  </div>
</template>
