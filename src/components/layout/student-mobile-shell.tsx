import { useRef, type ReactNode } from "react";
import { useRouterState } from "@tanstack/react-router";
import { AnimatePresence, motion } from "framer-motion";
import { StudentMobileTopbar } from "./student-mobile-topbar";
import { StudentBottomNav } from "./student-bottom-nav";

// Ordered tab list — used to decide slide direction (forward vs backward).
const TAB_ORDER = [
  "/student",
  "/student/documents",
  "/student/upload",
  "/student/notifications",
  "/student/profile",
];

const ROOT = "/student";

function tabIndex(pathname: string) {
  // Match deepest tab prefix; root needs exact match.
  let best = -1;
  let bestLen = -1;
  for (let i = 0; i < TAB_ORDER.length; i++) {
    const t = TAB_ORDER[i];
    const match = t === ROOT ? pathname === ROOT : pathname.startsWith(t);
    if (match && t.length > bestLen) { best = i; bestLen = t.length; }
  }
  return best;
}

/** Mobile-app style shell with directional page transitions. */
export function StudentMobileShell({ children }: { children: ReactNode }) {
  const pathname = useRouterState({ select: (s) => s.location.pathname });
  const prevPath = useRef(pathname);

  // Decide animation direction: forward (right→left) when moving deeper / next tab,
  // backward (left→right) when returning to root or previous tab.
  const prev = prevPath.current;
  const fromIdx = tabIndex(prev);
  const toIdx = tabIndex(pathname);
  let direction: 1 | -1 = 1;
  if (pathname === ROOT && prev !== ROOT) direction = -1;
  else if (fromIdx !== -1 && toIdx !== -1 && toIdx < fromIdx) direction = -1;
  prevPath.current = pathname;

  return (
    <div className="flex flex-col min-h-screen bg-bg lg:hidden">
      <StudentMobileTopbar />
      <main
        className="flex-1 px-4 pt-3 relative overflow-x-hidden"
        style={{ paddingBottom: "calc(env(safe-area-inset-bottom) + 5.5rem)" }}
      >
        <AnimatePresence mode="wait" initial={false} custom={direction}>
          <motion.div
            key={pathname}
            custom={direction}
            initial={{ opacity: 0, x: 24 * direction }}
            animate={{ opacity: 1, x: 0 }}
            exit={{ opacity: 0, x: -24 * direction }}
            transition={{ duration: 0.22, ease: [0.4, 0, 0.2, 1] }}
          >
            {children}
          </motion.div>
        </AnimatePresence>
      </main>
      <StudentBottomNav />
    </div>
  );
}
