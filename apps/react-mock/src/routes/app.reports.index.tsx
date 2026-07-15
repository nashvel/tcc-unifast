import { createFileRoute, Link } from "@tanstack/react-router";
import { PageHeader } from "@/components/ui/page-header";
import {
  IconUsersGroup, IconFolders, IconFileCheck, IconSchool, IconChecklist, IconHistory, IconReportAnalytics,
} from "@tabler/icons-react";

const reports = [
  { key: "grantee", title: "Grantee List", icon: IconUsersGroup, desc: "Complete grantee roster with personal & academic details." },
  { key: "batch", title: "Batch Report", icon: IconFolders, desc: "Per-batch summary, progress, and outcomes." },
  { key: "documents", title: "Document Validation", icon: IconFileCheck, desc: "Validation outcomes by document type and risk." },
  { key: "academic", title: "Academic Tracking", icon: IconSchool, desc: "GWA trends, retention, and at-risk grantees." },
  { key: "eligibility", title: "Eligibility Report", icon: IconChecklist, desc: "Eligible/ineligible distribution and reasons." },
  { key: "audit", title: "Audit Trail", icon: IconHistory, desc: "Filtered audit logs export." },
  { key: "office", title: "Office Report", icon: IconReportAnalytics, desc: "Consolidated UniFAST Office performance metrics." },
];

export const Route = createFileRoute("/app/reports/")({
  component: () => (
    <div>
      <PageHeader title="Reports" description="Generate, preview, and export operational reports." />
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        {reports.map((r) => (
          <Link key={r.key} to="/app/reports/generate" search={{ type: r.key }} className="rounded-lg border bg-surface p-4 hover:border-primary/40 hover:bg-primary-soft/10 transition-colors">
            <div className="h-9 w-9 rounded-md bg-primary-soft text-primary grid place-items-center mb-2"><r.icon size={18} /></div>
            <p className="text-sm font-semibold">{r.title}</p>
            <p className="text-xs text-text-muted mt-1">{r.desc}</p>
          </Link>
        ))}
      </div>
    </div>
  ),
});
