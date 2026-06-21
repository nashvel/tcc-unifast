import { createFileRoute, Link } from "@tanstack/react-router";
import { useState } from "react";
import { PageHeader } from "@/components/ui/page-header";
import { Btn } from "@/components/ui/btn";
import { FormField, TextInput, TextArea, Selectish } from "@/components/ui/form-field";
import { IconArrowLeft, IconSend } from "@tabler/icons-react";

export const Route = createFileRoute("/app/announcements/new")({
  component: NewAnnouncement,
});

function NewAnnouncement() {
  const [audience, setAudience] = useState("all");
  const [channels, setChannels] = useState({ in_app: true, email: true, sms: false });
  return (
    <div>
      <Link to="/app/announcements" className="inline-flex items-center gap-1 text-xs text-text-muted hover:text-text mb-3">
        <IconArrowLeft size={13} /> Back
      </Link>
      <PageHeader title="New Announcement" />
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div className="lg:col-span-2 rounded-lg border bg-surface p-4 space-y-3">
          <FormField label="Title" required><TextInput placeholder="e.g. Reminder: Submission deadline extended" /></FormField>
          <FormField label="Body" required><TextArea rows={6} placeholder="Write the announcement…" /></FormField>
        </div>
        <div className="rounded-lg border bg-surface p-4 space-y-3">
          <FormField label="Audience">
            <Selectish value={audience} onChange={(e) => setAudience(e.target.value)}>
              <option value="all">All students</option>
              <option value="batch">Specific batch</option>
              <option value="pending">Students with pending submissions</option>
              <option value="rejected">Students with rejected documents</option>
              <option value="eligible">Eligible grantees</option>
            </Selectish>
          </FormField>
          <FormField label="Channels">
            <div className="space-y-1.5">
              {(["in_app", "email", "sms"] as const).map((c) => (
                <label key={c} className="flex items-center gap-2 text-sm">
                  <input type="checkbox" checked={channels[c]} onChange={(e) => setChannels({ ...channels, [c]: e.target.checked })} />
                  <span className="capitalize">{c.replace("_", "-")}</span>
                </label>
              ))}
            </div>
          </FormField>
          <FormField label="Schedule (optional)"><TextInput type="datetime-local" /></FormField>
          <div className="flex gap-2 pt-2">
            <Btn variant="outline" className="flex-1">Save draft</Btn>
            <Btn variant="primary" icon={IconSend} className="flex-1">Publish</Btn>
          </div>
        </div>
      </div>
    </div>
  );
}
