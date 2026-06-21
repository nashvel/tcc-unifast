import { createFileRoute, Link } from "@tanstack/react-router";
import { useMemo, useState } from "react";
import { PageHeader } from "@/components/ui/page-header";
import { Btn } from "@/components/ui/btn";
import { DataTable, THead, Tr, Th, Td } from "@/components/ui/data-table";
import { StatusBadge, statusVariantFor, formatStatus } from "@/components/ui/status-badge";
import { useGrantees, type GranteeRow } from "@/hooks/queries";
import { downloadCSV } from "@/lib/csv";
import { IconArrowLeft, IconFileTypePdf, IconFileSpreadsheet, IconGripVertical, IconX } from "@tabler/icons-react";
import { toast } from "sonner";

export const Route = createFileRoute("/app/reports/preview")({
  component: ReportPreview,
});

const REPORT_TITLE = "Grantee List Report";
const REPORT_SUB = "AY 2024-2025 • 1st Semester";

type FieldKey =
  | "studentNumber"
  | "lastName"
  | "firstName"
  | "fullName"
  | "email"
  | "university"
  | "program"
  | "yearLevel"
  | "batch"
  | "submissionStatus"
  | "eligibility"
  | "gwa"
  | "risk";

const FIELDS: { key: FieldKey; label: string; get: (g: GranteeRow) => string | number }[] = [
  { key: "studentNumber", label: "Student #", get: (g) => g.studentNumber },
  { key: "lastName", label: "Last Name", get: (g) => g.lastName },
  { key: "firstName", label: "First Name", get: (g) => g.firstName },
  { key: "fullName", label: "Full Name", get: (g) => `${g.lastName}, ${g.firstName}` },
  { key: "email", label: "Email", get: (g) => (g as unknown as { email?: string }).email ?? "" },
  { key: "university", label: "University", get: (g) => g.university },
  { key: "program", label: "Program", get: (g) => g.program },
  { key: "yearLevel", label: "Year Level", get: (g) => g.yearLevel },
  { key: "batch", label: "Batch", get: (g) => g.batch },
  { key: "submissionStatus", label: "Submission", get: (g) => formatStatus(g.submissionStatus) },
  { key: "eligibility", label: "Eligibility", get: (g) => formatStatus(g.eligibility) },
  { key: "gwa", label: "GWA", get: (g) => g.gwa },
  { key: "risk", label: "Risk", get: (g) => formatStatus((g as unknown as { risk?: string }).risk ?? "") },
];

const FIELD_MAP = Object.fromEntries(FIELDS.map((f) => [f.key, f])) as Record<FieldKey, (typeof FIELDS)[number]>;

type Template = { id: string; name: string; fields: FieldKey[] };

const CSV_TEMPLATES: Template[] = [
  { id: "full", name: "Full Roster", fields: ["studentNumber", "lastName", "firstName", "email", "university", "program", "yearLevel", "batch", "submissionStatus", "eligibility", "gwa"] },
  { id: "compliance", name: "Compliance", fields: ["studentNumber", "fullName", "university", "submissionStatus", "eligibility", "risk"] },
  { id: "academic", name: "Academic", fields: ["studentNumber", "fullName", "program", "yearLevel", "gwa", "eligibility"] },
  { id: "contact", name: "Contact Sheet", fields: ["studentNumber", "fullName", "email", "university", "program"] },
];

type PdfLayout = "table" | "compact" | "cards";
const PDF_LAYOUTS: { id: PdfLayout; name: string; desc: string }[] = [
  { id: "table", name: "Table", desc: "Dense rows with borders" },
  { id: "compact", name: "Compact", desc: "Smaller font, more per page" },
  { id: "cards", name: "Cards", desc: "One block per grantee" },
];

const PDF_TEMPLATES: Template[] = [
  { id: "summary", name: "Summary", fields: ["studentNumber", "fullName", "university", "submissionStatus", "eligibility"] },
  { id: "detailed", name: "Detailed", fields: ["studentNumber", "fullName", "university", "program", "yearLevel", "submissionStatus", "eligibility", "gwa"] },
  { id: "academic", name: "Academic Focus", fields: ["studentNumber", "fullName", "program", "yearLevel", "gwa", "eligibility"] },
];

function ReportPreview() {
  const { data: grantees = [], isLoading } = useGrantees();
  const generatedAt = new Date().toISOString().slice(0, 10);

  const [csvTemplateId, setCsvTemplateId] = useState(CSV_TEMPLATES[0].id);
  const [csvFields, setCsvFields] = useState<FieldKey[]>(CSV_TEMPLATES[0].fields);
  const [pdfTemplateId, setPdfTemplateId] = useState(PDF_TEMPLATES[0].id);
  const [pdfFields, setPdfFields] = useState<FieldKey[]>(PDF_TEMPLATES[0].fields);
  const [pdfLayout, setPdfLayout] = useState<PdfLayout>("table");

  const applyCsvTemplate = (id: string) => {
    const t = CSV_TEMPLATES.find((x) => x.id === id)!;
    setCsvTemplateId(id);
    setCsvFields(t.fields);
  };
  const applyPdfTemplate = (id: string) => {
    const t = PDF_TEMPLATES.find((x) => x.id === id)!;
    setPdfTemplateId(id);
    setPdfFields(t.fields);
  };

  const exportCSV = () => {
    if (!grantees.length) return toast.error("No data to export");
    if (!csvFields.length) return toast.error("Pick at least one column");
    const rows = grantees.map((g) => {
      const o: Record<string, string | number> = {};
      csvFields.forEach((k) => { o[k] = FIELD_MAP[k].get(g); });
      return o;
    });
    downloadCSV(`grantee-${csvTemplateId}-${generatedAt}.csv`, rows);
    toast.success(`Exported ${rows.length} rows • ${csvFields.length} columns`);
  };

  const exportPDF = () => {
    if (!grantees.length) return toast.error("No data to export");
    if (!pdfFields.length) return toast.error("Pick at least one field");
    openPrintablePDF(grantees, generatedAt, pdfFields, pdfLayout);
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

      <div className="grid gap-4 lg:grid-cols-2 mb-4">
        <TemplatePanel
          icon={<IconFileSpreadsheet size={14} />}
          title="CSV Template"
          templates={CSV_TEMPLATES}
          activeId={csvTemplateId}
          onPick={applyCsvTemplate}
          fields={csvFields}
          setFields={setCsvFields}
        />
        <TemplatePanel
          icon={<IconFileTypePdf size={14} />}
          title="PDF Template"
          templates={PDF_TEMPLATES}
          activeId={pdfTemplateId}
          onPick={applyPdfTemplate}
          fields={pdfFields}
          setFields={setPdfFields}
          extra={
            <div className="mt-3 pt-3 border-t">
              <p className="text-[10px] uppercase tracking-wider font-semibold text-text-soft mb-2">Layout</p>
              <div className="flex flex-wrap gap-1.5">
                {PDF_LAYOUTS.map((l) => (
                  <button
                    key={l.id}
                    type="button"
                    onClick={() => setPdfLayout(l.id)}
                    title={l.desc}
                    className={`px-2.5 py-1 rounded-md text-xs border transition ${pdfLayout === l.id ? "bg-text text-bg border-text" : "bg-surface hover:bg-surface-2"}`}
                  >
                    {l.name}
                  </button>
                ))}
              </div>
            </div>
          }
        />
      </div>

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

function TemplatePanel({
  icon, title, templates, activeId, onPick, fields, setFields, extra,
}: {
  icon: React.ReactNode;
  title: string;
  templates: Template[];
  activeId: string;
  onPick: (id: string) => void;
  fields: FieldKey[];
  setFields: (f: FieldKey[]) => void;
  extra?: React.ReactNode;
}) {
  const available = useMemo(() => FIELDS.filter((f) => !fields.includes(f.key)), [fields]);
  const move = (from: number, to: number) => {
    if (to < 0 || to >= fields.length) return;
    const next = [...fields];
    const [it] = next.splice(from, 1);
    next.splice(to, 0, it);
    setFields(next);
  };

  return (
    <div className="rounded-lg border bg-surface p-4">
      <div className="flex items-center gap-2 mb-3">
        {icon}
        <h3 className="text-sm font-semibold">{title}</h3>
      </div>

      <div className="flex flex-wrap gap-1.5 mb-3">
        {templates.map((t) => (
          <button
            key={t.id}
            type="button"
            onClick={() => onPick(t.id)}
            className={`px-2.5 py-1 rounded-md text-xs border transition ${activeId === t.id ? "bg-text text-bg border-text" : "bg-surface hover:bg-surface-2"}`}
          >
            {t.name}
          </button>
        ))}
      </div>

      <p className="text-[10px] uppercase tracking-wider font-semibold text-text-soft mb-1.5">Fields ({fields.length}) — drag order</p>
      <ul className="space-y-1 mb-3">
        {fields.map((k, i) => (
          <li
            key={k}
            draggable
            onDragStart={(e) => e.dataTransfer.setData("text/plain", String(i))}
            onDragOver={(e) => e.preventDefault()}
            onDrop={(e) => {
              e.preventDefault();
              const from = Number(e.dataTransfer.getData("text/plain"));
              if (!Number.isNaN(from)) move(from, i);
            }}
            className="flex items-center gap-2 px-2 py-1.5 rounded border bg-surface-2/40 text-xs cursor-move"
          >
            <IconGripVertical size={12} className="text-text-soft" />
            <span className="flex-1">{FIELD_MAP[k].label}</span>
            <button type="button" onClick={() => move(i, i - 1)} disabled={i === 0} className="text-text-soft hover:text-text disabled:opacity-30">↑</button>
            <button type="button" onClick={() => move(i, i + 1)} disabled={i === fields.length - 1} className="text-text-soft hover:text-text disabled:opacity-30">↓</button>
            <button type="button" onClick={() => setFields(fields.filter((x) => x !== k))} className="text-text-soft hover:text-danger">
              <IconX size={12} />
            </button>
          </li>
        ))}
        {!fields.length && <li className="text-xs text-text-muted italic px-2 py-1.5">No fields selected</li>}
      </ul>

      {available.length > 0 && (
        <>
          <p className="text-[10px] uppercase tracking-wider font-semibold text-text-soft mb-1.5">Add field</p>
          <div className="flex flex-wrap gap-1">
            {available.map((f) => (
              <button
                key={f.key}
                type="button"
                onClick={() => setFields([...fields, f.key])}
                className="px-2 py-0.5 rounded text-[11px] border bg-surface hover:bg-surface-2"
              >
                + {f.label}
              </button>
            ))}
          </div>
        </>
      )}
      {extra}
    </div>
  );
}

function escapeHtml(v: unknown) {
  return String(v ?? "").replace(/[&<>"']/g, (c) =>
    ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]!),
  );
}

function openPrintablePDF(rows: GranteeRow[], generatedAt: string, fields: FieldKey[], layout: PdfLayout) {
  const w = window.open("", "_blank", "width=1024,height=768");
  if (!w) return toast.error("Pop-up blocked — allow pop-ups to export PDF");

  const headers = fields.map((k) => `<th>${escapeHtml(FIELD_MAP[k].label)}</th>`).join("");
  const cells = (g: GranteeRow) => fields.map((k) => `<td>${escapeHtml(FIELD_MAP[k].get(g))}</td>`).join("");

  let body = "";
  if (layout === "cards") {
    body = rows
      .map(
        (g) => `<div class="card">${fields
          .map((k) => `<div class="row"><span class="lbl">${escapeHtml(FIELD_MAP[k].label)}</span><span class="val">${escapeHtml(FIELD_MAP[k].get(g))}</span></div>`)
          .join("")}</div>`,
      )
      .join("");
  } else {
    body = `<table class="${layout}"><thead><tr>${headers}</tr></thead><tbody>${rows
      .map((g) => `<tr>${cells(g)}</tr>`)
      .join("")}</tbody></table>`;
  }

  const layoutCss =
    layout === "compact"
      ? `body{font-size:10px}table.compact th,table.compact td{padding:3px 5px}`
      : layout === "cards"
        ? `.card{border:1px solid #ddd;border-radius:6px;padding:10px 12px;margin-bottom:8px;page-break-inside:avoid}.card .row{display:flex;justify-content:space-between;gap:12px;padding:2px 0;border-bottom:1px dotted #eee}.card .row:last-child{border-bottom:none}.lbl{color:#666;font-size:10px;text-transform:uppercase;letter-spacing:.05em}.val{font-weight:500}`
        : "";

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
      ${layoutCss}
      @media print{body{margin:14mm}}
    </style></head><body>
    <div class="head">
      <div class="tag">Commission on Higher Education — UniFAST</div>
      <h1>${REPORT_TITLE}</h1>
      <div class="sub">${REPORT_SUB} • Generated ${generatedAt} • ${rows.length} records • ${layout} layout</div>
    </div>
    ${body}
    <div class="foot">Confidential — generated from live records under your access scope.</div>
    <script>window.addEventListener('load',()=>{setTimeout(()=>window.print(),200)})</script>
    </body></html>`);
  w.document.close();
}
