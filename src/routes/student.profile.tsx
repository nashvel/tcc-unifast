import { createFileRoute } from "@tanstack/react-router";
import { PageHeader } from "@/components/ui/page-header";
import { FormField, TextInput } from "@/components/ui/form-field";
import { Btn } from "@/components/ui/btn";

export const Route = createFileRoute("/student/profile")({
  component: () => (
    <div>
      <PageHeader title="My Profile" description="Personal and academic information on file." />
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div className="rounded-lg border bg-surface p-4 space-y-3">
          <p className="text-sm font-semibold">Personal</p>
          <FormField label="Full name"><TextInput defaultValue="Maria Clara Dela Cruz" /></FormField>
          <FormField label="Birthdate"><TextInput type="date" defaultValue="2003-05-14" /></FormField>
          <FormField label="Email"><TextInput defaultValue="mc.delacruz@plm.edu.ph" /></FormField>
          <FormField label="Contact"><TextInput defaultValue="+639171234567" /></FormField>
        </div>
        <div className="rounded-lg border bg-surface p-4 space-y-3">
          <p className="text-sm font-semibold">Academic</p>
          <FormField label="University"><TextInput defaultValue="Pamantasan ng Lungsod ng Maynila" /></FormField>
          <FormField label="Program"><TextInput defaultValue="BS Computer Science" /></FormField>
          <FormField label="Year level"><TextInput defaultValue="2" /></FormField>
          <FormField label="Student #"><TextInput defaultValue="2024-00123" disabled /></FormField>
        </div>
      </div>
      <div className="mt-4 flex justify-end gap-2">
        <Btn variant="outline">Cancel</Btn>
        <Btn variant="primary">Save changes</Btn>
      </div>
    </div>
  ),
});
