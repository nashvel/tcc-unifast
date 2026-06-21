import { createFileRoute, Link, useParams } from "@tanstack/react-router";
import { PageHeader } from "@/components/ui/page-header";
import { Btn } from "@/components/ui/btn";
import { FormField, TextInput, TextArea } from "@/components/ui/form-field";
import { useAnnouncement } from "@/hooks/queries";
import { IconArrowLeft } from "@tabler/icons-react";

export const Route = createFileRoute("/app/announcements/$id/edit")({
  component: EditPage,
});

function EditPage() {
  const { id } = useParams({ from: "/app/announcements/$id/edit" });
  const { data: a, isLoading } = useAnnouncement(id);
  if (isLoading) return <div className="text-sm text-text-muted">Loading…</div>;
  if (!a) return <div className="text-sm text-text-muted">Not found.</div>;
  return (
    <div>
      <Link to="/app/announcements" className="inline-flex items-center gap-1 text-xs text-text-muted hover:text-text mb-3">
        <IconArrowLeft size={13} /> Back
      </Link>
      <PageHeader title="Edit Announcement" />
      <div className="rounded-lg border bg-surface p-4 space-y-3 max-w-2xl">
        <FormField label="Title"><TextInput defaultValue={a.title} /></FormField>
        <FormField label="Body"><TextArea rows={6} defaultValue={a.body} /></FormField>
        <div className="flex gap-2">
          <Btn variant="outline" className="flex-1">Cancel</Btn>
          <Btn variant="primary" className="flex-1">Save changes</Btn>
        </div>
      </div>
    </div>
  );
}
