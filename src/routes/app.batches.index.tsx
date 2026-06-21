import { createFileRoute, Link } from "@tanstack/react-router";
import { PageHeader } from "@/components/ui/page-header";
import { Btn } from "@/components/ui/btn";
import { DataTable, THead, Tr, Th, Td } from "@/components/ui/data-table";
import { StatusBadge, statusVariantFor, formatStatus } from "@/components/ui/status-badge";
import { useBatches } from "@/hooks/queries";
import { IconPlus } from "@tabler/icons-react";

export const Route = createFileRoute("/app/batches/")({
  component: BatchesPage,
});

function BatchesPage() {
  const { data: batches = [], isLoading } = useBatches();
  return (
    <div>
      <PageHeader
        title="Batches"
        description="Manage TES grantee batches per academic period."
        actions={<Btn variant="primary" icon={IconPlus}>New batch</Btn>}
      />
      <DataTable>
        <THead>
          <Tr>
            <Th>Batch</Th><Th>Academic Year</Th><Th>Semester</Th><Th>Grantees</Th>
            <Th>Active</Th><Th>Pending</Th><Th>Validated</Th><Th>Status</Th><Th></Th>
          </Tr>
        </THead>
        <tbody>
          {isLoading && <Tr><Td colSpan={9} className="text-center text-text-muted py-6">Loading…</Td></Tr>}
          {!isLoading && batches.length === 0 && <Tr><Td colSpan={9} className="text-center text-text-muted py-6">No batches yet.</Td></Tr>}
          {batches.map((b) => (
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
