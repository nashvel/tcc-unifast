export type CourseRemark = "Passed" | "Failed" | "Dropped";

export type CourseGrade = {
  code: string;
  title: string;
  units: number;
  grade: string;
  remark: CourseRemark;
};

export type SemesterHistory = {
  term: string;
  gwa: string;
  unitsTaken: number;
  unitsPassed: number;
  courses: CourseGrade[];
};

export type AcademicRecord = {
  id: number;
  studentNo: string;
  name: string;
  program: string;
  yearLevel: string;
  latestGwa: string;
  approvedSubmissions: number;
  totalSubmissions: number;
  semesters: SemesterHistory[];
};

export const academicRecords: AcademicRecord[] = [
  {
    id: 1,
    studentNo: "2024-00182",
    name: "Maria Angela Santos",
    program: "BS Information Technology",
    yearLevel: "2nd Year",
    latestGwa: "1.42",
    approvedSubmissions: 4,
    totalSubmissions: 5,
    semesters: [
      {
        term: "1st Semester AY 2025-2026",
        gwa: "1.42",
        unitsTaken: 21,
        unitsPassed: 21,
        courses: [
          { code: "IT 211", title: "Data Structures", units: 3, grade: "1.50", remark: "Passed" },
          { code: "IT 212", title: "Database Systems", units: 3, grade: "1.25", remark: "Passed" },
          { code: "GE 205", title: "Ethics", units: 3, grade: "1.50", remark: "Passed" },
        ],
      },
      {
        term: "2nd Semester AY 2024-2025",
        gwa: "1.56",
        unitsTaken: 21,
        unitsPassed: 21,
        courses: [
          { code: "IT 121", title: "Computer Programming 2", units: 3, grade: "1.50", remark: "Passed" },
          { code: "MATH 112", title: "Discrete Mathematics", units: 3, grade: "1.75", remark: "Passed" },
        ],
      },
    ],
  },
  {
    id: 2,
    studentNo: "2024-00194",
    name: "John Paul Ramirez",
    program: "BS Business Administration",
    yearLevel: "3rd Year",
    latestGwa: "1.88",
    approvedSubmissions: 3,
    totalSubmissions: 4,
    semesters: [
      {
        term: "1st Semester AY 2025-2026",
        gwa: "1.88",
        unitsTaken: 18,
        unitsPassed: 18,
        courses: [
          { code: "BA 301", title: "Business Finance", units: 3, grade: "1.75", remark: "Passed" },
          { code: "BA 302", title: "Operations Management", units: 3, grade: "2.00", remark: "Passed" },
          { code: "GE 301", title: "Life and Works of Rizal", units: 3, grade: "1.75", remark: "Passed" },
        ],
      },
    ],
  },
  {
    id: 3,
    studentNo: "2024-00207",
    name: "Nicole Anne Flores",
    program: "BS Education",
    yearLevel: "2nd Year",
    latestGwa: "2.31",
    approvedSubmissions: 2,
    totalSubmissions: 4,
    semesters: [
      {
        term: "1st Semester AY 2025-2026",
        gwa: "2.31",
        unitsTaken: 21,
        unitsPassed: 18,
        courses: [
          { code: "ED 204", title: "Facilitating Learner-Centered Teaching", units: 3, grade: "2.00", remark: "Passed" },
          { code: "ED 205", title: "Assessment of Learning", units: 3, grade: "3.00", remark: "Passed" },
          { code: "SCI 102", title: "Environmental Science", units: 3, grade: "5.00", remark: "Failed" },
        ],
      },
      {
        term: "2nd Semester AY 2024-2025",
        gwa: "2.14",
        unitsTaken: 21,
        unitsPassed: 21,
        courses: [
          { code: "ED 103", title: "The Teaching Profession", units: 3, grade: "2.00", remark: "Passed" },
          { code: "GE 104", title: "Purposive Communication", units: 3, grade: "2.25", remark: "Passed" },
        ],
      },
    ],
  },
  {
    id: 4,
    studentNo: "2024-00231",
    name: "Christian Dela Cruz",
    program: "BS Criminology",
    yearLevel: "1st Year",
    latestGwa: "2.76",
    approvedSubmissions: 1,
    totalSubmissions: 3,
    semesters: [
      {
        term: "1st Semester AY 2025-2026",
        gwa: "2.76",
        unitsTaken: 18,
        unitsPassed: 12,
        courses: [
          { code: "CRIM 101", title: "Introduction to Criminology", units: 3, grade: "2.50", remark: "Passed" },
          { code: "CRIM 102", title: "Law Enforcement Organization", units: 3, grade: "5.00", remark: "Failed" },
          { code: "PE 101", title: "Physical Fitness", units: 2, grade: "DRP", remark: "Dropped" },
        ],
      },
    ],
  },
];

export function countCourseRemarks(record: AcademicRecord, remark: CourseRemark) {
  return record.semesters.reduce(
    (total, semester) => total + semester.courses.filter((course) => course.remark === remark).length,
    0,
  );
}
