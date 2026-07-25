import { computed } from "vue";
import { useMutation, useQuery, useQueryClient } from "@tanstack/vue-query";
import { apiFetch, queryKeys } from "@/api";
import type { StudentNotification, PaginatedResponse } from "@/api";

export function useNotificationList() {
  const query = useQuery({
    queryKey: queryKeys.notifications,
    queryFn: () =>
      apiFetch<PaginatedResponse<StudentNotification>>("/api/student/notifications?per_page=50"),
  });

  const items = computed(() => query.data.value?.data ?? []);

  return { query, items };
}

export function useMarkNotificationRead() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) =>
      apiFetch<{ data: StudentNotification }>(`/api/student/notifications/${id}/read`, {
        method: "POST",
      }),
    onMutate: async (id) => {
      await queryClient.cancelQueries({ queryKey: queryKeys.notifications });
      const previous = queryClient.getQueryData<PaginatedResponse<StudentNotification>>(
        queryKeys.notifications,
      );
      queryClient.setQueryData<PaginatedResponse<StudentNotification>>(queryKeys.notifications, (current) =>
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
    },
  });
}

export function useMarkAllNotificationsRead() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => apiFetch<{ ok: boolean }>("/api/student/notifications/read-all", { method: "POST" }),
    onMutate: async () => {
      await queryClient.cancelQueries({ queryKey: queryKeys.notifications });
      const previous = queryClient.getQueryData<PaginatedResponse<StudentNotification>>(
        queryKeys.notifications,
      );
      queryClient.setQueryData<PaginatedResponse<StudentNotification>>(queryKeys.notifications, (current) =>
        current
          ? { ...current, data: current.data.map((item) => ({ ...item, read: true })) }
          : current,
      );
      return { previous };
    },
    onError: (_error, _vars, context) => {
      if (context?.previous) {
        queryClient.setQueryData(queryKeys.notifications, context.previous);
      }
    },
  });
}
