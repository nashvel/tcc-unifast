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
  const [announcement, setAnnouncement] = useState("");

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
    const color = styles.getPropertyValue("--bg").trim() || (nextDark ? "#17110f" : "#faf6ef");
    root.classList.toggle("dark", dark); // revert; effect below will re-apply
    setRipple({ x, y, r, color, id: Date.now() });
    // Swap theme at the wave's peak coverage so light↔dark feels symmetric.
    window.setTimeout(() => setDark(nextDark), 260);
  }

  return (
    <>
      <button
        ref={btnRef}
        type="button"
        role="switch"
        aria-checked={dark}
        aria-label={dark ? "Dark mode on, activate to switch to light mode" : "Light mode on, activate to switch to dark mode"}
        aria-live="polite"
        aria-busy={!!ripple}
        disabled={!!ripple}
        title={dark ? "Switch to light mode" : "Switch to dark mode"}
        onClick={handleClick}
        className="relative p-2 rounded-md text-text-muted overflow-hidden hover:bg-surface-muted focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface disabled:cursor-wait disabled:opacity-70"
      >
        <span
          aria-hidden="true"
          key={dark ? "sun" : "moon"}
          className={`inline-flex motion-reduce:animate-none ${ripple ? "animate-[themePulse_640ms_ease-in-out_infinite]" : "animate-[themeSpin_420ms_ease-out]"}`}
        >
          {dark ? <IconSun size={18} /> : <IconMoon size={18} />}
        </span>
        {ripple && (
          <span aria-hidden="true" className="pointer-events-none absolute inset-0 rounded-md ring-1 ring-primary/40 motion-reduce:animate-none animate-[themePulse_640ms_ease-in-out_infinite]" />
        )}
        <span className="sr-only">
          {ripple ? "Switching theme…" : dark ? "Dark mode active" : "Light mode active"}
        </span>
      </button>

      <span role="status" aria-live="polite" aria-atomic="true" className="sr-only">
        {announcement}
      </span>

      {ripple && (
        <div
          key={ripple.id}
          className="pointer-events-none fixed inset-0 z-[9999]"
          onAnimationEnd={() => {
            setRipple(null);
            // Announce AFTER the wave finishes so the mode change is confirmed.
            const nowDark = document.documentElement.classList.contains("dark");
            setAnnouncement(nowDark ? "Dark mode activated" : "Light mode activated");
          }}
        >
          <span
            className="absolute rounded-full will-change-transform animate-[themeDrop_640ms_cubic-bezier(0.65,0,0.35,1)_forwards]"
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
          0%   { transform: scale(0);   opacity: 1; border-radius: 50%; }
          40%  { transform: scale(0.5); opacity: 1; border-radius: 46% 54% 58% 42% / 54% 46% 54% 46%; }
          100% { transform: scale(1);   opacity: 0; border-radius: 42% 58% 54% 46% / 50% 50% 50% 50%; }
        }
        @keyframes themeSpin {
          0%   { transform: rotate(-90deg) scale(0.6); opacity: 0; }
          100% { transform: rotate(0deg)   scale(1);   opacity: 1; }
        }
        @keyframes themePulse {
          0%, 100% { opacity: 0.55; transform: scale(0.94); }
          50%      { opacity: 1;    transform: scale(1.06); }
        }
      `}</style>
    </>
  );
}
