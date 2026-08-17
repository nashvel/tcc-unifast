import { apiFetch, ensureCsrfCookie } from "./client";
import { clearAuthSession, setMockSession, type AuthUser } from "@/auth/session";

export async function fetchCurrentUser(): Promise<AuthUser | null> {
  try {
    // Abort after 5 s — if the backend is dead/frozen we must not block the router forever.
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 5000);
    const payload = await apiFetch<{ user: AuthUser }>("/api/auth/me", { signal: controller.signal });
    clearTimeout(timeout);
    return payload.user;
  } catch {
    return null;
  }
}

export async function login(email: string, password: string, captcha?: string): Promise<AuthUser> {
  await ensureCsrfCookie();
  const payload = await apiFetch<{ user: AuthUser }>("/api/auth/login", {
    method: "POST",
    body: JSON.stringify({ email, password, captcha: captcha ?? "" }),
  });
  setMockSession(true);
  return payload.user;
}

export async function logout(): Promise<void> {
  try {
    await ensureCsrfCookie();
    await apiFetch("/api/auth/logout", { method: "POST" });
  } finally {
    clearAuthSession();
    const { resetEcho } = await import("@/composables/useEcho");
    resetEcho();
  }
}

export async function fetchLoginCaptcha(): Promise<{ image: string; key: string }> {
  return await apiFetch<{ image: string; key: string }>("/api/auth/captcha");
}
