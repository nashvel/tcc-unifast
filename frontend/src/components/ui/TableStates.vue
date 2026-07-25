<script setup lang="ts">
import { IconAlertCircle, IconInbox, IconRefresh, IconWifiOff } from "@tabler/icons-vue";

const props = withDefaults(
  defineProps<{
    colSpan: number;
    isLoading?: boolean;
    isFetching?: boolean;
    isError?: boolean;
    error?: unknown;
    isEmpty?: boolean;
    isOffline?: boolean;
    skeletonRows?: number;
    emptyTitle?: string;
    emptyHint?: string;
  }>(),
  {
    skeletonRows: 5,
    emptyTitle: "Nothing to show",
    emptyHint: "Try adjusting filters or come back later.",
  },
);

const emit = defineEmits<{ retry: [] }>();

function errorMessage() {
  return props.error instanceof Error
    ? props.error.message
    : "Something went wrong loading this data.";
}

function cellWidth(row: number, col: number) {
  return `${40 + ((row * 13 + col * 17) % 55)}%`;
}
</script>

<template>
  <template v-if="isLoading">
    <tr v-for="row in skeletonRows" :key="`sk-${row}`" class="pointer-events-none">
      <td v-for="col in colSpan" :key="col" class="px-3 py-3">
        <div
          class="h-3 animate-pulse rounded bg-surface-muted"
          :style="{ width: cellWidth(row, col) }"
        />
      </td>
    </tr>
  </template>
  <tr v-else-if="isOffline">
    <td :colspan="colSpan" class="px-3 py-10">
      <div class="flex flex-col items-center gap-2 text-center">
        <IconWifiOff :size="22" class="text-warning" />
        <p class="text-sm font-medium">You're offline</p>
        <p class="max-w-sm text-xs text-text-muted">
          Cached data may be stale. Reconnect to refresh.
        </p>
        <button
          class="mt-1 inline-flex items-center gap-1 text-xs text-primary hover:underline"
          @click="emit('retry')"
        >
          <IconRefresh :size="12" /> Retry
        </button>
      </div>
    </td>
  </tr>
  <tr v-else-if="isError">
    <td :colspan="colSpan" class="px-3 py-10">
      <div class="flex flex-col items-center gap-2 text-center">
        <IconAlertCircle :size="22" class="text-danger" />
        <p class="text-sm font-medium">Couldn't load data</p>
        <p class="max-w-sm text-xs text-text-muted">{{ errorMessage() }}</p>
        <button
          class="mt-1 inline-flex items-center gap-1 text-xs text-primary hover:underline"
          @click="emit('retry')"
        >
          <IconRefresh :size="12" /> Retry
        </button>
      </div>
    </td>
  </tr>
  <tr v-else-if="isEmpty">
    <td :colspan="colSpan" class="px-3 py-10">
      <div
        class="flex flex-col items-center gap-2 text-center"
        :class="isFetching && 'opacity-60'"
      >
        <IconInbox :size="22" class="text-text-muted" />
        <p class="text-sm font-medium">{{ emptyTitle }}</p>
        <p class="max-w-sm text-xs text-text-muted">{{ emptyHint }}</p>
      </div>
    </td>
  </tr>
</template>
