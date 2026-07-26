import type { AcademicRecord, AcademicRecordDetail } from "@/api/types";

export const mockAcademicRecords: AcademicRecord[] = [
  { id: 1, student_number: "2024-10001", student_id: "2024-001", name: "Maria Clara Dela Cruz", program: "BS Information Technology", year_level: "2nd Year", latest_gwa: "1.75", approved_submissions: 2, total_submissions: 3, remarks: { passed: 18, failed: 0, dropped: 1 } },
  { id: 2, student_number: "2024-10002", student_id: "2024-002", name: "Juan Carlos Reyes", program: "BS Business Administration", year_level: "3rd Year", latest_gwa: "2.10", approved_submissions: 1, total_submissions: 2, remarks: { passed: 15, failed: 1, dropped: 0 } },
  { id: 3, student_number: "2024-10003", student_id: "2024-003", name: "Ana Santos Garcia", program: "BS Education", year_level: "1st Year", latest_gwa: "1.50", approved_submissions: 1, total_submissions: 1, remarks: { passed: 20, failed: 0, dropped: 0 } },
];

export const mockAcademicDetail: AcademicRecordDetail = {
  ...mockAcademicRecords[0],
  semesters: [
    {
      id: 1,
      term: "1st Semester AY 2025-2026",
      gwa: "1.80",
      units_taken: 21,
      units_passed: 21,
      courses: [
        { id: 1, code: "IT 201", title: "Data Structures", units: 3, grade: "1.5", remark: "Passed" },
        { id: 2, code: "IT 202", title: "Algorithms", units: 3, grade: "1.75", remark: "Passed" },
        { id: 3, code: "MATH 201", title: "Linear Algebra", units: 3, grade: "2.0", remark: "Passed" },
        { id: 4, code: "IT 203", title: "Web Development", units: 3, grade: "1.25", remark: "Passed" },
        { id: 5, code: "ENG 201", title: "Technical Writing", units: 3, grade: "1.5", remark: "Passed" },
        { id: 6, code: "PE 201", title: "Physical Fitness", units: 2, grade: "1.0", remark: "Passed" },
        { id: 7, code: "NSTP 201", title: "Civic Welfare Training", units: 3, grade: "1.5", remark: "Passed" },
        { id: 8, code: "HUM 201", title: "Art Appreciation", units: 3, grade: "1.75", remark: "Passed" },
      ],
    },
    {
      id: 2,
      term: "2nd Semester AY 2025-2026",
      gwa: "1.70",
      units_taken: 18,
      units_passed: 18,
      courses: [
        { id: 9, code: "IT 211", title: "Database Systems", units: 3, grade: "1.5", remark: "Passed" },
        { id: 10, code: "IT 212", title: "Operating Systems", units: 3, grade: "1.75", remark: "Passed" },
        { id: 11, code: "IT 213", title: "Software Engineering", units: 3, grade: "1.25", remark: "Passed" },
        { id: 12, code: "MATH 202", title: "Discrete Mathematics", units: 3, grade: "2.0", remark: "Passed" },
        { id: 13, code: "IT 214", title: "Computer Networks", units: 3, grade: "1.5", remark: "Passed" },
        { id: 14, code: "ETHICS", title: "Ethics", units: 3, grade: "1.75", remark: "Passed" },
      ],
    },
  ],
};
