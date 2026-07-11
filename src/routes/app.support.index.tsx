import { createFileRoute, Link } from "@tanstack/react-router";
import { useMemo, useState } from "react";
import { PageHeader } from "@/components/ui/page-header";
import { Btn } from "@/components/ui/btn";
import { SearchInput } from "@/components/ui/search-input";
import { Selectish } from "@/components/ui/form-field";
import { DataTable, THead, Tr, Th, Td } from "@/components/ui/data-table";
import { StatusBadge } from "@/components/ui/status-badge";
import { useSupportTickets, type SupportStatus } from "@/hooks/support-queries";
import { IconPlus, IconBug, IconHelp, IconMessage } from "@tabler/icons-react";

export const Route = createFileRoute("/app/support/")({
  component: SupportListPage,
});

const statusTone: Record<SupportStatus, "success" | "warning" | "primary" | "neutral"> = {
  open: "warning",
  in_progress: "primary",
  resolved: "success",
  closed: "neutral",
};

const catIcon = { bug: IconBug, question: IconHelp, request: IconMessage } as const;

function SupportListPage() {
  const { data: tickets = [], isLoading } = useSupportTickets();
  const [q, setQ] = useState("");
  const [status, setStatus] = useState("all");
  const [category, setCategory] = useState("all");
  const [priority, setPriority] = useState("all");

  const filtered = useMemo(() => tickets.filter((t) => {
    if (q && !`${t.subject} ${t.creator_name ?? ""}`.toLowerCase().includes(q.toLowerCase())) return false;
    if (status !== "all" && t.status !== status) return false;
    if (category !== "all" && t.category !== category) return false;
    if (priority !== "all" && t.priority !== priority) return false;
    return true;
  }), [tickets, q, status, category, priority]);

  return (
    <div>
      <PageHeader
        title="Support Tickets"
        description="Raise bug reports, questions, or requests. Admins respond and track them here."
        actions={<Link to="/app/support/new"><Btn variant="primary" icon={IconPlus}>New ticket</Btn></Link>}
      />

      <div className="rounded-lg border bg-surface p-3 mb-4 grid grid-cols-1 md:grid-cols-5 gap-2">
        <SearchInput placeholder="Search subject or reporter" value={q} onChange={(e) => setQ(e.target.value)} className="md:col-span-2" />
        <Selectish value={status} onChange={(e) => setStatus(e.target.value)}>
          <option value="all">All statuses</option>
          <option value="open">Open</option><option value="in_progress">In progress</option>
          <option value="resolved">Resolved</option><option value="closed">Closed</option>
        </Selectish>
        <Selectish value={category} onChange={(e) => setCategory(e.target.value)}>
          <option value="all">All categories</option>
          <option value="bug">Bug</option><option value="question">Question</option><option value="request">Request</option>
        </Selectish>
        <Selectish value={priority} onChange={(e) => setPriority(e.target.value)}>
          <option value="all">All priorities</option>
          <option value="high">High</option><option value="normal">Normal</option><option value="low">Low</option>
        </Selectish>
      </div>

      <DataTable>
        <THead>
          <Tr>
            <Th>Subject</Th><Th>Category</Th><Th>Priority</Th><Th>Status</Th>
            <Th>Reporter</Th><Th>Assignee</Th><Th>Updated</Th><Th></Th>
          </Tr>
        </THead>
        <tbody>
          {isLoading && <Tr><Td colSpan={8} className="text-center text-text-muted py-6">Loading…</Td></Tr>}
          {!isLoading && filtered.length === 0 && (
            <Tr><Td colSpan={8} className="text-center text-text-muted py-6">No tickets match your filters.</Td></Tr>
          )}
          {filtered.map((t) => {
            const Icon = catIcon[t.category];
            return (
              <Tr key={t.id}>
                <Td>
                  <Link to="/app/support/$id" params={{ id: t.id }} className="font-medium hover:text-primary">
                    {t.subject}
                  </Link>
                </Td>
                <Td><span className="inline-flex items-center gap-1.5 text-xs capitalize"><Icon size={13} className="text-text-muted" />{t.category}</span></Td>
                <Td>
                  <StatusBadge variant={t.priority === "high" ? "danger" : t.priority === "normal" ? "primary" : "neutral"}>
                    {t.priority}
                  </StatusBadge>
                </Td>
                <Td><StatusBadge variant={statusTone[t.status]}>{t.status.replace("_"," ")}</StatusBadge></Td>
                <Td className="text-text-muted">{t.creator_name ?? "—"}</Td>
                <Td className="text-text-muted">{t.assignee_name ?? "—"}</Td>
                <Td className="text-text-muted whitespace-nowrap">{new Date(t.updated_at).toLocaleString()}</Td>
                <Td className="text-right">
                  <Link to="/app/support/$id" params={{ id: t.id }} className="text-xs text-primary hover:underline">Open</Link>
                </Td>
              </Tr>
            );
          })}
        </tbody>
      </DataTable>
    </div>
  );
}
