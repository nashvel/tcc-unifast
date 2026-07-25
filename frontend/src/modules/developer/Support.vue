<script setup lang="ts">
import { computed, ref } from "vue";
import { IconLifebuoy, IconSearch, IconPlus, IconMessage } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";

type Ticket = {
  id: string;
  title: string;
  category: string;
  priority: "Low" | "Normal" | "High" | "Critical";
  status: "Open" | "In Progress" | "Waiting" | "Resolved";
  reporter: string;
  assignee: string;
  createdAt: string;
  replies: number;
};

const tickets = ref<Ticket[]>([
  { id: "TK-001", title: "Face verification failing on mobile", category: "bug", priority: "High", status: "Open", reporter: "Maria Santos", assignee: "System Developer", createdAt: "Jul 12, 2026", replies: 3 },
  { id: "TK-002", title: "Request: Bulk student import via CSV", category: "feature", priority: "Normal", status: "In Progress", reporter: "Office Administrator", assignee: "System Developer", createdAt: "Jul 11, 2026", replies: 5 },
  { id: "TK-003", title: "OCR not reading handwritten notes", category: "bug", priority: "Normal", status: "Waiting", reporter: "UniFAST Staff", assignee: "System Developer", createdAt: "Jul 10, 2026", replies: 2 },
  { id: "TK-004", title: "How to reset student password?", category: "question", priority: "Low", status: "Resolved", reporter: "Student User", assignee: "System Developer", createdAt: "Jul 9, 2026", replies: 4 },
  { id: "TK-005", title: "API rate limiting too strict", category: "bug", priority: "Critical", status: "Open", reporter: "System Developer", assignee: "Unassigned", createdAt: "Jul 12, 2026", replies: 1 },
]);

const search = ref("");
const statusFilter = ref("all");

const filteredTickets = computed(() =>
  tickets.value.filter(
    (t) =>
      (statusFilter.value === "all" || t.status === statusFilter.value) &&
      (!search.value || t.title.toLowerCase().includes(search.value.toLowerCase())),
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

const selectedTicket = ref<Ticket | null>(null);
</script>

<template>
  <div>
    <PageHeader
      title="Support Tickets"
      description="Track and resolve system issues."
    >
      <template #actions>
        <button class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-xs text-white">
          <IconPlus :size="14" /> New ticket
        </button>
      </template>
    </PageHeader>

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

    <div class="grid gap-4 lg:grid-cols-[1fr_350px]">
      <div class="space-y-2">
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
                <span class="text-xs font-mono text-text-muted">{{ ticket.id }}</span>
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
            <span>{{ ticket.createdAt }}</span>
            <span class="flex items-center gap-1"><IconMessage :size="10" /> {{ ticket.replies }}</span>
          </div>
        </div>
      </div>

      <div v-if="selectedTicket" class="rounded-lg border bg-surface p-4">
        <h2 class="text-sm font-semibold">{{ selectedTicket.title }}</h2>
        <div class="mt-2 flex flex-wrap gap-2">
          <span :class="['rounded-full px-2 py-0.5 text-2xs font-semibold', priorityColors[selectedTicket.priority]]">
            {{ selectedTicket.priority }}
          </span>
          <span :class="['rounded-full px-2 py-0.5 text-2xs font-semibold', statusColors[selectedTicket.status]]">
            {{ selectedTicket.status }}
          </span>
          <span class="rounded-full bg-surface-muted px-2 py-0.5 text-2xs text-text-muted">
            {{ selectedTicket.category }}
          </span>
        </div>
        <div class="mt-4 space-y-3 text-xs text-text-muted">
          <div class="flex justify-between"><span>Reporter</span><span class="font-medium text-text">{{ selectedTicket.reporter }}</span></div>
          <div class="flex justify-between"><span>Assignee</span><span class="font-medium text-text">{{ selectedTicket.assignee }}</span></div>
          <div class="flex justify-between"><span>Created</span><span class="font-medium text-text">{{ selectedTicket.createdAt }}</span></div>
          <div class="flex justify-between"><span>Replies</span><span class="font-medium text-text">{{ selectedTicket.replies }}</span></div>
        </div>
      </div>

      <div v-else class="flex items-center justify-center rounded-lg border border-dashed bg-surface p-12">
        <p class="text-sm text-text-muted">Select a ticket to view details</p>
      </div>
    </div>
  </div>
</template>
