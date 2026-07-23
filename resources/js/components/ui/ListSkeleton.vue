<script setup lang="ts">
import Skeleton from "@/components/ui/Skeleton.vue";

withDefaults(
  defineProps<{
    rows?: number;
  }>(),
  { rows: 4 },
);
</script>

<template>
  <ul class="space-y-2" aria-busy="true" aria-live="polite">
    <li
      v-for="i in rows"
      :key="i"
      class="flex items-center gap-3 rounded-lg border bg-surface p-3"
    >
      <Skeleton class-name="h-9 w-9 rounded-md" />
      <div class="flex-1 space-y-2">
        <Skeleton class-name="h-3.5 w-2/3" />
        <Skeleton class-name="h-3 w-1/2" />
      </div>
      <Skeleton class-name="h-6 w-16 rounded-full" />
    </li>
  </ul>
</template>
