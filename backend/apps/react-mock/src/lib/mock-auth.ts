// Fully local, mock-only authentication. No backend calls.
import type { AppRole, AuthProfile } from "@/stores/authStore";

export type DemoRole = "admin" | "head" | "staff" | "student";

export interface DemoUser {
  role: DemoRole;
  email: string;
  fullName: string;
  student_number?: string;
  university?: string;
  program?: string;
  year_level?: number;
  contact?: string;
  birthdate?: string;
}

export const DEMO_USERS: DemoUser[] = [
  { role: "admin", email: "admin@unifast.gov.ph", fullName: "System Administrator" },
  { role: "head", email: "r.santos@unifast.gov.ph", fullName: "Ricardo Santos" },
  { role: "staff", email: "j.cruz@unifast.gov.ph", fullName: "Jessica Cruz" },
  {
    role: "student",
    email: "mc.delacruz@plm.edu.ph",
    fullName: "Maria Clara Dela Cruz",
    student_number: "2024-00123",
    university: "Pamantasan ng Lungsod ng Maynila",
    program: "BS Computer Science",
    year_level: 2,
    contact: "+639171234567",
    birthdate: "2003-05-14",
  },
];

// role -> in-app AppRole
const ROLE_MAP: Record<DemoRole, AppRole> = {
  admin: "admin",
  head: "head",
  staff: "staff",
  student: "student",
};

export interface MockSession {
  userId: string;
  email: string;
  role: AppRole;
  profile: AuthProfile;
}

const KEY = "unifast.mock.session";

function toProfile(u: DemoUser): AuthProfile {
  return {
    id: `user-${u.role}`,
    full_name: u.fullName,
    email: u.email,
    student_number: u.student_number ?? null,
    university: u.university ?? null,
    program: u.program ?? null,
    year_level: u.year_level ?? null,
    contact: u.contact ?? null,
    birthdate: u.birthdate ?? null,
    avatar_url: typeof window !== "undefined" ? localStorage.getItem(`unifast.mock.avatar.${u.role}`) : null,
  };
}

export function loadSession(): MockSession | null {
  if (typeof window === "undefined") return null;
  try {
    const raw = localStorage.getItem(KEY);
    if (!raw) return null;
    return JSON.parse(raw) as MockSession;
  } catch {
    return null;
  }
}

export function signInAs(role: DemoRole): MockSession {
  const u = DEMO_USERS.find((x) => x.role === role);
  if (!u) throw new Error("Unknown demo role");
  const session: MockSession = {
    userId: `user-${role}`,
    email: u.email,
    role: ROLE_MAP[role],
    profile: toProfile(u),
  };
  localStorage.setItem(KEY, JSON.stringify(session));
  recordLoginEvent(session.userId);
  return session;
}

export function signInWithEmail(email: string, _password: string): MockSession {
  const u = DEMO_USERS.find((x) => x.email.toLowerCase() === email.toLowerCase()) ?? DEMO_USERS[3];
  return signInAs(u.role);
}

export function signOut() {
  if (typeof window === "undefined") return;
  localStorage.removeItem(KEY);
}

export function updateProfileLocal(patch: Partial<AuthProfile>): MockSession | null {
  const s = loadSession();
  if (!s) return null;
  const next: MockSession = { ...s, profile: { ...s.profile, ...patch } };
  localStorage.setItem(KEY, JSON.stringify(next));
  return next;
}

export function setAvatarUrlLocal(role: AppRole, url: string | null) {
  if (typeof window === "undefined") return;
  const key = `unifast.mock.avatar.${role}`;
  if (url) localStorage.setItem(key, url);
  else localStorage.removeItem(key);
  updateProfileLocal({ avatar_url: url });
}

/* ---------------- Login events (local) ---------------- */
export interface LoginEvent {
  id: string;
  signed_in_at: string;
  ip_address: string | null;
  user_agent: string | null;
}
const EV_KEY = "unifast.mock.login_events";

export function recordLoginEvent(_userId: string) {
  if (typeof window === "undefined") return;
  const list = loadLoginEvents();
  list.unshift({
    id: `ev-${Date.now()}`,
    signed_in_at: new Date().toISOString(),
    ip_address: "127.0.0.1",
    user_agent: typeof navigator !== "undefined" ? navigator.userAgent : "Mock",
  });
  localStorage.setItem(EV_KEY, JSON.stringify(list.slice(0, 50)));
}

export function loadLoginEvents(): LoginEvent[] {
  if (typeof window === "undefined") return [];
  try {
    const raw = localStorage.getItem(EV_KEY);
    if (raw) return JSON.parse(raw) as LoginEvent[];
  } catch {
    // fallthrough
  }
  // Seed a few sample events so the UI has content.
  const now = Date.now();
  const seed: LoginEvent[] = [
    { id: "ev-seed-1", signed_in_at: new Date(now - 1000 * 60 * 60 * 3).toISOString(), ip_address: "192.168.1.10", user_agent: "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36" },
    { id: "ev-seed-2", signed_in_at: new Date(now - 1000 * 60 * 60 * 26).toISOString(), ip_address: "120.28.45.99", user_agent: "Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)" },
    { id: "ev-seed-3", signed_in_at: new Date(now - 1000 * 60 * 60 * 72).toISOString(), ip_address: "192.168.1.10", user_agent: "Mozilla/5.0 (Windows NT 10.0; Win64; x64)" },
  ];
  return seed;
}

/** Mock password change — just validates non-empty and simulates delay. */
export async function changePasswordMock(current: string, next: string): Promise<{ error: string | null }> {
  await new Promise((r) => setTimeout(r, 400));
  if (!current) return { error: "Current password is required" };
  if (!next || next.length < 8) return { error: "New password too short" };
  return { error: null };
}
