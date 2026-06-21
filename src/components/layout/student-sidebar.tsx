import { Link, useRouterState } from "@tanstack/react-router";
import {
  IconDashboard, IconUserCircle, IconFileCheck, IconUpload, IconClipboardList,
  IconSpeakerphone, IconBell, IconShieldCheck, IconSettings,
} from "@tabler/icons-react";
import { cn } from "@/lib/utils";
import type { ComponentType } from "react";

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
        <div className="h-7 w-7 rounded-md bg-primary grid place-items-center text-white">
          <IconShieldCheck size={16} />
        </div>
        <div className="leading-tight">
          <p className="text-[13px] font-semibold">Student Portal</p>
          <p className="text-[10px] text-text-muted">UniFAST TES</p>
        </div>
      </div>
      <nav className="flex-1 overflow-y-auto py-3 px-2">
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
                    "flex items-center gap-2 px-2 h-8 rounded-md text-[13px] transition-colors",
                    active ? "bg-sidebar-active text-sidebar-active-text font-medium" : "text-text hover:bg-surface-muted",
                  )}
                >
                  <Icon size={16} className={active ? "text-sidebar-active-text" : "text-text-muted"} />
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
