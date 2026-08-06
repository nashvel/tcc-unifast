<script setup lang="ts">
import AppToaster from "@/components/ui/AppToaster.vue";
import SuccessMilestoneOverlay from "@/components/ui/SuccessMilestoneOverlay.vue";
</script>

<template>
  <RouterView />
  <AppToaster />
  <SuccessMilestoneOverlay />
</template>
