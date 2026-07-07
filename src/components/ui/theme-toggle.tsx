import { useEffect, useRef, useState } from "react";
import { IconMoon, IconSun } from "@tabler/icons-react";
import { useThemeStore } from "@/stores/themeStore";

export function ThemeToggle() {
  const dark = useThemeStore((s) => s.dark);
  const setDark = useThemeStore((s) => s.set);
  const btnRef = useRef<HTMLButtonElement>(null);
  const [ripple, setRipple] = useState<{
    x: number; y: number; r: number; color: string; id: number;
  } | null>(null);

  useEffect(() => {
    if (typeof document === "undefined") return;
    document.documentElement.classList.toggle("dark", dark);
  }, [dark]);

  function handleClick() {
    const btn = btnRef.current;
    if (!btn || typeof window === "undefined") { setDark(!dark); return; }
    const rect = btn.getBoundingClientRect();
    const x = rect.left + rect.width / 2;
    const y = rect.top + rect.height / 2;
    // Radius that fully covers the viewport from the click point.
    const r = Math.hypot(
      Math.max(x, window.innerWidth - x),
      Math.max(y, window.innerHeight - y),
    );
    // Overlay uses the incoming theme's background so it "floods" the screen.
    const nextDark = !dark;
    const styles = getComputedStyle(document.documentElement);
    // Read the CSS var value that WILL be after theme swap: peek at both.
    // Trick: temporarily toggle .dark class, read --bg, revert.
    const root = document.documentElement;
    root.classList.toggle("dark", nextDark);
    const color = styles.getPropertyValue("--bg").trim() || (nextDark ? "#0e1418" : "#f4f6f8");
    root.classList.toggle("dark", dark); // revert; effect below will re-apply
    setRipple({ x, y, r, color, id: Date.now() });
    // Swap theme on next frame so the wave visually leads the change.
    requestAnimationFrame(() => setDark(nextDark));
  }

  return (
    <>
      <button
        ref={btnRef}
        onClick={handleClick}
        title={dark ? "Switch to light mode" : "Switch to dark mode"}
        className="relative p-2 rounded-md hover:bg-surface-muted text-text-muted overflow-hidden"
      >
        <span
          key={dark ? "sun" : "moon"}
          className="inline-flex animate-[themeSpin_420ms_ease-out]"
        >
          {dark ? <IconSun size={18} /> : <IconMoon size={18} />}
        </span>
      </button>

      {ripple && (
        <div
          key={ripple.id}
          className="pointer-events-none fixed inset-0 z-[9999]"
          onAnimationEnd={() => setRipple(null)}
        >
          <span
            className="absolute rounded-full will-change-transform animate-[themeDrop_720ms_cubic-bezier(0.22,1,0.36,1)_forwards]"
            style={{
              left: ripple.x,
              top: ripple.y,
              width: ripple.r * 2.2,
              height: ripple.r * 2.2,
              marginLeft: -(ripple.r * 1.1),
              marginTop: -(ripple.r * 1.1),
              background: ripple.color,
              boxShadow: `0 0 60px 10px ${ripple.color}`,
            }}
          />
        </div>
      )}

      <style>{`
        @keyframes themeDrop {
          0%   { transform: scale(0);    opacity: 0.95; border-radius: 50%; }
          55%  { transform: scale(0.55); opacity: 1;    border-radius: 45% 55% 60% 40% / 55% 45% 55% 45%; }
          100% { transform: scale(1);    opacity: 0;    border-radius: 40% 60% 55% 45% / 50% 50% 50% 50%; }
        }
        @keyframes themeSpin {
          0%   { transform: rotate(-90deg) scale(0.6); opacity: 0; }
          100% { transform: rotate(0deg)   scale(1);   opacity: 1; }
        }
      `}</style>
    </>
  );
}
