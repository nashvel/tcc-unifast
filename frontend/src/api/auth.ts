import { API_BASE } from "@/config";
import { ApiError, apiFetch, apiUrl } from "./client";
import { setAuthToken } from "@/auth/session";
import type { AuthUser } from "@/auth/session";

const useMock = import.meta.env.VITE_USE_MOCK === "true";

export async function fetchCurrentUser(): Promise<AuthUser | null> {
  try {
    const payload = await apiFetch<{ user: AuthUser }>("/api/auth/me");
    return payload.user;
  } catch {
    return null;
  }
}

export async function fetchLoginCaptcha(): Promise<string> {
  if (useMock) {
    return `data:image/svg+xml,${encodeURIComponent(`
      <svg xmlns="http://www.w3.org/2000/svg" width="420" height="88" viewBox="0 0 420 88">
        <rect width="420" height="88" rx="14" fill="#f1f5fb"/>
        <path d="M22 32 C116 8 169 78 261 56 S362 38 398 52" fill="none" stroke="#7a1e2b" stroke-opacity=".18" stroke-width="2"/>
        <text x="210" y="53" text-anchor="middle" transform="rotate(-2 210 44)" fill="#7a1e2b" font-family="ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace" font-size="25" font-weight="700" letter-spacing="9">A7K9QX</text>
      </svg>
    `)}`;
  }

  const payload = await apiFetch<{ image: string }>(`/api/auth/captcha?t=${Date.now()}`);
  return payload.image;
}

export async function login(email: string, password: string, captcha: string): Promise<AuthUser> {
  const payload = await apiFetch<{ user: AuthUser; token: string }>("/api/auth/login", {
    method: "POST",
    body: JSON.stringify({ email, password, captcha }),
  });
  setAuthToken(payload.token);
  return payload.user;
}

export async function logout(): Promise<void> {
  try {
    await apiFetch("/api/auth/logout", { method: "POST" });
  } finally {
    const { clearAuthToken } = await import("@/auth/session");
    clearAuthToken();
  }
}
