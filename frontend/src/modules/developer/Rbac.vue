<script setup lang="ts">
import { ref } from "vue";
import { IconShieldCheck, IconUsers, IconEdit, IconTrash, IconPlus } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";

type Permission = {
  id: string;
  name: string;
  description: string;
  category: string;
};

type Role = {
  id: string;
  name: string;
  description: string;
  color: string;
  userCount: number;
  permissions: string[];
};

const roles = ref<Role[]>([
  {
    id: "developer",
    name: "Developer",
    description: "Full system access including API management and configuration",
    color: "bg-white/10 text-white",
    userCount: 1,
    permissions: ["*"],
  },
  {
    id: "admin",
    name: "Administrator",
    description: "Manages users, batches, and system settings",
    color: "bg-blue-100 text-blue-700",
    userCount: 1,
    permissions: ["users.read", "users.write", "batches.read", "batches.write", "settings.read", "settings.write", "audit.read"],
  },
  {
    id: "staff",
    name: "Staff",
    description: "Handles document validation and grantee management",
    color: "bg-green-100 text-green-700",
    userCount: 1,
    permissions: ["grantees.read", "documents.read", "documents.write", "academic.read", "batches.read"],
  },
  {
    id: "student",
    name: "Student",
    description: "Submits documents and views own records",
    color: "bg-orange-100 text-orange-700",
    userCount: 1,
    permissions: ["profile.read", "profile.write", "documents.submit", "kyc.submit"],
  },
]);

const permissions = ref<Permission[]>([
  { id: "users.read", name: "View Users", description: "View user list and profiles", category: "Users" },
  { id: "users.write", name: "Manage Users", description: "Create, edit, and deactivate users", category: "Users" },
  { id: "batches.read", name: "View Batches", description: "View batch list and details", category: "Batches" },
  { id: "batches.write", name: "Manage Batches", description: "Create and modify batches", category: "Batches" },
  { id: "documents.read", name: "View Documents", description: "View document submissions", category: "Documents" },
  { id: "documents.write", name: "Review Documents", description: "Approve or reject submissions", category: "Documents" },
  { id: "documents.submit", name: "Submit Documents", description: "Upload documents for review", category: "Documents" },
  { id: "grantees.read", name: "View Grantees", description: "View grantee list and profiles", category: "Grantees" },
  { id: "academic.read", name: "View Academic Records", description: "View academic history", category: "Academic" },
  { id: "settings.read", name: "View Settings", description: "View system settings", category: "Settings" },
  { id: "settings.write", name: "Manage Settings", description: "Modify system settings", category: "Settings" },
  { id: "audit.read", name: "View Audit Log", description: "View system activity log", category: "Audit" },
  { id: "profile.read", name: "View Profile", description: "View own profile", category: "Profile" },
  { id: "profile.write", name: "Edit Profile", description: "Update own profile", category: "Profile" },
  { id: "kyc.submit", name: "Submit KYC", description: "Submit KYC verification", category: "KYC" },
]);

const selectedRole = ref<Role | null>(null);
const editDialog = ref(false);

function getRolePermissions(role: Role): Permission[] {
  if (role.permissions.includes("*")) return permissions.value;
  return permissions.value.filter((p) => role.permissions.includes(p.id));
}

function getCategoryPermissions(category: string): Permission[] {
  return permissions.value.filter((p) => p.category === category);
}

const categories = [...new Set(permissions.value.map((p) => p.category))];
</script>

<template>
  <div>
    <PageHeader
      title="Role-Based Access Control"
      description="Manage roles and permissions for system access."
    />

    <div class="grid gap-6 lg:grid-cols-[1fr_2fr]">
      <section class="space-y-4">
        <h2 class="text-sm font-semibold">Roles</h2>
        <div
          v-for="role in roles"
          :key="role.id"
          :class="[
            'rounded-lg border bg-surface p-4 cursor-pointer transition hover:shadow-sm',
            selectedRole?.id === role.id && 'border-primary ring-1 ring-primary/20',
          ]"
          @click="selectedRole = role"
        >
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <IconShieldCheck :size="18" class="text-primary" />
              <span class="text-sm font-semibold">{{ role.name }}</span>
            </div>
            <span class="rounded-full bg-surface-muted px-2 py-0.5 text-xs text-text-muted">
              {{ role.userCount }} user{{ role.userCount !== 1 ? "s" : "" }}
            </span>
          </div>
          <p class="mt-2 text-xs text-text-muted">{{ role.description }}</p>
          <div class="mt-3 flex flex-wrap gap-1">
            <span
              v-for="perm in role.permissions.slice(0, 3)"
              :key="perm"
              class="rounded bg-surface-muted px-1.5 py-0.5 text-2xs text-text-muted"
            >
              {{ perm === "*" ? "All permissions" : perm }}
            </span>
            <span
              v-if="role.permissions.length > 3"
              class="rounded bg-surface-muted px-1.5 py-0.5 text-2xs text-text-muted"
            >
              +{{ role.permissions.length - 3 }} more
            </span>
          </div>
        </div>
      </section>

      <section v-if="selectedRole" class="space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-sm font-semibold">{{ selectedRole.name }} Permissions</h2>
          <span :class="['rounded-full px-2 py-1 text-xs font-semibold', selectedRole.color]">
            {{ selectedRole.name }}
          </span>
        </div>

        <div v-for="category in categories" :key="category" class="rounded-lg border bg-surface p-4">
          <h3 class="mb-3 text-xs font-semibold uppercase text-text-muted">{{ category }}</h3>
          <div class="space-y-2">
            <label
              v-for="perm in getCategoryPermissions(category)"
              :key="perm.id"
              class="flex items-center gap-3 rounded-md p-2 hover:bg-surface-muted"
            >
              <input
                type="checkbox"
                :checked="selectedRole.permissions.includes('*') || selectedRole.permissions.includes(perm.id)"
                class="rounded border-border"
                disabled
              />
              <div>
                <p class="text-xs font-medium">{{ perm.name }}</p>
                <p class="text-2xs text-text-muted">{{ perm.description }}</p>
              </div>
            </label>
          </div>
        </div>
      </section>

      <section v-else class="flex items-center justify-center rounded-lg border border-dashed bg-surface p-12">
        <p class="text-sm text-text-muted">Select a role to view its permissions</p>
      </section>
    </div>
  </div>
</template>
