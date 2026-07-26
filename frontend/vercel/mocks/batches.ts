import type { Batch, BatchDetail } from "@/api/types";

export const mockBatches: Batch[] = [
  {
    id: 1,
    name: "TES Batch 01 - AY 2026-2027",
    academic_year: "AY 2026-2027",
    semester: "1st Semester",
    submission_deadline: "2026-08-15T23:59:00Z",
    is_active: true,
    window_status: "active",
    grantees_count: 248,
  },
  {
    id: 2,
    name: "TES Batch 02 - AY 2026-2027",
    academic_year: "AY 2026-2027",
    semester: "2nd Semester",
    submission_deadline: null,
    is_active: false,
    window_status: "draft",
    grantees_count: 0,
  },
  {
    id: 3,
    name: "TES Batch 03 - AY 2025-2026",
    academic_year: "AY 2025-2026",
    semester: "1st Semester",
    submission_deadline: "2025-12-15T23:59:00Z",
    is_active: false,
    window_status: "closed",
    grantees_count: 312,
  },
];

export const mockBatchDetail: BatchDetail = {
  ...mockBatches[0],
  grantees: [
    { id: 1, student_id: "2024-001", student_number: "2024-10001", full_name: "Maria Clara Dela Cruz", email: "mc.delacruz@tcc.edu.ph", program: "BS Information Technology", status: "active", account_status: "active" },
    { id: 2, student_id: "2024-002", student_number: "2024-10002", full_name: "Juan Carlos Reyes", email: "jc.reyes@tcc.edu.ph", program: "BS Business Administration", status: "active", account_status: "active" },
    { id: 3, student_id: "2024-003", student_number: "2024-10003", full_name: "Ana Santos Garcia", email: "as.garcia@tcc.edu.ph", program: "BS Education", status: "active", account_status: "pending_kyc" },
  ],
};
