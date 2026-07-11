import { Link, useRouterState } from "@tanstack/react-router";
import { IconChevronRight, IconHome } from "@tabler/icons-react";

// Human labels for known route segments. Unknown segments (ids, slugs) fall
// back to a title-cased version of the segment itself.
const LABELS: Record<string, string> = {
  app: "Dashboard",
  student: "Home",
  masterlist: "Masterlist",
  batches: "Batches",
  grantees: "Grantees",
  documents: "Document Validation",
  files: "File Manager",
  academic: "Academic Records",
  eligibility: "Eligibility",
  announcements: "Announcements",
  reports: "Reports",
  support: "Support Tickets",
  audit: "Audit Trail",
  security: "Security",
  memory: "Memory",
  users: "Users & Roles",
  permissions: "Permissions",
  settings: "Settings",
  appearance: "Appearance",
  "style-guide": "Style Guide",
  logs: "Logs",
  new: "New",
  edit: "Edit",
  generate: "Generate",
  preview: "Preview",
  profile: "Profile",
  notifications: "Notifications",
  submissions: "Submissions",
  upload: "Upload",
};

function label(seg: string) {
  if (LABELS[seg]) return LABELS[seg];
  // treat numeric / uuid-ish ids as "Detail"
  if (/^[0-9a-f-]{6,}$/i.test(seg) || /^\d+$/.test(seg)) return "Detail";
  return seg.replace(/[-_]/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());
}

export function AppBreadcrumbs() {
  const pathname = useRouterState({ select: (s) => s.location.pathname });
  const segments = pathname.split("/").filter(Boolean);
  if (segments.length === 0) return null;

  const crumbs = segments.map((seg, i) => ({
    label: label(seg),
    href: "/" + segments.slice(0, i + 1).join("/"),
  }));

  return (
    <nav
      aria-label="Breadcrumb"
      className="mb-3 flex items-center gap-1 text-xs text-text-muted overflow-x-auto"
    >
      <IconHome size={13} className="shrink-0" />
      {crumbs.map((c, i) => {
        const isLast = i === crumbs.length - 1;
        return (
          <span key={c.href} className="flex items-center gap-1 shrink-0">
            <IconChevronRight size={12} className="text-text-soft" />
            {isLast ? (
              <span className="font-medium text-text truncate max-w-[200px]">{c.label}</span>
            ) : (
              <Link
                to={c.href}
                className="hover:text-text hover:underline truncate max-w-[160px]"
              >
                {c.label}
              </Link>
            )}
          </span>
        );
      })}
    </nav>
  );
}
