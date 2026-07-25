<script setup lang="ts">
import { useNotificationChannel } from "@/composables/useEcho";
import { useNotificationList, useMarkNotificationRead, useMarkAllNotificationsRead } from "@/composables/useNotifications";
import { toast } from "@/composables/useToast";
import PageHeader from "@/components/ui/PageHeader.vue";
import ListSkeleton from "@/components/ui/ListSkeleton.vue";
import EmptyState from "@/components/ui/EmptyState.vue";

const { items, query: notificationsQuery } = useNotificationList();
const markReadMutation = useMarkNotificationRead();
const markAllMutation = useMarkAllNotificationsRead();

useNotificationChannel((payload) => {
  toast.info(payload.title, { description: payload.body });
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
    >
      <template #actions>
        <button
          class="h-9 rounded-md border px-3 text-xs disabled:opacity-50"
          :disabled="markAllMutation.isPending.value || !items.some((item) => !item.read)"
          @click="markAllMutation.mutate()"
        >
          Mark all read
        </button>
      </template>
    </PageHeader>

    <ListSkeleton v-if="notificationsQuery.isLoading.value" :rows="5" />
    <EmptyState
      v-else-if="notificationsQuery.isError.value"
      variant="error"
      title="Couldn't load notifications"
      :hint="
        notificationsQuery.error.value instanceof Error
          ? notificationsQuery.error.value.message
          : 'Unable to load notifications.'
      "
      @retry="notificationsQuery.refetch()"
    />
    <ul v-else class="space-y-2">
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
          @click="markReadMutation.mutate(item.id)"
        >
          Mark read
        </button>
      </li>
      <li v-if="!items.length">
        <EmptyState title="No notifications yet" hint="Batch window updates will appear here." />
      </li>
    </ul>
  </div>
</template>
