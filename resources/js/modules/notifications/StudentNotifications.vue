<script setup lang="ts">
import { computed } from "vue";
import { useMutation, useQuery, useQueryClient } from "@tanstack/vue-query";
import PageHeader from "@/components/ui/PageHeader.vue";
import ListSkeleton from "@/components/ui/ListSkeleton.vue";
import EmptyState from "@/components/ui/EmptyState.vue";
import { apiFetch, type PaginatedResponse } from "@/lib/api";
import { queryKeys } from "@/lib/queryClient";
import { toast } from "@/composables/useToast";
import { useNotificationChannel, type NotificationPayload } from "@/composables/useEcho";

type Notification = {
  id: number;
  title: string;
  body: string;
  time: string;
  type: string;
  read: boolean;
};

const queryClient = useQueryClient();

const notificationsQuery = useQuery({
  queryKey: queryKeys.notifications,
  queryFn: () =>
    apiFetch<PaginatedResponse<Notification>>("/api/student/notifications?per_page=50"),
});

const items = computed(() => notificationsQuery.data.value?.data ?? []);

useNotificationChannel((payload: NotificationPayload) => {
  queryClient.setQueryData<PaginatedResponse<Notification>>(queryKeys.notifications, (current) => {
    const nextItem: Notification = { ...payload };
    if (!current) {
      return {
        data: [nextItem],
        meta: {
          current_page: 1,
          last_page: 1,
          per_page: 50,
          total: 1,
          from: 1,
          to: 1,
        },
      };
    }
    if (current.data.some((item) => item.id === nextItem.id)) return current;
    return {
      ...current,
      data: [nextItem, ...current.data],
      meta: { ...current.meta, total: current.meta.total + 1 },
    };
  });
  toast.info(payload.title, { description: payload.body });
});

const markReadMutation = useMutation({
  mutationFn: (id: number) =>
    apiFetch<{ data: Notification }>(`/api/student/notifications/${id}/read`, { method: "POST" }),
  onMutate: async (id) => {
    await queryClient.cancelQueries({ queryKey: queryKeys.notifications });
    const previous = queryClient.getQueryData<PaginatedResponse<Notification>>(
      queryKeys.notifications,
    );
    queryClient.setQueryData<PaginatedResponse<Notification>>(queryKeys.notifications, (current) =>
      current
        ? {
            ...current,
            data: current.data.map((item) => (item.id === id ? { ...item, read: true } : item)),
          }
        : current,
    );
    return { previous };
  },
  onError: (_error, _id, context) => {
    if (context?.previous) {
      queryClient.setQueryData(queryKeys.notifications, context.previous);
    }
    toast.error("Unable to mark notification as read.");
  },
});

const markAllMutation = useMutation({
  mutationFn: () => apiFetch<{ ok: boolean }>("/api/student/notifications/read-all", { method: "POST" }),
  onMutate: async () => {
    await queryClient.cancelQueries({ queryKey: queryKeys.notifications });
    const previous = queryClient.getQueryData<PaginatedResponse<Notification>>(
      queryKeys.notifications,
    );
    queryClient.setQueryData<PaginatedResponse<Notification>>(queryKeys.notifications, (current) =>
      current
        ? { ...current, data: current.data.map((item) => ({ ...item, read: true })) }
        : current,
    );
    return { previous };
  },
  onSuccess: () => toast.success("All notifications marked read"),
  onError: (_error, _vars, context) => {
    if (context?.previous) {
      queryClient.setQueryData(queryKeys.notifications, context.previous);
    }
    toast.error("Unable to mark all as read.");
  },
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
