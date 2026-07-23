import { create } from "zustand";
import { persist } from "zustand/middleware";

export type FontKey = "absans" | "inter" | "ibm-plex" | "system";

export const FONT_OPTIONS: {
  key: FontKey;
  label: string;
  description: string;
  stack: string;
}[] = [
  {
    key: "absans",
    label: "Absans",
    description: "Custom sans — the default. Editorial, distinctive.",
    stack: `"Absans", ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif`,
  },
  {
    key: "inter",
    label: "Inter",
    description: "Neutral workhorse. Highly legible at 13–14px.",
    stack: `"Inter", ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif`,
  },
  {
    key: "ibm-plex",
    label: "IBM Plex Sans",
    description: "Technical, institutional. Reads as serious software.",
    stack: `"IBM Plex Sans", ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif`,
  },
  {
    key: "system",
    label: "System",
    description: "Whatever the OS ships — fastest, no download.",
    stack: `ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif`,
  },
];

interface ThemeState {
  dark: boolean;
  font: FontKey;
  toggle: () => void;
  set: (dark: boolean) => void;
  setFont: (font: FontKey) => void;
}

export const useThemeStore = create<ThemeState>()(
  persist(
    (set) => ({
      dark: false,
      font: "absans",
      toggle: () => set((s) => ({ dark: !s.dark })),
      set: (dark) => set({ dark }),
      setFont: (font) => set({ font }),
    }),
    { name: "unifast-theme" },
  ),
);
