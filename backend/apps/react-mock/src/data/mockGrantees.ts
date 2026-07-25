export type SubmissionStatus = "not_submitted" | "submitted" | "under_review" | "approved" | "rejected" | "resubmission_required";
export type EligibilityStatus = "eligible" | "ineligible" | "pending" | "for_evaluation";
export type RiskLevel = "low" | "medium" | "high";

export interface Grantee {
  id: string;
  studentNumber: string;
  firstName: string;
  lastName: string;
  middleName?: string;
  birthdate: string;
  email: string;
  contact: string;
  university: string;
  program: string;
  yearLevel: number;
  batchId: string;
  batch: string;
  accountStatus: "active" | "inactive" | "pending_activation" | "locked";
  submissionStatus: SubmissionStatus;
  eligibility: EligibilityStatus;
  risk: RiskLevel;
  gwa: number;
  profileCompletion: number;
  notes?: string;
}

const firstNames = ["Maria Clara", "Juan Miguel", "Andrea Nicole", "Joshua", "Patricia Mae", "Marco", "Bea", "Daniel", "Sophia", "Liam", "Isabella", "Gabriel", "Althea", "Rafael", "Janelle", "Kyle", "Mikaela", "Lance", "Trisha", "Nathaniel", "Yna", "Carlos", "Camille", "Earl", "Janine", "Paolo", "Erika", "Jericho", "Loraine", "Vincent"];
const lastNames = ["Dela Cruz", "Santos", "Reyes", "Tan", "Lim", "Villanueva", "Mendoza", "Garcia", "Aquino", "Perez", "Bautista", "Castillo", "Domingo", "Esguerra", "Fernandez", "Gutierrez", "Hernandez", "Ignacio", "Jimenez", "Kintanar"];
const unis = ["Pamantasan ng Lungsod ng Maynila", "University of the Philippines Diliman", "Polytechnic University of the Philippines", "De La Salle University - Dasmariñas", "Adamson University", "Far Eastern University", "University of Santo Tomas", "Mapua University", "Ateneo de Davao University", "Saint Louis University"];
const programs = ["BS Computer Science", "BS Civil Engineering", "BS Accountancy", "BS Information Technology", "BS Chemical Engineering", "BS Architecture", "BS Nursing", "BS Electronics Engineering", "AB Political Science", "BS Psychology"];
const subStatus: SubmissionStatus[] = ["not_submitted", "submitted", "under_review", "approved", "rejected", "resubmission_required"];
const elig: EligibilityStatus[] = ["eligible", "ineligible", "pending", "for_evaluation"];
const risks: RiskLevel[] = ["low", "low", "low", "medium", "high"];
const accountStatuses: Grantee["accountStatus"][] = ["active", "active", "active", "inactive", "pending_activation", "locked"];

function pick<T>(arr: T[], i: number) { return arr[i % arr.length]; }

export const mockGrantees: Grantee[] = Array.from({ length: 64 }).map((_, i) => {
  const fn = pick(firstNames, i * 3);
  const ln = pick(lastNames, i * 7);
  return {
    id: `g${i + 1}`,
    studentNumber: `2024-${String(10000 + i).padStart(5, "0")}`,
    firstName: fn,
    lastName: ln,
    middleName: pick(lastNames, i),
    birthdate: `200${(i % 5) + 1}-0${(i % 9) + 1}-1${i % 9}`,
    email: `${fn.toLowerCase().split(" ")[0]}.${ln.toLowerCase().replace(/\s/g, "")}@${pick(["plm", "up", "pup", "ust", "feu"], i)}.edu.ph`,
    contact: `+63917${String(1000000 + i * 137).slice(0, 7)}`,
    university: pick(unis, i),
    program: pick(programs, i),
    yearLevel: (i % 4) + 1,
    batchId: i < 40 ? "b1" : "b2",
    batch: i < 40 ? "AY 2024-2025 Sem 1" : "AY 2024-2025 Sem 2",
    accountStatus: pick(accountStatuses, i),
    submissionStatus: pick(subStatus, i),
    eligibility: pick(elig, i),
    risk: pick(risks, i),
    gwa: +(1.25 + (i % 30) * 0.07).toFixed(2),
    profileCompletion: 40 + (i * 7) % 60,
    notes: i % 6 === 0 ? "Flagged for late submission in previous semester." : undefined,
  };
});
