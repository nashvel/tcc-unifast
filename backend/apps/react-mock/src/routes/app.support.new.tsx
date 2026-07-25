import { createFileRoute, Link, useNavigate } from "@tanstack/react-router";
import { useState } from "react";
import { PageHeader } from "@/components/ui/page-header";
import { Btn } from "@/components/ui/btn";
import { TextInput, Selectish } from "@/components/ui/form-field";
import { useCreateTicket, type SupportCategory, type SupportPriority } from "@/hooks/support-queries";
import { IconArrowLeft } from "@tabler/icons-react";

export const Route = createFileRoute("/app/support/new")({
  component: NewTicketPage,
});

function NewTicketPage() {
  const navigate = useNavigate();
  const create = useCreateTicket();
  const [subject, setSubject] = useState("");
  const [category, setCategory] = useState<SupportCategory>("question");
  const [priority, setPriority] = useState<SupportPriority>("normal");
  const [body, setBody] = useState("");
  const [error, setError] = useState<string | null>(null);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    if (!subject.trim() || !body.trim()) { setError("Subject and description are required."); return; }
    try {
      const t = await create.mutateAsync({ subject: subject.trim(), body: body.trim(), category, priority });
      navigate({ to: "/app/support/$id", params: { id: t.id } });
    } catch (err) {
      setError(err instanceof Error ? err.message : "Failed to create ticket");
    }
  }

  return (
    <div className="max-w-2xl">
      <Link to="/app/support" className="inline-flex items-center gap-1 text-xs text-text-muted hover:text-text mb-3">
        <IconArrowLeft size={13} /> Back to tickets
      </Link>
      <PageHeader title="New support ticket" description="Report a bug, ask a question, or request a change." />

      <form onSubmit={submit} className="space-y-4 rounded-lg border bg-surface p-5">
        <div>
          <label className="text-xs font-medium text-text-muted">Subject</label>
          <TextInput value={subject} onChange={(e) => setSubject(e.target.value)} placeholder="Short summary" className="mt-1" />
        </div>
        <div className="grid grid-cols-2 gap-3">
          <div>
            <label className="text-xs font-medium text-text-muted">Category</label>
            <Selectish value={category} onChange={(e) => setCategory(e.target.value as SupportCategory)} className="mt-1">
              <option value="bug">Bug report</option>
              <option value="question">Question</option>
              <option value="request">Feature request</option>
            </Selectish>
          </div>
          <div>
            <label className="text-xs font-medium text-text-muted">Priority</label>
            <Selectish value={priority} onChange={(e) => setPriority(e.target.value as SupportPriority)} className="mt-1">
              <option value="low">Low</option>
              <option value="normal">Normal</option>
              <option value="high">High</option>
            </Selectish>
          </div>
        </div>
        <div>
          <label className="text-xs font-medium text-text-muted">Description</label>
          <textarea
            value={body}
            onChange={(e) => setBody(e.target.value)}
            rows={7}
            placeholder="Steps to reproduce, expected vs actual behavior, screenshots links, etc."
            className="mt-1 w-full rounded-md border bg-input p-2.5 text-sm focus-ring"
          />
        </div>

        {error && <p className="text-xs text-danger">{error}</p>}

        <div className="flex justify-end gap-2 pt-2 border-t">
          <Link to="/app/support"><Btn variant="outline" type="button">Cancel</Btn></Link>
          <Btn variant="primary" type="submit" disabled={create.isPending}>
            {create.isPending ? "Submitting…" : "Submit ticket"}
          </Btn>
        </div>
      </form>
    </div>
  );
}
