<script setup lang="ts">
import { Toaster } from "vue-sonner";
import "vue-sonner/style.css";
</script>

<template>
  <Toaster
    position="top-right"
    :duration="3500"
    rich-colors
    close-button
    :toast-options="{
      style: {
        borderRadius: '8px',
        fontSize: '12px',
        fontFamily: 'inherit',
      },
    }"
  />
</template>
