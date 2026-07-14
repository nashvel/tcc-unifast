<script setup lang="ts">
import AppTour from "@/components/tour/AppTour.vue";

defineProps<{ title: string; description: string }>();
</script>
<template>
  <header class="mb-4 flex flex-wrap items-start justify-between gap-3" data-tour="page-header">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight">{{ title }}</h1>
      <p class="mt-1 text-sm text-text-muted">{{ description }}</p>
    </div>
    <div class="flex gap-2">
      <AppTour />
      <slot name="actions" />
    </div>
  </header>
</template>
