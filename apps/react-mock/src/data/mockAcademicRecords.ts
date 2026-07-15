export interface SemesterRecord {
  semester: string;
  gwa: number;
  unitsTaken: number;
  unitsPassed: number;
  failed: string[];
  dropped: string[];
}

export interface AcademicRecord {
  granteeId: string;
  studentNumber: string;
  granteeName: string;
  program: string;
  cumulativeGwa: number;
  retentionPassed: boolean;
  recommendation: "eligible" | "ineligible" | "for_evaluation";
  semesters: SemesterRecord[];
}

export const mockAcademicRecords: AcademicRecord[] = [
  { granteeId: "g1", studentNumber: "2024-10000", granteeName: "Maria Clara Dela Cruz", program: "BS Computer Science", cumulativeGwa: 1.62, retentionPassed: true, recommendation: "eligible", semesters: [
    { semester: "AY 2023-24 Sem 1", gwa: 1.55, unitsTaken: 21, unitsPassed: 21, failed: [], dropped: [] },
    { semester: "AY 2023-24 Sem 2", gwa: 1.70, unitsTaken: 24, unitsPassed: 24, failed: [], dropped: [] },
    { semester: "AY 2024-25 Sem 1", gwa: 1.60, unitsTaken: 21, unitsPassed: 21, failed: [], dropped: [] },
  ] },
  { granteeId: "g2", studentNumber: "2024-10001", granteeName: "Juan Miguel Santos", program: "BS Civil Engineering", cumulativeGwa: 2.10, retentionPassed: true, recommendation: "for_evaluation", semesters: [
    { semester: "AY 2024-25 Sem 1", gwa: 2.10, unitsTaken: 18, unitsPassed: 15, failed: ["Math 11"], dropped: ["PE 1"] },
  ] },
  { granteeId: "g3", studentNumber: "2024-10002", granteeName: "Andrea Nicole Reyes", program: "BS Accountancy", cumulativeGwa: 2.95, retentionPassed: false, recommendation: "ineligible", semesters: [
    { semester: "AY 2023-24 Sem 1", gwa: 2.80, unitsTaken: 21, unitsPassed: 18, failed: ["ACC 102"], dropped: [] },
    { semester: "AY 2023-24 Sem 2", gwa: 3.10, unitsTaken: 24, unitsPassed: 18, failed: ["TAX 1", "MGT 2"], dropped: ["PE 2"] },
  ] },
];
