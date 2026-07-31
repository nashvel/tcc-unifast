import { computed, toValue, type MaybeRefOrGetter } from "vue";
import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/vue-query";
import { apiFetch, buildQuery, queryKeys } from "@/api";
import type {
  DocSubmissionDetail,
  DocSubmissionPackage,
  PaginatedResponse,
} from "@/api";

export function useDocumentPackageList(params: {
  page: () => number;
  search: () => string;
}) {
  const query = useQuery({
    queryKey: computed(() =>
      queryKeys.documentPackages({ page: params.page(), search: params.search() }),
    ),
    queryFn: () =>
      apiFetch<PaginatedResponse<DocSubmissionPackage>>(
        `/api/document-submission-packages${buildQuery({
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

export function useDocumentPackage(
  granteeId: MaybeRefOrGetter<string | number | null | undefined>,
  batchId: MaybeRefOrGetter<string | number | null | undefined>,
) {
  const resolvedGranteeId = computed(() => {
    const value = toValue(granteeId);
    return value == null || value === "" ? null : String(value);
  });
  const resolvedBatchId = computed(() => {
    const value = toValue(batchId);
    return value == null || value === "" ? null : String(value);
  });

  const query = useQuery({
    queryKey: computed(() =>
      queryKeys.documentPackage(
        resolvedGranteeId.value ?? "none",
        resolvedBatchId.value ?? "none",
      ),
    ),
    queryFn: async () => {
      const payload = await apiFetch<{ data: DocSubmissionPackage }>(
        `/api/document-submission-packages/${resolvedGranteeId.value}/${resolvedBatchId.value}`,
      );
      return payload.data;
    },
    enabled: computed(
      () => resolvedGranteeId.value != null && resolvedBatchId.value != null,
    ),
  });

  const pkg = computed(() => query.data.value ?? null);

  return { query, pkg };
}

/** @deprecated Prefer useDocumentPackageList for staff queue. */
export function useDocumentList(params: {
  page: () => number;
  search: () => string;
}) {
  return useDocumentPackageList(params);
}

export function useDocumentDetail(id: MaybeRefOrGetter<string | number | null | undefined>) {
  const queryClient = useQueryClient();
  const resolvedId = computed(() => {
    const value = toValue(id);
    return value == null || value === "" ? null : String(value);
  });

  const query = useQuery({
    queryKey: computed(() => queryKeys.document(resolvedId.value ?? "none")),
    queryFn: async () => {
      const payload = await apiFetch<{ data: DocSubmissionDetail }>(
        `/api/document-submissions/${resolvedId.value}`,
      );
      return payload.data;
    },
    enabled: computed(() => resolvedId.value != null),
    placeholderData: keepPreviousData,
  });

  const item = computed(() => query.data.value ?? null);

  const reviewMutation = useMutation({
    mutationFn: ({ decision, notes }: { decision: string; notes: string }) =>
      apiFetch<{ data: DocSubmissionDetail }>(
        `/api/document-submissions/${resolvedId.value}/review`,
        {
          method: "POST",
          body: JSON.stringify({ decision, notes }),
        },
      ),
    onSuccess: (payload) => {
      if (resolvedId.value) {
        queryClient.setQueryData(queryKeys.document(resolvedId.value), payload.data);
      }
      void queryClient.invalidateQueries({ queryKey: ["document-submissions"] });
      void queryClient.invalidateQueries({ queryKey: ["document-submission-packages"] });
    },
  });

  return { query, item, reviewMutation, queryClient };
}
