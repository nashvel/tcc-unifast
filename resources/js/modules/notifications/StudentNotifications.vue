<script setup lang="ts">
import { ref } from "vue";
import PageHeader from "@/components/ui/PageHeader.vue";
const items = ref([
  {
    id: 1,
    title: "Document approved",
    body: "Your PSA Birth Certificate was verified and accepted.",
    time: "May 12, 2025, 10:14 AM",
    type: "success",
    read: false,
  },
  {
    id: 2,
    title: "Resubmission required",
    body: "Please upload a clearer 2x2 ID Picture.",
    time: "May 11, 2025, 3:48 PM",
    type: "warning",
    read: false,
  },
  {
    id: 3,
    title: "Orientation reminder",
    body: "Scholarship orientation begins May 15 at the TCC AVR.",
    time: "May 10, 2025, 9:00 AM",
    type: "info",
    read: true,
  },
]);
const tones: Record<string, string> = {
  info: "bg-info",
  success: "bg-success",
  warning: "bg-warning",
  danger: "bg-danger",
};
</script>
<template>
  <div>
    <PageHeader
      title="Notifications"
      description="Account activity, validation updates, and reminders."
      ><template #actions
        ><button
          class="h-9 rounded-md border px-3 text-xs"
          @click="items.forEach((item) => (item.read = true))"
        >
          Mark all read
        </button></template
      ></PageHeader
    >
    <ul class="space-y-2">
      <li
        v-for="item in items"
        :key="item.id"
        :class="[
          'flex gap-3 rounded-lg border bg-surface p-3',
          !item.read && 'border-primary/30 bg-primary-soft/20',
        ]"
      >
        <i :class="['mt-2 h-2 w-2 shrink-0 rounded-full', tones[item.type]]" />
        <div class="min-w-0 flex-1">
          <div class="flex justify-between gap-2">
            <p class="text-sm font-medium">{{ item.title }}</p>
            <span class="text-micro text-text-soft">{{ item.time }}</span>
          </div>
          <p class="text-xs text-text-muted">{{ item.body }}</p>
        </div>
        <button
          v-if="!item.read"
          class="self-start text-micro text-primary"
          @click="item.read = true"
        >
          Mark read
        </button>
      </li>
    </ul>
  </div>
</template>
