import { createFileRoute, Link } from "@tanstack/react-router";
import { PageHeader } from "@/components/ui/page-header";
import { StatusBadge, statusVariantFor, formatStatus } from "@/components/ui/status-badge";
import { ListSkeleton } from "@/components/ui/skeletons";
import { useDocuments, type DocumentRow } from "@/hooks/queries";
import { requiredDocs } from "@/data/mockDocuments";
import {
  IconUpload,
  IconFileText,
  IconChevronRight,
  IconId,
  IconBook2,
  IconCameraSelfie,
} from "@tabler/icons-react";

import { cn } from "@/lib/utils";

type Status = "approved" | "rejected" | "suspicious" | "resubmission" | "pending" | "missing";

function classify(status?: string): Status {
  if (status === "approved") return "approved";
  if (status === "rejected" || status === "suspicious") return status as Status;
  if (status === "resubmission") return "resubmission";
  if (status === "pending") return "pending";
  return "missing";
}

const DOC_ICONS: Record<string, typeof IconFileText> = {
  "Student ID": IconId,
  "Course History": IconBook2,
  "Selfie with ID": IconCameraSelfie,
};

const STATUS_CLS: Record<Status, string> = {
  approved: "text-success bg-success-soft",
  rejected: "text-danger bg-danger-soft",
  suspicious: "text-danger bg-danger-soft",
  resubmission: "text-warning bg-warning-soft",
  pending: "text-info bg-info-soft",
  missing: "text-primary bg-primary-soft",
};

type Item = { req: string; doc?: DocumentRow };

const GROUPS: { key: Status[]; title: string; tone: string; hint: string }[] = [
  { key: ["rejected", "suspicious", "resubmission"], title: "Action needed", tone: "text-danger", hint: "Re-upload to keep your application moving." },
  { key: ["missing"], title: "Not submitted", tone: "text-text-muted", hint: "Upload these to complete your requirements." },
  { key: ["pending"], title: "Under review", tone: "text-info", hint: "Our team is verifying these submissions." },
  { key: ["approved"], title: "Approved", tone: "text-success", hint: "Verified and accepted." },
];

export const Route = createFileRoute("/student/documents")({
  component: () => {
    const { data: myDocs = [], isLoading } = useDocuments({ ownerOnly: true });

    const items: Item[] = requiredDocs.map((req) => ({
      req,
      doc: myDocs.find((x) => x.type === req),
    }));

    const total = items.length;
    const counts = {
      approved: items.filter((i) => classify(i.doc?.status) === "approved").length,
      pending: items.filter((i) => classify(i.doc?.status) === "pending").length,
      action: items.filter((i) => ["rejected", "suspicious", "resubmission"].includes(classify(i.doc?.status))).length,
      missing: items.filter((i) => classify(i.doc?.status) === "missing").length,
    };
    const pct = total ? Math.round((counts.approved / total) * 100) : 0;

    return (
      <div className="space-y-5 sm:space-y-6">
        <PageHeader
          title="Required Documents"
          description="Track the status of each required TES document."
        />

        {/* Progress card */}
        <section
          aria-label="Submission progress"
          className="rounded-2xl border bg-gradient-to-br from-surface to-surface-muted/40 p-4 sm:p-5 shadow-sm"
        >
          <div className="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-3">
            <div className="min-w-0">
              <p className="text-xs font-medium uppercase tracking-wide text-text-muted">
                Submission progress
              </p>
              <p className="mt-1 text-2xl sm:text-3xl font-semibold tabular-nums">
                {counts.approved}
                <span className="text-text-muted text-lg sm:text-xl font-normal"> / {total}</span>
              </p>
              <p className="text-xs text-text-muted">approved documents</p>
            </div>
            <div className="shrink-0 rounded-full bg-surface px-3 py-1.5 text-xs font-semibold tabular-nums text-primary border">
              {pct}%
            </div>
          </div>

          <div className="mt-4 h-2 rounded-full bg-surface-muted overflow-hidden">
            <div
              className="h-full bg-gradient-to-r from-primary to-primary-hover transition-all duration-500"
              style={{ width: `${pct}%` }}
            />
          </div>

          <div className="mt-4 grid grid-cols-4 gap-2 text-center">
            <Stat label="Approved" value={counts.approved} tone="text-success" />
            <Stat label="Review" value={counts.pending} tone="text-info" />
            <Stat label="Action" value={counts.action} tone="text-danger" />
            <Stat label="To do" value={counts.missing} tone="text-text-muted" />
          </div>
        </section>

        {isLoading ? (
          <ListSkeleton rows={requiredDocs.length} />
        ) : (
          <div className="space-y-5">
            {GROUPS.map((g) => {
              const groupItems = items.filter((i) => g.key.includes(classify(i.doc?.status)));
              if (groupItems.length === 0) return null;
              return (
                <section key={g.title} aria-label={g.title} className="space-y-2">
                  <div className="flex items-baseline justify-between px-1">
                    <h2 className={cn("text-xs font-semibold uppercase tracking-wide", g.tone)}>
                      {g.title}
                      <span className="ml-1.5 text-text-soft font-medium normal-case tracking-normal">
                        ({groupItems.length})
                      </span>
                    </h2>
                    <p className="hidden sm:block text-[11px] text-text-soft">{g.hint}</p>
                  </div>
                  <ul className="space-y-2">
                    {groupItems.map(({ req, doc: d }) => (
                      <li key={req}>
                        <DocRow req={req} doc={d} />
                      </li>
                    ))}
                  </ul>
                </section>
              );
            })}
          </div>
        )}
      </div>
    );
  },
});

function Stat({ label, value, tone }: { label: string; value: number; tone: string }) {
  return (
    <div className="rounded-lg bg-surface/70 border py-2">
      <p className={cn("text-base sm:text-lg font-semibold tabular-nums", tone)}>{value}</p>
      <p className="text-[10px] uppercase tracking-wide text-text-soft">{label}</p>
    </div>
  );
}

function DocRow({ req, doc: d }: Item) {
  const status = classify(d?.status);
  const { Icon, cls } = ICONS[status];
  return (
    <Link
      to="/student/upload"
      className="group grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-3 rounded-xl border bg-surface p-3 sm:p-3.5 hover:border-primary/40 hover:shadow-sm active:scale-[0.995] transition"
    >
      <div className={cn("h-10 w-10 shrink-0 rounded-xl grid place-items-center", cls)}>
        <Icon size={18} />
      </div>
      <div className="min-w-0">
        <p className="text-sm font-medium truncate">{req}</p>
        <p className="text-[11px] text-text-muted mt-0.5 truncate">
          {d ? `Updated ${new Date(d.uploaded_at).toLocaleDateString()}` : "Not yet submitted"}
        </p>
      </div>
      <div className="flex items-center gap-2 shrink-0">
        {d ? (
          <StatusBadge variant={statusVariantFor(d.status)}>{formatStatus(d.status)}</StatusBadge>
        ) : (
          <span className="inline-flex items-center gap-1 rounded-full bg-primary-soft px-2 py-0.5 text-[11px] font-medium text-primary">
            <IconUpload size={12} /> Upload
          </span>
        )}
        <IconChevronRight
          size={16}
          className="text-text-soft transition-transform group-hover:translate-x-0.5 group-hover:text-text-muted"
        />
      </div>
    </Link>
  );
}

// Keep import referenced for tree-shaking robustness across statuses.
void IconCircleDashed;
