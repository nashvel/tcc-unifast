<script setup lang="ts">
import { Toaster } from "vue-sonner";
import "vue-sonner/style.css";
</script>

<template>
  <Toaster
    position="top-right"
    :duration="4000"
    :toast-options="{
      class: 'border bg-surface text-text shadow-lg',
    }"
    rich-colors
    close-button
  />
</template>
