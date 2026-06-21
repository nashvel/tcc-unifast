export type AnnouncementStatus = "draft" | "scheduled" | "published";
export type Channel = "in_app" | "email" | "sms";

export interface Announcement {
  id: string;
  title: string;
  body: string;
  audience: "all" | "batch" | "pending" | "rejected" | "eligible";
  audienceLabel: string;
  channels: Channel[];
  status: AnnouncementStatus;
  publishedAt?: string;
  scheduledFor?: string;
  author: string;
}

export const mockAnnouncements: Announcement[] = [
  { id: "a1", title: "Submission Deadline Extended to June 30", body: "Due to system maintenance, the submission deadline for AY 2024-2025 Sem 1 has been extended to June 30, 2026.", audience: "all", audienceLabel: "All Students", channels: ["in_app", "email", "sms"], status: "published", publishedAt: "2026-06-19 10:00", author: "UniFAST Office" },
  { id: "a2", title: "Resubmission Required for Rejected Documents", body: "Please review your rejected documents and re-upload corrected versions within 7 days.", audience: "rejected", audienceLabel: "Students with Rejected Docs", channels: ["in_app", "email"], status: "published", publishedAt: "2026-06-17 08:30", author: "Validation Team" },
  { id: "a3", title: "Eligibility Results Will Be Released July 10", body: "Eligibility evaluation for this semester will be released on July 10, 2026.", audience: "eligible", audienceLabel: "Eligible Grantees", channels: ["in_app", "email"], status: "scheduled", scheduledFor: "2026-07-01 09:00", author: "UniFAST Office" },
  { id: "a4", title: "Draft: Welcome New Grantees", body: "Welcome to the TES Program...", audience: "batch", audienceLabel: "Batch AY 2024-2025 Sem 1", channels: ["in_app"], status: "draft", author: "Admin" },
];
