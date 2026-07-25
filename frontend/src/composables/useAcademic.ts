import { computed } from "vue";
import { keepPreviousData, useQuery } from "@tanstack/vue-query";
import { apiFetch, buildQuery, queryKeys } from "@/api";
import type { AcademicRecord, AcademicRecordDetail, PaginatedResponse } from "@/api";

export function useAcademicList(params: {
  page: () => number;
  search: () => string;
}) {
  const query = useQuery({
    queryKey: computed(() =>
      queryKeys.academic({ page: params.page(), search: params.search() }),
    ),
    queryFn: () =>
      apiFetch<PaginatedResponse<AcademicRecord>>(
        `/api/academic-records${buildQuery({
          page: params.page(),
          per_page: 15,
          search: params.search(),
        })}`,
      ),
    placeholderData: keepPreviousData,
  });

  const records = computed(() => query.data.value?.data ?? []);
  const meta = computed(() => query.data.value?.meta);

  return { query, records, meta };
}

export function useAcademicDetail(id: string | number) {
  const query = useQuery({
    queryKey: computed(() => queryKeys.academicDetail(String(id))),
    queryFn: async () => {
      const payload = await apiFetch<{ data: AcademicRecordDetail }>(`/api/academic-records/${id}`);
      return payload.data;
    },
  });

  const record = computed(() => query.data.value ?? null);

  return { query, record };
}
