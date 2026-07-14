<script setup lang="ts">
import {
  IconAlertTriangle,
  IconDownload,
  IconInfoCircle,
  IconShieldCheck,
} from "@tabler/icons-vue";
import { findings } from "@/constants/mockAdmin";
import PageHeader from "@/components/ui/PageHeader.vue";
import DataTable from "@/components/tables/DataTable.vue";
const stats = [
  ["Open", 0, IconAlertTriangle],
  ["Fixed", 3, IconShieldCheck],
  ["Ignored", 0, IconInfoCircle],
  ["Scanners", 3, IconShieldCheck],
];
</script>
<template>
  <div>
    <PageHeader
      title="Security Findings"
      description="Findings from every scanner wired into this project, including the workspace connector scan (Wiz)."
      ><template #actions
        ><button class="inline-flex h-9 items-center gap-1 rounded-md border px-3 text-xs">
          <IconDownload :size="14" />Export CSV
        </button></template
      ></PageHeader
    >
    <div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-4">
      <article
        v-for="stat in stats"
        :key="stat[0] as string"
        class="flex items-center gap-3 rounded-lg border bg-surface p-3"
      >
        <span class="grid h-9 w-9 place-items-center rounded-md bg-surface-muted text-primary"
          ><component :is="stat[2]" :size="18"
        /></span>
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
        <option>{{ value }}</option></select
      ><input
        placeholder="Search findings…"
        class="h-9 rounded-md border px-3 text-xs md:col-span-2"
      />
    </div>
    <DataTable :headings="['Severity', 'Finding', 'Scanner', 'State', 'Detected', '']"
      ><tr v-for="finding in findings" :key="finding[1] as string">
        <td class="px-3 py-3 text-warning">
          <IconAlertTriangle :size="12" class="mr-1 inline" />{{ finding[0] }}
        </td>
        <td class="px-3 py-3 font-medium">{{ finding[1] }}</td>
        <td class="px-3 py-3 text-text-muted">{{ finding[2] }}</td>
        <td class="px-3 py-3 text-success">{{ finding[3] }}</td>
        <td class="px-3 py-3 text-text-muted">{{ finding[4] }}</td>
        <td class="px-3 py-3 text-primary">View</td>
      </tr></DataTable
    >
  </div>
</template>
