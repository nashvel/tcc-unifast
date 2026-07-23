// Registry of guided tours keyed by route path (longest prefix match).
// Each step has a CSS selector target that Joyride will spotlight.
// Use "[data-tour=\"...\"]" hooks or generic selectors; steps without a
// meaningful target can use "body" for a centered modal-style step.

export interface TourStep {
  target: string;
  title: string;
  body: string;
}

export interface Tour {
  title: string;
  steps: TourStep[];
}

// Common hook selectors present on every page via PageHeader / layout.
const H = "[data-tour=\"page-header\"]";
const C = "[data-tour=\"page-content\"]";

export const TOURS: Record<string, Tour> = {
  "/app": {
    title: "Dashboard tour",
    steps: [
      { target: H, title: "Your dashboard", body: "This is your operations dashboard. It surfaces KPIs, pipeline progress, and today's priorities." },
      { target: "[data-tour=\"dashboard-switcher\"]", title: "Switch layouts", body: "Change between Operations, Analytics, and Compact views. Your choice is remembered." },
      { target: "[data-tour=\"dashboard-kpis\"]", title: "KPI strip", body: "At-a-glance metrics with 7-day trend sparklines." },
      { target: C, title: "Everything else", body: "Charts, pipeline, quick actions, priorities and broadcasts fill the rest of the page." },
    ],
  },
  "/app/masterlist": {
    title: "Masterlist tour",
    steps: [
      { target: H, title: "Master roster", body: "Source-of-truth roster of all imported grantee records." },
      { target: "[data-tour=\"masterlist-upload\"]", title: "Upload the file", body: "Drop a CSV or XLSX (up to 20MB) here to stage a new import." },
      { target: "[data-tour=\"masterlist-rules\"]", title: "Import rules", body: "How duplicates, invalid rows, and account activation are handled." },
      { target: "[data-tour=\"masterlist-stats\"]", title: "Preview counts", body: "After preview, review totals per status before processing." },
    ],
  },

  "/app/batches": {
    title: "Batches tour",
    steps: [
      { target: H, title: "Batches", body: "A batch groups grantees processed together for a term or program cycle." },
      { target: C, title: "Progress", body: "Each card shows validation progress, cut-off dates, and eligibility sign-off status." },
    ],
  },
  "/app/grantees": {
    title: "Grantees tour",
    steps: [
      { target: H, title: "Grantee directory", body: "Every enrolled beneficiary with submission, eligibility, and risk flags." },
      { target: C, title: "Filter & open", body: "Filter by status, program, region, or risk. Open a row for the full profile." },
    ],
  },
  "/app/documents": {
    title: "Document validation tour",
    steps: [
      { target: H, title: "Validation queue", body: "Submitted documents awaiting review, sorted by priority." },
      { target: "[data-tour=\"documents-filters\"]", title: "Narrow the queue", body: "Search or filter by status and risk level to focus on what needs attention now." },
      { target: "[data-tour=\"documents-queue\"]", title: "Review a document", body: "Open Review on any row to approve, reject, resubmit, or flag — every action is audit-logged." },
    ],
  },

  "/app/files": {
    title: "File manager tour",
    steps: [
      { target: H, title: "All uploaded files", body: "Every uploaded requirement across grantees in one searchable table." },
      { target: C, title: "Upload, preview, reassign", body: "Staff can upload on behalf of a grantee. Preview inline, download, or reassign a document type." },
    ],
  },
  "/app/academic": {
    title: "Academic records tour",
    steps: [
      { target: H, title: "Per-grantee tracking", body: "Track enrollment, GWA, and retention rule evaluations term-by-term." },
    ],
  },
  "/app/eligibility": {
    title: "Eligibility tour",
    steps: [
      { target: H, title: "Eligibility engine", body: "Run and review eligibility results against configured program rules." },
      { target: "[data-tour=\"eligibility-filters\"]", title: "Filter candidates", body: "Search or filter by eligibility outcome and risk to focus on borderline cases." },
      { target: "[data-tour=\"eligibility-table\"]", title: "Evaluate a grantee", body: "Open Evaluate on any row to view the rules trace and record a decision. Committee sign-off is required before release." },
    ],
  },
  "/app/announcements": {
    title: "Announcements tour",
    steps: [
      { target: H, title: "Broadcast center", body: "Draft, schedule, and publish announcements by audience and channel." },
      { target: "[data-tour=\"announcements-new\"]", title: "Compose a new one", body: "Start a new announcement — pick audience, channels (in-app, email, SMS), and schedule." },
      { target: "[data-tour=\"announcements-list\"]", title: "Manage & inspect", body: "Edit any announcement, or open Logs to see per-channel delivery results." },
    ],
  },

  "/app/reports": {
    title: "Reports tour",
    steps: [
      { target: H, title: "Report library", body: "Pre-built reports for operations, validation throughput, and disbursement." },
      { target: C, title: "Generate & export", body: "Pick parameters, preview, and export to PDF or CSV." },
    ],
  },
  "/app/support": {
    title: "Support tour",
    steps: [
      { target: H, title: "Ticket queue", body: "Bug reports, questions, and change requests are triaged here." },
    ],
  },
  "/app/audit": {
    title: "Audit trail tour",
    steps: [
      { target: H, title: "Every action, logged", body: "Actor, target, before/after diff, and IP for every state change." },
      { target: C, title: "Investigate", body: "Filter by module, actor, or date range to verify compliance." },
    ],
  },
  "/app/security": {
    title: "Security findings tour",
    steps: [
      { target: H, title: "Automated scans", body: "Findings from scans with severity and remediation guidance." },
      { target: C, title: "Triage", body: "Acknowledge, ignore with justification, or resolve. Ignored items are remembered by Security Memory." },
    ],
  },
  "/app/security/memory": {
    title: "Security memory tour",
    steps: [
      { target: H, title: "Persistent guidance", body: "Captures decisions that guide future scans." },
    ],
  },
  "/app/users": {
    title: "Users & roles tour",
    steps: [
      { target: H, title: "Account directory", body: "Manage staff, head, and admin accounts." },
      { target: C, title: "Permissions", body: "Open Permissions to change module-level access per role." },
    ],
  },
  "/app/settings": {
    title: "Settings tour",
    steps: [
      { target: H, title: "System settings", body: "SMTP, email templates, legal documents, and consent copy." },
      { target: C, title: "Sensitive changes", body: "Changes to SMTP, legal, or consent are audit-logged and admin-only." },
    ],
  },

  // Student surfaces
  "/student": {
    title: "Home tour",
    steps: [
      { target: H, title: "Your dashboard", body: "See submission status, upcoming deadlines, and the latest announcements." },
    ],
  },
  "/student/upload": {
    title: "Upload tour",
    steps: [
      { target: H, title: "Upload requirements", body: "Submit each document. File types and size limits are shown per slot." },
      { target: C, title: "Track status", body: "After upload, track approval status under Submissions." },
    ],
  },
  "/student/submissions": {
    title: "Submissions tour",
    steps: [
      { target: H, title: "Track everything", body: "Status and full history of every document you've uploaded." },
    ],
  },
  "/student/announcements": {
    title: "Announcements tour",
    steps: [
      { target: H, title: "Official updates", body: "All published announcements from the UniFAST office." },
    ],
  },
  "/student/notifications": {
    title: "Notifications tour",
    steps: [
      { target: H, title: "Your inbox", body: "System notifications about your submissions and account." },
    ],
  },
  "/student/profile": {
    title: "Profile tour",
    steps: [
      { target: H, title: "Personal details", body: "Keep your profile and academic details up to date." },
    ],
  },
  "/student/settings": {
    title: "Settings tour",
    steps: [
      { target: H, title: "Preferences", body: "Password, notifications, and privacy settings." },
    ],
  },
};

export function resolveTour(pathname: string): Tour | null {
  const keys = Object.keys(TOURS).sort((a, b) => b.length - a.length);
  for (const k of keys) {
    if (pathname === k || pathname.startsWith(k + "/")) return TOURS[k];
  }
  return null;
}
