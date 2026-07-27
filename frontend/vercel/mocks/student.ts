import type { StudentNotification, SubmissionWindow } from "@/api/types";

export const mockNotifications: StudentNotification[] = [
  { id: 1, title: "Submission window opened", body: "The submission window for TES Batch 01 is now open.", type: "window_opened", read: false, time: "2 hours ago" },
  { id: 2, title: "Document approved", body: "Your School ID has been approved by staff.", type: "success", read: false, time: "1 day ago" },
  { id: 3, title: "Deadline extended", body: "The submission deadline has been extended to Aug 15, 2026.", type: "info", read: true, time: "3 days ago" },
];

export const mockSubmissionWindow: SubmissionWindow = {
  open: true,
  status: "active",
  message: "Submission window is open",
  batch: {
    id: 1,
    name: "TES Batch 01",
    academic_year: "AY 2026-2027",
    semester: "1st Semester",
    submission_deadline: "2026-08-15T23:59:00Z",
    window_status: "active",
  },
};

export const mockVault = {
  window: { open: true, message: "Submission window is open" },
  grantee: { submission_status: "not_submitted", submitted_at: null },
  slots: {},
  identity_check: null,
};
