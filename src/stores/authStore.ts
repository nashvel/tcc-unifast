import { create } from "zustand";

export type AppRole = "admin" | "staff" | "head" | "student";

export interface AuthProfile {
  id: string;
  full_name: string;
  email: string | null;
  student_number: string | null;
  university: string | null;
  program: string | null;
  year_level: number | null;
  contact: string | null;
  birthdate: string | null;
}

interface AuthState {
  ready: boolean;
  userId: string | null;
  email: string | null;
  profile: AuthProfile | null;
  role: AppRole | null;
  setSession: (s: { userId: string | null; email: string | null }) => void;
  setProfile: (p: AuthProfile | null) => void;
  setRole: (r: AppRole | null) => void;
  setReady: (b: boolean) => void;
  reset: () => void;
}

export const useAuthStore = create<AuthState>((set) => ({
  ready: false,
  userId: null,
  email: null,
  profile: null,
  role: null,
  setSession: ({ userId, email }) => set({ userId, email }),
  setProfile: (profile) => set({ profile }),
  setRole: (role) => set({ role }),
  setReady: (ready) => set({ ready }),
  reset: () => set({ userId: null, email: null, profile: null, role: null }),
}));
