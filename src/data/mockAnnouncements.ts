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
  reach?: number;
  opens?: number;
}

export const mockAnnouncements: Announcement[] = [
  { id: "a1", title: "Submission Deadline Extended to June 30", body: "Due to system maintenance, the submission deadline for AY 2024-2025 Sem 1 has been extended to June 30, 2026.", audience: "all", audienceLabel: "All Students", channels: ["in_app", "email", "sms"], status: "published", publishedAt: "2026-06-19 10:00", author: "UniFAST Office", reach: 1240, opens: 1102 },
  { id: "a2", title: "Resubmission Required for Rejected Documents", body: "Please review your rejected documents and re-upload corrected versions within 7 days.", audience: "rejected", audienceLabel: "Students with Rejected Docs", channels: ["in_app", "email"], status: "published", publishedAt: "2026-06-17 08:30", author: "Validation Team", reach: 87, opens: 79 },
  { id: "a3", title: "Eligibility Results Will Be Released July 10", body: "Eligibility evaluation for this semester will be released on July 10, 2026.", audience: "eligible", audienceLabel: "Eligible Grantees", channels: ["in_app", "email"], status: "scheduled", scheduledFor: "2026-07-01 09:00", author: "UniFAST Office" },
  { id: "a4", title: "Draft: Welcome New Grantees", body: "Welcome to the TES Program — here is what to expect in your first semester.", audience: "batch", audienceLabel: "Batch AY 2024-2025 Sem 1", channels: ["in_app"], status: "draft", author: "Admin" },
  { id: "a5", title: "Orientation Webinar on July 5", body: "Mandatory online orientation for all new grantees. Link will be sent via email.", audience: "batch", audienceLabel: "Batch AY 2024-2025 Sem 1", channels: ["in_app", "email"], status: "published", publishedAt: "2026-06-10 11:00", author: "Office Head", reach: 540, opens: 488 },
  { id: "a6", title: "Reminder: Complete Profile Information", body: "Please complete your profile to avoid validation delays.", audience: "pending", audienceLabel: "Pending Activation", channels: ["in_app", "sms"], status: "published", publishedAt: "2026-06-05 16:00", author: "Validation Team", reach: 312, opens: 261 },
  { id: "a7", title: "System Maintenance Notice", body: "The portal will be unavailable on June 25, 2026 from 1:00 AM to 4:00 AM.", audience: "all", audienceLabel: "All Users", channels: ["in_app", "email"], status: "scheduled", scheduledFor: "2026-06-24 09:00", author: "Admin" },
];
