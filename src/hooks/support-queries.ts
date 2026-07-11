import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { supabase } from "@/integrations/supabase/client";
import { useAuthStore } from "@/stores/authStore";

export type SupportCategory = "bug" | "question" | "request";
export type SupportPriority = "low" | "normal" | "high";
export type SupportStatus = "open" | "in_progress" | "resolved" | "closed";

export interface SupportTicket {
  id: string;
  subject: string;
  body: string;
  category: SupportCategory;
  priority: SupportPriority;
  status: SupportStatus;
  created_by: string;
  assigned_to: string | null;
  created_at: string;
  updated_at: string;
  creator_name?: string | null;
  assignee_name?: string | null;
}

export interface SupportMessage {
  id: string;
  ticket_id: string;
  author_id: string;
  body: string;
  is_internal: boolean;
  created_at: string;
  author_name?: string | null;
}

async function attachProfiles<T extends { created_by?: string; assigned_to?: string | null; author_id?: string }>(
  rows: T[],
): Promise<Record<string, string>> {
  const ids = new Set<string>();
  rows.forEach((r) => {
    if (r.created_by) ids.add(r.created_by);
    if (r.assigned_to) ids.add(r.assigned_to);
    if (r.author_id) ids.add(r.author_id);
  });
  if (ids.size === 0) return {};
  const { data } = await supabase
    .from("profiles")
    .select("id, full_name, email")
    .in("id", Array.from(ids));
  const map: Record<string, string> = {};
  (data ?? []).forEach((p) => {
    map[p.id] = p.full_name?.trim() || p.email || "Unknown";
  });
  return map;
}

export function useSupportTickets() {
  return useQuery({
    queryKey: ["support_tickets"],
    queryFn: async (): Promise<SupportTicket[]> => {
      const { data, error } = await supabase
        .from("support_tickets")
        .select("*")
        .order("updated_at", { ascending: false });
      if (error) throw error;
      const rows = (data ?? []) as SupportTicket[];
      const names = await attachProfiles(rows);
      return rows.map((r) => ({
        ...r,
        creator_name: names[r.created_by] ?? null,
        assignee_name: r.assigned_to ? names[r.assigned_to] ?? null : null,
      }));
    },
  });
}

export function useSupportTicket(id: string | undefined) {
  return useQuery({
    queryKey: ["support_tickets", id],
    enabled: !!id,
    queryFn: async () => {
      const { data: ticket, error } = await supabase
        .from("support_tickets").select("*").eq("id", id!).maybeSingle();
      if (error) throw error;
      if (!ticket) return null;

      const { data: msgs, error: mErr } = await supabase
        .from("support_ticket_messages")
        .select("*")
        .eq("ticket_id", id!)
        .order("created_at", { ascending: true });
      if (mErr) throw mErr;

      const messages = (msgs ?? []) as SupportMessage[];
      const names = await attachProfiles([ticket as SupportTicket, ...messages]);
      return {
        ticket: {
          ...(ticket as SupportTicket),
          creator_name: names[(ticket as SupportTicket).created_by] ?? null,
          assignee_name: (ticket as SupportTicket).assigned_to
            ? names[(ticket as SupportTicket).assigned_to!] ?? null : null,
        },
        messages: messages.map((m) => ({ ...m, author_name: names[m.author_id] ?? null })),
      };
    },
  });
}

export function useCreateTicket() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (input: {
      subject: string; body: string; category: SupportCategory; priority: SupportPriority;
    }) => {
      const userId = useAuthStore.getState().userId;
      if (!userId) throw new Error("Not signed in");
      const { data, error } = await supabase
        .from("support_tickets")
        .insert({ ...input, created_by: userId })
        .select("id")
        .single();
      if (error) throw error;
      return data;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ["support_tickets"] }),
  });
}

export function useAddTicketMessage(ticketId: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (input: { body: string; is_internal?: boolean }) => {
      const userId = useAuthStore.getState().userId;
      if (!userId) throw new Error("Not signed in");
      const { error } = await supabase
        .from("support_ticket_messages")
        .insert({
          ticket_id: ticketId,
          author_id: userId,
          body: input.body,
          is_internal: input.is_internal ?? false,
        });
      if (error) throw error;
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["support_tickets", ticketId] });
      qc.invalidateQueries({ queryKey: ["support_tickets"] });
    },
  });
}

export function useUpdateTicket(ticketId: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (patch: Partial<Pick<SupportTicket, "status" | "priority" | "assigned_to">>) => {
      const { error } = await supabase.from("support_tickets").update(patch).eq("id", ticketId);
      if (error) throw error;
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["support_tickets", ticketId] });
      qc.invalidateQueries({ queryKey: ["support_tickets"] });
    },
  });
}
