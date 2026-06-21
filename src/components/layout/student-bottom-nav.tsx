import { Link, useRouterState } from "@tanstack/react-router";
import {
  IconDashboard, IconFileCheck, IconUpload, IconBell, IconUserCircle,
} from "@tabler/icons-react";
import { cn } from "@/lib/utils";

const items = [
  { to: "/student", label: "Home", icon: IconDashboard, exact: true },
  { to: "/student/documents", label: "Docs", icon: IconFileCheck },
  { to: "/student/upload", label: "Upload", icon: IconUpload },
  { to: "/student/notifications", label: "Alerts", icon: IconBell },
  { to: "/student/profile", label: "Profile", icon: IconUserCircle },
];

export function StudentBottomNav() {
  const pathname = useRouterState({ select: (s) => s.location.pathname });
  return (
    <nav
      className="lg:hidden fixed bottom-0 inset-x-0 z-30 border-t bg-surface/95 backdrop-blur supports-[backdrop-filter]:bg-surface/80"
      style={{ paddingBottom: "env(safe-area-inset-bottom)" }}
    >
      <ul className="grid grid-cols-5">
        {items.map((it) => {
          const active = it.exact ? pathname === it.to : pathname.startsWith(it.to);
          const Icon = it.icon;
          return (
            <li key={it.to}>
              <Link
                to={it.to}
                className={cn(
                  "flex flex-col items-center justify-center gap-0.5 py-2 text-[10px] transition-colors",
                  active ? "text-primary" : "text-text-muted hover:text-text",
                )}
              >
                <Icon size={20} />
                <span>{it.label}</span>
              </Link>
            </li>
          );
        })}
      </ul>
    </nav>
  );
}
