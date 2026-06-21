import { createFileRoute, Link } from "@tanstack/react-router";
import { PageHeader } from "@/components/ui/page-header";
import { Btn } from "@/components/ui/btn";
import { DataTable, THead, Tr, Th, Td } from "@/components/ui/data-table";
import { StatusBadge, statusVariantFor, formatStatus } from "@/components/ui/status-badge";
import { mockGrantees } from "@/data/mockGrantees";
import { IconDownload } from "@tabler/icons-react";

export const Route = createFileRoute("/app/eligibility/")({
  component: () => {
    const sample = mockGrantees.slice(0, 20);
    return (
      <div>
        <PageHeader title="Eligibility Evaluation" description="Rules-based decisions for the current period."
          actions={<Btn variant="outline" icon={IconDownload}>Export results</Btn>} />
        <DataTable>
          <THead><Tr><Th>Student #</Th><Th>Name</Th><Th>GWA</Th><Th>Risk</Th><Th>Recommendation</Th><Th></Th></Tr></THead>
          <tbody>
            {sample.map((g) => (
              <Tr key={g.id}>
                <Td className="font-mono text-xs">{g.studentNumber}</Td>
                <Td className="font-medium">{g.firstName} {g.lastName}</Td>
                <Td className="tabular-nums">{g.gwa.toFixed(2)}</Td>
                <Td><StatusBadge variant={statusVariantFor(g.risk)}>{formatStatus(g.risk)}</StatusBadge></Td>
                <Td><StatusBadge variant={statusVariantFor(g.eligibility)}>{formatStatus(g.eligibility)}</StatusBadge></Td>
                <Td className="text-right"><Link to="/app/eligibility/$id" params={{ id: g.id }} className="text-xs text-primary hover:underline">Evaluate</Link></Td>
              </Tr>
            ))}
          </tbody>
        </DataTable>
      </div>
    );
  },
});
