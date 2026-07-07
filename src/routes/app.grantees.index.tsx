import { createFileRoute, Link } from "@tanstack/react-router";
import { useMemo, useState } from "react";
import { PageHeader } from "@/components/ui/page-header";
import { Btn } from "@/components/ui/btn";
import { SearchInput } from "@/components/ui/search-input";
import { Selectish } from "@/components/ui/form-field";
import { DataTable, THead, Tr, Th, Td } from "@/components/ui/data-table";
import { TableStates } from "@/components/ui/table-states";
import { TablePagination } from "@/components/ui/table-pagination";
import { usePagination } from "@/hooks/use-pagination";
import { StatusBadge, statusVariantFor, formatStatus } from "@/components/ui/status-badge";
import { useGrantees, useBatches } from "@/hooks/queries";
import { IconDownload } from "@tabler/icons-react";
import { downloadCSV } from "@/lib/csv";

export const Route = createFileRoute("/app/grantees/")({
  component: GranteeList,
});



function GranteeList() {
  const { data: grantees = [], isLoading, isFetching, isError, error, refetch } = useGrantees();
  const { data: batches = [] } = useBatches();
  const [q, setQ] = useState("");
  const [batch, setBatch] = useState("all");
  const [acc, setAcc] = useState("all");
  const [sub, setSub] = useState("all");
  const [elig, setElig] = useState("all");
  const [risk, setRisk] = useState("all");

  const filtered = useMemo(() => grantees.filter((g) => {
    if (q && !`${g.firstName} ${g.lastName} ${g.studentNumber}`.toLowerCase().includes(q.toLowerCase())) return false;
    if (batch !== "all" && g.batchId !== batch) return false;
    if (acc !== "all" && g.accountStatus !== acc) return false;
    if (sub !== "all" && g.submissionStatus !== sub) return false;
    if (elig !== "all" && g.eligibility !== elig) return false;
    if (risk !== "all" && g.risk !== risk) return false;
    return true;
  }), [grantees, q, batch, acc, sub, elig, risk]);

  const pg = usePagination(filtered, 15);

  return (
    <div>
      <PageHeader
        title="Grantees"
        description="Search, filter, and manage TES grantee records."
        actions={<Btn variant="outline" icon={IconDownload} onClick={() => downloadCSV("grantees.csv", filtered)}>Export CSV</Btn>}
      />

      <div className="rounded-lg border bg-surface p-3 mb-4 grid grid-cols-1 md:grid-cols-6 gap-2">
        <SearchInput placeholder="Search by name or student #" value={q} onChange={(e) => setQ(e.target.value)} className="md:col-span-2" />
        <Selectish value={batch} onChange={(e) => setBatch(e.target.value)}>
          <option value="all">All batches</option>
          {batches.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
        </Selectish>
        <Selectish value={acc} onChange={(e) => setAcc(e.target.value)}>
          <option value="all">All accounts</option>
          <option>active</option><option>inactive</option><option>pending_activation</option><option>locked</option>
        </Selectish>
        <Selectish value={sub} onChange={(e) => setSub(e.target.value)}>
          <option value="all">All submissions</option>
          <option>not_submitted</option><option>submitted</option><option>under_review</option>
          <option>approved</option><option>rejected</option><option>resubmission_required</option>
        </Selectish>
        <div className="grid grid-cols-2 gap-2 md:contents">
          <Selectish value={elig} onChange={(e) => setElig(e.target.value)}>
            <option value="all">All eligibility</option>
            <option>eligible</option><option>ineligible</option><option>pending</option><option>for_evaluation</option>
          </Selectish>
          <Selectish value={risk} onChange={(e) => setRisk(e.target.value)}>
            <option value="all">All risk</option>
            <option>low</option><option>medium</option><option>high</option>
          </Selectish>
        </div>
      </div>

      <DataTable>
        <THead>
          <Tr><Th>Student #</Th><Th>Name</Th><Th>Program</Th><Th>Batch</Th><Th>Account</Th><Th>Submission</Th><Th>Eligibility</Th><Th>Risk</Th></Tr>
        </THead>
        <tbody>
          <TableStates
            colSpan={8}
            isLoading={isLoading}
            isFetching={isFetching}
            isError={isError}
            error={error}
            isEmpty={!isLoading && !isError && pg.pageItems.length === 0}
            onRetry={() => refetch()}
            emptyTitle="No grantees found"
            emptyHint="Adjust filters or import a masterlist to populate records."
          />
          {pg.pageItems.map((g) => (
            <Tr key={g.id}>
              <Td className="font-mono text-xs">{g.studentNumber}</Td>
              <Td><Link to="/app/grantees/$id" params={{ id: g.id }} className="font-medium hover:text-primary">{g.firstName} {g.lastName}</Link></Td>
              <Td className="text-text-muted truncate max-w-[180px]">{g.program}</Td>
              <Td className="text-text-muted">{g.batch}</Td>
              <Td><StatusBadge variant={statusVariantFor(g.accountStatus)}>{formatStatus(g.accountStatus)}</StatusBadge></Td>
              <Td><StatusBadge variant={statusVariantFor(g.submissionStatus)}>{formatStatus(g.submissionStatus)}</StatusBadge></Td>
              <Td><StatusBadge variant={statusVariantFor(g.eligibility)}>{formatStatus(g.eligibility)}</StatusBadge></Td>
              <Td><StatusBadge variant={statusVariantFor(g.risk)}>{formatStatus(g.risk)}</StatusBadge></Td>
            </Tr>
          ))}
        </tbody>
      </DataTable>
      <TablePagination {...pg} onPageChange={pg.setPage} onPageSizeChange={pg.setPageSize} className="rounded-b-lg border border-t-0 -mt-px" />
    </div>
  );
}
