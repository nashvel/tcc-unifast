import { createFileRoute, Link } from "@tanstack/react-router";
import { PageHeader } from "@/components/ui/page-header";
import { DataTable, THead, Tr, Th, Td } from "@/components/ui/data-table";
import { StatusBadge } from "@/components/ui/status-badge";
import { IconArrowLeft } from "@tabler/icons-react";

export const Route = createFileRoute("/app/announcements/logs")({
  component: () => (
    <div>
      <Link to="/app/announcements" className="inline-flex items-center gap-1 text-xs text-text-muted hover:text-text mb-3">
        <IconArrowLeft size={13} /> Back
      </Link>
      <PageHeader title="Notification Logs" description="Delivery results per channel for recent announcements." />
      <DataTable>
        <THead><Tr><Th>Announcement</Th><Th>Recipient</Th><Th>Channel</Th><Th>Status</Th><Th>Sent</Th></Tr></THead>
        <tbody>
          {[
            { t: "Submission Deadline Extended", r: "Maria Clara Dela Cruz", c: "Email", s: "delivered", at: "2026-06-19 10:01" },
            { t: "Submission Deadline Extended", r: "Juan Miguel Santos", c: "SMS", s: "delivered", at: "2026-06-19 10:01" },
            { t: "Submission Deadline Extended", r: "Andrea Reyes", c: "Email", s: "failed", at: "2026-06-19 10:01" },
            { t: "Resubmission Required", r: "Joshua Tan", c: "In-app", s: "delivered", at: "2026-06-17 08:31" },
            { t: "Resubmission Required", r: "Patricia Lim", c: "Email", s: "delivered", at: "2026-06-17 08:31" },
          ].map((r, i) => (
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
  ),
});
