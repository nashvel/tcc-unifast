import type { GranteeRow, GranteeDetail } from "@/api/types";

export const mockGrantees: GranteeRow[] = [
  { id: 1, student_number: "2024-10001", student_id: "2024-001", name: "Maria Clara Dela Cruz", program: "BS Information Technology", batch: "TES Batch 01", account: "active", submission: "docs_submitted", eligibility: "eligible", risk: "low" },
  { id: 2, student_number: "2024-10002", student_id: "2024-002", name: "Juan Carlos Reyes", program: "BS Business Administration", batch: "TES Batch 01", account: "active", submission: "not_submitted", eligibility: "pending", risk: "medium" },
  { id: 3, student_number: "2024-10003", student_id: "2024-003", name: "Ana Santos Garcia", program: "BS Education", batch: "TES Batch 01", account: "pending_kyc", submission: "not_submitted", eligibility: "pending", risk: "low" },
  { id: 4, student_number: "2024-10004", student_id: "2024-004", name: "Pedro Miguel Torres", program: "BS Computer Science", batch: "TES Batch 01", account: "active", submission: "docs_submitted", eligibility: "eligible", risk: "low" },
  { id: 5, student_number: "2024-10005", student_id: "2024-005", name: "Sofia Reyes Lim", program: "BS Nursing", batch: "TES Batch 01", account: "blocked", submission: "not_submitted", eligibility: "ineligible", risk: "high" },
];

export const mockGranteeDetail: GranteeDetail = {
  id: 1,
  student_number: "2024-10001",
  student_id: "2024-001",
  name: "Maria Clara Dela Cruz",
  email: "mc.delacruz@tcc.edu.ph",
  program: "BS Information Technology",
  batch: "TES Batch 01",
  account: "active",
  submission: "docs_submitted",
  eligibility: "eligible",
  risk: "low",
  contact: "+63 917 123 4567",
  year_level: "2nd Year",
  university: "Tagoloan Community College",
  gwa: "1.75",
  birthdate: "2003-05-14",
};
