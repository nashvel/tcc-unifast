import { apiFetch, ensureCsrfCookie } from "./client";
import { clearAuthSession, setMockSession, type AuthUser } from "@/auth/session";

export type LoginResult =
  | { user: AuthUser }
  | { two_factor_required: true; challenge_id: string; expires_at: string };

export type TwoFactorSetup = {
  secret: string;
  otpauth_uri: string;
};

export type TwoFactorStatus = {
  enabled: boolean;
  confirmed_at: string | null;
  recovery_codes_remaining: number;
};

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

export async function login(email: string, password: string, captcha?: string): Promise<LoginResult> {
  await ensureCsrfCookie();
  const payload = await apiFetch<LoginResult>("/api/auth/login", {
    method: "POST",
    body: JSON.stringify({ email, password, captcha: captcha ?? "" }),
  });
  if ("user" in payload) setMockSession(true);
  return payload;
}

export async function verifyTwoFactor(challengeId: string, code: string): Promise<AuthUser> {
  await ensureCsrfCookie();
  const payload = await apiFetch<{ user: AuthUser }>("/api/auth/2fa/verify", {
    method: "POST",
    body: JSON.stringify({ challenge_id: challengeId, code }),
  });
  setMockSession(true);
  return payload.user;
}

export async function beginGoogleLogin(): Promise<string> {
  await ensureCsrfCookie();
  const payload = await apiFetch<{ url: string }>("/api/auth/google/redirect");
  return payload.url;
}

export async function fetchTwoFactorStatus(): Promise<TwoFactorStatus> {
  return await apiFetch<TwoFactorStatus>("/api/auth/2fa");
}

export async function setupTwoFactor(): Promise<TwoFactorSetup> {
  await ensureCsrfCookie();
  return await apiFetch<TwoFactorSetup>("/api/auth/2fa/setup", { method: "POST" });
}

export async function enableTwoFactor(secret: string, code: string): Promise<{ enabled: boolean; recovery_codes: string[] }> {
  await ensureCsrfCookie();
  return await apiFetch<{ enabled: boolean; recovery_codes: string[] }>("/api/auth/2fa/enable", {
    method: "POST",
    body: JSON.stringify({ secret, code }),
  });
}

export async function disableTwoFactor(password: string): Promise<{ enabled: boolean; message: string }> {
  await ensureCsrfCookie();
  return await apiFetch<{ enabled: boolean; message: string }>("/api/auth/2fa", {
    method: "DELETE",
    body: JSON.stringify({ password }),
  });
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
