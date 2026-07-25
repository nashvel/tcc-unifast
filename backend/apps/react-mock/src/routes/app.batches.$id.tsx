import { createFileRoute, Link, useParams } from "@tanstack/react-router";
import { PageHeader } from "@/components/ui/page-header";
import { StatCard } from "@/components/ui/stat-card";
import { Btn } from "@/components/ui/btn";
import { DataTable, THead, Tr, Th, Td } from "@/components/ui/data-table";
import { StatusBadge, statusVariantFor, formatStatus } from "@/components/ui/status-badge";
import { useBatches, useGrantees } from "@/hooks/queries";
import { IconArrowLeft, IconUsersGroup, IconUserCheck, IconClipboardList, IconFileCheck } from "@tabler/icons-react";

export const Route = createFileRoute("/app/batches/$id")({
  component: BatchDetail,
});

function BatchDetail() {
  const { id } = useParams({ from: "/app/batches/$id" });
  const { data: batches = [] } = useBatches();
  const { data: allGrantees = [] } = useGrantees();
  const batch = batches.find((b) => b.id === id);
  const grantees = allGrantees.filter((g) => g.batchId === id);

  if (!batch) return <div className="text-sm text-text-muted">Batch not found.</div>;

  return (
    <div>
      <Link to="/app/batches" className="inline-flex items-center gap-1 text-xs text-text-muted hover:text-text mb-3">
        <IconArrowLeft size={13} /> Back to batches
      </Link>
      <PageHeader
        title={batch.name}
        description={`${batch.academicYear} • ${batch.semester}`}
        actions={<><Btn variant="outline">Export</Btn><Btn variant="primary">Edit batch</Btn></>}
      />
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
        <StatCard label="Total Grantees" value={batch.totalGrantees.toLocaleString()} icon={IconUsersGroup} tone="primary" />
        <StatCard label="Active" value={batch.active.toLocaleString()} icon={IconUserCheck} tone="success" />
        <StatCard label="Pending" value={batch.pending.toLocaleString()} icon={IconClipboardList} tone="warning" />
        <StatCard label="Validated" value={batch.validated.toLocaleString()} icon={IconFileCheck} tone="info" />
      </div>
      <p className="text-sm font-semibold mb-2">Grantees in this batch</p>
      <DataTable>
        <THead>
          <Tr><Th>Student #</Th><Th>Name</Th><Th>University</Th><Th>Account</Th><Th>Submission</Th></Tr>
        </THead>
        <tbody>
          {grantees.slice(0, 20).map((g) => (
            <Tr key={g.id}>
              <Td className="font-mono text-xs">{g.studentNumber}</Td>
              <Td><Link to="/app/grantees/$id" params={{ id: g.id }} className="font-medium hover:text-primary">{g.firstName} {g.lastName}</Link></Td>
              <Td className="text-text-muted">{g.university}</Td>
              <Td><StatusBadge variant={statusVariantFor(g.accountStatus)}>{formatStatus(g.accountStatus)}</StatusBadge></Td>
              <Td><StatusBadge variant={statusVariantFor(g.submissionStatus)}>{formatStatus(g.submissionStatus)}</StatusBadge></Td>
            </Tr>
          ))}
        </tbody>
      </DataTable>
    </div>
  );
}
