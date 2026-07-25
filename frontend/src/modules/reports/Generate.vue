<script setup lang="ts">
import { ref } from "vue";
import { IconArrowLeft, IconArrowRight } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
const type = ref("Grantee masterlist");
const period = ref("AY 2025–2026");
const format = ref("PDF");
</script>
<template>
  <div>
    <RouterLink
      to="/app/reports"
      class="mb-3 inline-flex items-center gap-1 text-xs text-text-muted"
      ><IconArrowLeft :size="14" />Reports</RouterLink
    ><PageHeader
      title="Generate Report"
      description="Select parameters and preview before export."
    />
    <section class="max-w-3xl rounded-lg border bg-surface p-5">
      <div class="grid gap-4 sm:grid-cols-2">
        <label class="text-xs font-medium"
          >Report type<select
            v-model="type"
            class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm"
          >
            <option>Grantee masterlist</option>
            <option>Document validation</option>
            <option>Eligibility summary</option>
            <option>Academic compliance</option>
          </select></label
        ><label class="text-xs font-medium"
          >Academic period<select
            v-model="period"
            class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm"
          >
            <option>AY 2025–2026</option>
            <option>AY 2024–2025</option>
          </select></label
        ><label class="text-xs font-medium"
          >Batch<select class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm">
            <option>All batches</option>
            <option>TES 2025 — Batch 04</option>
          </select></label
        ><label class="text-xs font-medium"
          >Output format<select
            v-model="format"
            class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm"
          >
            <option>PDF</option>
            <option>CSV</option>
          </select></label
        >
      </div>
      <div class="mt-5 rounded-md bg-surface-muted p-4 text-xs text-text-muted">
        {{ type }} · {{ period }} · {{ format }}
      </div>
      <div class="mt-5 flex justify-end">
        <RouterLink
          to="/app/reports/preview"
          class="inline-flex items-center gap-1.5 rounded-md bg-primary px-4 py-2 text-xs text-white"
          >Preview report<IconArrowRight :size="14"
        /></RouterLink>
      </div>
    </section>
  </div>
</template>
