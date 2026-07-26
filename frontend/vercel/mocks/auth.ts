export const mockUsers = [
  {
    id: 1,
    name: "System Developer",
    email: "admin@unifast.gov.ph",
    role: "developer" as const,
    student_id: null,
    account_status: "active" as const,
  },
  {
    id: 2,
    name: "Office Administrator",
    email: "head@unifast.gov.ph",
    role: "admin" as const,
    student_id: null,
    account_status: "active" as const,
  },
  {
    id: 3,
    name: "UniFAST Staff",
    email: "staff@unifast.gov.ph",
    role: "staff" as const,
    student_id: null,
    account_status: "active" as const,
  },
  {
    id: 4,
    name: "Maria Angela Santos",
    email: "student@tcc.edu.ph",
    role: "student" as const,
    student_id: "2024-00182",
    account_status: "active" as const,
  },
];

export const roleFromEmail: Record<string, { role: "developer" | "admin" | "staff" | "student"; name: string }> = {
  "admin@unifast.gov.ph": { role: "developer", name: "System Developer" },
  "head@unifast.gov.ph": { role: "admin", name: "Office Administrator" },
  "staff@unifast.gov.ph": { role: "staff", name: "UniFAST Staff" },
  "student@tcc.edu.ph": { role: "student", name: "Maria Angela Santos" },
};
