import { createFileRoute, Link, useParams } from "@tanstack/react-router";
import { PageHeader } from "@/components/ui/page-header";
import { StatCard } from "@/components/ui/stat-card";
import { DataTable, THead, Tr, Th, Td } from "@/components/ui/data-table";
import { StatusBadge, statusVariantFor, formatStatus } from "@/components/ui/status-badge";
import { useAcademicRecord } from "@/hooks/queries";
import { IconArrowLeft, IconChartBar, IconChecklist, IconAlertOctagon } from "@tabler/icons-react";

export const Route = createFileRoute("/app/academic/$id")({
  component: Detail,
});

function Detail() {
  const { id } = useParams({ from: "/app/academic/$id" });
  const { data: r, isLoading } = useAcademicRecord(id);
  if (isLoading) return <div className="text-sm text-text-muted">Loading…</div>;
  if (!r) return <div className="text-sm text-text-muted">Record not found.</div>;
  const failed = r.semesters.reduce((a, s) => a + s.failed.length, 0);
  const dropped = r.semesters.reduce((a, s) => a + s.dropped.length, 0);

  return (
    <div>
      <Link to="/app/academic" className="inline-flex items-center gap-1 text-xs text-text-muted hover:text-text mb-3">
        <IconArrowLeft size={13} /> Back
      </Link>
      <PageHeader title={r.granteeName} description={`${r.studentNumber} • ${r.program}`} />
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
        <StatCard label="Cumulative GWA" value={r.cumulativeGwa.toFixed(2)} icon={IconChartBar} tone="primary" />
        <StatCard label="Failed Subjects" value={failed} icon={IconAlertOctagon} tone={failed > 0 ? "danger" : "success"} />
        <StatCard label="Dropped Subjects" value={dropped} icon={IconAlertOctagon} tone={dropped > 0 ? "warning" : "success"} />
        <StatCard label="Retention Rule" value={r.retentionPassed ? "Passed" : "Failed"} icon={IconChecklist} tone={r.retentionPassed ? "success" : "danger"} />
      </div>
      <div className="rounded-lg border bg-surface p-4 mb-4 flex items-center justify-between">
        <div>
          <p className="text-xs text-text-muted">Eligibility Recommendation</p>
          <p className="text-sm font-medium mt-0.5">Based on cumulative GWA, retention rule, and failed/dropped subjects.</p>
        </div>
        <StatusBadge variant={statusVariantFor(r.recommendation)}>{formatStatus(r.recommendation)}</StatusBadge>
      </div>
      <DataTable>
        <THead><Tr><Th>Semester</Th><Th>GWA</Th><Th>Units Taken</Th><Th>Passed</Th><Th>Failed</Th><Th>Dropped</Th></Tr></THead>
        <tbody>
          {r.semesters.map((s) => (
            <Tr key={s.semester}>
              <Td className="font-medium">{s.semester}</Td>
              <Td className="tabular-nums">{s.gwa.toFixed(2)}</Td>
              <Td className="tabular-nums">{s.unitsTaken}</Td>
              <Td className="tabular-nums">{s.unitsPassed}</Td>
              <Td className="text-danger">{s.failed.join(", ") || "—"}</Td>
              <Td className="text-warning">{s.dropped.join(", ") || "—"}</Td>
            </Tr>
          ))}
        </tbody>
      </DataTable>
    </div>
  );
}
