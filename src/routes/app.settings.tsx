import { createFileRoute } from "@tanstack/react-router";
import { PageHeader } from "@/components/ui/page-header";
import { Btn } from "@/components/ui/btn";
import { FormField, TextInput, Selectish } from "@/components/ui/form-field";

export const Route = createFileRoute("/app/settings")({
  component: () => (
    <div>
      <PageHeader title="Settings" description="System and organization preferences." />
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div className="rounded-lg border bg-surface p-4 space-y-3">
          <p className="text-sm font-semibold">Organization</p>
          <FormField label="Office name"><TextInput defaultValue="UniFAST Office" /></FormField>
          <FormField label="Region"><TextInput defaultValue="NCR" /></FormField>
          <FormField label="Contact email"><TextInput defaultValue="info@unifast.gov.ph" /></FormField>
        </div>
        <div className="rounded-lg border bg-surface p-4 space-y-3">
          <p className="text-sm font-semibold">Validation rules</p>
          <FormField label="Auto-approve risk threshold" helper="Documents with risk below this score may be auto-approved.">
            <Selectish defaultValue="20"><option>10</option><option>20</option><option>30</option><option>40</option></Selectish>
          </FormField>
          <FormField label="Retention GWA cap">
            <TextInput defaultValue="2.75" />
          </FormField>
          <FormField label="Max failed subjects per semester">
            <TextInput defaultValue="1" type="number" />
          </FormField>
        </div>
      </div>
      <div className="mt-4 flex justify-end gap-2">
        <Btn variant="outline">Cancel</Btn>
        <Btn variant="primary">Save changes</Btn>
      </div>
    </div>
  ),
});
