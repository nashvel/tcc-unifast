import { useNavigate, useRouterState } from "@tanstack/react-router";
import { IconChevronRight, IconDots, IconHome, IconSlash } from "@tabler/icons-react";
import { useIsMobile } from "@/hooks/use-mobile";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";

export type BreadcrumbSeparator = "chevron" | "dot" | "slash";

interface AppBreadcrumbsProps {
  separator?: BreadcrumbSeparator;
}

function SeparatorIcon({ kind }: { kind: BreadcrumbSeparator }) {
  if (kind === "dot") {
    return (
      <span
        aria-hidden="true"
        className="mx-1 inline-block h-1 w-1 rounded-full bg-text-soft/60"
      />
    );
  }
  if (kind === "slash") {
    return <IconSlash size={12} className="text-text-soft/60 mx-0.5" aria-hidden="true" />;
  }
  return <IconChevronRight size={12} className="text-text-soft/60 mx-0.5" aria-hidden="true" />;
}

// Derive a human label from a route id segment ("app.users.$id" -> "Users").
function titleFromSegment(seg: string): string {
  if (!seg) return "";
  // dynamic params like "$id" or optional "{-$slug}" — treat as detail
  if (seg.startsWith("$") || seg.includes("$")) return "Detail";
  if (/^[0-9a-f-]{6,}$/i.test(seg) || /^\d+$/.test(seg)) return "Detail";
  return seg.replace(/[-_]/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());
}

function titleFromMeta(meta: unknown): string | null {
  if (!Array.isArray(meta)) return null;
  for (const m of meta) {
    if (m && typeof m === "object" && "title" in m && typeof (m as { title: unknown }).title === "string") {
      // strip trailing " — Site" style suffixes
      return (m as { title: string }).title.split(/\s[—–|-]\s/)[0].trim();
    }
  }
  return null;
}

function labelForMatch(match: {
  routeId: string;
  params: Record<string, string>;
  meta?: unknown;
  staticData?: Record<string, unknown>;
}): string {
  // 1. Explicit override via route staticData: { breadcrumb: "Label" }
  const staticLabel = match.staticData?.breadcrumb;
  if (typeof staticLabel === "string" && staticLabel.length > 0) return staticLabel;

  // 2. Route head() meta title
  const metaTitle = titleFromMeta(match.meta);
  if (metaTitle) return metaTitle;

  // 3. Last segment of the route id, with param substitution
  const segments = match.routeId.split("/").filter(Boolean);
  const last = segments[segments.length - 1] ?? "";

  // dynamic segment → use the param value if it's short/human, else "Detail"
  if (last.startsWith("$")) {
    const paramName = last.slice(1).replace(/[{}-]/g, "");
    const raw = match.params[paramName];
    if (raw && raw.length <= 32 && !/^[0-9a-f-]{6,}$/i.test(raw) && !/^\d+$/.test(raw)) {
      return titleFromSegment(raw);
    }
    return "Detail";
  }

  return titleFromSegment(last);
}

export function AppBreadcrumbs({ separator = "chevron" }: AppBreadcrumbsProps = {}) {
  const matches = useRouterState({ select: (s) => s.matches });
  const navigate = useNavigate();
  const isMobile = useIsMobile();

  // Skip the __root match and any pathless layout matches (routeId starts with "/_"
  // or produces an empty label). Drop duplicates that share a pathname with their
  // parent (e.g. "app.index" resolves to the same URL as "app").
  const crumbs: { label: string; href: string }[] = [];
  const seen = new Set<string>();
  for (const m of matches) {
    if (m.routeId === "__root__") continue;
    const label = labelForMatch(m as never);
    if (!label) continue;
    if (seen.has(m.pathname)) continue;
    seen.add(m.pathname);
    crumbs.push({ label, href: m.pathname });
  }
  if (crumbs.length === 0) return null;

  const [root, ...rest] = crumbs;

  return (
    <nav
      aria-label="Breadcrumb"
      className="mb-4 inline-flex max-w-full items-center gap-0.5 rounded-full border border-border/60 bg-surface/60 px-2.5 py-1 text-xs text-text-muted backdrop-blur-sm shadow-sm overflow-x-auto"
    >
      <button
        type="button"
        onClick={() => navigate({ to: root.href })}
        aria-label={root.label}
        title={root.label}
        className="flex items-center justify-center h-6 w-6 rounded-full text-text-muted hover:text-text hover:bg-surface-hover transition-colors shrink-0"
      >
        <IconHome size={13} />
      </button>
      {rest.map((c, i) => {
        const isLast = i === rest.length - 1;
        return (
          <span key={c.href} className="flex items-center gap-0.5 shrink-0">
            <SeparatorIcon kind={separator} />
            {isLast ? (
              <span
                aria-current="page"
                className="px-2 py-0.5 rounded-full bg-primary/10 text-primary font-medium truncate max-w-[240px]"
              >
                {c.label}
              </span>
            ) : (
              <button
                type="button"
                onClick={() => navigate({ to: c.href })}
                className="px-2 py-0.5 rounded-full hover:bg-surface-hover hover:text-text transition-colors truncate max-w-[180px]"
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
