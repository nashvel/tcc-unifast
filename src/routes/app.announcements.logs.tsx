import { createFileRoute, Link } from "@tanstack/react-router";
import { useMemo, useState } from "react";
import { PageHeader } from "@/components/ui/page-header";
import { SearchInput } from "@/components/ui/search-input";
import { Selectish } from "@/components/ui/form-field";
import { DataTable, THead, Tr, Th, Td } from "@/components/ui/data-table";
import { StatusBadge } from "@/components/ui/status-badge";
import { IconArrowLeft } from "@tabler/icons-react";

const LOGS = [
  { t: "Submission Deadline Extended", r: "Maria Clara Dela Cruz", c: "Email", s: "delivered", at: "2026-06-19 10:01" },
  { t: "Submission Deadline Extended", r: "Juan Miguel Santos", c: "SMS", s: "delivered", at: "2026-06-19 10:01" },
  { t: "Submission Deadline Extended", r: "Andrea Reyes", c: "Email", s: "failed", at: "2026-06-19 10:01" },
  { t: "Resubmission Required", r: "Joshua Tan", c: "In-app", s: "delivered", at: "2026-06-17 08:31" },
  { t: "Resubmission Required", r: "Patricia Lim", c: "Email", s: "delivered", at: "2026-06-17 08:31" },
];

export const Route = createFileRoute("/app/announcements/logs")({
  component: LogsPage,
});

function LogsPage() {
  const [q, setQ] = useState("");
  const [channel, setChannel] = useState("all");
  const [status, setStatus] = useState("all");

  const filtered = useMemo(() => LOGS.filter((r) => {
    if (q && !`${r.t} ${r.r}`.toLowerCase().includes(q.toLowerCase())) return false;
    if (channel !== "all" && r.c !== channel) return false;
    if (status !== "all" && r.s !== status) return false;
    return true;
  }), [q, channel, status]);

  return (
    <div>
      <Link to="/app/announcements" className="inline-flex items-center gap-1 text-xs text-text-muted hover:text-text mb-3">
        <IconArrowLeft size={13} /> Back
      </Link>
      <PageHeader title="Notification Logs" description="Delivery results per channel for recent announcements." />
      <div className="rounded-lg border bg-surface p-3 mb-4 grid grid-cols-1 md:grid-cols-4 gap-2">
        <SearchInput placeholder="Search announcement or recipient" value={q} onChange={(e) => setQ(e.target.value)} className="md:col-span-2" />
        <Selectish value={channel} onChange={(e) => setChannel(e.target.value)}>
          <option value="all">All channels</option>
          <option>Email</option><option>SMS</option><option>In-app</option>
        </Selectish>
        <Selectish value={status} onChange={(e) => setStatus(e.target.value)}>
          <option value="all">All statuses</option>
          <option>delivered</option><option>failed</option>
        </Selectish>
      </div>
      <DataTable>
        <THead><Tr><Th>Announcement</Th><Th>Recipient</Th><Th>Channel</Th><Th>Status</Th><Th>Sent</Th></Tr></THead>
        <tbody>
          {filtered.length === 0 && <Tr><Td colSpan={5} className="text-center text-text-muted py-6">No delivery logs match your filters.</Td></Tr>}
          {filtered.map((r, i) => (
            <Tr key={i}>
              <Td className="font-medium">{r.t}</Td>
              <Td>{r.r}</Td>
              <Td className="text-text-muted">{r.c}</Td>
              <Td><StatusBadge variant={r.s === "delivered" ? "success" : "danger"}>{r.s}</StatusBadge></Td>
              <Td className="text-text-muted">{r.at}</Td>
            </Tr>
          ))}
        </tbody>
      </DataTable>
    </div>
  );
}
