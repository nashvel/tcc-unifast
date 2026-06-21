import { createFileRoute, Link } from "@tanstack/react-router";
import { PageHeader } from "@/components/ui/page-header";
import { Btn } from "@/components/ui/btn";
import { DataTable, THead, Tr, Th, Td } from "@/components/ui/data-table";
import { StatusBadge, statusVariantFor, formatStatus } from "@/components/ui/status-badge";
import { mockGrantees } from "@/data/mockGrantees";
import { IconArrowLeft, IconFileTypePdf, IconFileSpreadsheet } from "@tabler/icons-react";

export const Route = createFileRoute("/app/reports/preview")({
  component: () => (
    <div>
      <Link to="/app/reports/generate" className="inline-flex items-center gap-1 text-xs text-text-muted hover:text-text mb-3">
        <IconArrowLeft size={13} /> Back
      </Link>
      <PageHeader title="Report Preview" description="Grantee List — AY 2024-2025 Sem 1"
        actions={<><Btn variant="outline" icon={IconFileSpreadsheet}>Excel</Btn><Btn variant="primary" icon={IconFileTypePdf}>PDF</Btn></>} />
      <div className="rounded-lg border bg-surface p-6">
        <div className="text-center mb-6">
          <p className="text-[10px] uppercase tracking-wider font-semibold text-text-soft">Commission on Higher Education — UniFAST</p>
          <p className="text-lg font-semibold mt-1">Grantee List Report</p>
          <p className="text-xs text-text-muted">AY 2024-2025 • 1st Semester • Generated 2026-06-21</p>
        </div>
        <DataTable>
          <THead><Tr><Th>Student #</Th><Th>Name</Th><Th>University</Th><Th>Submission</Th><Th>Eligibility</Th></Tr></THead>
          <tbody>
            {mockGrantees.slice(0, 12).map((g) => (
              <Tr key={g.id}>
                <Td className="font-mono text-xs">{g.studentNumber}</Td>
                <Td className="font-medium">{g.firstName} {g.lastName}</Td>
                <Td className="text-text-muted">{g.university}</Td>
                <Td><StatusBadge variant={statusVariantFor(g.submissionStatus)}>{formatStatus(g.submissionStatus)}</StatusBadge></Td>
                <Td><StatusBadge variant={statusVariantFor(g.eligibility)}>{formatStatus(g.eligibility)}</StatusBadge></Td>
              </Tr>
            ))}
          </tbody>
        </DataTable>
      </div>
    </div>
  ),
});
