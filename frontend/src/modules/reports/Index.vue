<script setup lang="ts">
import {
  IconChecklist,
  IconFileCheck,
  IconFolders,
  IconHistory,
  IconReportAnalytics,
  IconSchool,
  IconUsersGroup,
} from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
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
    <PageHeader title="Reports" description="Generate, preview, and export operational reports." />
    <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
      <RouterLink
        v-for="report in reports"
        :key="report[0] as string"
        to="/app/reports/generate"
        class="rounded-lg border bg-surface p-4 hover:border-primary/40 hover:bg-primary-soft/10"
      >
        <div class="mb-2 grid h-9 w-9 place-items-center rounded-md bg-primary-soft text-primary">
          <component :is="report[1]" :size="18" />
        </div>
        <p class="text-sm font-semibold">{{ report[0] }}</p>
        <p class="mt-1 text-xs text-text-muted">{{ report[2] }}</p>
      </RouterLink>
    </section>
  </div>
</template>
