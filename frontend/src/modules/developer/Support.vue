<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { IconLifebuoy, IconSearch, IconPlus, IconMessage, IconLoader, IconSend, IconRefresh, IconAlertTriangle } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import { apiFetch, isMockMode } from "@/api/client";
import { toast } from "@/composables/useToast";

type TicketReply = {
  author: string;
  message: string;
  created_at: string;
};

type Ticket = {
  id: string | number;
  ticket_id: string;
  title: string;
  category: string;
  priority: "Low" | "Normal" | "High" | "Critical";
  status: "Open" | "In Progress" | "Waiting" | "Resolved";
  reporter: string;
  assignee: string;
  created_at?: string;
  createdAt?: string;
  replies: TicketReply[] | number;
  description?: string;
};

const initialTickets: Ticket[] = [
  { id: 1, ticket_id: "TK-001", title: "Face verification timeout after 30s", category: "bug", priority: "High", status: "Open", reporter: "Maria Santos", assignee: "System Developer", createdAt: "Jul 12, 2026", replies: [], description: "Face verification API times out on weak mobile connections." },
  { id: 2, ticket_id: "TK-002", title: "Request: CSV export for audit trail", category: "feature", priority: "Normal", status: "In Progress", reporter: "Office Administrator", assignee: "System Developer", createdAt: "Jul 11, 2026", replies: [], description: "Admin requested CSV export capability for developer audit logs." },
  { id: 3, ticket_id: "TK-003", title: "OCR mismatch on non-standard font transcripts", category: "bug", priority: "Normal", status: "Waiting", reporter: "UniFAST Staff", assignee: "System Developer", createdAt: "Jul 10, 2026", replies: [], description: "Special characters on course names cause low confidence score." },
];

const tickets = ref<Ticket[]>([]);
const loading = ref(false);
const search = ref("");
const statusFilter = ref("all");
const selectedTicket = ref<Ticket | null>(null);
const errorMessage = ref("");

const createDialog = ref(false);
const newTicket = ref({ title: "", category: "bug", priority: "Normal" as const, description: "" });
const replyText = ref("");
const sendingReply = ref(false);

async function loadTickets() {
  loading.value = true;
  errorMessage.value = "";
  try {
    const res = await apiFetch<{ data: Ticket[] }>("/api/support-tickets");
    if (res.data && res.data.length > 0) {
      tickets.value = res.data;
    } else {
      tickets.value = isMockMode ? initialTickets : [];
    }
  } catch (err: any) {
    if (isMockMode) {
      tickets.value = initialTickets;
    } else {
      errorMessage.value = err?.message || "Failed to connect to backend support ticket API.";
      toast.error(errorMessage.value);
      tickets.value = [];
    }
  } finally {
    loading.value = false;
    if (tickets.value.length > 0 && !selectedTicket.value) {
      selectedTicket.value = tickets.value[0];
    }
  }
}

async function createTicket() {
  if (!newTicket.value.title.trim()) {
    toast.error("Ticket title is required.");
    return;
  }

  try {
    const res = await apiFetch<{ data: Ticket }>("/api/support-tickets", {
      method: "POST",
      body: JSON.stringify(newTicket.value),
    });
    tickets.value.unshift(res.data);
    selectedTicket.value = res.data;
    toast.success("Support ticket created");
    createDialog.value = false;
    newTicket.value = { title: "", category: "bug", priority: "Normal", description: "" };
  } catch (err: any) {
    if (isMockMode) {
      const mockId = `TK-${String(tickets.value.length + 1).padStart(3, '0')}`;
      const created: Ticket = {
        id: Date.now(),
        ticket_id: mockId,
        title: newTicket.value.title,
        category: newTicket.value.category,
        priority: newTicket.value.priority,
        status: "Open",
        reporter: "System Developer",
        assignee: "System Developer",
        createdAt: new Date().toLocaleDateString(),
        replies: [],
        description: newTicket.value.description,
      };
      tickets.value.unshift(created);
      selectedTicket.value = created;
      toast.success("Support ticket created (Mock mode)");
      createDialog.value = false;
      newTicket.value = { title: "", category: "bug", priority: "Normal", description: "" };
    } else {
      toast.error(err?.message || "Failed to create ticket on server.");
    }
  }
}

async function updateStatus(newStatus: Ticket["status"]) {
  if (!selectedTicket.value) return;

  const targetId = selectedTicket.value.id;
  selectedTicket.value.status = newStatus;

  try {
    await apiFetch(`/api/support-tickets/${targetId}`, {
      method: "PATCH",
      body: JSON.stringify({ status: newStatus }),
    });
    toast.success(`Ticket marked as ${newStatus}`);
  } catch (err: any) {
    if (isMockMode) {
      toast.success(`Ticket marked as ${newStatus} (Mock mode)`);
    } else {
      toast.error(err?.message || "Failed to update ticket status on server.");
    }
  }
}

async function addReply() {
  if (!replyText.value.trim() || !selectedTicket.value) return;

  sendingReply.value = true;
  const msg = replyText.value.trim();
  const targetId = selectedTicket.value.id;

  try {
    const res = await apiFetch<{ data: Ticket }>(`/api/support-tickets/${targetId}`, {
      method: "PATCH",
      body: JSON.stringify({ reply: msg }),
    });
    selectedTicket.value = res.data;
    replyText.value = "";
    toast.success("Reply posted");
  } catch (err: any) {
    if (isMockMode) {
      const currentReplies = Array.isArray(selectedTicket.value.replies) ? selectedTicket.value.replies : [];
      currentReplies.push({
        author: "System Developer",
        message: msg,
        created_at: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
      });
      selectedTicket.value.replies = currentReplies;
      replyText.value = "";
      toast.success("Reply posted (Mock mode)");
    } else {
      toast.error(err?.message || "Failed to post reply to server.");
    }
  } finally {
    sendingReply.value = false;
  }
}

const filteredTickets = computed(() =>
  tickets.value.filter(
    (t) =>
      (statusFilter.value === "all" || t.status === statusFilter.value) &&
      (!search.value ||
        t.title.toLowerCase().includes(search.value.toLowerCase()) ||
        t.ticket_id.toLowerCase().includes(search.value.toLowerCase())),
  ),
);

const priorityColors: Record<string, string> = {
  Low: "bg-surface-muted text-text-muted",
  Normal: "bg-info-soft text-info",
  High: "bg-warning-soft text-warning",
  Critical: "bg-danger-soft text-danger",
};

const statusColors: Record<string, string> = {
  Open: "bg-success-soft text-success",
  "In Progress": "bg-info-soft text-info",
  Waiting: "bg-warning-soft text-warning",
  Resolved: "bg-surface-muted text-text-muted",
};

onMounted(loadTickets);
</script>

<template>
  <div>
    <PageHeader
      title="Support Tickets"
      description="Track and resolve system issues with real backend ticketing."
    >
      <template #actions>
        <button
          class="inline-flex h-9 items-center gap-1.5 rounded-md bg-white text-black font-medium px-3 text-xs hover:bg-neutral-200 transition-colors shadow-sm"
          @click="createDialog = true"
        >
          <IconPlus :size="14" /> New ticket
        </button>
      </template>
    </PageHeader>

    <div v-if="errorMessage" class="mb-4 rounded-lg border border-red-500/30 bg-red-950/40 p-4 text-xs text-red-200">
      {{ errorMessage }}
    </div>

    <div class="mb-4 grid gap-2 md:grid-cols-[1fr_150px]">
      <div class="relative">
        <IconSearch :size="14" class="absolute left-3 top-1/2 -translate-y-1/2 text-text-soft" />
        <input
          v-model="search"
          class="h-9 w-full rounded-md border pl-9 pr-3 text-xs"
          placeholder="Search tickets..."
        />
      </div>
      <select v-model="statusFilter" class="h-9 rounded-md border bg-surface px-3 text-xs">
        <option value="all">All Status</option>
        <option value="Open">Open</option>
        <option value="In Progress">In Progress</option>
        <option value="Waiting">Waiting</option>
        <option value="Resolved">Resolved</option>
      </select>
    </div>

    <div class="grid gap-4 lg:grid-cols-[1fr_380px]">
      <div class="space-y-2">
        <div v-if="loading" class="flex items-center justify-center p-8">
          <IconLoader :size="20" class="animate-spin text-text-muted" />
        </div>

        <template v-else-if="filteredTickets.length === 0">
          <div class="rounded-lg border border-dashed bg-surface p-8 text-center text-xs text-text-muted">
            {{ errorMessage ? "Unable to load support tickets from server." : "No support tickets found." }}
          </div>
        </template>

        <template v-else>
          <div
            v-for="ticket in filteredTickets"
            :key="ticket.id"
            :class="[
              'rounded-lg border bg-surface p-4 cursor-pointer transition hover:shadow-sm',
              selectedTicket?.id === ticket.id && 'border-primary ring-1 ring-primary/20',
            ]"
            @click="selectedTicket = ticket"
          >
            <div class="flex items-start justify-between gap-2">
              <div>
                <div class="flex items-center gap-2">
                  <span class="text-xs font-mono text-text-muted">{{ ticket.ticket_id }}</span>
                  <span :class="['rounded-full px-2 py-0.5 text-2xs font-semibold', priorityColors[ticket.priority]]">
                    {{ ticket.priority }}
                  </span>
                </div>
                <p class="mt-1 text-sm font-medium">{{ ticket.title }}</p>
              </div>
              <span :class="['rounded-full px-2 py-0.5 text-2xs font-semibold', statusColors[ticket.status]]">
                {{ ticket.status }}
              </span>
            </div>
            <div class="mt-2 flex items-center gap-3 text-2xs text-text-muted">
              <span>{{ ticket.reporter }}</span>
              <span>{{ ticket.createdAt || ticket.created_at }}</span>
              <span class="flex items-center gap-1">
                <IconMessage :size="10" />
                {{ Array.isArray(ticket.replies) ? ticket.replies.length : ticket.replies || 0 }}
              </span>
            </div>
          </div>
        </template>
      </div>

      <!-- Ticket Detail Panel -->
      <div v-if="selectedTicket" class="rounded-lg border bg-surface p-4 flex flex-col h-[520px]">
        <div class="border-b pb-3">
          <div class="flex items-center justify-between">
            <span class="font-mono text-xs text-text-muted">{{ selectedTicket.ticket_id }}</span>
            <select
              :value="selectedTicket.status"
              class="h-7 rounded border bg-surface px-2 text-2xs font-medium"
              @change="updateStatus(($event.target as HTMLSelectElement).value as any)"
            >
              <option value="Open">Open</option>
              <option value="In Progress">In Progress</option>
              <option value="Waiting">Waiting</option>
              <option value="Resolved">Resolved</option>
            </select>
          </div>
          <h2 class="mt-2 text-sm font-semibold text-text">{{ selectedTicket.title }}</h2>
          <p v-if="selectedTicket.description" class="mt-1 text-xs text-text-muted">{{ selectedTicket.description }}</p>
        </div>

        <!-- Replies list -->
        <div class="flex-1 overflow-y-auto py-3 space-y-3">
          <div
            v-if="!Array.isArray(selectedTicket.replies) || selectedTicket.replies.length === 0"
            class="text-center py-6 text-xs text-text-muted"
          >
            No replies yet on this ticket.
          </div>

          <div
            v-for="(r, idx) in (Array.isArray(selectedTicket.replies) ? selectedTicket.replies : [])"
            :key="idx"
            class="rounded-md bg-surface-muted p-2.5 text-xs"
          >
            <div class="flex items-center justify-between text-2xs text-text-muted mb-1">
              <span class="font-medium text-text">{{ r.author }}</span>
              <span>{{ r.created_at }}</span>
            </div>
            <p class="text-text">{{ r.message }}</p>
          </div>
        </div>

        <!-- Reply Input -->
        <div class="pt-2 border-t flex gap-2">
          <input
            v-model="replyText"
            placeholder="Type a response..."
            class="h-9 flex-1 rounded-md border px-3 text-xs bg-surface"
            @keyup.enter="addReply"
          />
          <button
            class="h-9 px-3 rounded-md bg-primary text-white text-xs font-medium inline-flex items-center gap-1 disabled:opacity-50"
            :disabled="sendingReply || !replyText.trim()"
            @click="addReply"
          >
            <IconSend :size="13" /> Post
          </button>
        </div>
      </div>

      <div v-else class="flex items-center justify-center rounded-lg border border-dashed bg-surface p-12">
        <p class="text-sm text-text-muted">Select a ticket to view details</p>
      </div>
    </div>

    <!-- Create Dialog -->
    <div
      v-if="createDialog"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
      @click.self="createDialog = false"
    >
      <div class="w-full max-w-md rounded-lg bg-surface p-6 shadow-xl">
        <h2 class="text-sm font-semibold">Submit New Support Ticket</h2>
        <div class="mt-4 space-y-3">
          <label class="block text-xs font-medium">
            Subject Title *
            <input
              v-model="newTicket.title"
              class="mt-1 h-9 w-full rounded-md border px-3 text-sm"
              placeholder="Brief summary of the issue"
            />
          </label>
          <div class="grid grid-cols-2 gap-3">
            <label class="block text-xs font-medium">
              Category
              <select v-model="newTicket.category" class="mt-1 h-9 w-full rounded-md border bg-surface px-3 text-sm">
                <option value="bug">Bug</option>
                <option value="feature">Feature Request</option>
                <option value="question">Question</option>
                <option value="general">General</option>
              </select>
            </label>
            <label class="block text-xs font-medium">
              Priority
              <select v-model="newTicket.priority" class="mt-1 h-9 w-full rounded-md border bg-surface px-3 text-sm">
                <option value="Low">Low</option>
                <option value="Normal">Normal</option>
                <option value="High">High</option>
                <option value="Critical">Critical</option>
              </select>
            </label>
          </div>
          <label class="block text-xs font-medium">
            Description
            <textarea
              v-model="newTicket.description"
              rows="4"
              class="mt-1 w-full rounded-md border p-3 text-xs"
              placeholder="Provide relevant details..."
            />
          </label>
        </div>

        <div class="mt-4 flex justify-end gap-2">
          <button class="rounded-md border px-3 py-2 text-xs" @click="createDialog = false">Cancel</button>
          <button
            class="rounded-md bg-primary px-3 py-2 text-xs text-white"
            @click="createTicket"
          >
            Create Ticket
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
