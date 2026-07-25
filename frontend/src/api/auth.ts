import { apiFetch } from "./client";
import { setAuthToken, clearAuthToken } from "@/auth/session";
import type { AuthUser } from "@/auth/session";

export async function fetchCurrentUser(): Promise<AuthUser | null> {
  try {
    const payload = await apiFetch<{ user: AuthUser }>("/api/auth/me");
    return payload.user;
  } catch {
    return null;
  }
}

export async function login(email: string, password: string): Promise<AuthUser> {
  const payload = await apiFetch<{ user: AuthUser; token: string }>("/api/auth/login", {
    method: "POST",
    body: JSON.stringify({ email, password }),
  });
  setAuthToken(payload.token);
  return payload.user;
}

export async function logout(): Promise<void> {
  try {
    await apiFetch("/api/auth/logout", { method: "POST" });
  } finally {
    clearAuthToken();
  }
}
