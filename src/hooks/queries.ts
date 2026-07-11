/**
 * Mock-only data layer. Every hook returns local mock data — no backend calls.
 * Mutations mutate module-scope arrays and invalidate queries so the UI reacts.
 */
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useAuthStore } from "@/stores/authStore";

import { mockMasterlist } from "@/data/mockMasterlist";
import { mockDocuments, type DocumentItem } from "@/data/mockDocuments";
import { mockNotifications, type Notification } from "@/data/mockNotifications";
import { mockBatches } from "@/data/mockBatches";
import { mockGrantees } from "@/data/mockGrantees";
import { mockAcademicRecords } from "@/data/mockAcademicRecords";
import { mockAnnouncements } from "@/data/mockAnnouncements";
import { mockAuditLogs } from "@/data/mockAuditLogs";
import { mockUsers } from "@/data/mockUsers";

/* ---------------- Masterlist ---------------- */
export interface MasterlistRow {
  id: string;
  student_number: string;
  first_name: string;
  last_name: string;
  middle_name: string | null;
  birthdate: string | null;
  email: string | null;
  contact: string | null;
  university: string | null;
  program: string | null;
  year_level: number | null;
  batch: string | null;
  account_status: string;
  imported_at: string;
}

const masterlistRows: MasterlistRow[] = mockMasterlist.map((m) => ({
  id: m.id,
  student_number: m.studentNumber,
  first_name: m.firstName,
  last_name: m.lastName,
  middle_name: m.middleName ?? null,
  birthdate: m.birthdate,
  email: m.email,
  contact: m.contact,
  university: m.university,
  program: m.program,
  year_level: m.yearLevel,
  batch: m.batch,
  account_status: m.accountStatus,
  imported_at: m.importedAt,
}));

export function useMasterlist() {
  return useQuery({
    queryKey: ["masterlist"],
    queryFn: async () => masterlistRows,
  });
}

/* ---------------- Documents ---------------- */
export type DocStatus = "pending" | "approved" | "rejected" | "resubmission" | "suspicious";

export interface DocumentRow {
  id: string;
  owner_id: string | null;
  grantee_name: string;
  student_number: string;
  type: string;
  filename: string;
  uploaded_at: string;
  status: DocStatus;
  risk_score: number;
  remarks: string | null;
  ocr: Record<string, string> | null;
  exif: Record<string, string> | null;
}

// Owner id mapping: default student demo owns their docs.
const STUDENT_OWNER = "user-student";

const documentRows: DocumentRow[] = mockDocuments.map((d: DocumentItem, i) => ({
  id: d.id,
  owner_id: i === 0 ? STUDENT_OWNER : null,
  grantee_name: d.granteeName,
  student_number: d.studentNumber,
  type: d.type,
  filename: d.filename,
  uploaded_at: d.uploadedAt,
  status: d.status,
  risk_score: d.riskScore,
  remarks: d.remarks ?? null,
  ocr: d.ocr ?? null,
  exif: d.exif ?? null,
}));

export function useDocuments(opts: { ownerOnly?: boolean } = {}) {
  const userId = useAuthStore((s) => s.userId);
  return useQuery({
    queryKey: ["documents", opts.ownerOnly ? userId : "all"],
    enabled: !opts.ownerOnly || !!userId,
    queryFn: async () => {
      let list = [...documentRows].sort((a, b) => b.uploaded_at.localeCompare(a.uploaded_at));
      if (opts.ownerOnly && userId) list = list.filter((d) => d.owner_id === userId);
      return list;
    },
  });
}

export function useDocument(id: string | undefined) {
  return useQuery({
    queryKey: ["document", id],
    enabled: !!id,
    queryFn: async () => documentRows.find((d) => d.id === id) ?? null,
  });
}

export function useUpdateDocumentStatus() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (args: { id: string; status: DocStatus; remarks?: string }) => {
      const row = documentRows.find((d) => d.id === args.id);
      if (!row) throw new Error("Document not found");
      row.status = args.status;
      row.remarks = args.remarks ?? null;
    },
    onSuccess: (_, vars) => {
      qc.invalidateQueries({ queryKey: ["documents"] });
      qc.invalidateQueries({ queryKey: ["document", vars.id] });
    },
  });
}

export function useUploadDocument() {
  const qc = useQueryClient();
  const profile = useAuthStore((s) => s.profile);
  const userId = useAuthStore((s) => s.userId);
  return useMutation({
    mutationFn: async (args: { type: string; filename: string }) => {
      if (!userId || !profile) throw new Error("Not signed in");
      documentRows.unshift({
        id: `d-${Date.now()}`,
        owner_id: userId,
        grantee_name: profile.full_name,
        student_number: profile.student_number ?? "",
        type: args.type,
        filename: args.filename,
        uploaded_at: new Date().toISOString().slice(0, 16).replace("T", " "),
        status: "pending",
        risk_score: 0,
        remarks: null,
        ocr: null,
        exif: null,
      });
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ["documents"] }),
  });
}

/* ---------------- Notifications ---------------- */
export interface NotificationRow {
  id: string;
  user_id: string;
  title: string;
  body: string | null;
  type: "info" | "success" | "warning" | "danger";
  read: boolean;
  created_at: string;
}

const notificationRows: NotificationRow[] = mockNotifications.map((n: Notification) => ({
  id: n.id,
  user_id: STUDENT_OWNER,
  title: n.title,
  body: n.body,
  type: n.type,
  read: n.read,
  created_at: n.createdAt,
}));

export function useNotifications() {
  const userId = useAuthStore((s) => s.userId);
  return useQuery({
    queryKey: ["notifications", userId],
    enabled: !!userId,
    queryFn: async () => [...notificationRows].sort((a, b) => b.created_at.localeCompare(a.created_at)),
  });
}

export function useMarkNotificationRead() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (id: string | "all") => {
      if (id === "all") notificationRows.forEach((n) => (n.read = true));
      else {
        const n = notificationRows.find((x) => x.id === id);
        if (n) n.read = true;
      }
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ["notifications"] }),
  });
}

/* ---------------- Batches ---------------- */
export interface BatchRow {
  id: string; name: string; academicYear: string; semester: string;
  status: "open" | "closed" | "archived"; totalGrantees: number; active: number;
  pending: number; validated: number; createdAt: string;
}
export function useBatches() {
  return useQuery({
    queryKey: ["batches"],
    queryFn: async (): Promise<BatchRow[]> => mockBatches.map((b) => ({ ...b })),
  });
}

/* ---------------- Grantees ---------------- */
export interface GranteeRow {
  id: string; studentNumber: string; firstName: string; lastName: string; middleName?: string;
  birthdate: string; email: string; contact: string; university: string; program: string;
  yearLevel: number; batchId: string; batch: string;
  accountStatus: "active" | "inactive" | "pending_activation" | "locked";
  submissionStatus: "not_submitted" | "submitted" | "under_review" | "approved" | "rejected" | "resubmission_required";
  eligibility: "eligible" | "ineligible" | "pending" | "for_evaluation";
  risk: "low" | "medium" | "high"; gwa: number; profileCompletion: number; notes?: string;
}
export function useGrantees() {
  return useQuery({
    queryKey: ["grantees"],
    queryFn: async (): Promise<GranteeRow[]> => mockGrantees.map((g) => ({ ...g })),
  });
}
export function useGrantee(id: string | undefined) {
  return useQuery({
    queryKey: ["grantee", id],
    enabled: !!id,
    queryFn: async () => mockGrantees.find((g) => g.id === id) ?? null,
  });
}

/* ---------------- Academic Records ---------------- */
export interface AcademicSemester {
  semester: string; gwa: number; unitsTaken: number; unitsPassed: number; failed: string[]; dropped: string[];
}
export interface AcademicRow {
  granteeId: string; studentNumber: string; granteeName: string; program: string;
  cumulativeGwa: number; retentionPassed: boolean;
  recommendation: "eligible" | "ineligible" | "for_evaluation"; semesters: AcademicSemester[];
}
export function useAcademicRecords() {
  return useQuery({
    queryKey: ["academic"],
    queryFn: async (): Promise<AcademicRow[]> => mockAcademicRecords.map((r) => ({ ...r })),
  });
}
export function useAcademicRecord(id: string | undefined) {
  return useQuery({
    queryKey: ["academic", id],
    enabled: !!id,
    queryFn: async () => mockAcademicRecords.find((r) => r.granteeId === id) ?? null,
  });
}

/* ---------------- Announcements ---------------- */
export interface AnnouncementRow {
  id: string; title: string; body: string;
  audience: "all" | "batch" | "pending" | "rejected" | "eligible"; audienceLabel: string;
  channels: ("in_app" | "email" | "sms")[]; status: "draft" | "scheduled" | "published";
  publishedAt?: string; scheduledFor?: string; author: string; reach?: number; opens?: number;
}
export function useAnnouncements() {
  return useQuery({
    queryKey: ["announcements"],
    queryFn: async (): Promise<AnnouncementRow[]> => mockAnnouncements.map((a) => ({ ...a })),
  });
}
export function useAnnouncement(id: string | undefined) {
  return useQuery({
    queryKey: ["announcement", id],
    enabled: !!id,
    queryFn: async () => mockAnnouncements.find((a) => a.id === id) ?? null,
  });
}

/* ---------------- Audit Logs ---------------- */
export interface AuditLogRow {
  id: string; user: string; role: string; action: string; module: string;
  target: string; ip: string; timestamp: string;
  before?: Record<string, unknown>; after?: Record<string, unknown>;
}
export function useAuditLogs() {
  return useQuery({
    queryKey: ["audit_logs"],
    queryFn: async (): Promise<AuditLogRow[]> => mockAuditLogs.map((l) => ({ ...l })),
  });
}

export function useAppendAuditLog() {
  const qc = useQueryClient();
  const profile = useAuthStore((s) => s.profile);
  const role = useAuthStore((s) => s.role);
  return useMutation({
    mutationFn: async (args: {
      action: string;
      module: string;
      target: string;
      before?: Record<string, unknown>;
      after?: Record<string, unknown>;
    }) => {
      mockAuditLogs.unshift({
        id: `a-${Date.now()}`,
        user: profile?.full_name || "system",
        role: role ?? "system",
        action: args.action,
        module: args.module,
        target: args.target,
        ip: "127.0.0.1",
        timestamp: new Date().toISOString().slice(0, 19).replace("T", " "),
        before: args.before,
        after: args.after,
      });
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ["audit_logs"] }),
  });
}

export function useReassignDocument() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (args: { id: string; type?: string; grantee_name?: string; student_number?: string }) => {
      const row = mockDocuments.find((d) => d.id === args.id);
      if (!row) throw new Error("Document not found");
      const before = { type: row.type, granteeName: row.granteeName, studentNumber: row.studentNumber };
      if (args.type !== undefined) row.type = args.type;
      if (args.grantee_name !== undefined) row.granteeName = args.grantee_name;
      if (args.student_number !== undefined) row.studentNumber = args.student_number;
      return { before, after: { type: row.type, granteeName: row.granteeName, studentNumber: row.studentNumber } };
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ["documents"] }),
  });
}

/* ---------------- Staff Directory ---------------- */
export interface StaffUserRow {
  id: string; username: string; fullName: string; email: string;
  role: string; active: boolean; mfa: boolean; lastLogin: string;
}
export function useStaffUsers() {
  return useQuery({
    queryKey: ["staff_directory"],
    queryFn: async (): Promise<StaffUserRow[]> =>
      mockUsers.map((u) => ({
        id: u.id, username: u.username, fullName: u.fullName, email: u.email,
        role: u.role, active: u.active, mfa: u.mfa, lastLogin: u.lastLogin,
      })),
  });
}
