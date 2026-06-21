import { createFileRoute, Outlet, redirect } from "@tanstack/react-router";
import { useState } from "react";
import { AppSidebar } from "@/components/layout/app-sidebar";
import { AppTopbar } from "@/components/layout/app-topbar";
import { useAuthStore } from "@/stores/authStore";
import { CommandPalette } from "@/components/ui/command-palette";

export const Route = createFileRoute("/app")({
  ssr: false,
  beforeLoad: () => {
    const s = useAuthStore.getState();
    if (!s.ready) return; // hydrating
    if (!s.userId || s.role === "student") {
      throw redirect({ to: "/login" });
    }
  },
  component: AppLayout,
});

function AppLayout() {
  const [open, setOpen] = useState(false);
  return (
    <div className="min-h-screen flex bg-bg">
      <div className="hidden lg:block sticky top-0 h-screen">
        <AppSidebar />
      </div>
      {open && (
        <div className="lg:hidden fixed inset-0 z-40 flex">
          <div className="absolute inset-0 bg-black/30" onClick={() => setOpen(false)} />
          <div className="relative h-full">
            <AppSidebar onNavigate={() => setOpen(false)} />
          </div>
        </div>
      )}
      <div className="flex-1 min-w-0 flex flex-col">
        <AppTopbar onToggleSidebar={() => setOpen((v) => !v)} />
        <main className="flex-1 p-4 sm:p-6 max-w-[1400px] w-full mx-auto">
          <Outlet />
        </main>
      </div>
      <CommandPalette />
    </div>
  );
}
