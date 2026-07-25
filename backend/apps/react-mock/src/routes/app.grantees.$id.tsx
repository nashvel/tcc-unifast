import { createFileRoute, Link, useParams } from "@tanstack/react-router";
import { PageHeader } from "@/components/ui/page-header";
import { Btn } from "@/components/ui/btn";
import { StatusBadge, statusVariantFor, formatStatus } from "@/components/ui/status-badge";
import { requiredDocs } from "@/data/mockDocuments";
import { useGrantee, useAuditLogs } from "@/hooks/queries";
import { IconArrowLeft, IconEdit, IconShieldCheck } from "@tabler/icons-react";

export const Route = createFileRoute("/app/grantees/$id")({
  component: GranteeProfile,
});

function GranteeProfile() {
  const { id } = useParams({ from: "/app/grantees/$id" });
  const { data: g, isLoading } = useGrantee(id);
  const { data: logs = [] } = useAuditLogs();
  if (isLoading) return <div className="text-sm text-text-muted">Loading…</div>;
  if (!g) return <div className="text-sm text-text-muted">Grantee not found.</div>;
  const docs: { type: string; status: string }[] = [];

  return (
    <div>
      <Link to="/app/grantees" className="inline-flex items-center gap-1 text-xs text-text-muted hover:text-text mb-3">
        <IconArrowLeft size={13} /> Back to grantees
      </Link>
      <PageHeader
        title={`${g.firstName} ${g.middleName ? g.middleName + " " : ""}${g.lastName}`}
        description={`${g.studentNumber} • ${g.program} • ${g.university}`}
        actions={
          <>
            <Btn variant="outline" icon={IconEdit}>Edit</Btn>
            <Btn variant="primary" icon={IconShieldCheck}>Update status</Btn>
          </>
        }
      />

      <div className="flex flex-wrap gap-2 mb-4">
        <StatusBadge variant={statusVariantFor(g.accountStatus)}>Account: {formatStatus(g.accountStatus)}</StatusBadge>
        <StatusBadge variant={statusVariantFor(g.submissionStatus)}>Submission: {formatStatus(g.submissionStatus)}</StatusBadge>
        <StatusBadge variant={statusVariantFor(g.eligibility)}>Eligibility: {formatStatus(g.eligibility)}</StatusBadge>
        <StatusBadge variant={statusVariantFor(g.risk)}>Risk: {formatStatus(g.risk)}</StatusBadge>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div className="lg:col-span-2 space-y-4">
          <Section title="Personal Information">
            <Row label="Full Name" value={`${g.firstName} ${g.middleName ?? ""} ${g.lastName}`} />
            <Row label="Birthdate" value={g.birthdate} />
            <Row label="Email" value={g.email} />
            <Row label="Contact" value={g.contact} />
          </Section>
          <Section title="Academic Information">
            <Row label="University" value={g.university} />
            <Row label="Program" value={g.program} />
            <Row label="Year Level" value={String(g.yearLevel)} />
            <Row label="Cumulative GWA" value={g.gwa.toFixed(2)} />
          </Section>
          <Section title="Submitted Requirements">
            <ul className="divide-y border rounded-md">
              {requiredDocs.map((req) => {
                const d = docs.find((x) => x.type === req);
                return (
                  <li key={req} className="flex items-center justify-between px-3 py-2 text-sm">
                    <span>{req}</span>
                    {d ? (
                      <StatusBadge variant={statusVariantFor(d.status)}>{formatStatus(d.status)}</StatusBadge>
                    ) : (
                      <StatusBadge variant="neutral">Not Submitted</StatusBadge>
                    )}
                  </li>
                );
              })}
            </ul>
          </Section>
          <Section title="Validation History">
            <ul className="space-y-2 text-xs">
              {logs.slice(0, 4).map((l) => (
                <li key={l.id} className="flex items-start gap-2 border-b last:border-0 pb-2">
                  <div className="h-6 w-6 rounded bg-primary-soft text-primary grid place-items-center mt-0.5"><IconShieldCheck size={12} /></div>
                  <div className="flex-1">
                    <p><span className="font-medium">{l.user}</span> {formatStatus(l.action)} <span className="text-text-muted">on {l.target}</span></p>
                    <p className="text-text-soft">{l.timestamp}</p>
                  </div>
                </li>
              ))}
            </ul>
          </Section>
        </div>

        <div className="space-y-4">
          <Section title="Account Status">
            <Row label="Status" value={<StatusBadge variant={statusVariantFor(g.accountStatus)}>{formatStatus(g.accountStatus)}</StatusBadge>} />
            <Row label="Last login" value="2026-06-19 08:11" />
            <Row label="Profile completion" value={`${g.profileCompletion}%`} />
            <div className="h-1.5 rounded-full bg-surface-muted overflow-hidden mt-1">
              <div className="h-full bg-primary" style={{ width: `${g.profileCompletion}%` }} />
            </div>
          </Section>
          <Section title="Staff Notes">
            <p className="text-xs text-text-muted">{g.notes ?? "No notes recorded."}</p>
            <textarea placeholder="Add a note…" className="mt-2 w-full rounded-md border bg-input px-2.5 py-2 text-xs focus-ring min-h-[80px]" />
            <Btn variant="primary" size="sm" className="mt-2">Save note</Btn>
          </Section>
        </div>
      </div>
    </div>
  );
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div className="rounded-lg border bg-surface">
      <div className="px-4 h-10 flex items-center border-b">
        <p className="text-sm font-semibold">{title}</p>
      </div>
      <div className="p-4 space-y-2">{children}</div>
    </div>
  );
}
function Row({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="grid grid-cols-3 gap-2 text-sm">
      <span className="text-text-muted text-xs">{label}</span>
      <span className="col-span-2">{value}</span>
    </div>
  );
}
