<script setup lang="ts">
import type { FormStatus } from "@/api/types";

const props = defineProps<{ status: FormStatus }>();

const config: Record<FormStatus, { label: string; classes: string }> = {
  draft:     { label: "Draft",     classes: "bg-surface-muted text-text-muted border-border" },
  published: { label: "Published", classes: "bg-success/10 text-success border-success/30" },
  closed:    { label: "Closed",    classes: "bg-warning/10 text-warning border-warning/30" },
  archived:  { label: "Archived",  classes: "bg-surface-muted text-text-muted/60 border-border/50" },
};
</script>

<template>
  <span
    class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wider"
    :class="config[status].classes"
  >
    <span class="h-1.5 w-1.5 rounded-full"
      :class="{
        'bg-text-muted': status === 'draft' || status === 'archived',
        'bg-success': status === 'published',
        'bg-warning': status === 'closed',
      }"
    />
    {{ config[status].label }}
  </span>
</template>
