import { useEffect } from "react";
import { useThemeStore, FONT_OPTIONS } from "@/stores/themeStore";

const loaded = new Set<string>();

async function loadFont(key: string) {
  if (loaded.has(key)) return;
  loaded.add(key);
  try {
    if (key === "inter") {
      await Promise.all([
        import("@fontsource/inter/400.css"),
        import("@fontsource/inter/500.css"),
        import("@fontsource/inter/600.css"),
      ]);
    } else if (key === "ibm-plex") {
      await Promise.all([
        import("@fontsource/ibm-plex-sans/400.css"),
        import("@fontsource/ibm-plex-sans/500.css"),
        import("@fontsource/ibm-plex-sans/600.css"),
      ]);
    }
    // "absans" is bundled via @font-face in styles.css; "system" needs no download.
  } catch {
    /* ignore font-load failures */
  }
}

export function AppearanceApplier() {
  const dark = useThemeStore((s) => s.dark);
  const font = useThemeStore((s) => s.font);

  useEffect(() => {
    if (typeof document === "undefined") return;
    document.documentElement.classList.toggle("dark", dark);
  }, [dark]);

  useEffect(() => {
    if (typeof document === "undefined") return;
    const opt = FONT_OPTIONS.find((o) => o.key === font) ?? FONT_OPTIONS[0];
    void loadFont(font);
    document.documentElement.style.setProperty("--font-sans", opt.stack);
  }, [font]);

  return null;
}
