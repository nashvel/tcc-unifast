import { createFileRoute, Link } from "@tanstack/react-router";
import { useMemo, useState } from "react";
import { PageHeader } from "@/components/ui/page-header";
import { Btn } from "@/components/ui/btn";
import { SearchInput } from "@/components/ui/search-input";
import { Selectish } from "@/components/ui/form-field";
import { DataTable, THead, Tr, Th, Td } from "@/components/ui/data-table";
import { StatusBadge, statusVariantFor, formatStatus } from "@/components/ui/status-badge";
import { useBatches } from "@/hooks/queries";
import { IconPlus } from "@tabler/icons-react";

export const Route = createFileRoute("/app/batches/")({
  component: BatchesPage,
});

function BatchesPage() {
  const { data: batches = [], isLoading } = useBatches();
  const [q, setQ] = useState("");
  const [status, setStatus] = useState("all");

  const filtered = useMemo(() => batches.filter((b) => {
    if (q && !`${b.name} ${b.academicYear} ${b.semester}`.toLowerCase().includes(q.toLowerCase())) return false;
    if (status !== "all" && b.status !== status) return false;
    return true;
  }), [batches, q, status]);

  return (
    <div>
      <PageHeader
        title="Batches"
        description="Manage TES grantee batches per academic period."
        actions={<Btn variant="primary" icon={IconPlus}>New batch</Btn>}
      />
      <div className="rounded-lg border bg-surface p-3 mb-4 grid grid-cols-1 md:grid-cols-4 gap-2">
        <SearchInput placeholder="Search by batch, AY, or semester" value={q} onChange={(e) => setQ(e.target.value)} className="md:col-span-3" />
        <Selectish value={status} onChange={(e) => setStatus(e.target.value)}>
          <option value="all">All statuses</option>
          <option>active</option><option>closed</option><option>draft</option><option>archived</option>
        </Selectish>
      </div>
      <DataTable>
        <THead>
          <Tr>
            <Th>Batch</Th><Th>Academic Year</Th><Th>Semester</Th><Th>Grantees</Th>
            <Th>Active</Th><Th>Pending</Th><Th>Validated</Th><Th>Status</Th><Th></Th>
          </Tr>
        </THead>
        <tbody>
          {isLoading && <Tr><Td colSpan={9} className="text-center text-text-muted py-6">Loading…</Td></Tr>}
          {!isLoading && filtered.length === 0 && <Tr><Td colSpan={9} className="text-center text-text-muted py-6">No batches match your filters.</Td></Tr>}
          {filtered.map((b) => (
            <Tr key={b.id}>
              <Td><Link to="/app/batches/$id" params={{ id: b.id }} className="font-medium hover:text-primary">{b.name}</Link></Td>
              <Td className="text-text-muted">{b.academicYear}</Td>
              <Td className="text-text-muted">{b.semester}</Td>
              <Td className="tabular-nums">{b.totalGrantees.toLocaleString()}</Td>
              <Td className="tabular-nums">{b.active.toLocaleString()}</Td>
              <Td className="tabular-nums">{b.pending.toLocaleString()}</Td>
              <Td className="tabular-nums">{b.validated.toLocaleString()}</Td>
              <Td><StatusBadge variant={statusVariantFor(b.status)}>{formatStatus(b.status)}</StatusBadge></Td>
              <Td className="text-right"><Link to="/app/batches/$id" params={{ id: b.id }} className="text-xs text-primary hover:underline">View</Link></Td>
            </Tr>
          ))}
        </tbody>
      </DataTable>
    </div>
  );
}
