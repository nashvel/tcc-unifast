import { createFileRoute, Link } from "@tanstack/react-router";
import { useMemo, useState } from "react";
import { PageHeader } from "@/components/ui/page-header";
import { Btn } from "@/components/ui/btn";
import { SearchInput } from "@/components/ui/search-input";
import { Selectish } from "@/components/ui/form-field";
import { DataTable, THead, Tr, Th, Td } from "@/components/ui/data-table";
import { StatusBadge, statusVariantFor, formatStatus } from "@/components/ui/status-badge";
import { mockGrantees } from "@/data/mockGrantees";
import { mockBatches } from "@/data/mockBatches";
import { IconDownload, IconChevronLeft, IconChevronRight } from "@tabler/icons-react";

export const Route = createFileRoute("/app/grantees/")({
  component: GranteeList,
});

const PAGE = 15;

function GranteeList() {
  const [q, setQ] = useState("");
  const [batch, setBatch] = useState("all");
  const [acc, setAcc] = useState("all");
  const [sub, setSub] = useState("all");
  const [elig, setElig] = useState("all");
  const [risk, setRisk] = useState("all");
  const [page, setPage] = useState(1);

  const filtered = useMemo(() => mockGrantees.filter((g) => {
    if (q && !`${g.firstName} ${g.lastName} ${g.studentNumber}`.toLowerCase().includes(q.toLowerCase())) return false;
    if (batch !== "all" && g.batchId !== batch) return false;
    if (acc !== "all" && g.accountStatus !== acc) return false;
    if (sub !== "all" && g.submissionStatus !== sub) return false;
    if (elig !== "all" && g.eligibility !== elig) return false;
    if (risk !== "all" && g.risk !== risk) return false;
    return true;
  }), [q, batch, acc, sub, elig, risk]);

  const pages = Math.max(1, Math.ceil(filtered.length / PAGE));
  const visible = filtered.slice((page - 1) * PAGE, page * PAGE);

  return (
    <div>
      <PageHeader
        title="Grantees"
        description="Search, filter, and manage TES grantee records."
        actions={<Btn variant="outline" icon={IconDownload}>Export</Btn>}
      />

      <div className="rounded-lg border bg-surface p-3 mb-4 grid grid-cols-1 md:grid-cols-6 gap-2">
        <SearchInput placeholder="Search by name or student #" value={q} onChange={(e) => setQ(e.target.value)} className="md:col-span-2" />
        <Selectish value={batch} onChange={(e) => setBatch(e.target.value)}>
          <option value="all">All batches</option>
          {mockBatches.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
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
          {visible.map((g) => (
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

      <div className="flex items-center justify-between mt-3 text-xs text-text-muted">
        <span>Showing {Math.min(filtered.length, (page - 1) * PAGE + 1)}–{Math.min(filtered.length, page * PAGE)} of {filtered.length}</span>
        <div className="flex items-center gap-1">
          <button disabled={page <= 1} onClick={() => setPage((p) => p - 1)} className="h-7 w-7 grid place-items-center rounded border disabled:opacity-40 hover:bg-surface-muted"><IconChevronLeft size={14} /></button>
          <span className="px-2">Page {page} / {pages}</span>
          <button disabled={page >= pages} onClick={() => setPage((p) => p + 1)} className="h-7 w-7 grid place-items-center rounded border disabled:opacity-40 hover:bg-surface-muted"><IconChevronRight size={14} /></button>
        </div>
      </div>
    </div>
  );
}
