import { createFileRoute, Link } from "@tanstack/react-router";
import { PageHeader } from "@/components/ui/page-header";
import { DataTable, THead, Tr, Th, Td } from "@/components/ui/data-table";
import { StatusBadge, statusVariantFor, formatStatus } from "@/components/ui/status-badge";
import { useAcademicRecords } from "@/hooks/queries";

export const Route = createFileRoute("/app/academic/")({
  component: AcademicList,
});

function AcademicList() {
  const { data: records = [], isLoading } = useAcademicRecords();
  return (
    <div>
      <PageHeader title="Academic Records" description="Per-grantee academic tracking and retention rule evaluation." />
      <DataTable>
        <THead><Tr><Th>Student #</Th><Th>Name</Th><Th>Program</Th><Th>Cumulative GWA</Th><Th>Retention</Th><Th>Recommendation</Th><Th></Th></Tr></THead>
        <tbody>
          {isLoading && <Tr><Td colSpan={7} className="text-center text-text-muted py-6">Loading…</Td></Tr>}
          {!isLoading && records.length === 0 && <Tr><Td colSpan={7} className="text-center text-text-muted py-6">No academic records yet.</Td></Tr>}
          {records.map((r) => (
            <Tr key={r.granteeId}>
              <Td className="font-mono text-xs">{r.studentNumber}</Td>
              <Td className="font-medium">{r.granteeName}</Td>
              <Td className="text-text-muted">{r.program}</Td>
              <Td className="tabular-nums">{r.cumulativeGwa.toFixed(2)}</Td>
              <Td>{r.retentionPassed ? <StatusBadge variant="success">Passed</StatusBadge> : <StatusBadge variant="danger">Failed</StatusBadge>}</Td>
              <Td><StatusBadge variant={statusVariantFor(r.recommendation)}>{formatStatus(r.recommendation)}</StatusBadge></Td>
              <Td className="text-right"><Link to="/app/academic/$id" params={{ id: r.granteeId }} className="text-xs text-primary hover:underline">View</Link></Td>
            </Tr>
          ))}
        </tbody>
      </DataTable>
    </div>
  );
}
