<script setup lang="ts">
import { IconDeviceDesktop, IconDeviceTablet, IconDeviceMobile } from "@tabler/icons-vue";

type Device = "desktop" | "tablet" | "mobile";
const model = defineModel<Device>({ default: "desktop" });

const devices: { value: Device; icon: unknown; label: string }[] = [
  { value: "desktop", icon: IconDeviceDesktop, label: "Desktop" },
  { value: "tablet",  icon: IconDeviceTablet,  label: "Tablet" },
  { value: "mobile",  icon: IconDeviceMobile,  label: "Mobile" },
];
</script>

<template>
  <div class="flex items-center gap-1 bg-surface-muted rounded-lg p-1">
    <button
      v-for="device in devices"
      :key="device.value"
      class="flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium transition-all"
      :class="model === device.value
        ? 'bg-surface text-primary shadow-sm'
        : 'text-text-muted hover:text-text'"
      :title="device.label"
      @click="model = device.value"
    >
      <component :is="device.icon" :size="14" />
      <span class="hidden sm:inline">{{ device.label }}</span>
    </button>
  </div>
</template>
