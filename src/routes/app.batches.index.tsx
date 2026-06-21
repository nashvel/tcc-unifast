import { createFileRoute, Link } from "@tanstack/react-router";
import { PageHeader } from "@/components/ui/page-header";
import { Btn } from "@/components/ui/btn";
import { DataTable, THead, Tr, Th, Td } from "@/components/ui/data-table";
import { StatusBadge, statusVariantFor, formatStatus } from "@/components/ui/status-badge";
import { mockBatches } from "@/data/mockBatches";
import { IconPlus } from "@tabler/icons-react";

export const Route = createFileRoute("/app/batches")({
  component: () => (
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
          {mockBatches.map((b) => (
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
  ),
});
