import { createFileRoute, Link, useParams } from "@tanstack/react-router";
import { useState } from "react";
import { PageHeader } from "@/components/ui/page-header";
import { Btn } from "@/components/ui/btn";
import { StatusBadge, statusVariantFor, formatStatus } from "@/components/ui/status-badge";
import { ConfirmModal } from "@/components/ui/modal";
import { mockGrantees } from "@/data/mockGrantees";
import { IconArrowLeft, IconCheck, IconX } from "@tabler/icons-react";

export const Route = createFileRoute("/app/eligibility/$id")({
  component: EvalDetail,
});

const criteria = [
  { label: "Documents complete and approved", pass: true },
  { label: "Cumulative GWA within TES retention rule", pass: true },
  { label: "No more than 1 failed subject in the last semester", pass: true },
  { label: "Not enrolled in disqualified program", pass: true },
  { label: "Account verified and active", pass: true },
];

function EvalDetail() {
  const { id } = useParams({ from: "/app/eligibility/$id" });
  const g = mockGrantees.find((x) => x.id === id);
  const [confirm, setConfirm] = useState<null | "eligible" | "ineligible">(null);
  if (!g) return <div className="text-sm text-text-muted">Not found.</div>;

  return (
    <div>
      <Link to="/app/eligibility" className="inline-flex items-center gap-1 text-xs text-text-muted hover:text-text mb-3">
        <IconArrowLeft size={13} /> Back
      </Link>
      <PageHeader title={`Eligibility — ${g.firstName} ${g.lastName}`} description={`${g.studentNumber} • ${g.program}`} />

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div className="lg:col-span-2 rounded-lg border bg-surface p-4">
          <p className="text-sm font-semibold mb-3">Criteria Checklist</p>
          <ul className="space-y-2">
            {criteria.map((c, i) => (
              <li key={i} className="flex items-center justify-between text-sm border-b last:border-0 pb-2">
                <span>{c.label}</span>
                {c.pass ? <StatusBadge variant="success">Met</StatusBadge> : <StatusBadge variant="danger">Not met</StatusBadge>}
              </li>
            ))}
          </ul>
          <div className="mt-4 p-3 rounded-md bg-success-soft text-success text-xs">
            <p className="font-semibold">Rules-based decision: Eligible</p>
            <p className="text-text-muted mt-1">All criteria met. Recommendation may be overridden by Office Head with documented justification.</p>
          </div>
        </div>
        <div className="rounded-lg border bg-surface p-4">
          <p className="text-sm font-semibold mb-2">Current Status</p>
          <StatusBadge variant={statusVariantFor(g.eligibility)}>{formatStatus(g.eligibility)}</StatusBadge>
          <p className="text-xs text-text-muted mt-3">Update the official eligibility status for this grantee.</p>
          <div className="grid grid-cols-2 gap-2 mt-3">
            <Btn variant="primary" icon={IconCheck} onClick={() => setConfirm("eligible")}>Mark Eligible</Btn>
            <Btn variant="danger" icon={IconX} onClick={() => setConfirm("ineligible")}>Mark Ineligible</Btn>
          </div>
        </div>
      </div>

      <ConfirmModal
        open={!!confirm}
        onClose={() => setConfirm(null)}
        title={`Mark grantee as ${confirm}?`}
        description="This decision will be recorded in the audit trail and notify the student."
        confirmLabel="Confirm decision"
        danger={confirm === "ineligible"}
        onConfirm={() => {}}
      />
    </div>
  );
}
