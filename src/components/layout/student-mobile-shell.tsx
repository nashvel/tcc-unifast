import type { ReactNode } from "react";
import { StudentMobileTopbar } from "./student-mobile-topbar";
import { StudentBottomNav } from "./student-bottom-nav";

/** Mobile-app style shell for the student portal. No sidebar, sticky topbar, fixed bottom tab bar. */
export function StudentMobileShell({ children }: { children: ReactNode }) {
  return (
    <div className="flex flex-col min-h-screen bg-bg lg:hidden">
      <StudentMobileTopbar />
      <main
        className="flex-1 px-4 pt-3 pb-24"
        style={{ paddingBottom: "calc(env(safe-area-inset-bottom) + 5.5rem)" }}
      >
        {children}
      </main>
      <StudentBottomNav />
    </div>
  );
}
