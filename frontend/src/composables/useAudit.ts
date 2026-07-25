import { computed } from "vue";
import { useQuery } from "@tanstack/vue-query";
import { apiFetch, queryKeys } from "@/api";
import type { AuditLog, PaginatedResponse } from "@/api";

export function useAuditLogs() {
  const query = useQuery({
    queryKey: queryKeys.auditLogs,
    queryFn: async () => {
      const payload = await apiFetch<PaginatedResponse<AuditLog>>("/api/audit-logs");
      return payload.data;
    },
  });

  const logs = computed(() => query.data.value ?? []);

  return { query, logs };
}
