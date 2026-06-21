import { createFileRoute } from "@tanstack/react-router";
import { PageHeader } from "@/components/ui/page-header";
import { DataTable, THead, Tr, Th, Td } from "@/components/ui/data-table";
import { StatusBadge, statusVariantFor, formatStatus } from "@/components/ui/status-badge";
import { mockDocuments } from "@/data/mockDocuments";

export const Route = createFileRoute("/student/submissions")({
  component: () => {
    const my = mockDocuments.filter((d) => d.granteeId === "g1");
    return (
      <div>
        <PageHeader title="Submission Status" description="Status and history of your uploaded documents." />
        <DataTable>
          <THead><Tr><Th>Document</Th><Th>File</Th><Th>Uploaded</Th><Th>Status</Th><Th>Remarks</Th></Tr></THead>
          <tbody>
            {my.map((d) => (
              <Tr key={d.id}>
                <Td className="font-medium">{d.type}</Td>
                <Td className="font-mono text-xs text-text-muted">{d.filename}</Td>
                <Td className="text-text-muted">{d.uploadedAt}</Td>
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
