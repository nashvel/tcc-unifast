export interface Batch {
  id: string;
  name: string;
  academicYear: string;
  semester: string;
  status: "open" | "closed" | "archived";
  totalGrantees: number;
  active: number;
  pending: number;
  validated: number;
  createdAt: string;
}

export const mockBatches: Batch[] = [
  { id: "b1", name: "AY 2024-2025 Sem 1", academicYear: "2024-2025", semester: "1st Semester", status: "open", totalGrantees: 1240, active: 980, pending: 180, validated: 740, createdAt: "2025-08-01" },
  { id: "b2", name: "AY 2024-2025 Sem 2", academicYear: "2024-2025", semester: "2nd Semester", status: "open", totalGrantees: 1180, active: 410, pending: 620, validated: 220, createdAt: "2026-01-10" },
  { id: "b3", name: "AY 2023-2024 Sem 2", academicYear: "2023-2024", semester: "2nd Semester", status: "closed", totalGrantees: 1305, active: 1290, pending: 0, validated: 1290, createdAt: "2024-01-15" },
  { id: "b4", name: "AY 2023-2024 Sem 1", academicYear: "2023-2024", semester: "1st Semester", status: "archived", totalGrantees: 1198, active: 1190, pending: 0, validated: 1190, createdAt: "2023-08-12" },
  { id: "b5", name: "AY 2022-2023 Sem 2", academicYear: "2022-2023", semester: "2nd Semester", status: "archived", totalGrantees: 1102, active: 1090, pending: 0, validated: 1090, createdAt: "2023-01-15" },
  { id: "b6", name: "AY 2022-2023 Sem 1", academicYear: "2022-2023", semester: "1st Semester", status: "archived", totalGrantees: 1080, active: 1070, pending: 0, validated: 1070, createdAt: "2022-08-12" },
];
