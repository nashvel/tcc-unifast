<script setup lang="ts">
import { computed } from "vue";
import { IconCloud, IconCloudOff, IconLoader2 } from "@tabler/icons-vue";

type SaveStatus = "idle" | "saving" | "saved" | "error";

const props = defineProps<{ status: SaveStatus }>();
const emit = defineEmits<{ retry: [] }>();

const config = computed(() => {
  switch (props.status) {
    case "saving": return { icon: IconLoader2, text: "Saving…",         cls: "text-text-muted", spin: true };
    case "saved":  return { icon: IconCloud,    text: "All changes saved", cls: "text-success",   spin: false };
    case "error":  return { icon: IconCloudOff, text: "Couldn't save",  cls: "text-danger",    spin: false };
    default:       return null;
  }
});
</script>

<template>
  <div v-if="config" class="flex items-center gap-1.5 text-xs font-medium" :class="config.cls">
    <component :is="config.icon" :size="14" :class="{ 'animate-spin': config.spin }" />
    <span>{{ config.text }}</span>
    <button
      v-if="status === 'error'"
      class="underline hover:no-underline ml-1"
      @click="emit('retry')"
    >
      Retry
    </button>
  </div>
</template>
