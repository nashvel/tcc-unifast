import { getAuthToken } from "@/auth/session";
import { API_BASE } from "@/config";
import type { ListQuery } from "./types";

export const isMockMode = import.meta.env.VITE_USE_MOCK === "true";
const useMock = isMockMode || (!API_BASE && import.meta.env.VITE_USE_MOCK !== "false");

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

let mockHandler: typeof import("@/mock/handlers").handleMockRequest | null = null;

async function getMockHandler() {
  if (!mockHandler) {
    const mod = await import("@/mock/handlers");
    mockHandler = mod.handleMockRequest;
  }
  return mockHandler;
}

export async function apiFetch<T>(
  url: string,
  init: RequestInit = {},
): Promise<T> {
  // If mock mode is explicitly true or no API_BASE and VITE_USE_MOCK is not false
  if (useMock) {
    await getMockHandler();
    const method = (init.method || "GET").toUpperCase();
    const path = url.startsWith("http") ? new URL(url).pathname : url;
    const body = typeof init.body === "string" ? init.body : undefined;
    const mockResponse = mockHandler!(method, path.split("?")[0], body);
    if (mockResponse) {
      if (mockResponse.status >= 400) {
        throw new ApiError("Mock error", mockResponse.status);
      }
      return mockResponse.body as T;
    }
    console.warn(`[mock] No handler for ${method} ${path}`);
    return {} as T;
  }

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

  try {
    const response = await fetch(fullUrl, { 
      ...init, 
      headers,
      credentials: 'include' // Required for session cookies
    });
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
  } catch (error) {
    // Re-throw ApiError (HTTP errors with status codes) directly — no mock fallback
    // when VITE_USE_MOCK=false. This ensures real failures surface to the UI.
    if (error instanceof ApiError) throw error;
    throw new ApiError(
      error instanceof Error ? error.message : "Failed to connect to API server.",
      0,
    );
  }
}
