import { createFileRoute } from "@tanstack/react-router";
import { PageHeader } from "@/components/ui/page-header";
import { DataTable, THead, Tr, Th, Td } from "@/components/ui/data-table";
import { StatusBadge, statusVariantFor, formatStatus } from "@/components/ui/status-badge";
import { useDocuments } from "@/hooks/queries";
import { requiredDocs } from "@/data/mockDocuments";

export const Route = createFileRoute("/student/submissions")({
  component: () => {
    const { data: all = [], isLoading } = useDocuments({ ownerOnly: true });
    const my = all.filter((d) => requiredDocs.includes(d.type));
    return (
      <div>
        <PageHeader title="Submission Status" description="Status and history of your uploaded documents." />
        <DataTable>
          <THead><Tr><Th>Document</Th><Th>File</Th><Th>Uploaded</Th><Th>Status</Th><Th>Remarks</Th></Tr></THead>
          <tbody>
            {isLoading && <Tr><Td colSpan={5} className="text-center text-text-muted py-6">Loading…</Td></Tr>}
            {!isLoading && my.length === 0 && <Tr><Td colSpan={5} className="text-center text-text-muted py-6">No submissions yet.</Td></Tr>}
            {my.map((d) => (
              <Tr key={d.id}>
                <Td className="font-medium">{d.type}</Td>
                <Td className="font-mono text-xs text-text-muted">{d.filename}</Td>
                <Td className="text-text-muted">{new Date(d.uploaded_at).toLocaleString()}</Td>
                <Td><StatusBadge variant={statusVariantFor(d.status)}>{formatStatus(d.status)}</StatusBadge></Td>
                <Td className="text-text-muted text-xs">{d.remarks ?? "—"}</Td>
              </Tr>
            ))}
          </tbody>
        </DataTable>
      </div>
    );
  },
});
