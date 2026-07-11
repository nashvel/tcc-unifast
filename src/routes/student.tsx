import { createFileRoute, Outlet, redirect } from "@tanstack/react-router";
import { useEffect, useState } from "react";
import { StudentSidebar } from "@/components/layout/student-sidebar";
import { StudentMobileShell } from "@/components/layout/student-mobile-shell";
import { AppTopbar } from "@/components/layout/app-topbar";
import { useAuthStore } from "@/stores/authStore";
import { CommandPalette } from "@/components/ui/command-palette";
import { useIsMobile } from "@/hooks/use-mobile";
import { OnboardingScan } from "@/components/student/onboarding-scan";
import { PageTransition } from "@/components/page-transition";
import { AppBreadcrumbs } from "@/components/layout/app-breadcrumbs";

const SKIP_KEY = "unifast.onboarding.skipped";
const DONE_KEY = "unifast.mock.onboarding_completed_at";

export const Route = createFileRoute("/student")({
  ssr: false,
  beforeLoad: () => {
    const s = useAuthStore.getState();
    if (!s.ready) return;
    if (!s.userId || s.role !== "student") {
      throw redirect({ to: "/login" });
    }
  },
  component: StudentLayout,
});

function useOnboardingPrompt() {
  const userId = useAuthStore((s) => s.userId);
  const [show, setShow] = useState(false);

  useEffect(() => {
    if (!userId) return;
    if (sessionStorage.getItem(SKIP_KEY) === "1") return;
    if (!localStorage.getItem(DONE_KEY)) setShow(true);
  }, [userId]);

  return {
    show,
    skip: () => { sessionStorage.setItem(SKIP_KEY, "1"); setShow(false); },
    close: () => { sessionStorage.setItem(SKIP_KEY, "1"); setShow(false); },
    complete: () => { sessionStorage.removeItem(SKIP_KEY); setShow(false); },
  };
}

function StudentLayout() {
  const isMobile = useIsMobile();
  const onboarding = useOnboardingPrompt();

  const overlay = onboarding.show ? (
    <OnboardingScan onClose={onboarding.close} onSkip={onboarding.skip} onComplete={onboarding.complete} />
  ) : null;

  if (isMobile) {
    return (
      <StudentMobileShell>
        <PageTransition><Outlet /></PageTransition>
        {overlay}
      </StudentMobileShell>
    );
  }

  return (
    <div className="fixed inset-0 overflow-hidden flex bg-bg">
      <div className="h-full shrink-0 overflow-hidden z-30">
        <StudentSidebar />
      </div>
      <div className="h-full flex-1 min-w-0 flex flex-col overflow-hidden">
        <AppTopbar onToggleSidebar={() => {}} />
        <main className="flex-1 min-h-0 overflow-y-auto">
          <div className="p-6 max-w-[1200px] w-full mx-auto">
          <AppBreadcrumbs />
          <PageTransition><Outlet /></PageTransition>
        </main>
      </div>
      <CommandPalette />
      {overlay}
    </div>
  );
}
