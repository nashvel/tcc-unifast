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
        risk_score: Math.floor(Math.random() * 35),
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
