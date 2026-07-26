<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import {
  IconShieldCheck,
  IconUsers,
  IconEdit,
  IconTrash,
  IconPlus,
  IconLoader,
  IconCheck,
  IconBuildingBank,
  IconFileText,
  IconUserCheck,
  IconGridDots,
  IconChecklist,
  IconLock,
  IconSortAscending,
} from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import { apiFetch, isMockMode } from "@/api/client";
import { toast } from "@/composables/useToast";

type Role = {
  id: string;
  name: string;
  description: string;
  color: string;
  userCount: number;
  permissions: string[];
};

type DynamicMenuItem = {
  id: string;
  name: string;
  description: string;
};

type DynamicMenuGroup = {
  id: string;
  title: string;
  description: string;
  items: DynamicMenuItem[];
};

const developerDefaultItems = new Set([
  "dashboard",
  "developer.rbac",
  "developer.apidocs",
  "developer.flowchart",
  "developer.database",
  "developer.terms",
  "developer.faqs",
  "support",
  "developer.audit",
  "collaborators",
  "users",
  "settings",
]);

const menuGroups: DynamicMenuGroup[] = [
  {
    id: "system",
    title: "System & Developer Modules",
    description: "Developer console telemetry, schema inspectors, and system configuration",
    items: [
      { id: "dashboard", name: "Dashboard Overview", description: "Main system metrics & telemetry desk" },
      { id: "developer.rbac", name: "RBAC & Permissions", description: "Role & access control manager" },
      { id: "developer.apidocs", name: "API Documentation", description: "OpenAPI endpoint specification" },
      { id: "developer.flowchart", name: "System Flow Charts", description: "Architecture & process diagrams" },
      { id: "developer.database", name: "Database Inspector", description: "Live schema & query inspector" },
      { id: "developer.terms", name: "Terms & Conditions", description: "Policy & agreement template editor" },
      { id: "developer.faqs", name: "FAQs Knowledge Base", description: "Student & applicant help content" },
    ],
  },
  {
    id: "communication",
    title: "Communication & Reports",
    description: "Institutional broadcasts, billing submissions, and distribution tracking",
    items: [
      { id: "announcements", name: "Announcements Desk", description: "Broadcast institutional announcements" },
      { id: "reports", name: "Monitoring & Reports", description: "Compliance & operational metrics" },
      { id: "billing", name: "Billing / Call-for-Billing", description: "UniFAST billing submission manager" },
      { id: "distribution", name: "Distribution Report", description: "Disbursement & Landbank tracking" },
      { id: "support", name: "Support Tickets Desk", description: "Student & staff inquiry support desk" },
    ],
  },
  {
    id: "operations",
    title: "Operations & Validation Desk",
    description: "Grantee masterlists, document vault review, and academic validation",
    items: [
      { id: "onboarding", name: "Onboarding Center", description: "Student identity onboarding queue" },
      { id: "masterlist", name: "Grantee Masterlist", description: "Grantee masterlist import & status" },
      { id: "batches", name: "Batch Management", description: "Batch creation & verification tracking" },
      { id: "grantees", name: "Grantee Profiles & Records", description: "Grantee profile & eligibility records" },
      { id: "documents", name: "Document Vault Review", description: "Review & validate student files" },
      { id: "files", name: "File Storage Manager", description: "Document vault storage manager" },
      { id: "academic", name: "Academic Records History", description: "Inspect student TOR & grades" },
      { id: "eligibility", name: "Eligibility Verification", description: "Student eligibility checking engine" },
      { id: "developer.audit", name: "Developer Audit Log", description: "Security & audit event tracking" },
      { id: "collaborators", name: "Team & Collaborators", description: "Access rights & team invitations" },
    ],
  },
  {
    id: "administration",
    title: "Administration & Security",
    description: "User account management, security finding logs, and global settings",
    items: [
      { id: "users", name: "Users & Roles", description: "User accounts, roles & permissions" },
      { id: "security.findings", name: "Security Findings Log", description: "Threat detection & vulnerability logs" },
      { id: "security.memory", name: "Security Memory Vault", description: "AI security context & pattern vault" },
      { id: "settings", name: "Global System Settings", description: "Global institutional policy & settings" },
    ],
  },
  {
    id: "student_portal",
    title: "Student Portal Modules",
    description: "Student portal identity verification, document submissions, and profile",
    items: [
      { id: "student.dashboard", name: "Student Dashboard", description: "Grantee overview & status alerts" },
      { id: "student.verify", name: "Identity KYC Verification", description: "Liveness & ID card scanner" },
      { id: "student.profile", name: "Personal Profile", description: "Manage profile & personal info" },
      { id: "student.documents", name: "Required Documents Vault", description: "Upload required grant files" },
      { id: "student.upload", name: "Upload Documents Desk", description: "File upload desk" },
      { id: "student.notifications", name: "Student Notifications", description: "View status alerts & updates" },
    ],
  },
];

const initialRoles: Role[] = [
  {
    id: "developer",
    name: "Developer",
    description: "Full system access to developer tools, APIs, database, and system settings",
    color: "bg-white/10 text-white",
    userCount: 1,
    permissions: Array.from(developerDefaultItems),
  },
  {
    id: "admin",
    name: "Administrator",
    description: "Manages users, batches, and system settings",
    color: "bg-blue-100 text-blue-700",
    userCount: 1,
    permissions: [
      "dashboard",
      "announcements",
      "reports",
      "billing",
      "distribution",
      "support",
      "developer.audit",
      "security.findings",
      "security.memory",
      "users",
      "settings",
    ],
  },
  {
    id: "staff",
    name: "Staff",
    description: "Handles document validation and grantee management",
    color: "bg-green-100 text-green-700",
    userCount: 1,
    permissions: [
      "dashboard",
      "onboarding",
      "masterlist",
      "batches",
      "grantees",
      "documents",
      "files",
      "academic",
      "eligibility",
    ],
  },
  {
    id: "student",
    name: "Student",
    description: "Submits documents and views own records",
    color: "bg-orange-100 text-orange-700",
    userCount: 1,
    permissions: [
      "student.dashboard",
      "student.verify",
      "student.profile",
      "student.documents",
      "student.upload",
      "student.notifications",
    ],
  },
];

const roles = ref<Role[]>([]);
const selectedRole = ref<Role | null>(null);
const loading = ref(false);
const savingPermId = ref<string | null>(null);
const errorMessage = ref("");
const sortBy = ref<"default" | "name_asc" | "name_desc" | "users_desc" | "users_asc" | "perms_desc">("default");

function ensureArray(val: any): string[] {
  if (!val) return [];
  let arr: any[] = [];
  if (Array.isArray(val)) arr = val;
  else if (typeof val === "object") arr = Object.values(val);
  else arr = [val];

  return arr
    .map(String)
    .filter(
      (s) =>
        s.trim() !== "" &&
        !s.includes("Illuminate\\") &&
        !s.includes("Support\\Collection") &&
        !s.includes("[object")
    );
}

function normalizeRole(r: any): Role {
  return {
    id: String(r.id),
    name: r.name || "Role",
    description: r.description || "",
    color: r.color || "bg-surface-muted text-text-muted",
    userCount: r.userCount ?? r.users_count ?? 0,
    permissions: ensureArray(r.permissions),
  };
}

// KPI Metrics Computations
const totalUsersCount = computed(() => roles.value.reduce((acc, r) => acc + (r.userCount || 0), 0));
const superuserRolesCount = computed(() => roles.value.filter((r) => ensureArray(r.permissions).includes("*")).length);
const totalMenuItemsCount = computed(() => menuGroups.reduce((acc, g) => acc + g.items.length, 0));

// Role Sorting Computation
const sortedRoles = computed(() => {
  const list = [...roles.value];
  if (sortBy.value === "name_asc") {
    return list.sort((a, b) => a.name.localeCompare(b.name));
  }
  if (sortBy.value === "name_desc") {
    return list.sort((a, b) => b.name.localeCompare(a.name));
  }
  if (sortBy.value === "users_desc") {
    return list.sort((a, b) => b.userCount - a.userCount);
  }
  if (sortBy.value === "users_asc") {
    return list.sort((a, b) => a.userCount - b.userCount);
  }
  if (sortBy.value === "perms_desc") {
    return list.sort((a, b) => ensureArray(b.permissions).length - ensureArray(a.permissions).length);
  }
  return list;
});

async function loadRbac() {
  loading.value = true;
  errorMessage.value = "";
  try {
    const rolesRes = await apiFetch<{ data: Role[] }>("/api/rbac/roles");
    const rawRoles = rolesRes.data?.length ? rolesRes.data : (isMockMode ? initialRoles : []);
    roles.value = rawRoles.map(normalizeRole);
  } catch (err: any) {
    if (isMockMode) {
      roles.value = initialRoles.map(normalizeRole);
    } else {
      errorMessage.value = err?.message || "Failed to load RBAC settings from backend server.";
      toast.error(errorMessage.value);
      roles.value = [];
    }
  } finally {
    loading.value = false;
    if (roles.value.length > 0 && !selectedRole.value) {
      selectedRole.value = roles.value[0];
    }
  }
}

function isMenuItemActive(itemId: string): boolean {
  if (!selectedRole.value) return false;
  const perms = ensureArray(selectedRole.value.permissions);

  if (perms.includes("*")) {
    return developerDefaultItems.has(itemId);
  }

  return perms.includes(itemId);
}

function isGroupAllActive(group: DynamicMenuGroup): boolean {
  if (!selectedRole.value) return false;
  return group.items.every((item) => isMenuItemActive(item.id));
}

async function saveRolePermissions(newPermissions: string[]) {
  if (!selectedRole.value) return;
  selectedRole.value.permissions = newPermissions;

  try {
    await apiFetch(`/api/rbac/roles/${selectedRole.value.id}`, {
      method: "PUT",
      body: JSON.stringify({ permission_ids: newPermissions }),
    });
    toast.success("Role menu permissions updated");
  } catch (err: any) {
    if (isMockMode) {
      toast.success("Updated (Mock mode)");
    } else {
      toast.error(err?.message || "Failed to save menu permission update.");
    }
  }
}

async function toggleMenuItem(itemId: string) {
  if (!selectedRole.value) return;
  if (savingPermId.value) return;

  savingPermId.value = itemId;

  let current: string[];
  const perms = ensureArray(selectedRole.value.permissions);
  if (perms.includes("*")) {
    current = Array.from(developerDefaultItems);
  } else {
    current = [...perms];
  }

  const idx = current.indexOf(itemId);
  if (idx >= 0) {
    current.splice(idx, 1);
  } else {
    current.push(itemId);
  }

  try {
    await saveRolePermissions(current);
  } finally {
    savingPermId.value = null;
  }
}

async function toggleGroupAll(group: DynamicMenuGroup) {
  if (!selectedRole.value) return;
  if (savingPermId.value) return;

  savingPermId.value = group.id;

  let current: string[];
  const perms = ensureArray(selectedRole.value.permissions);
  if (perms.includes("*")) {
    current = Array.from(developerDefaultItems);
  } else {
    current = [...perms];
  }

  const allGroupItemIds = group.items.map((i) => i.id);
  const isAllActive = allGroupItemIds.every((id) => current.includes(id));

  let updatedPerms: string[];
  if (isAllActive) {
    updatedPerms = current.filter((id) => !allGroupItemIds.includes(id));
  } else {
    const toAdd = allGroupItemIds.filter((id) => !current.includes(id));
    updatedPerms = [...current, ...toAdd];
  }

  try {
    await saveRolePermissions(updatedPerms);
  } finally {
    savingPermId.value = null;
  }
}

onMounted(loadRbac);
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      title="Role-Based Access Control"
      description="Manage dynamic sidemenu access and module permissions for all system roles in real time."
    />

    <!-- KPI Metric Summary Grid -->
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <div class="rounded-lg border bg-surface p-4 shadow-sm">
        <div class="flex items-center justify-between">
          <span class="text-2xs font-semibold uppercase tracking-wider text-text-muted">Total System Roles</span>
          <div class="grid size-8 place-items-center rounded-md bg-primary/10 text-primary">
            <IconShieldCheck :size="18" />
          </div>
        </div>
        <div class="mt-2 flex items-baseline gap-2">
          <span class="text-2xl font-bold text-text">{{ roles.length }}</span>
          <span class="text-3xs text-text-muted">active roles</span>
        </div>
      </div>

      <div class="rounded-lg border bg-surface p-4 shadow-sm">
        <div class="flex items-center justify-between">
          <span class="text-2xs font-semibold uppercase tracking-wider text-text-muted">Assigned Users</span>
          <div class="grid size-8 place-items-center rounded-md bg-blue-500/10 text-blue-400">
            <IconUsers :size="18" />
          </div>
        </div>
        <div class="mt-2 flex items-baseline gap-2">
          <span class="text-2xl font-bold text-text">{{ totalUsersCount }}</span>
          <span class="text-3xs text-text-muted">accounts bound</span>
        </div>
      </div>

      <div class="rounded-lg border bg-surface p-4 shadow-sm">
        <div class="flex items-center justify-between">
          <span class="text-2xs font-semibold uppercase tracking-wider text-text-muted">Superuser Roles</span>
          <div class="grid size-8 place-items-center rounded-md bg-purple-500/10 text-purple-400">
            <IconLock :size="18" />
          </div>
        </div>
        <div class="mt-2 flex items-baseline gap-2">
          <span class="text-2xl font-bold text-text">{{ superuserRolesCount }}</span>
          <span class="text-3xs text-text-muted">full root access (*)</span>
        </div>
      </div>

      <div class="rounded-lg border bg-surface p-4 shadow-sm">
        <div class="flex items-center justify-between">
          <span class="text-2xs font-semibold uppercase tracking-wider text-text-muted">Module Items</span>
          <div class="grid size-8 place-items-center rounded-md bg-green-500/10 text-green-400">
            <IconGridDots :size="18" />
          </div>
        </div>
        <div class="mt-2 flex items-baseline gap-2">
          <span class="text-2xl font-bold text-text">{{ totalMenuItemsCount }}</span>
          <span class="text-3xs text-text-muted">menu items across {{ menuGroups.length }} groups</span>
        </div>
      </div>
    </div>

    <div v-if="errorMessage" class="rounded-lg border border-red-500/30 bg-red-950/40 p-4 text-xs text-red-200">
      {{ errorMessage }}
    </div>

    <div v-if="loading" class="flex items-center justify-center p-12">
      <IconLoader :size="24" class="animate-spin text-text-muted" />
    </div>

    <div v-else-if="roles.length === 0" class="flex items-center justify-center rounded-lg border border-dashed p-12 text-sm text-text-muted">
      No roles loaded from server. Check API backend connection.
    </div>

    <div v-else class="grid gap-6 lg:grid-cols-[1fr_2fr]">
      <!-- Sticky Left Side Roles Column -->
      <section class="space-y-4 lg:sticky lg:top-6 lg:self-start">
        <div class="flex items-center justify-between">
          <h2 class="text-sm font-semibold text-text">System Roles</h2>
          <!-- Role Sorting Control -->
          <div class="flex items-center gap-1.5">
            <IconSortAscending :size="14" class="text-text-muted" />
            <select
              v-model="sortBy"
              class="rounded-md border border-border bg-surface-muted/60 px-2 py-1 text-xs text-text focus:outline-hidden"
            >
              <option value="default">Default</option>
              <option value="name_asc">Name (A–Z)</option>
              <option value="name_desc">Name (Z–A)</option>
              <option value="users_desc">Users (High to Low)</option>
              <option value="users_asc">Users (Low to High)</option>
              <option value="perms_desc">Permissions (Most Access)</option>
            </select>
          </div>
        </div>

        <div
          v-for="role in sortedRoles"
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
              <span class="text-sm font-semibold text-text">{{ role.name }}</span>
            </div>
            <span class="rounded-full bg-surface-muted px-2 py-0.5 text-xs text-text-muted font-medium">
              {{ role.userCount }} user{{ role.userCount !== 1 ? "s" : "" }}
            </span>
          </div>
          <p class="mt-2 text-xs text-text-muted leading-relaxed">{{ role.description }}</p>
          <div class="mt-3 flex flex-wrap gap-1">
            <span
              v-for="perm in ensureArray(role.permissions).slice(0, 3)"
              :key="perm"
              class="rounded bg-surface-muted px-1.5 py-0.5 text-2xs text-text-muted font-mono"
            >
              {{ perm === "*" ? "All permissions" : perm }}
            </span>
            <span
              v-if="ensureArray(role.permissions).length > 3"
              class="rounded bg-surface-muted px-1.5 py-0.5 text-2xs text-text-muted font-mono"
            >
              +{{ ensureArray(role.permissions).length - 3 }} more
            </span>
          </div>
        </div>
      </section>

      <!-- Right Side Dynamic Sidemenu & Module Access Configurator -->
      <section v-if="selectedRole" class="space-y-5">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-sm font-bold text-text"><span class="capitalize">{{ selectedRole.name }}</span> Sidemenu Access Matrix</h2>
            <p class="text-2xs text-text-muted mt-0.5">Toggle individual sidemenu items or entire module groups for this role.</p>
          </div>
          <span :class="['rounded-full px-3 py-1 text-xs font-medium border border-border', selectedRole.color]">
            {{ selectedRole.name }}
          </span>
        </div>

        <!-- Dynamic Sidemenu Groups -->
        <div v-for="group in menuGroups" :key="group.id" class="rounded-lg border bg-surface p-4 shadow-sm space-y-3">
          <!-- Group Header -->
          <div class="flex items-center justify-between border-b border-border pb-3">
            <div>
              <h3 class="text-xs font-bold uppercase tracking-wider text-text">{{ group.title }}</h3>
              <p class="text-3xs text-text-muted mt-0.5">{{ group.description }}</p>
            </div>
            <button
              :disabled="savingPermId === group.id"
              :class="[
                'inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-3xs font-semibold transition-colors border border-border',
                isGroupAllActive(group)
                  ? 'bg-surface-muted text-text hover:bg-neutral-800'
                  : 'bg-primary text-white hover:bg-primary/90',
                savingPermId === group.id && 'opacity-60 cursor-not-allowed',
              ]"
              @click="toggleGroupAll(group)"
            >
              <IconLoader v-if="savingPermId === group.id" :size="11" class="animate-spin" />
              <IconChecklist v-else :size="12" />
              {{ isGroupAllActive(group) ? "Deselect Group" : "Select Group All" }}
            </button>
          </div>

          <!-- Group Menu Items Grid -->
          <div class="grid gap-2 sm:grid-cols-2">
            <div
              v-for="item in group.items"
              :key="item.id"
              :class="[
                'flex items-center gap-3 rounded-md border border-border p-2.5 transition-colors cursor-pointer select-none',
                savingPermId === item.id ? 'opacity-60 bg-surface-muted/40' : 'hover:bg-surface-muted/60 bg-surface-muted/30',
              ]"
              @click="toggleMenuItem(item.id)"
            >
              <!-- Custom Checkbox -->
              <div
                :class="[
                  'grid size-4.5 place-items-center rounded border transition-colors shrink-0',
                  isMenuItemActive(item.id)
                    ? 'border-white bg-white text-black font-bold shadow-xs'
                    : 'border-neutral-600 bg-surface-muted/60 text-transparent',
                  savingPermId === item.id && 'opacity-70 cursor-not-allowed',
                ]"
              >
                <IconLoader v-if="savingPermId === item.id" :size="12" class="animate-spin text-black" />
                <IconCheck v-else :size="12" stroke-width="3" />
              </div>

              <div class="min-w-0 flex-1">
                <div class="flex items-center justify-between gap-1">
                  <p class="text-xs font-medium text-text truncate">{{ item.name }}</p>
                  <span class="text-3xs font-mono text-text-muted shrink-0">{{ item.id }}</span>
                </div>
                <p class="text-3xs text-text-muted mt-0.5 truncate">{{ item.description }}</p>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section v-else class="flex items-center justify-center rounded-lg border border-dashed bg-surface p-12">
        <p class="text-sm text-text-muted">Select a role to view its dynamic sidemenu permissions</p>
      </section>
    </div>
  </div>
</template>
