import { getAuthToken } from "@/auth/session";
import { API_BASE } from "@/config";
import type { ListQuery } from "./types";

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

export function apiUrl(path: string): string {
  return `${API_BASE}${path}`;
}

export async function apiFetch<T>(
  url: string,
  init: RequestInit = {},
): Promise<T> {
  const fullUrl = url.startsWith("http") ? url : apiUrl(url);
  const headers = new Headers(init.headers);
  if (!headers.has("Accept")) headers.set("Accept", "application/json");
  if (init.body && !headers.has("Content-Type") && !(init.body instanceof FormData)) {
    headers.set("Content-Type", "application/json");
  }

  const token = getAuthToken();
  if (token && !headers.has("Authorization")) {
    headers.set("Authorization", `Bearer ${token}`);
  }

  const response = await fetch(fullUrl, { ...init, headers });
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
