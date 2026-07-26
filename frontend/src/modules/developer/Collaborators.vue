<script setup lang="ts">
import { computed, ref } from "vue";
import { IconUsersGroup, IconPlus, IconShieldCheck, IconMail, IconTrash } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";

type Collaborator = {
  id: string;
  name: string;
  email: string;
  role: "developer" | "admin" | "staff";
  access: string[];
  status: "active" | "pending" | "disabled";
  invitedAt: string;
};

const collaborators = ref<Collaborator[]>([
  {
    id: "1",
    name: "System Developer",
    email: "admin@unifast.gov.ph",
    role: "developer",
    access: ["*"],
    status: "active",
    invitedAt: "Jul 1, 2026",
  },
  {
    id: "2",
    name: "Office Administrator",
    email: "head@unifast.gov.ph",
    role: "admin",
    access: ["users", "batches", "settings", "audit"],
    status: "active",
    invitedAt: "Jul 1, 2026",
  },
  {
    id: "3",
    name: "Dev Assistant",
    email: "dev2@unifast.gov.ph",
    role: "staff",
    access: ["documents", "grantees", "academic"],
    status: "pending",
    invitedAt: "Jul 12, 2026",
  },
]);

const roleColors: Record<string, string> = {
  developer: "bg-white/10 text-white",
  admin: "bg-blue-100 text-blue-700",
  staff: "bg-green-100 text-green-700",
};

const statusColors: Record<string, string> = {
  active: "bg-success-soft text-success",
  pending: "bg-warning-soft text-warning",
  disabled: "bg-surface-muted text-text-muted",
};

const inviteDialog = ref(false);
const newInvite = ref({ email: "", role: "staff" as "developer" | "admin" | "staff", access: [] as string[] });

const accessOptions = [
  { id: "users", label: "Users & Roles" },
  { id: "batches", label: "Batches" },
  { id: "documents", label: "Documents" },
  { id: "grantees", label: "Grantees" },
  { id: "academic", label: "Academic" },
  { id: "settings", label: "Settings" },
  { id: "audit", label: "Audit Log" },
  { id: "support", label: "Support Tickets" },
];

function invite() {
  if (!newInvite.value.email) return;
  collaborators.value.push({
    id: String(collaborators.value.length + 1),
    name: newInvite.value.email.split("@")[0],
    email: newInvite.value.email,
    role: newInvite.value.role,
    access: newInvite.value.role === "developer" ? ["*"] : newInvite.value.access,
    status: "pending",
    invitedAt: new Date().toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" }),
  });
  inviteDialog.value = false;
  newInvite.value = { email: "", role: "staff", access: [] };
}

function removeCollaborator(id: string) {
  collaborators.value = collaborators.value.filter((c) => c.id !== id);
}
</script>

<template>
  <div>
    <PageHeader
      title="Developer Collaborators"
      description="Manage team access and permissions."
    >
      <template #actions>
        <button
          class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-xs text-white"
          @click="inviteDialog = true"
        >
          <IconPlus :size="14" /> Invite
        </button>
      </template>
    </PageHeader>

    <div class="space-y-3">
      <div
        v-for="collab in collaborators"
        :key="collab.id"
        class="flex items-center gap-4 rounded-lg border bg-surface p-4"
      >
        <div class="grid size-10 place-items-center rounded-full bg-surface-muted text-sm font-semibold text-text-muted">
          {{ collab.name.charAt(0) }}
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2">
            <p class="text-sm font-medium truncate">{{ collab.name }}</p>
            <span :class="['rounded-full px-2 py-0.5 text-2xs font-semibold', roleColors[collab.role]]">
              {{ collab.role }}
            </span>
            <span :class="['rounded-full px-2 py-0.5 text-2xs font-semibold', statusColors[collab.status]]">
              {{ collab.status }}
            </span>
          </div>
          <p class="text-xs text-text-muted">{{ collab.email }}</p>
          <div class="mt-1 flex flex-wrap gap-1">
            <span
              v-for="a in collab.access"
              :key="a"
              class="rounded bg-surface-muted px-1.5 py-0.5 text-2xs text-text-muted"
            >
              {{ a === "*" ? "Full access" : a }}
            </span>
          </div>
        </div>
        <div class="text-right text-xs text-text-muted">
          <p>Invited {{ collab.invitedAt }}</p>
          <button
            class="mt-1 text-danger hover:underline"
            @click="removeCollaborator(collab.id)"
          >
            Remove
          </button>
        </div>
      </div>
    </div>

    <div
      v-if="inviteDialog"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
      @click.self="inviteDialog = false"
    >
      <div class="w-full max-w-md rounded-lg bg-surface p-6 shadow-xl">
        <h2 class="text-sm font-semibold">Invite Collaborator</h2>
        <p class="mt-1 text-xs text-text-muted">Send an invitation to join the developer team.</p>

        <div class="mt-4 space-y-3">
          <label class="block text-xs font-medium">
            Email
            <input
              v-model="newInvite.email"
              type="email"
              class="mt-1 h-9 w-full rounded-md border px-3 text-sm"
              placeholder="colleague@unifast.gov.ph"
            />
          </label>
          <label class="block text-xs font-medium">
            Role
            <select v-model="newInvite.role" class="mt-1 h-9 w-full rounded-md border bg-surface px-3 text-sm">
              <option value="staff">Staff</option>
              <option value="admin">Administrator</option>
              <option value="developer">Developer</option>
            </select>
          </label>
          <div v-if="newInvite.role !== 'developer'" class="space-y-2">
            <p class="text-xs font-medium">Access</p>
            <label
              v-for="opt in accessOptions"
              :key="opt.id"
              class="flex items-center gap-2 text-xs"
            >
              <input
                v-model="newInvite.access"
                type="checkbox"
                :value="opt.id"
                class="rounded border-border"
              />
              {{ opt.label }}
            </label>
          </div>
        </div>

        <div class="mt-4 flex justify-end gap-2">
          <button class="rounded-md border px-3 py-2 text-xs" @click="inviteDialog = false">Cancel</button>
          <button
            class="rounded-md bg-primary px-3 py-2 text-xs text-white"
            @click="invite"
          >
            Send Invite
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
