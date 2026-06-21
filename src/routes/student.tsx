import { createFileRoute, Outlet, redirect } from "@tanstack/react-router";
import { useEffect, useState } from "react";
import { StudentSidebar } from "@/components/layout/student-sidebar";
import { StudentMobileShell } from "@/components/layout/student-mobile-shell";
import { AppTopbar } from "@/components/layout/app-topbar";
import { useAuthStore } from "@/stores/authStore";
import { CommandPalette } from "@/components/ui/command-palette";
import { useIsMobile } from "@/hooks/use-mobile";
import { OnboardingScan } from "@/components/student/onboarding-scan";
import { supabase } from "@/integrations/supabase/client";

const SKIP_KEY = "unifast.onboarding.skipped";

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
    // Skipped within this session → don't show again until next sign-in.
    if (sessionStorage.getItem(SKIP_KEY) === "1") return;
    let cancelled = false;
    (async () => {
      const { data } = await supabase
        .from("profiles")
        .select("onboarding_completed_at")
        .eq("id", userId)
        .maybeSingle();
      if (cancelled) return;
      if (!data?.onboarding_completed_at) setShow(true);
    })();
    return () => { cancelled = true; };
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
        <Outlet />
        {overlay}
      </StudentMobileShell>
    );
  }

  return (
    <div className="min-h-screen flex bg-bg">
      <div className="sticky top-0 h-screen">
        <StudentSidebar />
      </div>
      <div className="flex-1 min-w-0 flex flex-col">
        <AppTopbar onToggleSidebar={() => {}} />
        <main className="flex-1 p-6 max-w-[1200px] w-full mx-auto">
          <Outlet />
        </main>
      </div>
      <CommandPalette />
      {overlay}
    </div>
  );
}
