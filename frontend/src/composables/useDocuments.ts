import { computed } from "vue";
import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/vue-query";
import { apiFetch, buildQuery, queryKeys } from "@/api";
import type { DocSubmission, DocSubmissionDetail, PaginatedResponse } from "@/api";

export function useDocumentList(params: {
  page: () => number;
  search: () => string;
}) {
  const query = useQuery({
    queryKey: computed(() =>
      queryKeys.documents({ page: params.page(), search: params.search() }),
    ),
    queryFn: () =>
      apiFetch<PaginatedResponse<DocSubmission>>(
        `/api/document-submissions${buildQuery({
          page: params.page(),
          per_page: 15,
          search: params.search(),
        })}`,
      ),
    placeholderData: keepPreviousData,
  });

  const rows = computed(() => query.data.value?.data ?? []);
  const meta = computed(() => query.data.value?.meta);

  return { query, rows, meta };
}

export function useDocumentDetail(id: string | number) {
  const queryClient = useQueryClient();

  const query = useQuery({
    queryKey: computed(() => queryKeys.document(String(id))),
    queryFn: async () => {
      const payload = await apiFetch<{ data: DocSubmissionDetail }>(
        `/api/document-submissions/${id}`,
      );
      return payload.data;
    },
  });

  const item = computed(() => query.data.value ?? null);

  const reviewMutation = useMutation({
    mutationFn: (decision: string) =>
      apiFetch<{ data: DocSubmissionDetail }>(`/api/document-submissions/${id}/review`, {
        method: "POST",
        body: JSON.stringify({ decision, notes: "" }),
      }),
    onSuccess: (payload) => {
      queryClient.setQueryData(queryKeys.document(String(id)), payload.data);
      void queryClient.invalidateQueries({ queryKey: ["document-submissions"] });
    },
  });

  return { query, item, reviewMutation, queryClient };
}
