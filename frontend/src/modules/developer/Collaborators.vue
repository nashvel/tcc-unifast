<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import {
  IconUsers,
  IconUserPlus,
  IconTrash,
  IconCheck,
  IconShieldCheck,
  IconCode,
  IconLoader,
  IconRefresh,
  IconAlertTriangle,
  IconRotateClockwise,
} from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import { apiFetch, isMockMode } from "@/api/client";
import { toast } from "@/composables/useToast";

type Collaborator = {
  id: string;
  name: string;
  email: string;
  role: string;
  access: string[];
  status: "active" | "pending" | "inactive";
  invitedAt: string;
};

type CollabSummary = {
  total_members: number;
  active_members: number;
  pending_invites: number;
  developers: number;
};

const initialCollaborators: Collaborator[] = [
  { id: "1", name: "System Developer", email: "admin@unifast.gov.ph", role: "developer", access: ["*"], status: "active", invitedAt: "Jul 1, 2026" },
  { id: "2", name: "Office Administrator", email: "head@unifast.gov.ph", role: "admin", access: ["users", "batches", "settings", "audit"], status: "active", invitedAt: "Jul 1, 2026" },
  { id: "3", name: "UniFAST Staff", email: "staff@unifast.gov.ph", role: "staff", access: ["users", "batches", "documents", "settings", "audit"], status: "active", invitedAt: "Jul 1, 2026" },
  { id: "4", name: "Dev Assistant", email: "dev2@unifast.gov.ph", role: "staff", access: ["documents", "grantees", "academic"], status: "pending", invitedAt: "Jul 12, 2026" },
];

const collaborators = ref<Collaborator[]>([]);
const summary = ref<CollabSummary | null>(null);
const loading = ref(false);
const inviteDialog = ref(false);
const confirmDeleteDialog = ref(false);
const selectedCollab = ref<Collaborator | null>(null);
const errorMessage = ref("");
const statusFilter = ref<"all" | "active" | "pending" | "inactive">("all");
const newInvite = ref({ email: "", role: "staff", access: [] as string[] });

async function loadCollaborators() {
  loading.value = true;
  errorMessage.value = "";
  try {
    const res = await apiFetch<{ data: Collaborator[]; summary?: CollabSummary }>("/api/collaborators");
    if (res.data && res.data.length > 0) {
      collaborators.value = res.data;
      if (res.summary) {
        summary.value = res.summary;
      } else {
        calculateSummary(res.data);
      }
    } else {
      collaborators.value = isMockMode ? initialCollaborators : [];
      calculateSummary(collaborators.value);
    }
  } catch (err: any) {
    if (isMockMode) {
      collaborators.value = initialCollaborators;
      calculateSummary(initialCollaborators);
    } else {
      errorMessage.value = err?.message || "Failed to load team collaborators from server.";
      toast.error(errorMessage.value);
      collaborators.value = [];
      summary.value = null;
    }
  } finally {
    loading.value = false;
  }
}

function calculateSummary(list: Collaborator[]) {
  summary.value = {
    total_members: list.length,
    active_members: list.filter((c) => c.status === "active").length,
    pending_invites: list.filter((c) => c.status === "pending").length,
    developers: list.filter((c) => c.role === "developer").length,
  };
}

// Filter out current logged-in developer's name & apply selected status filter
const displayedCollaborators = computed(() => {
  return collaborators.value.filter((c) => {
    if (c.name === "System Developer" || c.email === "admin@unifast.gov.ph") return false;
    if (statusFilter.value === "all") return true;
    return c.status === statusFilter.value;
  });
});

const inactiveCount = computed(() => {
  return collaborators.value.filter((c) => c.status === "inactive" && c.email !== "admin@unifast.gov.ph").length;
});

function promptDeactivate(collab: Collaborator) {
  selectedCollab.value = collab;
  confirmDeleteDialog.value = true;
}

async function confirmDeactivate() {
  if (!selectedCollab.value) return;
  const collab = selectedCollab.value;

  try {
    await apiFetch(`/api/collaborators/${collab.id}`, { method: "DELETE" });
    const target = collaborators.value.find((c) => c.id === collab.id);
    if (target) {
      target.status = "inactive";
    }
    calculateSummary(collaborators.value);
    toast.success(`Collaborator ${collab.email} set to inactive (soft deleted).`);
  } catch (err: any) {
    if (isMockMode) {
      const target = collaborators.value.find((c) => c.id === collab.id);
      if (target) {
        target.status = "inactive";
      }
      calculateSummary(collaborators.value);
      toast.success(`Collaborator ${collab.email} set to inactive (Mock mode).`);
    } else {
      toast.error(err?.message || "Failed to deactivate collaborator on server.");
    }
  } finally {
    confirmDeleteDialog.value = false;
    selectedCollab.value = null;
  }
}

async function reactivateCollab(collab: Collaborator) {
  try {
    const target = collaborators.value.find((c) => c.id === collab.id);
    if (target) {
      target.status = "active";
    }
    calculateSummary(collaborators.value);
    toast.success(`Collaborator ${collab.email} reactivated.`);
  } catch (err: any) {
    toast.error(err?.message || "Failed to reactivate collaborator.");
  }
}

async function sendInvite() {
  if (!newInvite.value.email.trim()) {
    toast.error("Email is required.");
    return;
  }

  try {
    const res = await apiFetch<{ data: Collaborator }>("/api/collaborators/invite", {
      method: "POST",
      body: JSON.stringify(newInvite.value),
    });
    collaborators.value.push(res.data);
    calculateSummary(collaborators.value);
    toast.success(`Invitation sent to ${newInvite.value.email}`);
    inviteDialog.value = false;
    newInvite.value = { email: "", role: "staff", access: [] };
  } catch (err: any) {
    if (isMockMode) {
      collaborators.value.push({
        id: String(Date.now()),
        name: newInvite.value.email.split("@")[0],
        email: newInvite.value.email,
        role: newInvite.value.role,
        access: newInvite.value.access,
        status: "pending",
        invitedAt: "Just now",
      });
      calculateSummary(collaborators.value);
      toast.success(`Invitation sent (Mock mode)`);
      inviteDialog.value = false;
      newInvite.value = { email: "", role: "staff", access: [] };
    } else {
      toast.error(err?.message || "Failed to send invitation to server.");
    }
  }
}

onMounted(loadCollaborators);
</script>

<template>
  <div class="space-y-5">
    <PageHeader
      title="Team & Collaborators"
      description="Manage developer access, team members, and feature level access control."
    >
      <template #actions>
        <button
          class="inline-flex h-9 items-center gap-1.5 rounded-md bg-white text-black font-medium px-3 text-xs hover:bg-neutral-200 transition-colors shadow-sm"
          @click="inviteDialog = true"
        >
          <IconUserPlus :size="14" /> Invite Collaborator
        </button>
      </template>
    </PageHeader>

    <div v-if="errorMessage" class="rounded-lg border border-red-500/30 bg-red-950/40 p-4 text-xs text-red-200">
      {{ errorMessage }}
    </div>

    <!-- Team & Collaborators KPI Summary Cards -->
    <section v-if="summary" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <div class="rounded-lg border bg-surface p-4 shadow-sm">
        <div class="flex items-center justify-between">
          <span class="text-xs font-medium text-text-muted">Total Team Members</span>
          <span class="grid size-7 place-items-center rounded-md bg-surface-muted text-text-muted">
            <IconUsers :size="15" />
          </span>
        </div>
        <p class="mt-2 text-2xl font-bold font-mono tracking-tight text-text">{{ summary.total_members }}</p>
        <p class="mt-1 text-2xs text-text-muted">Registered Team Accounts</p>
      </div>

      <div class="rounded-lg border bg-surface p-4 shadow-sm">
        <div class="flex items-center justify-between">
          <span class="text-xs font-medium text-text-muted">Active Accounts</span>
          <span class="grid size-7 place-items-center rounded-md bg-surface-muted text-text-muted">
            <IconShieldCheck :size="15" />
          </span>
        </div>
        <p class="mt-2 text-2xl font-bold font-mono tracking-tight text-text">{{ summary.active_members }}</p>
        <p class="mt-1 text-2xs text-text-muted">Granted System Access</p>
      </div>

      <div class="rounded-lg border bg-surface p-4 shadow-sm">
        <div class="flex items-center justify-between">
          <span class="text-xs font-medium text-text-muted">Pending Invites</span>
          <span class="grid size-7 place-items-center rounded-md bg-surface-muted text-text-muted">
            <IconUserPlus :size="15" />
          </span>
        </div>
        <p class="mt-2 text-2xl font-bold font-mono tracking-tight text-text">{{ summary.pending_invites }}</p>
        <p class="mt-1 text-2xs text-text-muted">Awaiting Invitation Accept</p>
      </div>

      <div class="rounded-lg border bg-surface p-4 shadow-sm cursor-pointer hover:border-border-strong transition-colors" @click="statusFilter = 'inactive'">
        <div class="flex items-center justify-between">
          <span class="text-xs font-medium text-text-muted">Inactive Accounts</span>
          <span class="grid size-7 place-items-center rounded-md bg-danger-soft text-danger">
            <IconTrash :size="15" />
          </span>
        </div>
        <p class="mt-2 text-2xl font-bold font-mono tracking-tight text-danger">{{ inactiveCount }}</p>
        <p class="mt-1 text-2xs text-text-muted">Soft Deleted / Deactivated</p>
      </div>
    </section>

    <!-- Status Filter Bar -->
    <div class="flex items-center gap-1 border-b border-border pb-2">
      <button
        :class="[
          'px-3 py-1.5 text-xs font-medium rounded-md transition-colors',
          statusFilter === 'all' ? 'bg-surface-muted text-text font-bold' : 'text-text-muted hover:text-text hover:bg-surface-muted/50',
        ]"
        @click="statusFilter = 'all'"
      >
        All Accounts
      </button>
      <button
        :class="[
          'px-3 py-1.5 text-xs font-medium rounded-md transition-colors',
          statusFilter === 'active' ? 'bg-success-soft text-success font-bold' : 'text-text-muted hover:text-text hover:bg-surface-muted/50',
        ]"
        @click="statusFilter = 'active'"
      >
        Active
      </button>
      <button
        :class="[
          'px-3 py-1.5 text-xs font-medium rounded-md transition-colors',
          statusFilter === 'pending' ? 'bg-warning-soft text-warning font-bold' : 'text-text-muted hover:text-text hover:bg-surface-muted/50',
        ]"
        @click="statusFilter = 'pending'"
      >
        Pending
      </button>
      <button
        :class="[
          'px-3 py-1.5 text-xs font-medium rounded-md transition-colors flex items-center gap-1.5',
          statusFilter === 'inactive' ? 'bg-danger-soft text-danger font-bold' : 'text-text-muted hover:text-text hover:bg-surface-muted/50',
        ]"
        @click="statusFilter = 'inactive'"
      >
        Inactive (Soft Deleted)
        <span v-if="inactiveCount > 0" class="rounded-full bg-danger px-1.5 py-0.2 text-3xs text-white">{{ inactiveCount }}</span>
      </button>
    </div>

    <div class="overflow-x-auto rounded-lg border bg-surface">
      <table class="w-full text-xs">
        <thead>
          <tr class="border-b bg-surface-muted text-left text-text-muted">
            <th class="px-4 py-3 font-medium">Collaborator</th>
            <th class="px-4 py-3 font-medium">Role</th>
            <th class="px-4 py-3 font-medium">Access Rights</th>
            <th class="px-4 py-3 font-medium">Status</th>
            <th class="px-4 py-3 font-medium">Invited Date</th>
            <th class="px-4 py-3 text-right font-medium">Action</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="6" class="p-8 text-center text-text-muted">
              <IconLoader :size="18" class="animate-spin inline mr-2" /> Loading team list...
            </td>
          </tr>
          <tr v-else-if="displayedCollaborators.length === 0">
            <td colspan="6" class="p-8 text-center text-text-muted">
              {{ statusFilter === 'inactive' ? "No inactive (soft deleted) accounts found." : errorMessage ? "Unable to connect to backend collaborator API." : "No team collaborators found for this filter." }}
            </td>
          </tr>
          <tr v-for="c in displayedCollaborators" :key="c.id" class="border-b last:border-0 hover:bg-surface-muted/50">
            <td class="px-4 py-3">
              <p class="font-medium text-text">{{ c.name }}</p>
              <p class="text-2xs text-text-muted">{{ c.email }}</p>
            </td>
            <td class="px-4 py-3 font-capitalize text-text">{{ c.role }}</td>
            <td class="px-4 py-3">
              <div class="flex flex-wrap gap-1">
                <span v-for="a in c.access" :key="a" class="rounded bg-surface-muted px-1.5 py-0.5 text-2xs text-text-muted font-mono">
                  {{ a === "*" ? "Full Access" : a }}
                </span>
              </div>
            </td>
            <td class="px-4 py-3">
              <span
                :class="[
                  'rounded-full px-2 py-0.5 text-2xs font-semibold',
                  c.status === 'active' ? 'bg-success-soft text-success' : c.status === 'pending' ? 'bg-warning-soft text-warning' : 'bg-danger-soft text-danger',
                ]"
              >
                {{ c.status === 'inactive' ? 'Inactive (Soft Deleted)' : c.status }}
              </span>
            </td>
            <td class="px-4 py-3 text-text-muted">{{ c.invitedAt }}</td>
            <td class="px-4 py-3 text-right">
              <button
                v-if="c.status !== 'inactive'"
                class="text-text-muted hover:text-danger p-1"
                title="Deactivate collaborator (soft delete)"
                @click="promptDeactivate(c)"
              >
                <IconTrash :size="14" />
              </button>
              <button
                v-else
                class="inline-flex items-center gap-1 rounded bg-surface-muted px-2 py-1 text-2xs text-text hover:bg-success-soft hover:text-success transition-colors"
                title="Reactivate account"
                @click="reactivateCollab(c)"
              >
                <IconRotateClockwise :size="12" /> Reactivate
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Confirmation Deactivate Modal -->
    <div
      v-if="confirmDeleteDialog && selectedCollab"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
      @click.self="confirmDeleteDialog = false"
    >
      <div class="w-full max-w-sm rounded-lg bg-surface p-5 shadow-2xl border border-border">
        <div class="flex items-center gap-3">
          <div class="grid size-9 place-items-center rounded-full bg-danger-soft text-danger shrink-0">
            <IconAlertTriangle :size="18" />
          </div>
          <div>
            <h3 class="text-sm font-bold text-text">Deactivate Collaborator?</h3>
            <p class="text-2xs text-text-muted mt-0.5">
              Confirm deactivating <span class="font-semibold text-text">{{ selectedCollab.email }}</span>. This will soft-delete their account access.
            </p>
          </div>
        </div>

        <div class="mt-5 flex justify-end gap-2">
          <button
            class="rounded-md border border-border px-3 py-1.5 text-xs text-text hover:bg-surface-muted transition-colors"
            @click="confirmDeleteDialog = false"
          >
            Cancel
          </button>
          <button
            class="rounded-md bg-danger px-3 py-1.5 text-xs font-medium text-white hover:bg-danger/90 transition-colors"
            @click="confirmDeactivate"
          >
            Confirm Deactivate
          </button>
        </div>
      </div>
    </div>

    <!-- Invite Modal -->
    <div
      v-if="inviteDialog"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
      @click.self="inviteDialog = false"
    >
      <div class="w-full max-w-md rounded-lg bg-surface p-6 shadow-xl border border-border">
        <h2 class="text-sm font-semibold text-text">Invite Team Member</h2>
        <div class="mt-4 space-y-3">
          <label class="block text-xs font-medium text-text">
            Email Address *
            <input
              v-model="newInvite.email"
              type="email"
              class="mt-1 h-9 w-full rounded-md border border-border bg-surface px-3 text-xs text-text"
              placeholder="colleague@unifast.gov.ph"
            />
          </label>
          <label class="block text-xs font-medium text-text">
            System Role
            <select v-model="newInvite.role" class="mt-1 h-9 w-full rounded-md border border-border bg-surface px-3 text-xs text-text">
              <option value="developer">Developer</option>
              <option value="admin">Administrator</option>
              <option value="staff">Staff</option>
            </select>
          </label>
        </div>

        <div class="mt-6 flex justify-end gap-2">
          <button class="rounded-md border border-border px-3 py-2 text-xs text-text hover:bg-surface-muted" @click="inviteDialog = false">Cancel</button>
          <button class="rounded-md bg-white text-black font-medium px-3 py-2 text-xs hover:bg-neutral-200" @click="sendInvite">
            Send Invite
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
