import { getAuthToken } from "@/auth/session";
import { API_BASE } from "@/config";
import type { ListQuery } from "./types";

const useMock = import.meta.env.VITE_USE_MOCK === "true" || !API_BASE;

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

function handleMockResponse<T>(method: string, path: string, body?: string): T | null {
  if (!mockHandler) return null;
  const mockResponse = mockHandler(method, path, body);
  if (mockResponse) {
    if (mockResponse.status >= 400) {
      throw new ApiError("Mock error", mockResponse.status);
    }
    return mockResponse.body as T;
  }
  return null;
}

export async function apiFetch<T>(
  url: string,
  init: RequestInit = {},
): Promise<T> {
  // Mock mode: lazy-load mock handlers and intercept
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
  } catch (error) {
    // If API fails and we have mock data, fall back to mock
    if (error instanceof ApiError && error.status >= 400) throw error;
    console.warn(`[api] Request failed, falling back to mock: ${error}`);
    await getMockHandler();
    const method = (init.method || "GET").toUpperCase();
    const path = url.startsWith("http") ? new URL(url).pathname : url;
    const body = typeof init.body === "string" ? init.body : undefined;
    const mockResponse = mockHandler!(method, path.split("?")[0], body);
    if (mockResponse) {
      return mockResponse.body as T;
    }
    throw error;
  }
}
