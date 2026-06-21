import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { supabase } from "@/integrations/supabase/client";
import { useAuthStore } from "@/stores/authStore";

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

export function useMasterlist() {
  return useQuery({
    queryKey: ["masterlist"],
    queryFn: async (): Promise<MasterlistRow[]> => {
      const { data, error } = await supabase
        .from("masterlist")
        .select("*")
        .order("imported_at", { ascending: false });
      if (error) throw error;
      return (data ?? []) as MasterlistRow[];
    },
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

export function useDocuments(opts: { ownerOnly?: boolean } = {}) {
  const userId = useAuthStore((s) => s.userId);
  return useQuery({
    queryKey: ["documents", opts.ownerOnly ? userId : "all"],
    enabled: !opts.ownerOnly || !!userId,
    queryFn: async (): Promise<DocumentRow[]> => {
      let q = supabase.from("documents").select("*").order("uploaded_at", { ascending: false });
      if (opts.ownerOnly && userId) q = q.eq("owner_id", userId);
      const { data, error } = await q;
      if (error) throw error;
      return (data ?? []) as DocumentRow[];
    },
  });
}

export function useDocument(id: string | undefined) {
  return useQuery({
    queryKey: ["document", id],
    enabled: !!id,
    queryFn: async (): Promise<DocumentRow | null> => {
      if (!id) return null;
      const { data, error } = await supabase.from("documents").select("*").eq("id", id).maybeSingle();
      if (error) throw error;
      return data as DocumentRow | null;
    },
  });
}

export function useUpdateDocumentStatus() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (args: { id: string; status: DocStatus; remarks?: string }) => {
      const { error } = await supabase
        .from("documents")
        .update({ status: args.status, remarks: args.remarks ?? null })
        .eq("id", args.id);
      if (error) throw error;
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
      const { error } = await supabase.from("documents").insert({
        owner_id: userId,
        grantee_name: profile.full_name,
        student_number: profile.student_number ?? "",
        type: args.type,
        filename: args.filename,
        status: "pending",
        // risk_score is enforced server-side via trigger; never client-supplied.
      });
      if (error) throw error;
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

export function useNotifications() {
  const userId = useAuthStore((s) => s.userId);
  return useQuery({
    queryKey: ["notifications", userId],
    enabled: !!userId,
    queryFn: async (): Promise<NotificationRow[]> => {
      const { data, error } = await supabase
        .from("notifications")
        .select("*")
        .order("created_at", { ascending: false });
      if (error) throw error;
      return (data ?? []) as NotificationRow[];
    },
  });
}

export function useMarkNotificationRead() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (id: string | "all") => {
      if (id === "all") {
        const { error } = await supabase.from("notifications").update({ read: true }).eq("read", false);
        if (error) throw error;
      } else {
        const { error } = await supabase.from("notifications").update({ read: true }).eq("id", id);
        if (error) throw error;
      }
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ["notifications"] }),
  });
}

/* ---------------- Batches ---------------- */
export interface BatchRow {
  id: string; name: string; academicYear: string; semester: string;
  status: "open"|"closed"|"archived"; totalGrantees: number; active: number;
  pending: number; validated: number; createdAt: string;
}
export function useBatches() {
  return useQuery({
    queryKey: ["batches"],
    queryFn: async (): Promise<BatchRow[]> => {
      const { data, error } = await supabase.from("batches").select("*").order("created_at", { ascending: false });
      if (error) throw error;
      return (data ?? []).map((b: any) => ({
        id: b.id, name: b.name, academicYear: b.academic_year, semester: b.semester,
        status: b.status, totalGrantees: b.total_grantees, active: b.active,
        pending: b.pending, validated: b.validated, createdAt: b.created_at,
      }));
    },
  });
}

/* ---------------- Grantees ---------------- */
export interface GranteeRow {
  id: string; studentNumber: string; firstName: string; lastName: string; middleName?: string;
  birthdate: string; email: string; contact: string; university: string; program: string;
  yearLevel: number; batchId: string; batch: string;
  accountStatus: "active"|"inactive"|"pending_activation"|"locked";
  submissionStatus: "not_submitted"|"submitted"|"under_review"|"approved"|"rejected"|"resubmission_required";
  eligibility: "eligible"|"ineligible"|"pending"|"for_evaluation";
  risk: "low"|"medium"|"high"; gwa: number; profileCompletion: number; notes?: string;
}
function mapGrantee(g: any): GranteeRow {
  return {
    id: g.id, studentNumber: g.student_number, firstName: g.first_name, lastName: g.last_name,
    middleName: g.middle_name ?? undefined, birthdate: g.birthdate ?? "", email: g.email ?? "",
    contact: g.contact ?? "", university: g.university ?? "", program: g.program ?? "",
    yearLevel: g.year_level ?? 0, batchId: g.batch_id ?? "", batch: g.batch ?? "",
    accountStatus: g.account_status, submissionStatus: g.submission_status,
    eligibility: g.eligibility, risk: g.risk, gwa: Number(g.gwa ?? 0),
    profileCompletion: g.profile_completion, notes: g.notes ?? undefined,
  };
}
export function useGrantees() {
  return useQuery({
    queryKey: ["grantees"],
    queryFn: async (): Promise<GranteeRow[]> => {
      const { data, error } = await supabase.from("grantees").select("*").order("id");
      if (error) throw error;
      return (data ?? []).map(mapGrantee);
    },
  });
}
export function useGrantee(id: string | undefined) {
  return useQuery({
    queryKey: ["grantee", id], enabled: !!id,
    queryFn: async (): Promise<GranteeRow | null> => {
      if (!id) return null;
      const { data, error } = await supabase.from("grantees").select("*").eq("id", id).maybeSingle();
      if (error) throw error;
      return data ? mapGrantee(data) : null;
    },
  });
}

/* ---------------- Academic Records ---------------- */
export interface AcademicSemester {
  semester: string; gwa: number; unitsTaken: number; unitsPassed: number; failed: string[]; dropped: string[];
}
export interface AcademicRow {
  granteeId: string; studentNumber: string; granteeName: string; program: string;
  cumulativeGwa: number; retentionPassed: boolean;
  recommendation: "eligible"|"ineligible"|"for_evaluation"; semesters: AcademicSemester[];
}
function mapAcademic(r: any): AcademicRow {
  return {
    granteeId: r.grantee_id, studentNumber: r.student_number, granteeName: r.grantee_name,
    program: r.program ?? "", cumulativeGwa: Number(r.cumulative_gwa ?? 0),
    retentionPassed: r.retention_passed, recommendation: r.recommendation,
    semesters: (r.semesters ?? []) as AcademicSemester[],
  };
}
export function useAcademicRecords() {
  return useQuery({
    queryKey: ["academic"],
    queryFn: async (): Promise<AcademicRow[]> => {
      const { data, error } = await supabase.from("academic_records").select("*");
      if (error) throw error;
      return (data ?? []).map(mapAcademic);
    },
  });
}
export function useAcademicRecord(id: string | undefined) {
  return useQuery({
    queryKey: ["academic", id], enabled: !!id,
    queryFn: async (): Promise<AcademicRow | null> => {
      if (!id) return null;
      const { data, error } = await supabase.from("academic_records").select("*").eq("grantee_id", id).maybeSingle();
      if (error) throw error;
      return data ? mapAcademic(data) : null;
    },
  });
}

/* ---------------- Announcements ---------------- */
export interface AnnouncementRow {
  id: string; title: string; body: string;
  audience: "all"|"batch"|"pending"|"rejected"|"eligible"; audienceLabel: string;
  channels: ("in_app"|"email"|"sms")[]; status: "draft"|"scheduled"|"published";
  publishedAt?: string; scheduledFor?: string; author: string; reach?: number; opens?: number;
}
function mapAnnouncement(a: any): AnnouncementRow {
  return {
    id: a.id, title: a.title, body: a.body, audience: a.audience,
    audienceLabel: a.audience_label ?? "", channels: a.channels ?? [], status: a.status,
    publishedAt: a.published_at ?? undefined, scheduledFor: a.scheduled_for ?? undefined,
    author: a.author ?? "", reach: a.reach ?? undefined, opens: a.opens ?? undefined,
  };
}
export function useAnnouncements() {
  return useQuery({
    queryKey: ["announcements"],
    queryFn: async (): Promise<AnnouncementRow[]> => {
      const { data, error } = await supabase.from("announcements").select("*").order("created_at", { ascending: false });
      if (error) throw error;
      return (data ?? []).map(mapAnnouncement);
    },
  });
}
export function useAnnouncement(id: string | undefined) {
  return useQuery({
    queryKey: ["announcement", id], enabled: !!id,
    queryFn: async (): Promise<AnnouncementRow | null> => {
      if (!id) return null;
      const { data, error } = await supabase.from("announcements").select("*").eq("id", id).maybeSingle();
      if (error) throw error;
      return data ? mapAnnouncement(data) : null;
    },
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
    queryFn: async (): Promise<AuditLogRow[]> => {
      const { data, error } = await supabase.from("audit_logs").select("*").order("timestamp", { ascending: false });
      if (error) throw error;
      return (data ?? []).map((l: any) => ({
        id: l.id, user: l.user, role: l.role, action: l.action, module: l.module,
        target: l.target, ip: l.ip ?? "", timestamp: new Date(l.timestamp).toLocaleString("sv-SE").replace("T", " ").slice(0, 16),
        before: l.before ?? undefined, after: l.after ?? undefined,
      }));
    },
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
    queryFn: async (): Promise<StaffUserRow[]> => {
      const { data, error } = await supabase.from("staff_directory").select("*").order("username");
      if (error) throw error;
      return (data ?? []).map((u: any) => ({
        id: u.id, username: u.username, fullName: u.full_name, email: u.email,
        role: u.role, active: u.active, mfa: u.mfa,
        lastLogin: u.last_login ? new Date(u.last_login).toLocaleString("sv-SE").replace("T", " ").slice(0, 16) : "",
      }));
    },
  });
}
