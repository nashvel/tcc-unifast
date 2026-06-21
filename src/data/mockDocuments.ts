export type DocStatus = "pending" | "approved" | "rejected" | "resubmission" | "suspicious";

export interface DocumentItem {
  id: string;
  granteeId: string;
  granteeName: string;
  studentNumber: string;
  type: string;
  filename: string;
  uploadedAt: string;
  status: DocStatus;
  riskScore: number; // 0-100
  remarks?: string;
  ocr?: Record<string, string>;
  exif?: Record<string, string>;
}

export const requiredDocs = [
  "Student ID",
  "Course History",
  "Selfie with ID",
];


export const mockDocuments: DocumentItem[] = [
  { id: "d1", granteeId: "g1", granteeName: "Maria Clara Dela Cruz", studentNumber: "2024-10000", type: "Certificate of Registration", filename: "COR_delacruz_sem1.pdf", uploadedAt: "2026-06-18 09:14", status: "pending", riskScore: 18, ocr: { name: "Maria Clara Dela Cruz", studentNo: "2024-10000", units: "21" }, exif: { device: "iPhone 13", takenAt: "2026-06-18 09:10", gps: "14.5995, 120.9842" } },
  { id: "d2", granteeId: "g2", granteeName: "Juan Miguel Santos", studentNumber: "2024-10001", type: "Grade Report / TOR", filename: "TOR_santos.pdf", uploadedAt: "2026-06-17 16:42", status: "approved", riskScore: 8, remarks: "All grades match academic record.", ocr: { name: "Juan Miguel Santos", gwa: "1.75" }, exif: { device: "Scanner", takenAt: "2026-06-17 16:30" } },
  { id: "d3", granteeId: "g3", granteeName: "Andrea Nicole Reyes", studentNumber: "2024-10002", type: "Valid Government ID", filename: "ID_reyes.jpg", uploadedAt: "2026-06-17 11:08", status: "suspicious", riskScore: 82, remarks: "Possible image tampering detected.", ocr: { name: "Andrea N. Reyes", idNo: "1234-5678-9012" }, exif: { device: "Photoshop 25.0", takenAt: "2026-06-17 11:05" } },
  { id: "d4", granteeId: "g4", granteeName: "Joshua Tan", studentNumber: "2024-10003", type: "Birth Certificate (PSA)", filename: "PSA_tan.pdf", uploadedAt: "2026-06-16 10:21", status: "resubmission", riskScore: 45, remarks: "Document is blurred. Please re-upload a clear copy.", ocr: { name: "Joshua Tan", dob: "2003-07-22" } },
  { id: "d5", granteeId: "g5", granteeName: "Patricia Mae Lim", studentNumber: "2024-10004", type: "Selfie with ID (Liveness)", filename: "selfie_lim.jpg", uploadedAt: "2026-06-16 08:55", status: "pending", riskScore: 22, ocr: {}, exif: { device: "Samsung A52", takenAt: "2026-06-16 08:54", gps: "14.6760, 121.0437" } },
  { id: "d6", granteeId: "g6", granteeName: "Marco Villanueva", studentNumber: "2024-10005", type: "Certificate of Indigency", filename: "indigency_villanueva.pdf", uploadedAt: "2026-06-15 14:30", status: "rejected", riskScore: 60, remarks: "Document signature does not match barangay records." },
  { id: "d7", granteeId: "g7", granteeName: "Bea Mendoza", studentNumber: "2024-10006", type: "Certificate of Registration", filename: "COR_mendoza.pdf", uploadedAt: "2026-06-15 09:12", status: "approved", riskScore: 5 },
  { id: "d8", granteeId: "g8", granteeName: "Daniel Garcia", studentNumber: "2024-10007", type: "Grade Report / TOR", filename: "TOR_garcia.pdf", uploadedAt: "2026-06-14 17:01", status: "pending", riskScore: 30 },
];
