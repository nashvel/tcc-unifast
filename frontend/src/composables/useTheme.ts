/**
 * useTheme — Flare / Default theme switcher
 *
 * Manages the `html.flare` class independently of the existing
 * `dark` / `dev-dark` logic in AppShell.vue.
 *
 * Usage:
 *   const { isFlare, toggleFlare, setTheme } = useTheme()
 */
import { ref, computed } from "vue";

type ThemeName = "default" | "flare";

const STORAGE_KEY = "unifast-theme";

function readStored(): ThemeName {
  try {
    const v = localStorage.getItem(STORAGE_KEY);
    if (v === "flare") return "flare";
  } catch {
    // SSR / private browsing
  }
  return "default";
}

// Singleton reactive state — shared across all composable calls
const _theme = ref<ThemeName>(readStored());

function apply(theme: ThemeName) {
  if (typeof document === "undefined") return;
  if (theme === "flare") {
    document.documentElement.classList.add("flare");
  } else {
    document.documentElement.classList.remove("flare");
  }
}

// Apply on load
apply(_theme.value);

export function useTheme() {
  const isFlare = computed(() => _theme.value === "flare");

  function setTheme(name: ThemeName) {
    _theme.value = name;
    apply(name);
    try {
      localStorage.setItem(STORAGE_KEY, name);
    } catch {
      // ignore
    }
  }

  function toggleFlare() {
    setTheme(_theme.value === "flare" ? "default" : "flare");
  }

  return {
    /** Current theme name */
    theme: _theme,
    /** True when the Flare theme is active */
    isFlare,
    /** Set theme by name */
    setTheme,
    /** Toggle between Default and Flare */
    toggleFlare,
  };
}
