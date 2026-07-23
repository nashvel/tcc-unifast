<script setup lang="ts">
import { IconWifiOff } from "@tabler/icons-vue";
import { useOnline } from "@/composables/useOnline";

const { online } = useOnline();
</script>

<template>
  <div
    v-if="!online"
    class="flex items-center justify-center gap-2 border-b border-warning/30 bg-warning-soft px-3 py-2 text-xs text-warning"
    role="status"
  >
    <IconWifiOff :size="14" />
    You're offline — showing cached data where available.
  </div>
</template>
