import { createFileRoute, Link } from "@tanstack/react-router";
import { useState } from "react";
import { PageHeader } from "@/components/ui/page-header";
import { Btn } from "@/components/ui/btn";
import { Selectish } from "@/components/ui/form-field";
import { SearchInput } from "@/components/ui/search-input";
import { DataTable, THead, Tr, Th, Td } from "@/components/ui/data-table";
import { TableStates } from "@/components/ui/table-states";
import { TablePagination } from "@/components/ui/table-pagination";
import { usePagination } from "@/hooks/use-pagination";
import { StatusBadge, statusVariantFor, formatStatus } from "@/components/ui/status-badge";
import { useDocuments } from "@/hooks/queries";

export const Route = createFileRoute("/app/documents/")({
  component: DocQueue,
});

function DocQueue() {
  const { data: docs = [], isLoading, isFetching, isError, error, refetch } = useDocuments();
  const [q, setQ] = useState("");
  const [status, setStatus] = useState("all");
  const [risk, setRisk] = useState("all");

  const filtered = docs.filter((d) => {
    if (q && !`${d.grantee_name} ${d.student_number} ${d.type} ${d.filename}`.toLowerCase().includes(q.toLowerCase())) return false;
    if (status !== "all" && d.status !== status) return false;
    if (risk === "high" && d.risk_score < 70) return false;
    if (risk === "medium" && (d.risk_score < 40 || d.risk_score >= 70)) return false;
    if (risk === "low" && d.risk_score >= 40) return false;
    return true;
  });
  const pg = usePagination(filtered, 15);


  return (
    <div>
      <PageHeader title="Document Validation Queue" description="Review submitted documents and take action." />
      <div className="rounded-lg border bg-surface p-3 mb-4 grid grid-cols-1 md:grid-cols-4 gap-2">
        <SearchInput placeholder="Search by grantee, student #, type, or file" value={q} onChange={(e) => setQ(e.target.value)} className="md:col-span-2" />
        <Selectish value={status} onChange={(e) => setStatus(e.target.value)}>
          <option value="all">All statuses</option>
          <option>pending</option><option>approved</option><option>rejected</option><option>resubmission</option><option>suspicious</option>
        </Selectish>
        <Selectish value={risk} onChange={(e) => setRisk(e.target.value)}>
          <option value="all">All risk levels</option>
          <option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option>
        </Selectish>
      </div>
      <DataTable>
        <THead>
          <Tr><Th>Grantee</Th><Th>Document Type</Th><Th>File</Th><Th>Uploaded</Th><Th>Risk</Th><Th>Status</Th><Th></Th></Tr>
        </THead>
        <tbody>
          <TableStates
            colSpan={7}
            isLoading={isLoading}
            isFetching={isFetching}
            isError={isError}
            error={error}
            isEmpty={!isLoading && !isError && filtered.length === 0}
            onRetry={() => refetch()}
            emptyTitle="No documents in this view"
            emptyHint="Try clearing filters or check back once new documents are submitted."
          />
          {pg.pageItems.map((d) => {
            const tone = d.risk_score >= 70 ? "danger" : d.risk_score >= 40 ? "warning" : "success";
            return (
              <Tr key={d.id}>
                <Td><span className="font-medium">{d.grantee_name}</span><div className="text-micro text-text-soft font-mono">{d.student_number}</div></Td>
                <Td>{d.type}</Td>
                <Td className="font-mono text-xs text-text-muted">{d.filename}</Td>
                <Td className="text-text-muted">{new Date(d.uploaded_at).toLocaleString()}</Td>
                <Td><StatusBadge variant={tone}>{d.risk_score}</StatusBadge></Td>
                <Td><StatusBadge variant={statusVariantFor(d.status)}>{formatStatus(d.status)}</StatusBadge></Td>
                <Td><Link to="/app/documents/$id" params={{ id: d.id }}><Btn size="sm">Review</Btn></Link></Td>
              </Tr>
            );
          })}
        </tbody>
      </DataTable>
      <TablePagination {...pg} onPageChange={pg.setPage} onPageSizeChange={pg.setPageSize} isLoading={isLoading} disabled={isError} className="rounded-b-lg border border-t-0 -mt-px" />
    </div>
  );
}
