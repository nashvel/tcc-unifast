import { createFileRoute, Link } from "@tanstack/react-router";
import { useState } from "react";
import { PageHeader } from "@/components/ui/page-header";
import { Btn } from "@/components/ui/btn";
import { Selectish } from "@/components/ui/form-field";
import { StatusBadge } from "@/components/ui/status-badge";
import {
  useSupportTicket, useAddTicketMessage, useUpdateTicket,
  type SupportStatus, type SupportPriority,
} from "@/hooks/support-queries";
import { useAuthStore } from "@/stores/authStore";
import { IconArrowLeft, IconLock } from "@tabler/icons-react";
import { cn } from "@/lib/utils";

export const Route = createFileRoute("/app/support/$id")({
  component: TicketDetail,
});

const statusTone: Record<SupportStatus, "success" | "warning" | "primary" | "neutral"> = {
  open: "warning", in_progress: "primary", resolved: "success", closed: "neutral",
};

function TicketDetail() {
  const { id } = Route.useParams();
  const role = useAuthStore((s) => s.role);
  const userId = useAuthStore((s) => s.userId);
  const isAdmin = role === "admin";
  const { data, isLoading } = useSupportTicket(id);
  const addMsg = useAddTicketMessage(id);
  const updateTicket = useUpdateTicket(id);

  const [reply, setReply] = useState("");
  const [internal, setInternal] = useState(false);

  if (isLoading) return <p className="text-sm text-text-muted">Loading ticket…</p>;
  if (!data) return (
    <div>
      <Link to="/app/support" className="text-xs text-primary hover:underline">← Back</Link>
      <p className="mt-4 text-sm text-text-muted">Ticket not found or you don't have access.</p>
    </div>
  );

  const { ticket, messages } = data;
  const canReply = isAdmin || ticket.created_by === userId;

  async function sendReply(e: React.FormEvent) {
    e.preventDefault();
    if (!reply.trim()) return;
    await addMsg.mutateAsync({ body: reply.trim(), is_internal: isAdmin ? internal : false });
    setReply("");
    setInternal(false);
  }

  return (
    <div className="max-w-4xl">
      <Link to="/app/support" className="inline-flex items-center gap-1 text-xs text-text-muted hover:text-text mb-3">
        <IconArrowLeft size={13} /> Back to tickets
      </Link>
      <PageHeader
        title={ticket.subject}
        description={`Opened by ${ticket.creator_name ?? "Unknown"} · ${new Date(ticket.created_at).toLocaleString()}`}
      />

      <div className="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_260px] gap-4">
        <div className="space-y-4">
          {/* Original body */}
          <article className="rounded-lg border bg-surface p-4">
            <div className="flex items-center justify-between mb-2">
              <p className="text-sm font-semibold">{ticket.creator_name ?? "Reporter"}</p>
              <span className="text-2xs text-text-muted">{new Date(ticket.created_at).toLocaleString()}</span>
            </div>
            <p className="text-sm whitespace-pre-wrap text-text">{ticket.body}</p>
          </article>

          {/* Thread */}
          <div className="space-y-3">
            {messages.length === 0 && (
              <p className="text-xs text-text-muted italic">No replies yet.</p>
            )}
            {messages.map((m) => (
              <article
                key={m.id}
                className={cn(
                  "rounded-lg border p-4",
                  m.is_internal ? "border-warning/40 bg-warning-soft" : "bg-surface",
                )}
              >
                <div className="flex items-center justify-between mb-2">
                  <p className="text-sm font-semibold inline-flex items-center gap-2">
                    {m.author_name ?? "Unknown"}
                    {m.is_internal && (
                      <span className="inline-flex items-center gap-1 text-2xs font-medium text-warning">
                        <IconLock size={10} /> Internal
                      </span>
                    )}
                  </p>
                  <span className="text-2xs text-text-muted">{new Date(m.created_at).toLocaleString()}</span>
                </div>
                <p className="text-sm whitespace-pre-wrap">{m.body}</p>
              </article>
            ))}
          </div>

          {/* Reply */}
          {canReply && ticket.status !== "closed" ? (
            <form onSubmit={sendReply} className="rounded-lg border bg-surface p-4 space-y-3">
              <textarea
                value={reply}
                onChange={(e) => setReply(e.target.value)}
                rows={4}
                placeholder="Write a reply…"
                className="w-full rounded-md border bg-input p-2.5 text-sm focus-ring"
              />
              <div className="flex items-center justify-between">
                {isAdmin ? (
                  <label className="inline-flex items-center gap-2 text-xs text-text-muted">
                    <input type="checkbox" checked={internal} onChange={(e) => setInternal(e.target.checked)} />
                    Internal note (only admins see this)
                  </label>
                ) : <span />}
                <Btn variant="primary" type="submit" disabled={addMsg.isPending || !reply.trim()}>
                  {addMsg.isPending ? "Sending…" : "Send reply"}
                </Btn>
              </div>
            </form>
          ) : (
            <p className="text-xs text-text-muted italic">
              {ticket.status === "closed" ? "This ticket is closed." : "You can't reply to this ticket."}
            </p>
          )}
        </div>

        {/* Sidebar */}
        <aside className="space-y-3">
          <div className="rounded-lg border bg-surface p-4 space-y-3">
            <Field label="Status">
              {isAdmin ? (
                <Selectish
                  value={ticket.status}
                  onChange={(e) => updateTicket.mutate({ status: e.target.value as SupportStatus })}
                >
                  <option value="open">Open</option>
                  <option value="in_progress">In progress</option>
                  <option value="resolved">Resolved</option>
                  <option value="closed">Closed</option>
                </Selectish>
              ) : (
                <StatusBadge variant={statusTone[ticket.status]}>{ticket.status.replace("_"," ")}</StatusBadge>
              )}
            </Field>
            <Field label="Priority">
              {isAdmin ? (
                <Selectish
                  value={ticket.priority}
                  onChange={(e) => updateTicket.mutate({ priority: e.target.value as SupportPriority })}
                >
                  <option value="low">Low</option>
                  <option value="normal">Normal</option>
                  <option value="high">High</option>
                </Selectish>
              ) : (
                <StatusBadge variant={ticket.priority === "high" ? "danger" : ticket.priority === "normal" ? "primary" : "neutral"}>
                  {ticket.priority}
                </StatusBadge>
              )}
            </Field>
            <Field label="Category">
              <span className="text-sm capitalize">{ticket.category}</span>
            </Field>
            <Field label="Reporter">
              <span className="text-sm">{ticket.creator_name ?? "—"}</span>
            </Field>
            <Field label="Assignee">
              <span className="text-sm">{ticket.assignee_name ?? "Unassigned"}</span>
              {isAdmin && ticket.assigned_to !== userId && (
                <button
                  className="mt-1 text-xs text-primary hover:underline"
                  onClick={() => updateTicket.mutate({ assigned_to: userId })}
                >
                  Assign to me
                </button>
              )}
            </Field>
          </div>
        </aside>
      </div>
    </div>
  );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div>
      <p className="text-2xs uppercase tracking-wide text-text-muted font-medium mb-1">{label}</p>
      <div>{children}</div>
    </div>
  );
}
