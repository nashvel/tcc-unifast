import { Link, useRouterState } from "@tanstack/react-router";
import {
  IconDashboard,
  IconFileCheck,
  IconUpload,
  IconBell,
  IconUserCircle,
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
      aria-label="Primary"
      className="lg:hidden fixed inset-x-0 bottom-0 z-30 px-3 pt-2 pointer-events-none"
      style={{ paddingBottom: "calc(env(safe-area-inset-bottom) + 0.5rem)" }}
    >
      <ul className="pointer-events-auto mx-auto flex max-w-md items-center justify-between gap-1.5 rounded-full border bg-surface/95 backdrop-blur supports-[backdrop-filter]:bg-surface/80 px-2 py-2 shadow-[0_10px_30px_-12px_rgba(0,0,0,0.25)]">
        {items.map((it) => {
          const active = it.exact
            ? pathname === it.to
            : pathname.startsWith(it.to);
          const Icon = it.icon;
          return (
            <li key={it.to} className={cn(active ? "flex-1" : "flex-none")}>
              <Link
                to={it.to}
                aria-label={it.label}
                aria-current={active ? "page" : undefined}
                className={cn(
                  "group flex items-center justify-center gap-2 rounded-full transition-all duration-300 ease-out outline-none focus-visible:ring-2 focus-visible:ring-primary",
                  active
                    ? "h-11 px-4 bg-primary text-white shadow-md"
                    : "h-11 w-11 bg-surface text-text-muted border hover:text-text hover:bg-surface-muted active:scale-95",
                )}
              >
                <Icon
                  size={20}
                  stroke={active ? 2.2 : 1.8}
                  className="shrink-0"
                />
                {active && (
                  <span className="text-sm font-semibold whitespace-nowrap">
                    {it.label}
                  </span>
                )}
              </Link>
            </li>
          );
        })}
      </ul>
    </nav>
  );
}
