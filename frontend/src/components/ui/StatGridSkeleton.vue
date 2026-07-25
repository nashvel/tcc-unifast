<script setup lang="ts">
import Skeleton from "@/components/ui/Skeleton.vue";

withDefaults(
  defineProps<{
    count?: number;
  }>(),
  { count: 4 },
);
</script>

<template>
  <div class="grid grid-cols-2 gap-3 sm:grid-cols-4" aria-busy="true">
    <div
      v-for="i in count"
      :key="i"
      class="space-y-2 rounded-lg border bg-surface p-3"
    >
      <Skeleton class-name="h-3 w-2/3" />
      <Skeleton class-name="h-5 w-1/2" />
    </div>
  </div>
</template>
