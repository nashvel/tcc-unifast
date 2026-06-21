import { create } from "zustand";
import { persist } from "zustand/middleware";

export type AppRole = "admin" | "staff" | "head" | "student";

export interface AuthUser {
  id: string;
  name: string;
  email: string;
  role: AppRole;
  studentNumber?: string;
}

interface AuthState {
  user: AuthUser | null;
  login: (user: AuthUser) => void;
  logout: () => void;
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set) => ({
      user: null,
      login: (user) => set({ user }),
      logout: () => set({ user: null }),
    }),
    { name: "unifast-auth" },
  ),
);
