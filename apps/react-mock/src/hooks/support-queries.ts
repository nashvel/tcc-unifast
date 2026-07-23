import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
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

const TICKETS_KEY = "tcc-unifast.mock.support.tickets";
const MESSAGES_KEY = "tcc-unifast.mock.support.messages";

const defaultTickets: SupportTicket[] = [
  {
    id: "ticket-001",
    subject: "Cannot upload my COR",
    body: "The upload keeps stopping before it reaches 100%.",
    category: "bug",
    priority: "high",
    status: "open",
    created_by: "student-001",
    assigned_to: "staff-001",
    created_at: "2026-07-11T09:30:00.000Z",
    updated_at: "2026-07-11T10:20:00.000Z",
    creator_name: "Maria Angela Santos",
    assignee_name: "UniFAST Staff",
  },
  {
    id: "ticket-002",
    subject: "Need help with validation status",
    body: "My requirements are uploaded but my account still says limited access.",
    category: "question",
    priority: "normal",
    status: "in_progress",
    created_by: "student-002",
    assigned_to: "staff-001",
    created_at: "2026-07-10T08:15:00.000Z",
    updated_at: "2026-07-11T08:40:00.000Z",
    creator_name: "John Paul Ramirez",
    assignee_name: "UniFAST Staff",
  },
  {
    id: "ticket-003",
    subject: "Request to update contact number",
    body: "I changed my mobile number and need it updated in my profile.",
    category: "request",
    priority: "low",
    status: "resolved",
    created_by: "student-003",
    assigned_to: "staff-002",
    created_at: "2026-07-09T06:25:00.000Z",
    updated_at: "2026-07-10T12:05:00.000Z",
    creator_name: "Nicole Anne Flores",
    assignee_name: "Office Head",
  },
];

const defaultMessages: Record<string, SupportMessage[]> = {
  "ticket-001": [
    {
      id: "message-001",
      ticket_id: "ticket-001",
      author_id: "student-001",
      body: "The file is a clear PDF and below the max size.",
      is_internal: false,
      created_at: "2026-07-11T09:35:00.000Z",
      author_name: "Maria Angela Santos",
    },
    {
      id: "message-002",
      ticket_id: "ticket-001",
      author_id: "staff-001",
      body: "Thanks. Please try the alternate upload slot while we check the queue.",
      is_internal: false,
      created_at: "2026-07-11T10:20:00.000Z",
      author_name: "UniFAST Staff",
    },
  ],
  "ticket-002": [
    {
      id: "message-003",
      ticket_id: "ticket-002",
      author_id: "staff-001",
      body: "Your ID scan passed. We are only waiting for the COR review.",
      is_internal: false,
      created_at: "2026-07-11T08:40:00.000Z",
      author_name: "UniFAST Staff",
    },
  ],
};

function readJson<T>(key: string, fallback: T): T {
  if (typeof window === "undefined") return fallback;

  const raw = window.localStorage.getItem(key);
  if (!raw) {
    window.localStorage.setItem(key, JSON.stringify(fallback));
    return fallback;
  }

  try {
    return JSON.parse(raw) as T;
  } catch {
    window.localStorage.setItem(key, JSON.stringify(fallback));
    return fallback;
  }
}

function writeJson<T>(key: string, value: T) {
  if (typeof window === "undefined") return;
  window.localStorage.setItem(key, JSON.stringify(value));
}

function getTickets() {
  return readJson<SupportTicket[]>(TICKETS_KEY, defaultTickets);
}

function setTickets(tickets: SupportTicket[]) {
  writeJson(TICKETS_KEY, tickets);
}

function getMessages() {
  return readJson<Record<string, SupportMessage[]>>(MESSAGES_KEY, defaultMessages);
}

function setMessages(messages: Record<string, SupportMessage[]>) {
  writeJson(MESSAGES_KEY, messages);
}

function currentUser() {
  const state = useAuthStore.getState();
  return {
    id: state.userId ?? "mock-user",
    name: state.profile?.full_name?.trim() || state.email || "Mock User",
  };
}

export function useSupportTickets() {
  return useQuery({
    queryKey: ["support_tickets"],
    queryFn: async (): Promise<SupportTicket[]> =>
      [...getTickets()].sort((a, b) => b.updated_at.localeCompare(a.updated_at)),
  });
}

export function useSupportTicket(id: string | undefined) {
  return useQuery({
    queryKey: ["support_tickets", id],
    enabled: !!id,
    queryFn: async () => {
      const ticket = getTickets().find((item) => item.id === id);
      if (!ticket) return null;

      return {
        ticket,
        messages: (getMessages()[ticket.id] ?? []).sort((a, b) => a.created_at.localeCompare(b.created_at)),
      };
    },
  });
}

export function useCreateTicket() {
  const qc = useQueryClient();

  return useMutation({
    mutationFn: async (input: {
      subject: string;
      body: string;
      category: SupportCategory;
      priority: SupportPriority;
    }) => {
      const user = currentUser();
      const timestamp = new Date().toISOString();
      const ticket: SupportTicket = {
        id: `ticket-${Date.now()}`,
        ...input,
        status: "open",
        created_by: user.id,
        assigned_to: null,
        created_at: timestamp,
        updated_at: timestamp,
        creator_name: user.name,
        assignee_name: null,
      };

      setTickets([ticket, ...getTickets()]);
      setMessages({ ...getMessages(), [ticket.id]: [] });

      return { id: ticket.id };
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ["support_tickets"] }),
  });
}

export function useAddTicketMessage(ticketId: string) {
  const qc = useQueryClient();

  return useMutation({
    mutationFn: async (input: { body: string; is_internal?: boolean }) => {
      const user = currentUser();
      const timestamp = new Date().toISOString();
      const messages = getMessages();
      const nextMessage: SupportMessage = {
        id: `message-${Date.now()}`,
        ticket_id: ticketId,
        author_id: user.id,
        body: input.body,
        is_internal: input.is_internal ?? false,
        created_at: timestamp,
        author_name: user.name,
      };

      setMessages({
        ...messages,
        [ticketId]: [...(messages[ticketId] ?? []), nextMessage],
      });
      setTickets(
        getTickets().map((ticket) =>
          ticket.id === ticketId ? { ...ticket, updated_at: timestamp } : ticket,
        ),
      );
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
      const timestamp = new Date().toISOString();

      setTickets(
        getTickets().map((ticket) =>
          ticket.id === ticketId
            ? {
                ...ticket,
                ...patch,
                assignee_name:
                  patch.assigned_to === undefined
                    ? ticket.assignee_name
                    : patch.assigned_to
                      ? "Assigned Staff"
                      : null,
                updated_at: timestamp,
              }
            : ticket,
        ),
      );
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["support_tickets", ticketId] });
      qc.invalidateQueries({ queryKey: ["support_tickets"] });
    },
  });
}
