import { createFileRoute, Link } from "@tanstack/react-router";
import { PageHeader } from "@/components/ui/page-header";
import { StatusBadge, statusVariantFor, formatStatus } from "@/components/ui/status-badge";
import { ListSkeleton } from "@/components/ui/skeletons";
import { useDocuments } from "@/hooks/queries";
import { requiredDocs } from "@/data/mockDocuments";
import {
  IconUpload,
  IconFileText,
  IconCheck,
  IconClock,
  IconAlertTriangle,
  IconChevronRight,
} from "@tabler/icons-react";
import { cn } from "@/lib/utils";

function iconFor(status?: string) {
  if (status === "approved") return { icon: IconCheck, cls: "text-success bg-success-soft" };
  if (status === "rejected" || status === "suspicious")
    return { icon: IconAlertTriangle, cls: "text-danger bg-danger-soft" };
  if (status === "resubmission")
    return { icon: IconAlertTriangle, cls: "text-warning bg-warning-soft" };
  if (status === "pending") return { icon: IconClock, cls: "text-info bg-info-soft" };
  return { icon: IconFileText, cls: "text-text-muted bg-surface-muted" };
}

export const Route = createFileRoute("/student/documents")({
  component: () => {
    const { data: myDocs = [], isLoading } = useDocuments({ ownerOnly: true });

    const items = requiredDocs.map((req) => ({
      req,
      doc: myDocs.find((x) => x.type === req),
    }));
    const approved = items.filter((i) => i.doc?.status === "approved").length;
    const total = items.length;
    const pct = total ? Math.round((approved / total) * 100) : 0;

    return (
      <div className="space-y-4">
        <PageHeader
          title="Required Documents"
          description="Track the status of each required TES document."
        />

        {/* Progress card */}
        <div className="rounded-xl border bg-surface p-4">
          <div className="flex items-baseline justify-between">
            <p className="text-sm font-medium">Submission progress</p>
            <p className="text-sm tabular-nums text-text-muted">
              <span className="text-text font-semibold">{approved}</span> / {total} approved
            </p>
          </div>
          <div className="mt-2 h-2 rounded-full bg-surface-muted overflow-hidden">
            <div
              className="h-full bg-primary transition-all duration-500"
              style={{ width: `${pct}%` }}
            />
          </div>
          <p className="mt-2 text-[11px] text-text-soft">{pct}% complete</p>
        </div>

        {isLoading ? (
          <ListSkeleton rows={requiredDocs.length} />
        ) : (
          <ul className="space-y-2">
            {items.map(({ req, doc: d }) => {
              const { icon: Icon, cls } = iconFor(d?.status);
              return (
                <li key={req}>
                  <Link
                    to="/student/upload"
                    className="group flex items-center gap-3 rounded-xl border bg-surface p-3 hover:border-primary/40 hover:bg-surface-muted/40 active:scale-[0.99] transition"
                  >
                    <div
                      className={cn(
                        "h-10 w-10 shrink-0 rounded-lg grid place-items-center",
                        cls,
                      )}
                    >
                      <Icon size={18} />
                    </div>
                    <div className="flex-1 min-w-0">
                      <p className="text-sm font-medium truncate">{req}</p>
                      <p className="text-[11px] text-text-muted mt-0.5 truncate">
                        {d
                          ? `Updated ${new Date(d.uploaded_at).toLocaleDateString()}`
                          : "Not yet submitted"}
                      </p>
                    </div>
                    <div className="flex items-center gap-2 shrink-0">
                      {d ? (
                        <StatusBadge variant={statusVariantFor(d.status)}>
                          {formatStatus(d.status)}
                        </StatusBadge>
                      ) : (
                        <span className="inline-flex items-center gap-1 text-[11px] font-medium text-primary">
                          <IconUpload size={12} /> Upload
                        </span>
                      )}
                      <IconChevronRight
                        size={16}
                        className="text-text-soft group-hover:text-text-muted"
                      />
                    </div>
                  </Link>
                </li>
              );
            })}
          </ul>
        )}
      </div>
    );
  },
});
