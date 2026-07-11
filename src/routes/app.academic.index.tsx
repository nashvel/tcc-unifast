import { createFileRoute, Link } from "@tanstack/react-router";
import { useMemo, useState } from "react";
import { PageHeader } from "@/components/ui/page-header";
import { SearchInput } from "@/components/ui/search-input";
import { Selectish } from "@/components/ui/form-field";
import { DataTable, THead, Tr, Th, Td } from "@/components/ui/data-table";
import { StatusBadge, statusVariantFor, formatStatus } from "@/components/ui/status-badge";
import { useAcademicRecords } from "@/hooks/queries";

export const Route = createFileRoute("/app/academic/")({
  component: AcademicList,
});

function AcademicList() {
  const { data: records = [], isLoading } = useAcademicRecords();
  const [q, setQ] = useState("");
  const [retention, setRetention] = useState("all");

  const filtered = useMemo(() => records.filter((r) => {
    if (q && !`${r.granteeName} ${r.studentNumber} ${r.program}`.toLowerCase().includes(q.toLowerCase())) return false;
    if (retention === "passed" && !r.retentionPassed) return false;
    if (retention === "failed" && r.retentionPassed) return false;
    return true;
  }), [records, q, retention]);

  return (
    <div>
      <PageHeader title="Academic Records" description="Per-grantee academic tracking and retention rule evaluation." />
      <div className="rounded-lg border bg-surface p-3 mb-4 grid grid-cols-1 md:grid-cols-4 gap-2">
        <SearchInput placeholder="Search by name, student #, or program" value={q} onChange={(e) => setQ(e.target.value)} className="md:col-span-3" />
        <Selectish value={retention} onChange={(e) => setRetention(e.target.value)}>
          <option value="all">All retention</option>
          <option value="passed">Passed</option>
          <option value="failed">Failed</option>
        </Selectish>
      </div>
      <DataTable>
        <THead><Tr><Th>Student #</Th><Th>Name</Th><Th>Program</Th><Th>Cumulative GWA</Th><Th>Retention</Th><Th>Recommendation</Th><Th></Th></Tr></THead>
        <tbody>
          {isLoading && <Tr><Td colSpan={7} className="text-center text-text-muted py-6">Loading…</Td></Tr>}
          {!isLoading && filtered.length === 0 && <Tr><Td colSpan={7} className="text-center text-text-muted py-6">No records match your filters.</Td></Tr>}
          {filtered.map((r) => (
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
