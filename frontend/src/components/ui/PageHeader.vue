<script setup lang="ts">
import AppTour from "@/components/tour/AppTour.vue";
import { useI18n } from "vue-i18n";
import { translateKnownText } from "@/i18n/knownText";

defineProps<{ title: string; description: string }>();
const { t } = useI18n();
</script>
<template>
  <header class="mb-4 flex flex-wrap items-start justify-between gap-3" data-tour="page-header">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight">{{ translateKnownText(t, title) }}</h1>
      <p class="mt-1 text-sm text-text-muted">{{ translateKnownText(t, description) }}</p>
    </div>
    <div class="flex gap-2">
      <AppTour />
      <slot name="actions" />
    </div>
  </header>
</template>
