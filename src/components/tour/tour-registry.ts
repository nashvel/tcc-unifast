// Registry of guided tours keyed by route path (longest prefix match).
// Add entries here to expose a help button on the corresponding page.

export interface TourStep {
  title: string;
  body: string;
}

export interface Tour {
  title: string;
  steps: TourStep[];
}

export const TOURS: Record<string, Tour> = {
  "/app": {
    title: "Dashboard tour",
    steps: [
      { title: "Overview", body: "This is your operations dashboard. It surfaces KPIs, pipeline progress, and today's priorities at a glance." },
      { title: "Switch layouts", body: "Use the layout switcher in the header to change between Operations, Analytics, and Compact views. Your choice is remembered." },
      { title: "Quick actions", body: "Jump straight into common workflows — review submissions, run eligibility, manage batches, or draft an announcement." },
      { title: "Broadcasts & priorities", body: "The bottom row highlights your priority batch and the latest published announcement." },
    ],
  },
  "/app/masterlist": {
    title: "Masterlist tour",
    steps: [
      { title: "Master roster", body: "The masterlist is the source-of-truth roster of all imported grantee records." },
      { title: "Import", body: "Use Import to upload CSV/XLSX rosters. Rows are validated before they land in a batch." },
      { title: "Filters & search", body: "Narrow the list by status, program, or university, then search by name or student number." },
    ],
  },
  "/app/batches": {
    title: "Batches tour",
    steps: [
      { title: "What is a batch?", body: "A batch groups grantees processed together for a specific term or program cycle." },
      { title: "Progress", body: "Each batch shows validation progress, cut-off dates, and eligibility sign-off status." },
      { title: "Open a batch", body: "Click a batch to review its grantees, documents, and release schedule." },
    ],
  },
  "/app/grantees": {
    title: "Grantees tour",
    steps: [
      { title: "Grantee directory", body: "Every enrolled beneficiary lives here with their submission, eligibility, and risk flags." },
      { title: "Filters", body: "Filter by status, program, region, or risk. Use search for name or student number lookups." },
      { title: "Grantee profile", body: "Open a row to see full academic records, documents, and audit history." },
    ],
  },
  "/app/documents": {
    title: "Document validation tour",
    steps: [
      { title: "Validation queue", body: "All submitted documents awaiting review appear here, sorted by priority." },
      { title: "Take action", body: "Approve, reject, or flag documents. Actions are recorded in the audit trail." },
      { title: "Detail view", body: "Open a document to preview the file, see extracted fields, and add reviewer notes." },
    ],
  },
  "/app/files": {
    title: "File manager tour",
    steps: [
      { title: "All uploaded files", body: "Every uploaded requirement across grantees appears here in one searchable table." },
      { title: "Upload", body: "Staff can drag-and-drop or use the picker to upload files on behalf of a grantee. The file is auto-linked to the correct submission." },
      { title: "Preview & download", body: "Preview images and PDFs inline, download originals, or reassign a file's document type." },
    ],
  },
  "/app/academic": {
    title: "Academic records tour",
    steps: [
      { title: "Per-grantee tracking", body: "Track enrollment, GWA, and retention rule evaluations term-by-term." },
      { title: "Retention rules", body: "The system evaluates whether each grantee meets the retention criteria configured for their program." },
    ],
  },
  "/app/eligibility": {
    title: "Eligibility tour",
    steps: [
      { title: "Eligibility engine", body: "Run and review eligibility results for grantees against configured program rules." },
      { title: "Sign-off", body: "Committee sign-off is required before batches can move to the release stage." },
    ],
  },
  "/app/announcements": {
    title: "Announcements tour",
    steps: [
      { title: "Broadcast center", body: "Draft, schedule, and publish announcements to grantees by audience and channel." },
      { title: "Delivery logs", body: "Open Notification Logs to see per-channel delivery results (email, SMS, in-app)." },
    ],
  },
  "/app/reports": {
    title: "Reports tour",
    steps: [
      { title: "Report library", body: "Pre-built reports for operations, validation throughput, and disbursement summaries." },
      { title: "Generate & export", body: "Use Generate to pick parameters, preview the output, and export to PDF or CSV." },
    ],
  },
  "/app/support": {
    title: "Support tickets tour",
    steps: [
      { title: "Ticket queue", body: "Bug reports, questions, and change requests from staff and grantees are triaged here." },
      { title: "New ticket", body: "Use New to raise your own ticket with category, priority, and reproduction steps." },
    ],
  },
  "/app/audit": {
    title: "Audit trail tour",
    steps: [
      { title: "Every action, logged", body: "The audit trail records every state change with the actor, target, before/after diff, and IP." },
      { title: "Search & filter", body: "Filter by module, actor, or date range to investigate incidents or verify compliance." },
    ],
  },
  "/app/security": {
    title: "Security findings tour",
    steps: [
      { title: "Automated scans", body: "Security findings surfaced by scans are listed here with severity and remediation guidance." },
      { title: "Triage", body: "Acknowledge, ignore with justification, or mark findings as resolved. Ignored items are remembered by Security Memory." },
    ],
  },
  "/app/security/memory": {
    title: "Security memory tour",
    steps: [
      { title: "Persistent guidance", body: "Security Memory captures decisions that guide future scans — ignored findings and accepted risks." },
      { title: "Keep it fresh", body: "Prune stale entries when the app's security posture changes so old advice doesn't hide new issues." },
    ],
  },
  "/app/users": {
    title: "Users & roles tour",
    steps: [
      { title: "Account directory", body: "Manage staff, head, and admin accounts. Deactivate or reset access from here." },
      { title: "Permission matrix", body: "Open Permissions to review or change module-level access per role." },
    ],
  },
  "/app/settings": {
    title: "Settings tour",
    steps: [
      { title: "Configuration", body: "System-wide settings live here — SMTP, email templates, legal documents, and consent copy." },
      { title: "Sensitive changes", body: "Changes to SMTP, legal, or consent are audit-logged and require admin role." },
    ],
  },
  // Student surfaces
  "/student": {
    title: "Home tour",
    steps: [
      { title: "Your dashboard", body: "See your submission status, upcoming deadlines, and the latest announcements from UniFAST." },
    ],
  },
  "/student/upload": {
    title: "Upload requirements tour",
    steps: [
      { title: "Submit documents", body: "Upload each required document. Accepted file types and size limits are shown per slot." },
      { title: "Status", body: "After upload, track approval status under Submissions. Rejected items include reviewer notes." },
    ],
  },
  "/student/submissions": {
    title: "Submissions tour",
    steps: [
      { title: "Track everything", body: "See the status and full history of every document you've uploaded." },
    ],
  },
  "/student/announcements": {
    title: "Announcements tour",
    steps: [
      { title: "Official updates", body: "All published announcements from the UniFAST office appear here, newest first." },
    ],
  },
  "/student/notifications": {
    title: "Notifications tour",
    steps: [
      { title: "Your inbox", body: "System notifications about your submissions and account activity." },
    ],
  },
  "/student/profile": {
    title: "Profile tour",
    steps: [
      { title: "Personal details", body: "Keep your profile, contact info, and academic details up to date." },
    ],
  },
  "/student/settings": {
    title: "Settings tour",
    steps: [
      { title: "Preferences", body: "Change your password, notification preferences, and privacy settings." },
    ],
  },
};

export function resolveTour(pathname: string): Tour | null {
  // longest-prefix match: prefer more specific routes
  const keys = Object.keys(TOURS).sort((a, b) => b.length - a.length);
  for (const k of keys) {
    if (pathname === k || pathname.startsWith(k + "/")) return TOURS[k];
  }
  return null;
}
