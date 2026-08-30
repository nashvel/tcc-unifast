import { API_BASE } from "@/config";
import type { ListQuery } from "./types";
import { clearAuthSession, hasMockSession, setMockSession } from "@/auth/session";

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
let refreshInFlight: Promise<boolean> | null = null;
let csrfReady = false;

async function getMockHandler() {
  if (!mockHandler) {
    const mod = await import("@/mock/handlers");
    mockHandler = mod.handleMockRequest;
  }
  return mockHandler;
}

function readXsrfToken(): string | null {
  if (typeof document === "undefined") return null;
  const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
  if (!match) return null;
  try {
    return decodeURIComponent(match[1]);
  } catch {
    return match[1];
  }
}

/** Fetch Sanctum CSRF cookie (readable XSRF-TOKEN) before mutating auth requests. */
export async function ensureCsrfCookie(): Promise<void> {
  if (useMock || csrfReady) return;
  await fetch(apiUrl("/sanctum/csrf-cookie"), {
    method: "GET",
    credentials: "include",
    headers: { Accept: "application/json" },
  });
  csrfReady = true;
}

function applyCsrfHeader(headers: Headers) {
  const xsrf = readXsrfToken();
  if (xsrf && !headers.has("X-XSRF-TOKEN")) {
    headers.set("X-XSRF-TOKEN", xsrf);
  }
}

function requestPath(url: string): string {
  try {
    const raw = url.startsWith("http") ? new URL(url).pathname : url;
    return raw.split("?")[0] || raw;
  } catch {
    return url;
  }
}

function shouldAttemptRefresh(path: string, init: RequestInit & { _authRetry?: boolean }): boolean {
  if (init._authRetry) return false;
  if ((init.method || "GET").toUpperCase() === "GET" && path.endsWith("/auth/me")) {
    // Let the router decide; still refresh so mid-session expiry recovers.
  }
  const skip = [
    "/api/auth/login",
    "/api/auth/2fa/verify",
    "/api/auth/refresh",
    "/api/auth/logout",
    "/api/auth/google/redirect",
    "/sanctum/csrf-cookie",
    "/api/auth/captcha",
  ];
  return !skip.some((p) => path === p || path.endsWith(p));
}

async function tryRefreshSession(): Promise<boolean> {
  if (!refreshInFlight) {
    refreshInFlight = (async () => {
      try {
        await ensureCsrfCookie();
        const headers = new Headers({ Accept: "application/json" });
        applyCsrfHeader(headers);
        const response = await fetch(apiUrl("/api/auth/refresh"), {
          method: "POST",
          credentials: "include",
          headers,
        });
        if (!response.ok) return false;
        const { resetEcho } = await import("@/composables/useEcho");
        resetEcho();
        return true;
      } catch {
        return false;
      } finally {
        refreshInFlight = null;
      }
    })();
  }
  return refreshInFlight;
}

/**
 * Onboarding sessions are short-lived and intentionally not refreshable, so a 401
 * mid-funnel is expected rather than exceptional. Sending the student to /login is
 * wrong: they have no password yet. Point them back at their activation link and
 * reassure them that progress is saved — it is, server-side.
 */
function isOnboardingFunnelPath(pathname: string): boolean {
  return pathname.startsWith("/student/onboarding") || pathname === "/student/kyc";
}

function redirectToLogin() {
  clearAuthSession();
  if (typeof window === "undefined") return;
  const lang = new URLSearchParams(window.location.search).get("lang");
  const suffix = lang ? `?lang=${encodeURIComponent(lang)}` : "";

  if (isOnboardingFunnelPath(window.location.pathname)) {
    const target = `/activation/resend${suffix}${suffix ? "&" : "?"}reason=session_expired`;
    window.location.assign(target);
    return;
  }

  const target = `/login${suffix}`;
  if (!window.location.pathname.startsWith("/login")) {
    window.location.assign(target);
  }
}

export async function apiFetch<T>(
  url: string,
  init: RequestInit & { _authRetry?: boolean } = {},
): Promise<T> {
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
  const path = requestPath(fullUrl);
  const headers = new Headers(init.headers);
  if (!headers.has("Accept")) headers.set("Accept", "application/json");
  if (init.body && !headers.has("Content-Type") && !(init.body instanceof FormData)) {
    headers.set("Content-Type", "application/json");
  }
  // Never attach Bearer from storage — access token lives in HttpOnly cookie.
  headers.delete("Authorization");
  applyCsrfHeader(headers);

  try {
    const response = await fetch(fullUrl, {
      ...init,
      headers,
      credentials: "include",
    });

    if (response.status === 401 && shouldAttemptRefresh(path, init)) {
      const refreshed = await tryRefreshSession();
      if (refreshed) {
        return apiFetch<T>(url, { ...init, _authRetry: true });
      }
      redirectToLogin();
      throw new ApiError("Unauthenticated.", 401);
    }

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
    if (error instanceof ApiError) throw error;
    throw new ApiError(
      error instanceof Error ? error.message : "Failed to connect to API server.",
      0,
    );
  }
}

/** Authenticated binary/blob fetch with cookie session + one-shot refresh. */
export async function apiFetchBlob(
  url: string,
  init: RequestInit & { _authRetry?: boolean } = {},
): Promise<Response> {
  if (useMock) {
    throw new ApiError("Blob fetch not available in mock mode", 0);
  }

  const fullUrl = url.startsWith("http") ? url : apiUrl(url);
  const path = requestPath(fullUrl);
  const headers = new Headers(init.headers);
  if (!headers.has("Accept")) headers.set("Accept", "*/*");
  headers.delete("Authorization");
  applyCsrfHeader(headers);

  const response = await fetch(fullUrl, {
    ...init,
    headers,
    credentials: "include",
  });

  if (response.status === 401 && shouldAttemptRefresh(path, init)) {
    const refreshed = await tryRefreshSession();
    if (refreshed) {
      return apiFetchBlob(url, { ...init, _authRetry: true });
    }
    redirectToLogin();
    throw new ApiError("Unauthenticated.", 401);
  }

  return response;
}

// Re-export mock helpers used by auth flows in mock mode.
export { hasMockSession, setMockSession };
