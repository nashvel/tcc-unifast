import { Link, useRouterState } from "@tanstack/react-router";
import {
  IconDashboard, IconFileImport, IconFolders, IconUsersGroup, IconFileCheck,
  IconSchool, IconChecklist, IconSpeakerphone, IconReportAnalytics, IconHistory,
  IconUserCog, IconSettings, IconShieldCheck,
} from "@tabler/icons-react";
import { cn } from "@/lib/utils";
import type { ComponentType } from "react";

interface NavItem { to: string; label: string; icon: ComponentType<{ size?: number; className?: string }>; }

const sections: { label?: string; items: NavItem[] }[] = [
  { items: [{ to: "/app", label: "Dashboard", icon: IconDashboard }] },
  {
    label: "Operations",
    items: [
      { to: "/app/masterlist", label: "Masterlist", icon: IconFileImport },
      { to: "/app/batches", label: "Batches", icon: IconFolders },
      { to: "/app/grantees", label: "Grantees", icon: IconUsersGroup },
    ],
  },
  {
    label: "Validation",
    items: [
      { to: "/app/documents", label: "Document Validation", icon: IconFileCheck },
      { to: "/app/academic", label: "Academic Records", icon: IconSchool },
      { to: "/app/eligibility", label: "Eligibility", icon: IconChecklist },
    ],
  },
  {
    label: "Communication",
    items: [
      { to: "/app/announcements", label: "Announcements", icon: IconSpeakerphone },
      { to: "/app/reports", label: "Reports", icon: IconReportAnalytics },
    ],
  },
  {
    label: "Administration",
    items: [
      { to: "/app/audit", label: "Audit Trail", icon: IconHistory },
      { to: "/app/security", label: "Security Findings", icon: IconShieldCheck },
      { to: "/app/users", label: "Users & Roles", icon: IconUserCog },
      { to: "/app/settings", label: "Settings", icon: IconSettings },
    ],
  },
];

export function AppSidebar({ onNavigate }: { onNavigate?: () => void }) {
  const pathname = useRouterState({ select: (s) => s.location.pathname });

  return (
    <aside className="h-full w-60 shrink-0 border-r bg-sidebar-bg flex flex-col">
      <div className="h-14 flex items-center gap-2 px-4 border-b">
        <div className="h-7 w-7 rounded-md bg-primary grid place-items-center text-white">
          <IconShieldCheck size={16} />
        </div>
        <div className="leading-tight">
          <p className="text-[13px] font-semibold">UniFAST TES</p>
          <p className="text-[10px] text-text-muted">Grantee Management</p>
        </div>
      </div>
      <nav className="flex-1 overflow-y-auto py-3">
        {sections.map((sec, i) => (
          <div key={i} className="px-2 mb-3">
            {sec.label && <p className="px-2 mb-1 text-[10px] uppercase tracking-wider font-semibold text-text-soft">{sec.label}</p>}
            <ul className="space-y-0.5">
              {sec.items.map((it) => {
                const active = it.to === "/app" ? pathname === "/app" : pathname.startsWith(it.to);
                const Icon = it.icon;
                return (
                  <li key={it.to}>
                    <Link
                      to={it.to}
                      onClick={onNavigate}
                      className={cn(
                        "flex items-center gap-2 px-2 h-8 rounded-md text-[13px] transition-colors",
                        active
                          ? "bg-sidebar-active text-sidebar-active-text font-medium"
                          : "text-text hover:bg-surface-muted",
                      )}
                    >
                      <Icon size={16} className={active ? "text-sidebar-active-text" : "text-text-muted"} />
                      <span className="truncate">{it.label}</span>
                    </Link>
                  </li>
                );
              })}
            </ul>
          </div>
        ))}
      </nav>
    </aside>
  );
}
