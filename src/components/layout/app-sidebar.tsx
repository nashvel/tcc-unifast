import { Link, useRouterState } from "@tanstack/react-router";
import {
  IconDashboard, IconFileImport, IconFolders, IconUsersGroup, IconFileCheck,
  IconSchool, IconChecklist, IconSpeakerphone, IconReportAnalytics, IconHistory,
  IconUserCog, IconSettings, IconShieldCheck, IconLifebuoy, IconFolder,
} from "@tabler/icons-react";
import { cn } from "@/lib/utils";
import type { ComponentType } from "react";
import { branding } from "@/config/branding";
import { BrandLogo } from "@/components/brand/brand-logo";
import { useAuthStore } from "@/stores/authStore";

interface NavItem { to: string; label: string; icon: ComponentType<{ size?: number; className?: string }>; }
type Section = { label?: string; items: NavItem[] };

// Admin: oversight only — monitoring, announcements, audit, security,
// users, settings (SMTP/legal/consent live under Settings).
const adminSections: Section[] = [
  { items: [{ to: "/app", label: "Dashboard", icon: IconDashboard }] },
  {
    label: "Communication",
    items: [
      { to: "/app/announcements", label: "Announcements", icon: IconSpeakerphone },
      { to: "/app/reports", label: "Monitoring & Reports", icon: IconReportAnalytics },
      { to: "/app/support", label: "Support Tickets", icon: IconLifebuoy },
    ],
  },
  {
    label: "Administration",
    items: [
      { to: "/app/audit", label: "Audit Trail", icon: IconHistory },
      { to: "/app/security", label: "Security Findings", icon: IconShieldCheck },
      { to: "/app/security/memory", label: "Security Memory", icon: IconShieldCheck },
      { to: "/app/users", label: "Users & Roles", icon: IconUserCog },
      { to: "/app/settings", label: "Settings", icon: IconSettings },
    ],
  },
];

// Staff/head: day-to-day operations and validation work.
const staffSections: Section[] = [
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
      { to: "/app/files", label: "File Manager", icon: IconFolder },
      { to: "/app/academic", label: "Academic Records", icon: IconSchool },
      { to: "/app/eligibility", label: "Eligibility", icon: IconChecklist },
    ],
  },
  {
    label: "Communication",
    items: [
      { to: "/app/announcements", label: "Announcements", icon: IconSpeakerphone },
      { to: "/app/reports", label: "Reports", icon: IconReportAnalytics },
      { to: "/app/support", label: "Support Tickets", icon: IconLifebuoy },
    ],
  },
  {
    label: "Administration",
    items: [
      { to: "/app/audit", label: "Audit Trail", icon: IconHistory },
      { to: "/app/settings", label: "Settings", icon: IconSettings },
    ],
  },
];

export function AppSidebar({ onNavigate }: { onNavigate?: () => void }) {
  const pathname = useRouterState({ select: (s) => s.location.pathname });

  return (
    <aside className="h-full w-60 shrink-0 border-r bg-sidebar-bg flex flex-col">
      <div className="h-14 flex items-center gap-2 px-4 border-b">
        <BrandLogo size="md" />
        <div className="leading-tight">
          <p className="text-sm font-semibold text-sidebar-text">{branding.systemName}</p>
          <p className="text-2xs text-sidebar-text-muted">{branding.systemTagline}</p>
        </div>
      </div>
      <nav className="flex-1 overflow-y-auto py-3">
        {sections.map((sec, i) => (
          <div key={i} className="px-2 mb-3">
            {sec.label && <p className="px-2 mb-1 text-2xs uppercase tracking-wider font-semibold text-sidebar-text-muted">{sec.label}</p>}
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
                        "flex items-center gap-2 px-2 h-8 rounded-md text-sm transition-colors",
                        active
                          ? "bg-sidebar-active text-sidebar-active-text font-medium"
                          : "text-sidebar-text hover:bg-sidebar-hover",
                      )}
                    >
                      <Icon size={16} className={active ? "text-sidebar-active-text" : "text-sidebar-text-muted"} />
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
