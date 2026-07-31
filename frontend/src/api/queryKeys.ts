export const queryKeys = {
  batches: ["batches"] as const,
  batch: (id: string | number) => ["batches", String(id)] as const,
  academic: (params?: unknown) => ["academic-records", params] as const,
  academicDetail: (id: string | number) => ["academic-records", String(id)] as const,
  documents: (params?: unknown) => ["document-submissions", params] as const,
  document: (id: string | number) => ["document-submissions", String(id)] as const,
  documentPackages: (params?: unknown) => ["document-submission-packages", params] as const,
  documentPackage: (granteeId: string | number, batchId: string | number) =>
    ["document-submission-packages", String(granteeId), String(batchId)] as const,
  notifications: ["notifications"] as const,
  grantees: (params?: unknown) => ["grantees", params] as const,
  grantee: (id: string | number) => ["grantees", String(id)] as const,
eligibility: (params?: unknown) => ["eligibility", params] as const,
  eligibilityDetail: (id: string | number) => ["eligibility", String(id)] as const,
  auditLogs: ["audit-logs"] as const,
  submissionWindow: ["submission-window"] as const,
};
