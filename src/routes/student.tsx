import { createFileRoute, Outlet, redirect } from "@tanstack/react-router";
import { StudentSidebar } from "@/components/layout/student-sidebar";
import { StudentMobileShell } from "@/components/layout/student-mobile-shell";
import { AppTopbar } from "@/components/layout/app-topbar";
import { useAuthStore } from "@/stores/authStore";
import { CommandPalette } from "@/components/ui/command-palette";

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
  return (
    <>
      {/* Desktop shell (≥ lg) — unchanged sidebar + topbar */}
      <div className="hidden lg:flex min-h-screen bg-bg">
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

      {/* Mobile shell (< lg) — app-style topbar + bottom nav, no sidebar */}
      <StudentMobileShell>
        <Outlet />
      </StudentMobileShell>
    </>
  );
}
