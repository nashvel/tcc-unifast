import { createFileRoute, Link, useParams, useNavigate } from "@tanstack/react-router";
import { useState } from "react";
import { PageHeader } from "@/components/ui/page-header";
import { Btn } from "@/components/ui/btn";
import { StatusBadge, statusVariantFor, formatStatus } from "@/components/ui/status-badge";
import { useDocument, useUpdateDocumentStatus, type DocStatus } from "@/hooks/queries";
import { TextArea } from "@/components/ui/form-field";
import { IconArrowLeft, IconCheck, IconX, IconRefresh, IconShieldExclamation, IconFile, IconScan, IconCameraSelfie } from "@tabler/icons-react";

export const Route = createFileRoute("/app/documents/$id")({
  component: DocDetail,
});

function DocDetail() {
  const { id } = useParams({ from: "/app/documents/$id" });
  const navigate = useNavigate();
  const { data: doc, isLoading } = useDocument(id);
  const update = useUpdateDocumentStatus();
  const [remarks, setRemarks] = useState("");

  if (isLoading) return <div className="text-sm text-text-muted">Loading…</div>;
  if (!doc) return <div className="text-sm text-text-muted">Document not found.</div>;
  const tone = doc.risk_score >= 70 ? "danger" : doc.risk_score >= 40 ? "warning" : "success";

  function act(status: DocStatus) {
    update.mutate({ id: doc!.id, status, remarks }, { onSuccess: () => navigate({ to: "/app/documents" }) });
  }

  return (
    <div>
      <Link to="/app/documents" className="inline-flex items-center gap-1 text-xs text-text-muted hover:text-text mb-3">
        <IconArrowLeft size={13} /> Back to queue
      </Link>
      <PageHeader
        title={doc.type}
        description={`From ${doc.grantee_name} (${doc.student_number})`}
        actions={<StatusBadge variant={statusVariantFor(doc.status)}>{formatStatus(doc.status)}</StatusBadge>}
      />

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div className="lg:col-span-2 space-y-4">
          <Panel title="File Preview" icon={IconFile}>
            <div className="aspect-[4/3] rounded-md border bg-surface-muted grid place-items-center text-text-soft text-xs">
              [ Document preview: {doc.filename} ]
            </div>
            <p className="text-xs text-text-muted mt-2">Uploaded {new Date(doc.uploaded_at).toLocaleString()}</p>
          </Panel>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <Panel title="OCR Extracted Data" icon={IconScan}>
              <KvList items={doc.ocr ?? {}} empty="No data extracted." />
            </Panel>
            <Panel title="Metadata / EXIF" icon={IconScan}>
              <KvList items={doc.exif ?? {}} empty="No metadata captured." />
            </Panel>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <Panel title="Extracted vs On-record">
              <table className="w-full text-xs">
                <thead className="text-text-muted">
                  <tr><th className="text-left py-1">Field</th><th className="text-left">Extracted</th><th className="text-left">On record</th></tr>
                </thead>
                <tbody>
                  <tr className="border-t"><td className="py-1.5">Name</td><td>{doc.ocr?.name ?? "—"}</td><td>{doc.grantee_name}</td></tr>
                  <tr className="border-t"><td className="py-1.5">Student #</td><td>{doc.ocr?.studentNo ?? "—"}</td><td>{doc.student_number}</td></tr>
                </tbody>
              </table>
            </Panel>
            <Panel title="Face Verification / Liveness" icon={IconCameraSelfie}>
              <div className="flex items-center gap-3">
                <div className="h-16 w-16 rounded-md bg-surface-muted grid place-items-center text-text-soft text-2xs">ID</div>
                <div className="h-16 w-16 rounded-md bg-surface-muted grid place-items-center text-text-soft text-2xs">Selfie</div>
                <div className="text-xs">
                  <p>Match score: <span className="font-semibold">{100 - doc.risk_score}%</span></p>
                  <p className="text-text-muted">Liveness: {doc.risk_score < 50 ? "Pass" : "Review"}</p>
                </div>
              </div>
            </Panel>
          </div>
        </div>

        <div className="space-y-4">
          <Panel title="Risk Assessment" icon={IconShieldExclamation}>
            <div className="flex items-center gap-3">
              <div className={`h-14 w-14 rounded-full grid place-items-center text-sm font-semibold ${tone === "danger" ? "bg-danger-soft text-danger" : tone === "warning" ? "bg-warning-soft text-warning" : "bg-success-soft text-success"}`}>
                {doc.risk_score}
              </div>
              <div className="text-xs">
                <p className="font-medium">{tone === "danger" ? "High risk" : tone === "warning" ? "Medium risk" : "Low risk"}</p>
                <p className="text-text-muted mt-0.5">{doc.remarks ?? "No automatic flags."}</p>
              </div>
            </div>
          </Panel>

          <Panel title="Staff Decision">
            <TextArea placeholder="Validation remarks…" value={remarks} onChange={(e) => setRemarks(e.target.value)} />
            <div className="grid grid-cols-2 gap-2 mt-3">
              <Btn variant="primary" icon={IconCheck} onClick={() => act("approved")} disabled={update.isPending}>Approve</Btn>
              <Btn variant="danger" icon={IconX} onClick={() => act("rejected")} disabled={update.isPending}>Reject</Btn>
              <Btn variant="outline" icon={IconRefresh} onClick={() => act("resubmission")} disabled={update.isPending}>Resubmit</Btn>
              <Btn variant="outline" icon={IconShieldExclamation} onClick={() => act("suspicious")} disabled={update.isPending}>Suspicious</Btn>
            </div>
          </Panel>
        </div>
      </div>
    </div>
  );
}

function Panel({ title, icon: Icon, children }: { title: string; icon?: React.ComponentType<{ size?: number; className?: string }>; children: React.ReactNode }) {
  return (
    <div className="rounded-lg border bg-surface">
      <div className="px-3 h-9 flex items-center gap-1.5 border-b">
        {Icon && <Icon size={14} className="text-text-muted" />}
        <p className="text-2xs uppercase tracking-wide text-text-muted font-medium">{title}</p>
      </div>
      <div className="p-3">{children}</div>
    </div>
  );
}

function KvList({ items, empty }: { items: Record<string, string>; empty: string }) {
  const entries = Object.entries(items);
  if (!entries.length) return <p className="text-xs text-text-muted">{empty}</p>;
  return (
    <ul className="text-xs space-y-1">
      {entries.map(([k, v]) => (
        <li key={k} className="flex justify-between gap-2 border-b last:border-0 py-1">
          <span className="text-text-muted">{k}</span>
          <span className="font-medium text-right truncate">{v}</span>
        </li>
      ))}
    </ul>
  );
}
