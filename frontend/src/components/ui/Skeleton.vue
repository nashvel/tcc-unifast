<script setup lang="ts">
withDefaults(
  defineProps<{
    className?: string;
  }>(),
  { className: "" },
);
</script>

<template>
  <div
    :class="[
      'animate-pulse rounded-md bg-surface-muted',
      className,
    ]"
    aria-hidden="true"
  />
</template>
