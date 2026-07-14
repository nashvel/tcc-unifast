<script setup lang="ts">
import { onMounted, ref } from "vue";
import PageHeader from "@/components/ui/PageHeader.vue";

type Notification = {
  id: number;
  title: string;
  body: string;
  time: string;
  type: string;
  read: boolean;
};
const items = ref<Notification[]>([]);
const loading = ref(true);
const error = ref("");

onMounted(async () => {
  try {
    const response = await fetch("/api/student/notifications", { headers: { Accept: "application/json" } });
    const payload = await response.json();
    if (!response.ok) throw new Error(payload.message || "Unable to load notifications.");
    items.value = payload.data || [];
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "Unable to load notifications.";
  } finally {
    loading.value = false;
  }
});

const tones: Record<string, string> = {
  info: "bg-info",
  success: "bg-success",
  warning: "bg-warning",
  danger: "bg-danger",
  window_opened: "bg-success",
  window_closed: "bg-warning",
  deadline_extended: "bg-info",
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
    <p v-if="loading" class="rounded-lg border bg-surface p-4 text-sm text-text-muted">
      Loading notifications...
    </p>
    <p v-else-if="error" class="rounded-lg border border-danger/30 bg-danger-soft p-4 text-sm text-danger">
      {{ error }}
    </p>
    <ul class="space-y-2">
      <li
        v-for="item in items"
        :key="item.id"
        :class="[
          'flex gap-3 rounded-lg border bg-surface p-3',
          !item.read && 'border-primary/30 bg-primary-soft/20',
        ]"
      >
        <i :class="['mt-2 h-2 w-2 shrink-0 rounded-full', tones[item.type] || 'bg-info']" />
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
      <li v-if="!loading && !items.length" class="rounded-lg border bg-surface p-4 text-sm text-text-muted">
        No notifications yet.
      </li>
    </ul>
  </div>
</template>
