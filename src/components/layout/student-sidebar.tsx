import { Link, useRouterState } from "@tanstack/react-router";
import {
  IconDashboard, IconUserCircle, IconFileCheck, IconUpload, IconClipboardList,
  IconSpeakerphone, IconBell, IconSettings,
} from "@tabler/icons-react";
import { cn } from "@/lib/utils";
import type { ComponentType } from "react";
import { branding } from "@/config/branding";

const items: { to: string; label: string; icon: ComponentType<{ size?: number; className?: string }> }[] = [
  { to: "/student", label: "Dashboard", icon: IconDashboard },
  { to: "/student/profile", label: "Profile", icon: IconUserCircle },
  { to: "/student/documents", label: "Required Documents", icon: IconFileCheck },
  { to: "/student/upload", label: "Upload Requirements", icon: IconUpload },
  { to: "/student/submissions", label: "Submission Status", icon: IconClipboardList },
  { to: "/student/announcements", label: "Announcements", icon: IconSpeakerphone },
  { to: "/student/notifications", label: "Notifications", icon: IconBell },
  { to: "/student/settings", label: "Settings", icon: IconSettings },
];

export function StudentSidebar({ onNavigate }: { onNavigate?: () => void }) {
  const pathname = useRouterState({ select: (s) => s.location.pathname });
  return (
    <aside className="h-full w-60 shrink-0 border-r bg-sidebar-bg flex flex-col">
      <div className="h-14 flex items-center gap-2 px-4 border-b">
        <img src={systemLogo} alt="Tagoloan Community College" className="h-9 w-9 object-contain" />
        <div className="leading-tight">
          <p className="text-sm font-semibold text-sidebar-text">Student Portal</p>
          <p className="text-2xs text-sidebar-text-muted">UniFAST TES</p>
        </div>
      </div>
      <nav className="flex-1 min-h-0 overflow-y-auto py-3 px-2 [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
        <ul className="space-y-0.5">
          {items.map((it) => {
            const active = it.to === "/student" ? pathname === "/student" : pathname.startsWith(it.to);
            const Icon = it.icon;
            return (
              <li key={it.to}>
                <Link
                  to={it.to}
                  onClick={onNavigate}
                  className={cn(
                    "flex items-center gap-2 px-2 h-8 rounded-md text-sm transition-colors",
                    active ? "bg-sidebar-active text-sidebar-active-text font-medium" : "text-sidebar-text hover:bg-sidebar-hover",
                  )}
                >
                  <Icon size={16} className={active ? "text-sidebar-active-text" : "text-sidebar-text-muted"} />
                  <span className="truncate">{it.label}</span>
                </Link>
              </li>
            );
          })}
        </ul>
      </nav>
    </aside>
  );
}
