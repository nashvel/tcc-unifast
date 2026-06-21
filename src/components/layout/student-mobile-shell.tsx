import { useEffect, useRef, useState, type ReactNode } from "react";
import { useRouterState } from "@tanstack/react-router";
import { AnimatePresence, motion, useReducedMotion } from "framer-motion";
import { StudentMobileTopbar } from "./student-mobile-topbar";
import { StudentBottomNav } from "./student-bottom-nav";

const TAB_ORDER = [
  "/student",
  "/student/documents",
  "/student/upload",
  "/student/notifications",
  "/student/profile",
];

const ROOT = "/student";

const TITLES: Record<string, string> = {
  "/student": "Home",
  "/student/profile": "Profile",
  "/student/documents": "Required Documents",
  "/student/upload": "Upload",
  "/student/submissions": "My Submissions",
  "/student/announcements": "Announcements",
  "/student/notifications": "Notifications",
};

function tabIndex(pathname: string) {
  let best = -1;
  let bestLen = -1;
  for (let i = 0; i < TAB_ORDER.length; i++) {
    const t = TAB_ORDER[i];
    const match = t === ROOT ? pathname === ROOT : pathname.startsWith(t);
    if (match && t.length > bestLen) { best = i; bestLen = t.length; }
  }
  return best;
}

/** Mobile-app style shell with directional transitions, focus restoration, and SR announcements. */
export function StudentMobileShell({ children }: { children: ReactNode }) {
  const pathname = useRouterState({ select: (s) => s.location.pathname });
  const prevPath = useRef(pathname);
  const pageRef = useRef<HTMLDivElement>(null);
  const reduce = useReducedMotion();
  const [announce, setAnnounce] = useState("");

  const prev = prevPath.current;
  const fromIdx = tabIndex(prev);
  const toIdx = tabIndex(pathname);
  let direction: 1 | -1 = 1;
  if (pathname === ROOT && prev !== ROOT) direction = -1;
  else if (fromIdx !== -1 && toIdx !== -1 && toIdx < fromIdx) direction = -1;
  prevPath.current = pathname;

  // Announce the new page title to screen readers on every route change.
  useEffect(() => {
    const title = TITLES[pathname] ?? "Student Portal";
    // Force re-announcement even when the title text repeats.
    setAnnounce("");
    const id = window.setTimeout(() => setAnnounce(`${title} page loaded`), 50);
    return () => window.clearTimeout(id);
  }, [pathname]);

  // Move focus to the first heading/input of the new page after it animates in.
  function handleEntered() {
    const root = pageRef.current;
    if (!root) return;
    const target =
      root.querySelector<HTMLElement>("[data-autofocus]") ||
      root.querySelector<HTMLElement>("h1, h2") ||
      root;
    // Headings aren't focusable by default — make them programmatically focusable.
    if (!target.hasAttribute("tabindex")) target.setAttribute("tabindex", "-1");
    target.focus({ preventScroll: true });
    // Reset scroll to top so the new page starts fresh.
    window.scrollTo({ top: 0, behavior: "instant" as ScrollBehavior });
  }

  const distance = reduce ? 0 : 24;
  const duration = reduce ? 0 : 0.22;

  return (
    <div className="flex flex-col min-h-dvh bg-bg lg:hidden">
      <StudentMobileTopbar />
      <main
        id="student-main"
        className="flex-1 px-4 pt-3 relative overflow-x-hidden"
        style={{ paddingBottom: "calc(env(safe-area-inset-bottom) + 5.5rem)" }}
      >
        <AnimatePresence mode="wait" initial={false} custom={direction}>
          <motion.div
            ref={pageRef}
            key={pathname}
            custom={direction}
            tabIndex={-1}
            className="outline-none focus-visible:ring-2 focus-visible:ring-primary/30 rounded-md"
            initial={{ opacity: 0, x: distance * direction }}
            animate={{ opacity: 1, x: 0 }}
            exit={{ opacity: 0, x: -distance * direction }}
            transition={{ duration, ease: [0.4, 0, 0.2, 1] }}
            onAnimationComplete={(definition) => {
              // Only on the entering animation, not exit.
              if (typeof definition === "object" && definition && "x" in definition && definition.x === 0) {
                handleEntered();
              }
            }}
          >
            {children}
          </motion.div>
        </AnimatePresence>
      </main>

      {/* SR-only live region announces every route change */}
      <div
        role="status"
        aria-live="polite"
        aria-atomic="true"
        className="sr-only"
      >
        {announce}
      </div>

      <StudentBottomNav />
    </div>
  );
}
