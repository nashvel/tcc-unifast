// Re-export from vercel/mocks for backward compatibility
export {
  mockUsers,
  roleFromEmail,
  mockBatches,
  mockBatchDetail,
  mockGrantees,
  mockGranteeDetail,
  mockAcademicRecords,
  mockAcademicDetail,
  mockDocuments,
  mockDocumentDetail,
  mockDocumentPackages,
  mockAuditLogs,
  mockNotifications,
  mockSubmissionWindow,
  mockVault,
  mockTables,
  mockDbStats,
  mockUserTable,
  mockUserRows,
  mockTerms,
  mockFaqs,
} from "../../vercel/mocks";

// Legacy alias
export const mockUser = {
  id: 1,
  name: "System Developer",
  email: "admin@unifast.gov.ph",
  role: "developer" as const,
  student_id: null,
  account_status: "active" as const,
};
