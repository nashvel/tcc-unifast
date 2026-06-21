export interface Notification {
  id: string;
  title: string;
  body: string;
  type: "info" | "success" | "warning" | "danger";
  read: boolean;
  createdAt: string;
}

export const mockNotifications: Notification[] = [
  { id: "n1", title: "Document approved", body: "Your Certificate of Registration has been approved.", type: "success", read: false, createdAt: "2026-06-20 10:42" },
  { id: "n2", title: "Resubmission required", body: "Your Birth Certificate needs to be re-uploaded.", type: "warning", read: false, createdAt: "2026-06-19 16:01" },
  { id: "n3", title: "New announcement", body: "Submission Deadline Extended to June 30.", type: "info", read: true, createdAt: "2026-06-19 10:00" },
  { id: "n4", title: "Eligibility update", body: "You are tentatively marked Eligible for AY 2024-25 Sem 1.", type: "success", read: true, createdAt: "2026-06-18 09:15" },
];
