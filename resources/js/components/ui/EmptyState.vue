<script setup lang="ts">
import { IconAlertCircle, IconInbox, IconRefresh, IconWifiOff } from "@tabler/icons-vue";

withDefaults(
  defineProps<{
    title?: string;
    hint?: string;
    variant?: "empty" | "error" | "offline";
  }>(),
  {
    title: "Nothing to show",
    hint: "Try adjusting filters or come back later.",
    variant: "empty",
  },
);

defineEmits<{ retry: [] }>();
</script>

<template>
  <div class="flex flex-col items-center gap-2 rounded-lg border bg-surface px-4 py-10 text-center">
    <IconWifiOff v-if="variant === 'offline'" :size="22" class="text-warning" />
    <IconAlertCircle v-else-if="variant === 'error'" :size="22" class="text-danger" />
    <IconInbox v-else :size="22" class="text-text-muted" />
    <p class="text-sm font-medium">{{ title }}</p>
    <p class="max-w-sm text-xs text-text-muted">{{ hint }}</p>
    <button
      v-if="variant !== 'empty'"
      class="mt-1 inline-flex items-center gap-1 text-xs text-primary hover:underline"
      @click="$emit('retry')"
    >
      <IconRefresh :size="12" /> Retry
    </button>
  </div>
</template>
