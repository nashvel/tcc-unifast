import { useNavigate, useRouterState } from "@tanstack/react-router";
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
  if (/^[0-9a-f-]{6,}$/i.test(seg) || /^\d+$/.test(seg)) return "Detail";
  return seg.replace(/[-_]/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());
}

export function AppBreadcrumbs() {
  const pathname = useRouterState({ select: (s) => s.location.pathname });
  const navigate = useNavigate();
  const segments = pathname.split("/").filter(Boolean);
  if (segments.length === 0) return null;

  const rootHref = "/" + segments[0];
  const crumbs = segments.slice(1).map((seg, i) => ({
    label: label(seg),
    href: "/" + segments.slice(0, i + 2).join("/"),
  }));

  return (
    <nav
      aria-label="Breadcrumb"
      className="mb-4 inline-flex max-w-full items-center gap-0.5 rounded-full border border-border/60 bg-surface/60 px-2.5 py-1 text-xs text-text-muted backdrop-blur-sm shadow-sm overflow-x-auto"
    >
      <button
        type="button"
        onClick={() => navigate({ to: rootHref })}
        aria-label="Home"
        className="flex items-center justify-center h-6 w-6 rounded-full text-text-muted hover:text-text hover:bg-surface-hover transition-colors shrink-0"
      >
        <IconHome size={13} />
      </button>
      {crumbs.map((c, i) => {
        const isLast = i === crumbs.length - 1;
        return (
          <span key={c.href} className="flex items-center gap-0.5 shrink-0">
            <IconChevronRight size={12} className="text-text-soft/60 mx-0.5" />
            {isLast ? (
              <span
                aria-current="page"
                className="px-2 py-0.5 rounded-full bg-primary/10 text-primary font-medium truncate max-w-[220px]"
              >
                {c.label}
              </span>
            ) : (
              <button
                type="button"
                onClick={() => navigate({ to: c.href })}
                className="px-2 py-0.5 rounded-full hover:bg-surface-hover hover:text-text transition-colors truncate max-w-[160px]"
              >
                {c.label}
              </button>
            )}
          </span>
        );
      })}
    </nav>
  );
}
