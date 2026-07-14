export const announcements = [
  {
    title: "Scholarship orientation schedule",
    body: "Orientation for new TES grantees will be held at the TCC AVR on May 15.",
    audience: "All grantees",
    status: "Published",
    channels: ["In-app", "Email"],
    date: "May 12, 2025",
  },
  {
    title: "TES application deadline",
    body: "Complete and upload all pending requirements before the application cut-off.",
    audience: "Applicants",
    status: "Published",
    channels: ["In-app", "Email", "SMS"],
    date: "May 8, 2025",
  },
  {
    title: "System maintenance advisory",
    body: "The student portal will be unavailable during scheduled maintenance.",
    audience: "All users",
    status: "Draft",
    channels: ["In-app"],
    date: "—",
  },
];

export const tickets = [
  [
    "Unable to replace uploaded transcript",
    "bug",
    "High",
    "Open",
    "Maria Santos",
    "Admin User",
    "Jul 11, 2026, 9:42 AM",
  ],
  [
    "Question about eligibility result",
    "question",
    "Normal",
    "In progress",
    "John Ramirez",
    "UniFAST Staff",
    "Jul 10, 2026, 3:18 PM",
  ],
  [
    "Request to update contact number",
    "request",
    "Low",
    "Resolved",
    "Nicole Flores",
    "Admin User",
    "Jul 9, 2026, 11:04 AM",
  ],
];

export const auditLogs = [
  [
    "Jul 11, 2026 9:42 AM",
    "System Administrator",
    "Admin",
    "Viewed report",
    "Reports",
    "Office performance",
    "192.168.1.14",
  ],
  [
    "Jul 11, 2026 9:31 AM",
    "UniFAST Staff",
    "Staff",
    "Updated record",
    "Eligibility",
    "2024-00231",
    "192.168.1.22",
  ],
  [
    "Jul 11, 2026 9:10 AM",
    "Office Head",
    "Head",
    "Approved batch",
    "Batches",
    "TES Batch 04",
    "192.168.1.10",
  ],
];

export const findings = [
  [
    "Warning",
    "All authenticated users can read any avatar",
    "Supabase (Lovable scanner)",
    "Fixed",
    "Jun 21, 2026",
  ],
  [
    "Warning",
    "Ambiguous INSERT path on user_roles",
    "Supabase (Lovable scanner)",
    "Fixed",
    "Jun 21, 2026",
  ],
  ["Warning", "Leaked Password Protection disabled", "Supabase platform", "Fixed", "Jun 21, 2026"],
];
