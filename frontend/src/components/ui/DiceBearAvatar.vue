<script setup lang="ts">
import { computed } from "vue";

const props = withDefaults(
  defineProps<{
    seed: string;
    size?: number;
    src?: string | null;
    alt?: string;
  }>(),
  { size: 28, src: null, alt: "" },
);

const avatarUrl = computed(() => {
  if (props.src) return props.src;
  const seed = encodeURIComponent((props.seed || "anonymous").trim().toLowerCase());
  return `https://api.dicebear.com/9.x/adventurer/svg?seed=${seed}&backgroundType=gradientLinear&radius=50`;
});
</script>

<template>
  <img
    :src="avatarUrl"
    :alt="alt"
    :width="size"
    :height="size"
    loading="lazy"
    decoding="async"
    class="shrink-0 rounded-full bg-surface-muted object-cover"
    :style="{ width: `${size}px`, height: `${size}px` }"
  />
</template>
