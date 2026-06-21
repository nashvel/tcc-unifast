import { createFileRoute, Outlet, redirect } from "@tanstack/react-router";
import { useState } from "react";
import { StudentSidebar } from "@/components/layout/student-sidebar";
import { StudentBottomNav } from "@/components/layout/student-bottom-nav";
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
  const [open, setOpen] = useState(false);
  return (
    <div className="min-h-screen flex bg-bg">
      <div className="hidden lg:block sticky top-0 h-screen">
        <StudentSidebar />
      </div>
      {open && (
        <div className="lg:hidden fixed inset-0 z-40 flex">
          <div className="absolute inset-0 bg-black/30" onClick={() => setOpen(false)} />
          <div className="relative h-full">
            <StudentSidebar onNavigate={() => setOpen(false)} />
          </div>
        </div>
      )}
      <div className="flex-1 min-w-0 flex flex-col">
        <AppTopbar onToggleSidebar={() => setOpen((v) => !v)} />
        <main className="flex-1 p-4 sm:p-6 pb-24 lg:pb-6 max-w-[1200px] w-full mx-auto">
          <Outlet />
        </main>
      </div>
      <StudentBottomNav />
      <CommandPalette />
    </div>
  );
}
