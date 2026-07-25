import { computed } from "vue";
import { keepPreviousData, useQuery } from "@tanstack/vue-query";
import { apiFetch, buildQuery, queryKeys } from "@/api";
import type { GranteeRow, GranteeDetail, PaginatedResponse } from "@/api";

export function useGranteeList(params: {
  page: () => number;
  search: () => string;
  account: () => string;
  submission: () => string;
}) {
  const query = useQuery({
    queryKey: computed(() =>
      queryKeys.grantees({
        page: params.page(),
        search: params.search(),
        account: params.account(),
        submission: params.submission(),
      }),
    ),
    queryFn: () =>
      apiFetch<PaginatedResponse<GranteeRow>>(
        `/api/grantees${buildQuery({
          page: params.page(),
          per_page: 15,
          search: params.search(),
          account: params.account(),
          submission: params.submission(),
        })}`,
      ),
    placeholderData: keepPreviousData,
  });

  const rows = computed(() => query.data.value?.data ?? []);
  const meta = computed(() => query.data.value?.meta);

  return { query, rows, meta };
}

export function useGranteeDetail(id: string | number) {
  const query = useQuery({
    queryKey: computed(() => queryKeys.grantee(String(id))),
    queryFn: async () => {
      const payload = await apiFetch<{ data: GranteeDetail }>(`/api/grantees/${id}`);
      return payload.data;
    },
  });

  const grantee = computed(() => query.data.value);

  return { query, grantee };
}
