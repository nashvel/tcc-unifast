<script setup lang="ts">
import { IconArrowLeft, IconDownload, IconPrinter } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import DataTable from "@/components/tables/DataTable.vue";
const rows = [
  ["2024-00182", "Maria Angela Santos", "BS Information Technology", "Eligible", "Active"],
  [
    "2024-00194",
    "John Paul Ramirez",
    "BS Business Administration",
    "For evaluation",
    "Pending activation",
  ],
  ["2024-00207", "Nicole Anne Flores", "BS Education", "Eligible", "Active"],
];
</script>
<template>
  <div>
    <RouterLink
      to="/app/reports/generate"
      class="mb-3 inline-flex items-center gap-1 text-xs text-text-muted"
      ><IconArrowLeft :size="14" />Report parameters</RouterLink
    ><PageHeader title="Report Preview" description="TES Grantee Masterlist — AY 2025–2026"
      ><template #actions
        ><button class="inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-xs">
          <IconPrinter :size="14" />Print</button
        ><button
          class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-xs text-white"
        >
          <IconDownload :size="14" />Export PDF
        </button></template
      ></PageHeader
    >
    <article class="rounded-lg border bg-white p-6 text-black shadow-sm">
      <header class="border-b pb-5 text-center">
        <p class="text-xs uppercase tracking-widest">Tagoloan Community College</p>
        <h2 class="mt-2 text-xl font-semibold">TES Grantee Masterlist</h2>
        <p class="mt-1 text-xs text-gray-500">Academic Year 2025–2026 · All batches</p>
      </header>
      <div class="my-5 grid grid-cols-3 gap-3 text-center">
        <div>
          <p class="text-xl font-semibold">2,486</p>
          <p class="text-xs text-gray-500">Total grantees</p>
        </div>
        <div>
          <p class="text-xl font-semibold">2,184</p>
          <p class="text-xs text-gray-500">Eligible</p>
        </div>
        <div>
          <p class="text-xl font-semibold">2,301</p>
          <p class="text-xs text-gray-500">Active</p>
        </div>
      </div>
      <DataTable :headings="['Student #', 'Name', 'Program', 'Eligibility', 'Account']"
        ><tr v-for="r in rows" :key="r[0]">
          <td class="px-3 py-3 font-mono">{{ r[0] }}</td>
          <td class="px-3 py-3 font-medium">{{ r[1] }}</td>
          <td class="px-3 py-3">{{ r[2] }}</td>
          <td class="px-3 py-3">{{ r[3] }}</td>
          <td class="px-3 py-3">{{ r[4] }}</td>
        </tr></DataTable
      >
      <footer class="mt-6 border-t pt-4 text-center text-xs text-gray-500">
        Generated July 12, 2026 · UniFAST TES Grantee Management
      </footer>
    </article>
  </div>
</template>
