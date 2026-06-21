import { createFileRoute, Link } from "@tanstack/react-router";
import { PageHeader } from "@/components/ui/page-header";
import { StatusBadge, statusVariantFor, formatStatus } from "@/components/ui/status-badge";
import { requiredDocs, mockDocuments } from "@/data/mockDocuments";
import { IconUpload } from "@tabler/icons-react";

export const Route = createFileRoute("/student/documents")({
  component: () => {
    const myDocs = mockDocuments.filter((d) => d.granteeId === "g1");
    return (
      <div>
        <PageHeader title="Required Documents" description="Track the status of each required TES document." />
        <ul className="space-y-2">
          {requiredDocs.map((req) => {
            const d = myDocs.find((x) => x.type === req);
            return (
              <li key={req} className="rounded-lg border bg-surface p-3 flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium">{req}</p>
                  <p className="text-xs text-text-muted mt-0.5">{d ? `Last updated ${d.uploadedAt}` : "Not yet submitted"}</p>
                </div>
                <div className="flex items-center gap-3">
                  {d ? <StatusBadge variant={statusVariantFor(d.status)}>{formatStatus(d.status)}</StatusBadge> : <StatusBadge variant="neutral">Pending</StatusBadge>}
                  <Link to="/student/upload" className="inline-flex items-center gap-1 text-xs text-primary hover:underline">
                    <IconUpload size={12} /> Upload
                  </Link>
                </div>
              </li>
            );
          })}
        </ul>
      </div>
    );
  },
});
