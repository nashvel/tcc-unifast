export type AccountStatus = "active" | "inactive" | "duplicate" | "invalid" | "pending_activation";

export interface MasterlistRow {
  id: string;
  studentNumber: string;
  firstName: string;
  lastName: string;
  middleName?: string;
  birthdate: string; // YYYY-MM-DD
  email: string;
  contact: string;
  university: string;
  program: string;
  yearLevel: number;
  batch: string;
  accountStatus: AccountStatus;
  importedAt: string;
}

export const mockMasterlist: MasterlistRow[] = [
  { id: "m1", studentNumber: "2024-00123", firstName: "Maria Clara", lastName: "Dela Cruz", middleName: "Reyes", birthdate: "2003-05-14", email: "mc.delacruz@plm.edu.ph", contact: "+639171234567", university: "Pamantasan ng Lungsod ng Maynila", program: "BS Computer Science", yearLevel: 2, batch: "AY 2024-2025 Sem 1", accountStatus: "active", importedAt: "2025-08-12" },
  { id: "m2", studentNumber: "2024-00124", firstName: "Juan Miguel", lastName: "Santos", birthdate: "2004-02-20", email: "jm.santos@up.edu.ph", contact: "+639181234568", university: "University of the Philippines Diliman", program: "BS Civil Engineering", yearLevel: 1, batch: "AY 2024-2025 Sem 1", accountStatus: "pending_activation", importedAt: "2025-08-12" },
  { id: "m3", studentNumber: "2023-08812", firstName: "Andrea Nicole", lastName: "Reyes", birthdate: "2002-11-03", email: "an.reyes@pup.edu.ph", contact: "+639192234569", university: "Polytechnic University of the Philippines", program: "BS Accountancy", yearLevel: 3, batch: "AY 2024-2025 Sem 1", accountStatus: "active", importedAt: "2025-08-12" },
  { id: "m4", studentNumber: "2024-00567", firstName: "Joshua", lastName: "Tan", birthdate: "2003-07-22", email: "j.tan@dlsud.edu.ph", contact: "+639172234570", university: "De La Salle University - Dasmariñas", program: "BS Information Technology", yearLevel: 2, batch: "AY 2024-2025 Sem 1", accountStatus: "inactive", importedAt: "2025-08-12" },
  { id: "m5", studentNumber: "2024-00568", firstName: "Patricia Mae", lastName: "Lim", birthdate: "2004-09-30", email: "pm.lim@adamson.edu.ph", contact: "+639183234571", university: "Adamson University", program: "BS Chemical Engineering", yearLevel: 1, batch: "AY 2024-2025 Sem 1", accountStatus: "pending_activation", importedAt: "2025-08-12" },
  { id: "m6", studentNumber: "2023-08813", firstName: "Marco", lastName: "Villanueva", birthdate: "2002-04-17", email: "m.villanueva@feu.edu.ph", contact: "+639174234572", university: "Far Eastern University", program: "BS Architecture", yearLevel: 3, batch: "AY 2024-2025 Sem 1", accountStatus: "active", importedAt: "2025-08-12" },
  { id: "m7", studentNumber: "2024-00569", firstName: "Bea", lastName: "Mendoza", birthdate: "2004-12-05", email: "b.mendoza@ust.edu.ph", contact: "+639195234573", university: "University of Santo Tomas", program: "BS Nursing", yearLevel: 1, batch: "AY 2024-2025 Sem 1", accountStatus: "inactive", importedAt: "2025-08-12" },
  { id: "m8", studentNumber: "2024-00570", firstName: "Daniel", lastName: "Garcia", birthdate: "2003-03-11", email: "d.garcia@mapua.edu.ph", contact: "+639176234574", university: "Mapua University", program: "BS Electronics Engineering", yearLevel: 2, batch: "AY 2024-2025 Sem 1", accountStatus: "active", importedAt: "2025-08-12" },
  { id: "m9", studentNumber: "2024-00571", firstName: "Sophia", lastName: "Aquino", birthdate: "2004-06-29", email: "s.aquino@addu.edu.ph", contact: "+639197234575", university: "Ateneo de Davao University", program: "AB Political Science", yearLevel: 1, batch: "AY 2024-2025 Sem 1", accountStatus: "pending_activation", importedAt: "2025-08-12" },
  { id: "m10", studentNumber: "2024-00571", firstName: "Sophia", lastName: "Aquino", birthdate: "2004-06-29", email: "s.aquino@addu.edu.ph", contact: "+639197234575", university: "Ateneo de Davao University", program: "AB Political Science", yearLevel: 1, batch: "AY 2024-2025 Sem 1", accountStatus: "duplicate", importedAt: "2025-08-12" },
  { id: "m11", studentNumber: "", firstName: "Liam", lastName: "Perez", birthdate: "2004-01-14", email: "invalid-email", contact: "n/a", university: "Saint Louis University", program: "BS Psychology", yearLevel: 1, batch: "AY 2024-2025 Sem 1", accountStatus: "invalid", importedAt: "2025-08-12" },
];
