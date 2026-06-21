import { createFileRoute, Link } from "@tanstack/react-router";
import { PageHeader } from "@/components/ui/page-header";
import { Btn } from "@/components/ui/btn";
import { DataTable, THead, Tr, Th, Td } from "@/components/ui/data-table";
import { StatusBadge, statusVariantFor, formatStatus } from "@/components/ui/status-badge";
import { useGrantees, type GranteeRow } from "@/hooks/queries";
import { downloadCSV } from "@/lib/csv";
import { IconArrowLeft, IconFileTypePdf, IconFileSpreadsheet } from "@tabler/icons-react";
import { toast } from "sonner";

export const Route = createFileRoute("/app/reports/preview")({
  component: ReportPreview,
});

const REPORT_TITLE = "Grantee List Report";
const REPORT_SUB = "AY 2024-2025 • 1st Semester";

function ReportPreview() {
  const { data: grantees = [], isLoading } = useGrantees();
  const generatedAt = new Date().toISOString().slice(0, 10);

  const exportCSV = () => {
    if (!grantees.length) return toast.error("No data to export");
    const rows = grantees.map((g) => ({
      student_number: g.studentNumber,
      last_name: g.lastName,
      first_name: g.firstName,
      university: g.university,
      program: g.program,
      year_level: g.yearLevel,
      batch: g.batch,
      submission: formatStatus(g.submissionStatus),
      eligibility: formatStatus(g.eligibility),
      gwa: g.gwa,
    }));
    downloadCSV(`grantee-list-${generatedAt}.csv`, rows);
    toast.success(`Exported ${rows.length} rows to CSV`);
  };

  const exportPDF = () => {
    if (!grantees.length) return toast.error("No data to export");
    openPrintablePDF(grantees, generatedAt);
  };

  return (
    <div>
      <Link to="/app/reports/generate" className="inline-flex items-center gap-1 text-xs text-text-muted hover:text-text mb-3">
        <IconArrowLeft size={13} /> Back
      </Link>
      <PageHeader
        title="Report Preview"
        description={`${REPORT_TITLE} — ${REPORT_SUB}`}
        actions={
          <>
            <Btn variant="outline" icon={IconFileSpreadsheet} onClick={exportCSV} disabled={isLoading}>CSV</Btn>
            <Btn variant="primary" icon={IconFileTypePdf} onClick={exportPDF} disabled={isLoading}>PDF</Btn>
          </>
        }
      />
      <div className="rounded-lg border bg-surface p-6">
        <div className="text-center mb-6">
          <p className="text-[10px] uppercase tracking-wider font-semibold text-text-soft">Commission on Higher Education — UniFAST</p>
          <p className="text-lg font-semibold mt-1">{REPORT_TITLE}</p>
          <p className="text-xs text-text-muted">{REPORT_SUB} • Generated {generatedAt} • {grantees.length} records</p>
        </div>
        <DataTable>
          <THead><Tr><Th>Student #</Th><Th>Name</Th><Th>University</Th><Th>Submission</Th><Th>Eligibility</Th></Tr></THead>
          <tbody>
            {grantees.slice(0, 12).map((g) => (
              <Tr key={g.id}>
                <Td className="font-mono text-xs">{g.studentNumber}</Td>
                <Td className="font-medium">{g.firstName} {g.lastName}</Td>
                <Td className="text-text-muted">{g.university}</Td>
                <Td><StatusBadge variant={statusVariantFor(g.submissionStatus)}>{formatStatus(g.submissionStatus)}</StatusBadge></Td>
                <Td><StatusBadge variant={statusVariantFor(g.eligibility)}>{formatStatus(g.eligibility)}</StatusBadge></Td>
              </Tr>
            ))}
          </tbody>
        </DataTable>
        {grantees.length > 12 && (
          <p className="text-[11px] text-text-muted mt-3 text-center">
            Showing 12 of {grantees.length} — export to view all.
          </p>
        )}
      </div>
    </div>
  );
}

function escapeHtml(v: unknown) {
  return String(v ?? "").replace(/[&<>"']/g, (c) =>
    ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]!),
  );
}

function openPrintablePDF(rows: GranteeRow[], generatedAt: string) {
  const w = window.open("", "_blank", "width=1024,height=768");
  if (!w) return toast.error("Pop-up blocked — allow pop-ups to export PDF");
  const body = rows
    .map(
      (g) => `<tr>
        <td>${escapeHtml(g.studentNumber)}</td>
        <td>${escapeHtml(g.lastName)}, ${escapeHtml(g.firstName)}</td>
        <td>${escapeHtml(g.university)}</td>
        <td>${escapeHtml(g.program)}</td>
        <td>${escapeHtml(formatStatus(g.submissionStatus))}</td>
        <td>${escapeHtml(formatStatus(g.eligibility))}</td>
        <td style="text-align:right">${escapeHtml(g.gwa)}</td>
      </tr>`,
    )
    .join("");
  w.document.write(`<!doctype html><html><head><meta charset="utf-8" />
    <title>${REPORT_TITLE} ${generatedAt}</title>
    <style>
      *{box-sizing:border-box}
      body{font-family:-apple-system,system-ui,Segoe UI,Roboto,sans-serif;color:#111;margin:32px;font-size:12px}
      h1{font-size:18px;margin:0}
      .sub{color:#555;font-size:11px;margin-top:4px}
      .head{text-align:center;margin-bottom:20px;border-bottom:1px solid #ddd;padding-bottom:12px}
      .tag{font-size:10px;letter-spacing:.08em;text-transform:uppercase;color:#666}
      table{width:100%;border-collapse:collapse;margin-top:8px}
      th,td{border-bottom:1px solid #eee;padding:6px 8px;text-align:left;vertical-align:top}
      th{background:#f7f7f8;font-size:10px;text-transform:uppercase;letter-spacing:.05em;color:#444}
      tr:nth-child(even) td{background:#fafafa}
      .foot{margin-top:16px;font-size:10px;color:#777;text-align:right}
      @media print{body{margin:14mm}}
    </style></head><body>
    <div class="head">
      <div class="tag">Commission on Higher Education — UniFAST</div>
      <h1>${REPORT_TITLE}</h1>
      <div class="sub">${REPORT_SUB} • Generated ${generatedAt} • ${rows.length} records</div>
    </div>
    <table>
      <thead><tr><th>Student #</th><th>Name</th><th>University</th><th>Program</th><th>Submission</th><th>Eligibility</th><th style="text-align:right">GWA</th></tr></thead>
      <tbody>${body}</tbody>
    </table>
    <div class="foot">Confidential — generated from live records under your access scope.</div>
    <script>window.addEventListener('load',()=>{setTimeout(()=>window.print(),200)})</script>
    </body></html>`);
  w.document.close();
}
