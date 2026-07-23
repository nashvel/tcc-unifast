import { csrfToken } from "@/auth/session";

export type PaginationMeta = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
};

export type PaginatedResponse<T> = {
  data: T[];
  meta: PaginationMeta;
};

export type ListQuery = {
  page?: number;
  per_page?: number;
  search?: string;
  sort?: string;
  direction?: "asc" | "desc";
  [key: string]: string | number | boolean | undefined | null;
};

export class ApiError extends Error {
  status: number;
  constructor(message: string, status: number) {
    super(message);
    this.name = "ApiError";
    this.status = status;
  }
}

export function buildQuery(params: ListQuery = {}): string {
  const search = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null || value === "") continue;
    search.set(key, String(value));
  }
  const qs = search.toString();
  return qs ? `?${qs}` : "";
}

export async function apiFetch<T>(
  url: string,
  init: RequestInit = {},
): Promise<T> {
  const headers = new Headers(init.headers);
  if (!headers.has("Accept")) headers.set("Accept", "application/json");
  if (init.body && !headers.has("Content-Type") && !(init.body instanceof FormData)) {
    headers.set("Content-Type", "application/json");
  }
  if (init.method && init.method !== "GET" && !headers.has("X-CSRF-TOKEN")) {
    headers.set("X-CSRF-TOKEN", csrfToken());
  }

  const response = await fetch(url, { ...init, headers });
  const payload = await response.json().catch(() => ({}));

  if (!response.ok) {
    const validation = payload?.errors
      ? Object.values(payload.errors as Record<string, string[]>).flat().join(" ")
      : "";
    throw new ApiError(
      validation || payload?.message || `Request failed (${response.status})`,
      response.status,
    );
  }

  return payload as T;
}

export function presentPaginator(paginator: {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
}): PaginationMeta {
  return {
    current_page: paginator.current_page,
    last_page: paginator.last_page,
    per_page: paginator.per_page,
    total: paginator.total,
    from: paginator.from,
    to: paginator.to,
  };
}
