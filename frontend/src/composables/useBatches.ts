import { computed, ref } from "vue";
import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/vue-query";
import { apiFetch, buildQuery, queryKeys } from "@/api";
import type { Batch, BatchDetail, PaginatedResponse } from "@/api";

export function useBatchList(page: Ref<number>, perPage = 24) {
  const query = useQuery({
    queryKey: computed(() => [...queryKeys.batches, { page: page.value }]),
    queryFn: () =>
      apiFetch<PaginatedResponse<Batch>>(`/api/batches?page=${page.value}&per_page=${perPage}`),
    placeholderData: keepPreviousData,
  });

  const batches = computed(() => query.data.value?.data ?? []);
  const meta = computed(() => query.data.value?.meta);
  const activeBatch = computed(() => batches.value.find((b) => b.window_status === "active"));

  return { query, batches, meta, activeBatch };
}

export function useBatchDetail(id: string | number) {
  const query = useQuery({
    queryKey: computed(() => queryKeys.batch(String(id))),
    queryFn: async () => {
      const payload = await apiFetch<{ data: BatchDetail }>(`/api/batches/${id}`);
      return payload.data;
    },
  });

  const batch = computed(() => query.data.value ?? null);

  return { query, batch };
}

export function useCreateBatch() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (form: { name: string; academic_year: string; semester: string; submission_deadline: string }) =>
      apiFetch<{ data: Batch }>("/api/batches", {
        method: "POST",
        body: JSON.stringify(form),
      }),
    onSuccess: (payload) => {
      queryClient.setQueryData<PaginatedResponse<Batch>>(
        [...queryKeys.batches, { page: 1 }],
        (current) => {
          if (!current) {
            return {
              data: [payload.data],
              meta: {
                current_page: 1,
                last_page: 1,
                per_page: 24,
                total: 1,
                from: 1,
                to: 1,
              },
            };
          }
          return { ...current, data: [payload.data, ...current.data] };
        },
      );
      void queryClient.invalidateQueries({ queryKey: queryKeys.batches });
    },
  });
}

export function useBatchAction(id: string | number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ path, body }: { path: string; body?: Record<string, string> }) =>
      apiFetch<{ data: Partial<BatchDetail>; mail?: { sent: number; failed: unknown[] } }>(
        `/api/batches/${id}/${path}`,
        {
          method: "POST",
          body: body ? JSON.stringify(body) : undefined,
        },
      ),
    onSuccess: (payload) => {
      queryClient.setQueryData(queryKeys.batch(String(id)), (current: BatchDetail | undefined) =>
        current ? { ...current, ...payload.data } : current,
      );
      void queryClient.invalidateQueries({ queryKey: queryKeys.batches });
    },
  });
}

import type { Ref } from "vue";
