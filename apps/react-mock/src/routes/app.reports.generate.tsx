import { createFileRoute, Link } from "@tanstack/react-router";
import { PageHeader } from "@/components/ui/page-header";
import { Btn } from "@/components/ui/btn";
import { FormField, Selectish, TextInput } from "@/components/ui/form-field";
import { IconArrowLeft, IconFileTypePdf, IconFileSpreadsheet, IconEye } from "@tabler/icons-react";

export const Route = createFileRoute("/app/reports/generate")({
  component: () => (
    <div>
      <Link to="/app/reports" className="inline-flex items-center gap-1 text-xs text-text-muted hover:text-text mb-3">
        <IconArrowLeft size={13} /> Back
      </Link>
      <PageHeader title="Generate Report" description="Select parameters and preview before export." />
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div className="lg:col-span-2 rounded-lg border bg-surface p-4 space-y-3">
          <FormField label="Report type">
            <Selectish>
              <option>Grantee List</option><option>Batch Report</option><option>Document Validation</option>
              <option>Academic Tracking</option><option>Eligibility Report</option><option>Audit Trail</option><option>Office Report</option>
            </Selectish>
          </FormField>
          <div className="grid grid-cols-2 gap-3">
            <FormField label="Date from"><TextInput type="date" /></FormField>
            <FormField label="Date to"><TextInput type="date" /></FormField>
          </div>
          <FormField label="Batch">
            <Selectish><option>All batches</option><option>AY 2024-2025 Sem 1</option><option>AY 2024-2025 Sem 2</option></Selectish>
          </FormField>
          <FormField label="Format">
            <Selectish><option>Detailed</option><option>Summary</option></Selectish>
          </FormField>
        </div>
        <div className="rounded-lg border bg-surface p-4 space-y-2">
          <p className="text-sm font-semibold">Export options</p>
          <Link to="/app/reports/preview" className="block"><Btn variant="outline" className="w-full" icon={IconEye}>Preview report</Btn></Link>
          <Btn variant="primary" className="w-full" icon={IconFileTypePdf}>Download PDF</Btn>
          <Btn variant="outline" className="w-full" icon={IconFileSpreadsheet}>Download Excel</Btn>
        </div>
      </div>
    </div>
  ),
});
