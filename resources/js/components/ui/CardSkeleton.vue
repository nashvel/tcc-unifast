<script setup lang="ts">
import Skeleton from "@/components/ui/Skeleton.vue";

withDefaults(
  defineProps<{
    lines?: number;
    className?: string;
  }>(),
  { lines: 3, className: "" },
);
</script>

<template>
  <div :class="['space-y-3 rounded-lg border bg-surface p-4', className]" aria-busy="true">
    <Skeleton class-name="h-4 w-1/3" />
    <Skeleton
      v-for="i in lines"
      :key="i"
      :class-name="i % 2 ? 'h-3 w-4/5' : 'h-3 w-full'"
    />
  </div>
</template>
