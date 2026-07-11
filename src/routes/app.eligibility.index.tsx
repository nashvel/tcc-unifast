import { createFileRoute, Link } from "@tanstack/react-router";
import { useMemo, useState } from "react";
import { PageHeader } from "@/components/ui/page-header";
import { Btn } from "@/components/ui/btn";
import { SearchInput } from "@/components/ui/search-input";
import { Selectish } from "@/components/ui/form-field";
import { DataTable, THead, Tr, Th, Td } from "@/components/ui/data-table";
import { StatusBadge, statusVariantFor, formatStatus } from "@/components/ui/status-badge";
import { useGrantees } from "@/hooks/queries";
import { IconDownload } from "@tabler/icons-react";

export const Route = createFileRoute("/app/eligibility/")({
  component: EligibilityPage,
});

function EligibilityPage() {
  const { data: grantees = [], isLoading } = useGrantees();
  const [q, setQ] = useState("");
  const [elig, setElig] = useState("all");
  const [risk, setRisk] = useState("all");

  const filtered = useMemo(() => grantees.filter((g) => {
    if (q && !`${g.firstName} ${g.lastName} ${g.studentNumber}`.toLowerCase().includes(q.toLowerCase())) return false;
    if (elig !== "all" && g.eligibility !== elig) return false;
    if (risk !== "all" && g.risk !== risk) return false;
    return true;
  }).slice(0, 100), [grantees, q, elig, risk]);

  return (
    <div>
      <PageHeader title="Eligibility Evaluation" description="Rules-based decisions for the current period."
        actions={<Btn variant="outline" icon={IconDownload}>Export results</Btn>} />
      <div data-tour="eligibility-filters" className="rounded-lg border bg-surface p-3 mb-4 grid grid-cols-1 md:grid-cols-4 gap-2">
        <SearchInput placeholder="Search by name or student #" value={q} onChange={(e) => setQ(e.target.value)} className="md:col-span-2" />
        <Selectish value={elig} onChange={(e) => setElig(e.target.value)}>
          <option value="all">All eligibility</option>
          <option>eligible</option><option>ineligible</option><option>pending</option><option>for_evaluation</option>
        </Selectish>
        <Selectish value={risk} onChange={(e) => setRisk(e.target.value)}>
          <option value="all">All risk</option>
          <option>low</option><option>medium</option><option>high</option>
        </Selectish>
      </div>
      <div data-tour="eligibility-table">
      <DataTable>

        <THead><Tr><Th>Student #</Th><Th>Name</Th><Th>GWA</Th><Th>Risk</Th><Th>Recommendation</Th><Th></Th></Tr></THead>
        <tbody>
          {isLoading && <Tr><Td colSpan={6} className="text-center text-text-muted py-6">Loading…</Td></Tr>}
          {!isLoading && filtered.length === 0 && <Tr><Td colSpan={6} className="text-center text-text-muted py-6">No grantees match your filters.</Td></Tr>}
          {filtered.map((g) => (
            <Tr key={g.id}>
              <Td className="font-mono text-xs">{g.studentNumber}</Td>
              <Td className="font-medium">{g.firstName} {g.lastName}</Td>
              <Td className="tabular-nums">{g.gwa.toFixed(2)}</Td>
              <Td><StatusBadge variant={statusVariantFor(g.risk)}>{formatStatus(g.risk)}</StatusBadge></Td>
              <Td><StatusBadge variant={statusVariantFor(g.eligibility)}>{formatStatus(g.eligibility)}</StatusBadge></Td>
              <Td className="text-right"><Link to="/app/eligibility/$id" params={{ id: g.id }} className="text-xs text-primary hover:underline">Evaluate</Link></Td>
            </Tr>
          ))}
        </tbody>
      </DataTable>
      </div>
    </div>

  );
}
