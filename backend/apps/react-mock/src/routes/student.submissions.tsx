import { createFileRoute } from "@tanstack/react-router";
import { useMemo, useState } from "react";
import { PageHeader } from "@/components/ui/page-header";
import { SearchInput } from "@/components/ui/search-input";
import { Selectish } from "@/components/ui/form-field";
import { DataTable, THead, Tr, Th, Td } from "@/components/ui/data-table";
import { StatusBadge, statusVariantFor, formatStatus } from "@/components/ui/status-badge";
import { useDocuments } from "@/hooks/queries";
import { requiredDocs } from "@/data/mockDocuments";

export const Route = createFileRoute("/student/submissions")({
  component: SubmissionsPage,
});

function SubmissionsPage() {
  const { data: all = [], isLoading } = useDocuments({ ownerOnly: true });
  const [q, setQ] = useState("");
  const [status, setStatus] = useState("all");

  const filtered = useMemo(() => all
    .filter((d) => requiredDocs.includes(d.type))
    .filter((d) => {
      if (q && !`${d.type} ${d.filename}`.toLowerCase().includes(q.toLowerCase())) return false;
      if (status !== "all" && d.status !== status) return false;
      return true;
    }), [all, q, status]);

  return (
    <div>
      <PageHeader title="Submission Status" description="Status and history of your uploaded documents." />
      <div className="rounded-lg border bg-surface p-3 mb-4 grid grid-cols-1 md:grid-cols-4 gap-2">
        <SearchInput placeholder="Search by document or file name" value={q} onChange={(e) => setQ(e.target.value)} className="md:col-span-3" />
        <Selectish value={status} onChange={(e) => setStatus(e.target.value)}>
          <option value="all">All statuses</option>
          <option>pending</option><option>approved</option><option>rejected</option><option>resubmission</option>
        </Selectish>
      </div>
      <DataTable>
        <THead><Tr><Th>Document</Th><Th>File</Th><Th>Uploaded</Th><Th>Status</Th><Th>Remarks</Th></Tr></THead>
        <tbody>
          {isLoading && <Tr><Td colSpan={5} className="text-center text-text-muted py-6">Loading…</Td></Tr>}
          {!isLoading && filtered.length === 0 && <Tr><Td colSpan={5} className="text-center text-text-muted py-6">No submissions match your filters.</Td></Tr>}
          {filtered.map((d) => (
            <Tr key={d.id}>
              <Td className="font-medium">{d.type}</Td>
              <Td className="font-mono text-xs text-text-muted">{d.filename}</Td>
              <Td className="text-text-muted">{new Date(d.uploaded_at).toLocaleString()}</Td>
              <Td><StatusBadge variant={statusVariantFor(d.status)}>{formatStatus(d.status)}</StatusBadge></Td>
              <Td className="text-text-muted text-xs">{d.remarks ?? "—"}</Td>
            </Tr>
          ))}
        </tbody>
      </DataTable>
    </div>
  );
}
