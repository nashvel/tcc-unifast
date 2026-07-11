import { createFileRoute, Link } from "@tanstack/react-router";
import { useState } from "react";
import { PageHeader } from "@/components/ui/page-header";
import { Btn } from "@/components/ui/btn";
import { FileUpload } from "@/components/ui/file-upload";
import { SearchInput } from "@/components/ui/search-input";
import { Selectish } from "@/components/ui/form-field";
import { DataTable, THead, Tr, Th, Td } from "@/components/ui/data-table";
import { TableStates } from "@/components/ui/table-states";
import { TablePagination } from "@/components/ui/table-pagination";
import { usePagination } from "@/hooks/use-pagination";
import { StatusBadge, statusVariantFor, formatStatus } from "@/components/ui/status-badge";
import { useMasterlist } from "@/hooks/queries";
import { useAuthStore } from "@/stores/authStore";
import { IconAlertTriangle, IconUpload, IconCheck, IconArrowRight } from "@tabler/icons-react";

export const Route = createFileRoute("/app/masterlist")({
  component: MasterlistPage,
});

function MasterlistPage() {
  const { data: rows = [], isLoading, isFetching, isError, error, refetch } = useMasterlist();
  const canWrite = useAuthStore((s) => s.role) !== "admin";
  const [previewed, setPreviewed] = useState(false);
  const [q, setQ] = useState("");
  const [statusF, setStatusF] = useState("all");
  const filteredRows = rows.filter((r) => {
    if (q && !`${r.student_number ?? ""} ${r.first_name} ${r.last_name} ${r.university} ${r.program}`.toLowerCase().includes(q.toLowerCase())) return false;
    if (statusF !== "all" && r.account_status !== statusF) return false;
    return true;
  });
  const pg = usePagination(filteredRows, 20);




  const counts = {
    total: rows.length,
    active: rows.filter((r) => r.account_status === "active").length,
    duplicate: rows.filter((r) => r.account_status === "duplicate").length,
    invalid: rows.filter((r) => r.account_status === "invalid").length,
    pending: rows.filter((r) => r.account_status === "pending_activation").length,
    inactive: rows.filter((r) => r.account_status === "inactive").length,
  };

  return (
    <div>
      <PageHeader
        title="Masterlist Import"
        description="Upload the TES masterlist. Student accounts are auto-generated as inactive and require manual activation."
        actions={
          <>
            <Btn variant="outline" icon={IconUpload}>Download template</Btn>
            {canWrite && <Btn variant="primary" icon={IconArrowRight} onClick={() => setPreviewed(true)}>Process import</Btn>}
          </>
        }
      />

      {!previewed ? (
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
          <div className="lg:col-span-2">
            <FileUpload hint="CSV or XLSX up to 20MB" />
          </div>
          <div className="rounded-lg border bg-surface p-4">
            <p className="text-sm font-semibold">Import rules</p>
            <ul className="text-xs text-text-muted mt-2 space-y-1.5 list-disc list-inside">
              <li>Student accounts are <strong>auto-generated</strong> from the masterlist.</li>
              <li>All new accounts are <strong>inactive</strong> by default.</li>
              <li>Students must <strong>self-activate</strong> by verifying student #, birthdate, and email/contact.</li>
              <li>Duplicate rows (same student #) are flagged and skipped.</li>
              <li>Rows with invalid email, missing student #, or malformed data are marked invalid.</li>
            </ul>
          </div>
        </div>
      ) : (
        <div className="space-y-4">
          <div className="grid grid-cols-2 sm:grid-cols-6 gap-3">
            <Stat label="Total rows" value={counts.total} />
            <Stat label="Active" value={counts.active} variant="success" />
            <Stat label="Pending activation" value={counts.pending} variant="warning" />
            <Stat label="Inactive" value={counts.inactive} variant="neutral" />
            <Stat label="Duplicate" value={counts.duplicate} variant="info" />
            <Stat label="Invalid" value={counts.invalid} variant="danger" />
          </div>

          {(counts.duplicate > 0 || counts.invalid > 0) && (
            <div className="rounded-lg border border-warning/30 bg-warning-soft p-3 text-xs flex gap-2 items-start">
              <IconAlertTriangle size={14} className="text-warning shrink-0 mt-0.5" />
              <div>
                <p className="font-medium text-warning">Some rows need attention</p>
                <p className="text-text-muted mt-0.5">
                  {counts.duplicate} duplicate row(s) and {counts.invalid} invalid row(s) detected. Review them below — duplicates will be skipped and invalid rows will not create accounts.
                </p>
              </div>
            </div>
          )}

          <div className="rounded-lg border bg-surface p-3 grid grid-cols-1 md:grid-cols-4 gap-2">
            <SearchInput placeholder="Search by student #, name, university, or program" value={q} onChange={(e) => setQ(e.target.value)} className="md:col-span-3" />
            <Selectish value={statusF} onChange={(e) => setStatusF(e.target.value)}>
              <option value="all">All statuses</option>
              <option value="active">Active</option>
              <option value="pending_activation">Pending activation</option>
              <option value="inactive">Inactive</option>
              <option value="duplicate">Duplicate</option>
              <option value="invalid">Invalid</option>
            </Selectish>
          </div>

          <DataTable>
            <THead>
              <Tr>
                <Th>Student #</Th><Th>Name</Th><Th>University</Th><Th>Program</Th><Th>Year</Th><Th>Account Status</Th><Th></Th>
              </Tr>
            </THead>
            <tbody>
              <TableStates
                colSpan={7}
                isLoading={isLoading}
                isFetching={isFetching}
                isError={isError}
                error={error}
                isEmpty={!isLoading && !isError && rows.length === 0}
                onRetry={() => refetch()}
                emptyTitle="No rows yet"
                emptyHint="Upload a masterlist CSV or XLSX to preview rows here."
              />
              {pg.pageItems.map((r) => (
                <Tr key={r.id}>
                  <Td className="font-mono text-xs">{r.student_number || <span className="text-danger italic">missing</span>}</Td>
                  <Td>{r.first_name} {r.last_name}</Td>
                  <Td className="text-text-muted">{r.university}</Td>
                  <Td className="text-text-muted">{r.program}</Td>
                  <Td>{r.year_level}</Td>
                  <Td>
                    <StatusBadge variant={statusVariantFor(r.account_status)}>{formatStatus(r.account_status)}</StatusBadge>
                  </Td>
                  <Td className="text-right"><Link to="/app/grantees" className="text-xs text-primary hover:underline">View</Link></Td>
                </Tr>
              ))}
            </tbody>
          </DataTable>
          <TablePagination {...pg} onPageChange={pg.setPage} onPageSizeChange={pg.setPageSize} isLoading={isLoading} disabled={isError} className="rounded-b-lg border border-t-0 -mt-px" />

          <div className="flex justify-end gap-2">
            <Btn variant="outline" onClick={() => setPreviewed(false)}>Cancel</Btn>
            <Btn variant="primary" icon={IconCheck}>Confirm import ({counts.total - counts.duplicate - counts.invalid} accounts)</Btn>
          </div>
        </div>
      )}
    </div>
  );
}

function Stat({ label, value, variant = "neutral" }: { label: string; value: number; variant?: "success" | "warning" | "danger" | "info" | "neutral" }) {
  const tones: Record<string, string> = {
    success: "text-success", warning: "text-warning", danger: "text-danger", info: "text-info", neutral: "text-text",
  };
  return (
    <div className="rounded-lg border bg-surface p-3">
      <p className="text-xs text-text-muted">{label}</p>
      <p className={`text-xl font-semibold tabular-nums mt-0.5 ${tones[variant]}`}>{value}</p>
    </div>
  );
}
