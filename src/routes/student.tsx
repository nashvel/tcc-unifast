import { createFileRoute, Outlet, redirect } from "@tanstack/react-router";
import { StudentSidebar } from "@/components/layout/student-sidebar";
import { StudentMobileShell } from "@/components/layout/student-mobile-shell";
import { AppTopbar } from "@/components/layout/app-topbar";
import { useAuthStore } from "@/stores/authStore";
import { CommandPalette } from "@/components/ui/command-palette";
import { useIsMobile } from "@/hooks/use-mobile";

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

function StudentLayout() {
  const isMobile = useIsMobile();

  if (isMobile) {
    return (
      <StudentMobileShell>
        <Outlet />
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
    </div>
  );
}
