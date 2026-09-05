<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useRoute } from "vue-router";
import { useI18n } from "vue-i18n";
import {
  IconArrowLeft,
  IconCheck,
  IconDownload,
  IconKey,
  IconLoader2,
  IconLock,
  IconPencil,
  IconPlus,
  IconRefresh,
  IconSearch,
  IconTrash,
  IconX,
} from "@tabler/icons-vue";
import AppDialog from "@/components/dialogs/AppDialog.vue";
import AppTour from "@/components/tour/AppTour.vue";
import { translateKnownText } from "@/i18n/knownText";
import { withLang } from "@/i18n/routeLang";
import { apiFetch, isMockMode } from "@/api/client";
import { ApiError } from "@/api/client";
import { useToast } from "@/composables/useToast";
import {
  useRbacRoles,
  useRbacPermissions,
  useCreateRole,
  useUpdateRole,
  useDeleteRole,
} from "@/composables/useRbac";
import type { RbacRole } from "@/api/rbac";

const route = useRoute();
const { t } = useI18n();
const toast = useToast();

// ── View mode ─────────────────────────────────────────────────────────────────
const isMatrix = computed(
  () =>
    route.params.section === "permissions" ||
    route.path.endsWith("/users/permissions"),
);

// ─── User list (collaborators) ────────────────────────────────────────────────
const search = ref("");
const roleFilter = ref("All roles");
const statusFilter = ref("All statuses");
const userDialog = ref(false);
const accountDialog = ref(false);
const accountAction = ref("");
const accountName = ref("");

function confirmAccount(action: string, name: string) {
  accountAction.value = action;
  accountName.value = name;
  accountDialog.value = true;
}

const initialUsers: any[][] = [
  ["sysadmin", "System Developer", "admin@unifast.gov.ph", "Developer", true, true, "Jul 11, 2026, 7:41 PM"],
  ["office.head", "Office Administrator", "head@unifast.gov.ph", "Admin", true, true, "Jul 11, 2026, 5:12 PM"],
  ["unifast.staff", "UniFAST Staff", "staff@unifast.gov.ph", "Staff", false, true, "Jul 11, 2026, 4:48 PM"],
  ["reviewer.01", "Document Reviewer", "reviewer@unifast.gov.ph", "Staff", false, false, "Jul 8, 2026, 9:16 AM"],
];
const users = ref<any[][]>(isMockMode ? initialUsers : []);

async function loadUsers() {
  try {
    const res = await apiFetch<{ data: any[] }>("/api/collaborators");
    if (res.data && res.data.length > 0) {
      users.value = res.data.map((collab) => [
        collab.email.split("@")[0],
        collab.name,
        collab.email,
        collab.role.charAt(0).toUpperCase() + collab.role.slice(1),
        true,
        collab.status === "active" || collab.status === "pending",
        collab.invitedAt || "Recent",
      ]);
    } else if (isMockMode) {
      users.value = initialUsers;
    } else {
      users.value = [];
    }
  } catch {
    if (isMockMode) users.value = initialUsers;
    else users.value = [];
  }
}
if (typeof window !== "undefined") loadUsers();

const filtered = computed(() =>
  users.value.filter((user) => {
    const matchesSearch =
      !search.value ||
      `${user[0]} ${user[1]} ${user[2]}`.toLowerCase().includes(search.value.toLowerCase());
    const matchesRole = roleFilter.value === "All roles" || user[3] === roleFilter.value;
    const matchesStatus =
      statusFilter.value === "All statuses" ||
      (statusFilter.value === "Active" ? user[5] : !user[5]);
    return matchesSearch && matchesRole && matchesStatus;
  }),
);

// ─── Live RBAC data ───────────────────────────────────────────────────────────
const { roles, query: rolesQuery } = useRbacRoles();
const { grouped: permGroups, categories, query: permsQuery } = useRbacPermissions();

const isLoading = computed(
  () => rolesQuery.isFetching.value || permsQuery.isFetching.value,
);
const loadError = computed(
  () => rolesQuery.error.value || permsQuery.error.value,
);

/** Non-superuser roles (developer role has wildcard '*' permissions). */
const editableRoles = computed<RbacRole[]>(() =>
  roles.value.filter((r) => !(r.permissions as string[]).includes("*")),
);

// ─── Permission-toggle mutation ───────────────────────────────────────────────
const updateRole = useUpdateRole();
const pendingToggle = ref<string | null>(null); // "roleId:permName"

async function togglePermission(role: RbacRole, permName: string) {
  // Superuser and system roles are read-only — backend will 403 anyway,
  // but we guard in the UI too to avoid a wasted round-trip.
  if ((role.permissions as string[]).includes("*") || role.is_system) return;

  const key = `${role.id}:${permName}`;
  pendingToggle.value = key;

  const currentPerms = (role.permissions as string[]) ?? [];
  const hasIt = currentPerms.includes(permName);
  const newPerms = hasIt
    ? currentPerms.filter((p) => p !== permName)
    : [...currentPerms, permName];

  try {
    await updateRole.mutateAsync({ id: role.id, form: { permission_ids: newPerms } });
    toast.success(
      hasIt
        ? `Removed "${permName}" from ${role.name}`
        : `Added "${permName}" to ${role.name}`,
    );
  } catch (err) {
    const msg = err instanceof ApiError ? err.message : "Failed to update permission.";
    toast.error(msg);
  } finally {
    pendingToggle.value = null;
  }
}

function roleHasPerm(role: RbacRole, permName: string): boolean {
  const perms = role.permissions as string[];
  return perms.includes("*") || perms.includes(permName);
}

// ─── Create role dialog ───────────────────────────────────────────────────────
const createRoleDialog = ref(false);
const createForm = ref({ name: "", description: "", color: "#6366f1" });
const createRoleMutation = useCreateRole();

async function submitCreateRole() {
  if (!createForm.value.name.trim()) return;
  try {
    await createRoleMutation.mutateAsync({
      name: createForm.value.name.trim(),
      description: createForm.value.description.trim() || undefined,
      color: createForm.value.color || undefined,
    });
    toast.success(`Role "${createForm.value.name}" created.`);
    createForm.value = { name: "", description: "", color: "#6366f1" };
    createRoleDialog.value = false;
  } catch (err) {
    const msg = err instanceof ApiError ? err.message : "Failed to create role.";
    toast.error(msg);
  }
}

// ─── Edit role dialog ─────────────────────────────────────────────────────────
const editRoleDialog = ref(false);
const editingRole = ref<RbacRole | null>(null);
const editForm = ref({ name: "", description: "", color: "" });
const editRoleMutation = useUpdateRole();

function openEditRole(role: RbacRole) {
  editingRole.value = role;
  editForm.value = {
    name: role.name,
    description: role.description ?? "",
    color: role.color ?? "#6366f1",
  };
  editRoleDialog.value = true;
}

async function submitEditRole() {
  if (!editingRole.value || !editForm.value.name.trim()) return;
  try {
    await editRoleMutation.mutateAsync({
      id: editingRole.value.id,
      form: {
        name: editForm.value.name.trim(),
        description: editForm.value.description.trim() || undefined,
        color: editForm.value.color || undefined,
      },
    });
    toast.success(`Role "${editForm.value.name}" updated.`);
    editRoleDialog.value = false;
  } catch (err) {
    const msg = err instanceof ApiError ? err.message : "Failed to update role.";
    toast.error(msg);
  }
}

// ─── Delete role dialog ───────────────────────────────────────────────────────
const deleteRoleDialog = ref(false);
const deletingRole = ref<RbacRole | null>(null);
const deleteRoleMutation = useDeleteRole();

function openDeleteRole(role: RbacRole) {
  deletingRole.value = role;
  deleteRoleDialog.value = true;
}

async function submitDeleteRole() {
  if (!deletingRole.value) return;
  try {
    await deleteRoleMutation.mutateAsync(deletingRole.value.id);
    toast.success(`Role "${deletingRole.value.name}" deleted.`);
    deleteRoleDialog.value = false;
  } catch (err) {
    const msg = err instanceof ApiError ? err.message : "Failed to delete role.";
    toast.error(msg);
  }
}

// Close dialogs on navigation away from matrix
watch(isMatrix, (v) => {
  if (!v) {
    createRoleDialog.value = false;
    editRoleDialog.value = false;
    deleteRoleDialog.value = false;
  }
});
</script>

<template>
  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <!-- USER LIST VIEW                                                          -->
  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <div v-if="!isMatrix">
    <header class="mb-4 flex flex-wrap items-start justify-between gap-3" data-tour="page-header">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">{{ t("users.title") }}</h1>
        <p class="mt-1 text-sm text-text-muted">{{ t("users.description") }}</p>
      </div>
      <div class="flex gap-2">
        <AppTour />
        <button class="inline-flex h-9 items-center gap-1.5 rounded-md border bg-surface px-3 text-xs">
          <IconDownload :size="14" />{{ t("common.export") }}
        </button>
        <RouterLink
          :to="withLang(route.path.startsWith('/app/developer') ? '/app/developer/users/permissions' : '/app/users/permissions', route.query.lang)"
          class="inline-flex h-9 items-center rounded-md border bg-surface px-3 text-xs"
        >{{ t("users.permissionMatrix") }}</RouterLink>
        <button
          class="inline-flex h-9 items-center gap-1.5 rounded-md bg-white text-black font-medium px-3 text-xs hover:bg-neutral-200 transition-colors shadow-sm"
          @click="userDialog = true"
        >
          <IconPlus :size="14" />{{ t("users.newUser") }}
        </button>
      </div>
    </header>

    <section class="mb-4 grid grid-cols-1 gap-2 rounded-lg border bg-surface p-3 md:grid-cols-4">
      <div class="relative md:col-span-2">
        <IconSearch :size="14" class="absolute left-3 top-1/2 -translate-y-1/2 text-text-soft" />
        <input
          v-model="search"
          :placeholder="t('users.searchPlaceholder')"
          class="h-9 w-full rounded-md border pl-9 pr-3 text-xs"
        />
      </div>
      <select v-model="roleFilter" class="h-9 rounded-md border bg-surface px-3 text-xs">
        <option value="All roles">{{ t("users.allRoles") }}</option>
        <option value="Developer">{{ t("roles.developer") }}</option>
        <option value="Admin">{{ t("roles.admin") }}</option>
        <option value="Staff">{{ t("roles.staff") }}</option>
      </select>
      <select v-model="statusFilter" class="h-9 rounded-md border bg-surface px-3 text-xs">
        <option value="All statuses">{{ t("users.allStatuses") }}</option>
        <option value="Active">{{ t("status.active") }}</option>
        <option value="Disabled">{{ t("status.disabled") }}</option>
      </select>
    </section>

    <div class="overflow-x-auto rounded-lg border bg-surface" data-tour="page-content">
      <table class="w-full text-left text-xs">
        <thead class="bg-surface-muted text-2xs uppercase text-text-muted">
          <tr>
            <th v-for="heading in [t('users.username'), t('users.fullName'), t('common.email'), t('users.role'), t('users.mfa'), t('users.status'), t('users.lastLogin'), '']"
              :key="heading"
              :class="['px-3 py-2.5', heading === '' ? 'w-40 text-right' : '']"
            >{{ heading }}</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <tr v-for="user in filtered" :key="user[0] as string">
            <td class="px-3 py-3 font-mono">{{ user[0] }}</td>
            <td class="px-3 py-3 font-medium">{{ user[1] }}</td>
            <td class="px-3 py-3 text-text-muted">{{ user[2] }}</td>
            <td class="px-3 py-3">
              <span class="rounded-full bg-primary-soft px-2 py-0.5 text-primary">{{ translateKnownText(t, String(user[3])) }}</span>
            </td>
            <td class="px-3 py-3">
              <span :class="['rounded-full px-2 py-0.5', user[4] ? 'bg-success-soft text-success' : 'bg-warning-soft text-warning']">
                {{ user[4] ? t("status.enabled") : t("status.off") }}
              </span>
            </td>
            <td class="px-3 py-3">
              <span :class="['rounded-full px-2 py-0.5', user[5] ? 'bg-success-soft text-success' : 'bg-danger-soft text-danger']">
                {{ user[5] ? t("status.active") : t("status.disabled") }}
              </span>
            </td>
            <td class="whitespace-nowrap px-3 py-3 text-text-muted">{{ user[6] }}</td>
            <td class="w-40 whitespace-nowrap px-3 py-3">
              <div class="ml-auto grid w-36 grid-cols-[4rem_5rem] items-center justify-end text-left">
                <button class="inline-flex items-center gap-1 text-primary" @click="confirmAccount('Reset password', String(user[1]))">
                  <IconKey :size="12" />{{ t("users.reset") }}
                </button>
                <button class="text-left text-text-muted" @click="confirmAccount(user[5] ? 'Deactivate' : 'Activate', String(user[1]))">
                  {{ user[5] ? t("users.deactivate") : t("users.activate") }}
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      <footer class="flex justify-between border-t px-3 py-2.5 text-xs text-text-muted">
        <span>{{ t("users.showingStaffUsers", { count: filtered.length }) }}</span>
        <span>{{ t("pagination.pageOf", { page: 1, pages: 1 }) }}</span>
      </footer>
    </div>

    <!-- Create user dialog -->
    <AppDialog v-model="userDialog" :title="t('users.createStaffUser')" :description="t('users.createStaffUserDescription')" size="lg">
      <div class="grid gap-4 sm:grid-cols-2">
        <label class="text-xs font-medium">{{ t("users.fullName") }}<input class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm" :placeholder="t('users.fullName')" /></label>
        <label class="text-xs font-medium">{{ t("users.username") }}<input class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm" :placeholder="t('users.usernamePlaceholder')" /></label>
        <label class="text-xs font-medium">{{ t("common.email") }}<input type="email" class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm" placeholder="name@unifast.gov.ph" /></label>
        <label class="text-xs font-medium">{{ t("users.role") }}<select class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm">
          <option>{{ t("roles.staff") }}</option>
          <option>{{ t("roles.admin") }}</option>
          <option>{{ t("roles.developer") }}</option>
        </select></label>
        <label class="flex items-center gap-2 text-xs sm:col-span-2"><input type="checkbox" checked />{{ t("users.requirePasswordMfa") }}</label>
      </div>
      <template #footer="{ close }">
        <button class="rounded-md border px-4 py-2 text-xs" @click="close">{{ t("common.cancel") }}</button>
        <button class="rounded-md bg-primary px-4 py-2 text-xs text-white" @click="close">{{ t("users.createUser") }}</button>
      </template>
    </AppDialog>

    <!-- Account action dialog -->
    <AppDialog v-model="accountDialog" :title="t('users.accountActionTitle', { action: translateKnownText(t, accountAction) })" :description="t('users.accountActionDescription', { action: translateKnownText(t, accountAction), name: accountName })" size="sm">
      <label v-if="accountAction === 'Reset password'" class="text-xs font-medium">{{ t("users.resetMethod") }}<select class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm">
        <option>{{ t("users.sendPasswordResetEmail") }}</option>
        <option>{{ t("users.generateTemporaryPassword") }}</option>
        <option>{{ t("users.forceResetAtNextLogin") }}</option>
      </select></label>
      <p v-else class="text-sm text-text-muted">{{ t("users.confirmAccountAction", { action: translateKnownText(t, accountAction.toLowerCase()) }) }}</p>
      <template #footer="{ close }">
        <button class="rounded-md border px-4 py-2 text-xs" @click="close">{{ t("common.cancel") }}</button>
        <button class="rounded-md bg-primary px-4 py-2 text-xs text-white" @click="close">{{ t("common.confirm") }}</button>
      </template>
    </AppDialog>
  </div>

  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <!-- PERMISSION MATRIX VIEW (live RBAC data)                                -->
  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <div v-else>
    <RouterLink
      :to="withLang(route.path.startsWith('/app/developer') ? '/app/developer/users' : '/app/users', route.query.lang)"
      class="mb-3 inline-flex items-center gap-1 text-xs text-text-muted hover:text-text"
    ><IconArrowLeft :size="13" />{{ t("common.back") }}</RouterLink>

    <header class="mb-4 flex flex-wrap items-start justify-between gap-3" data-tour="page-header">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">{{ t("users.permissionMatrix") }}</h1>
        <p class="mt-1 text-sm text-text-muted">{{ t("users.permissionMatrixDescription") }}</p>
      </div>
      <div class="flex items-center gap-2">
        <!-- Loading / error indicator -->
        <span v-if="isLoading" class="inline-flex items-center gap-1.5 text-xs text-text-muted">
          <IconLoader2 :size="13" class="animate-spin" /> Syncing…
        </span>
        <span v-else-if="loadError" class="inline-flex items-center gap-1.5 text-xs text-danger">
          <IconX :size="13" /> Failed to load
          <button class="underline" @click="() => { rolesQuery.refetch(); permsQuery.refetch(); }">retry</button>
        </span>
        <AppTour />
        <!-- Create new role button -->
        <button
          class="inline-flex h-9 items-center gap-1.5 rounded-md bg-white text-black font-medium px-3 text-xs hover:bg-neutral-200 transition-colors shadow-sm"
          @click="createRoleDialog = true"
        >
          <IconPlus :size="14" /> New role
        </button>
      </div>
    </header>

    <!-- Loading skeleton -->
    <template v-if="rolesQuery.isPending.value || permsQuery.isPending.value">
      <div v-for="i in 4" :key="i" class="mb-4 overflow-hidden rounded-lg border bg-surface">
        <div class="animate-pulse bg-surface-muted px-3 py-2.5 h-8 w-40 rounded mb-1" />
        <div v-for="j in 3" :key="j" class="flex gap-4 px-3 py-3 border-t">
          <div class="h-3 w-40 rounded bg-surface-muted animate-pulse" />
          <div v-for="k in (editableRoles.length || 3)" :key="k" class="h-3 w-8 rounded bg-surface-muted animate-pulse" />
        </div>
      </div>
    </template>

    <!-- Live permission matrix -->
    <template v-else-if="!loadError">
      <section v-for="category in categories" :key="category" class="mb-4">
        <p class="mb-1.5 text-2xs font-medium uppercase tracking-wide text-text-muted">
          {{ category }}
        </p>
        <div class="overflow-hidden rounded-lg border bg-surface">
          <table class="w-full text-left text-xs">
            <thead class="bg-surface-muted text-2xs uppercase text-text-muted">
              <tr>
                <th class="px-3 py-2.5 min-w-[180px]">Permission</th>
                <!-- System developer column always shown first -->
                <th
                  v-for="role in roles"
                  :key="role.id"
                  class="px-3 py-2.5 whitespace-nowrap"
                >
                  <div class="flex items-center gap-1.5">
                    <span
                      v-if="role.color"
                      class="inline-block h-2 w-2 rounded-full"
                      :style="{ background: role.color }"
                    />
                    {{ role.name }}
                    <IconLock v-if="role.is_system" :size="10" class="text-text-soft" />
                  </div>
                </th>
                <th class="w-8" />
              </tr>
            </thead>
            <tbody class="divide-y">
              <tr
                v-for="perm in permGroups[category]"
                :key="perm.id"
                class="group"
              >
                <td class="px-3 py-2.5">
                  <span class="font-medium">{{ perm.name }}</span>
                  <span v-if="perm.description" class="ml-1.5 text-2xs text-text-soft">
                    {{ perm.description }}
                  </span>
                </td>
                <td
                  v-for="role in roles"
                  :key="role.id"
                  class="px-3 py-2.5"
                >
                  <!-- Superuser cell — always checked, not clickable -->
                  <span
                    v-if="(role.permissions as string[]).includes('*')"
                    title="Superuser — has all permissions"
                  >
                    <IconCheck :size="14" class="text-success opacity-50" />
                  </span>
                  <!-- System role cell — read-only, shows current state but no toggle -->
                  <span
                    v-else-if="role.is_system"
                    :title="`${role.name} is a system role — permissions are fixed`"
                  >
                    <IconCheck v-if="roleHasPerm(role, perm.name)" :size="14" class="text-success opacity-40" />
                    <IconX v-else :size="14" class="text-text-soft opacity-20" />
                  </span>
                  <!-- Toggleable cell — custom roles only -->
                  <button
                    v-else
                    :disabled="pendingToggle === `${role.id}:${perm.name}`"
                    :title="roleHasPerm(role, perm.name) ? `Remove from ${role.name}` : `Add to ${role.name}`"
                    class="flex h-6 w-6 items-center justify-center rounded transition-colors hover:bg-surface-muted disabled:cursor-wait"
                    @click="togglePermission(role, perm.name)"
                  >
                    <IconLoader2
                      v-if="pendingToggle === `${role.id}:${perm.name}`"
                      :size="13"
                      class="animate-spin text-text-muted"
                    />
                    <IconCheck
                      v-else-if="roleHasPerm(role, perm.name)"
                      :size="14"
                      class="text-success"
                    />
                    <IconX
                      v-else
                      :size="14"
                      class="text-text-soft opacity-0 transition-opacity group-hover:opacity-100"
                    />
                  </button>
                </td>
                <td class="w-8" />
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Role management cards -->
      <section class="mt-6">
        <p class="mb-2 text-2xs font-medium uppercase tracking-wide text-text-muted">Roles</p>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <div
            v-for="role in roles"
            :key="role.id"
            class="flex items-start justify-between rounded-lg border bg-surface p-3"
          >
            <div class="min-w-0">
              <div class="flex items-center gap-2">
                <span
                  v-if="role.color"
                  class="inline-block h-2.5 w-2.5 flex-shrink-0 rounded-full"
                  :style="{ background: role.color }"
                />
                <span class="truncate text-sm font-medium">{{ role.name }}</span>
                <span
                  v-if="role.is_system"
                  class="inline-flex items-center gap-0.5 rounded bg-surface-muted px-1.5 py-0.5 text-2xs text-text-muted"
                >
                  <IconLock :size="9" /> system
                </span>
              </div>
              <p v-if="role.description" class="mt-0.5 truncate text-2xs text-text-muted">
                {{ role.description }}
              </p>
              <p class="mt-1 text-2xs text-text-soft">
                {{ role.permissions_count }} permission{{ role.permissions_count !== 1 ? "s" : "" }}
                · {{ role.users_count }} user{{ role.users_count !== 1 ? "s" : "" }}
              </p>
            </div>
            <!-- Edit / delete — disabled for system roles -->
            <div class="ml-2 flex flex-shrink-0 gap-1">
              <button
                :disabled="role.is_system"
                :class="['rounded p-1 transition-colors', role.is_system ? 'cursor-not-allowed text-text-soft' : 'hover:bg-surface-muted text-text-muted hover:text-text']"
                title="Edit role"
                @click="!role.is_system && openEditRole(role)"
              >
                <IconPencil :size="13" />
              </button>
              <button
                :disabled="role.is_system"
                :class="['rounded p-1 transition-colors', role.is_system ? 'cursor-not-allowed text-text-soft' : 'hover:bg-danger-soft text-text-muted hover:text-danger']"
                title="Delete role"
                @click="!role.is_system && openDeleteRole(role)"
              >
                <IconTrash :size="13" />
              </button>
            </div>
          </div>
        </div>
      </section>
    </template>

    <!-- Error state -->
    <div v-else class="rounded-lg border bg-surface p-8 text-center">
      <p class="text-sm text-danger">Failed to load role data.</p>
      <button
        class="mt-3 inline-flex items-center gap-1.5 rounded-md border px-3 py-1.5 text-xs hover:bg-surface-muted"
        @click="() => { rolesQuery.refetch(); permsQuery.refetch(); }"
      >
        <IconRefresh :size="13" /> Retry
      </button>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <!-- DIALOGS                                                                 -->
  <!-- ═══════════════════════════════════════════════════════════════════════ -->

  <!-- Create role -->
  <AppDialog v-model="createRoleDialog" title="Create role" description="Add a new role that can be assigned permissions and users." size="sm">
    <div class="grid gap-3">
      <label class="text-xs font-medium">
        Name <span class="text-danger">*</span>
        <input
          v-model="createForm.name"
          class="mt-1.5 h-9 w-full rounded-md border px-3 text-sm"
          placeholder="e.g. Reviewer"
          maxlength="50"
        />
      </label>
      <label class="text-xs font-medium">
        Description
        <input
          v-model="createForm.description"
          class="mt-1.5 h-9 w-full rounded-md border px-3 text-sm"
          placeholder="Optional short description"
          maxlength="255"
        />
      </label>
      <label class="text-xs font-medium">
        Badge color
        <div class="mt-1.5 flex items-center gap-2">
          <input type="color" v-model="createForm.color" class="h-9 w-12 cursor-pointer rounded border p-1" />
          <span class="text-xs text-text-muted font-mono">{{ createForm.color }}</span>
        </div>
      </label>
    </div>
    <template #footer="{ close }">
      <button class="rounded-md border px-4 py-2 text-xs" @click="close">Cancel</button>
      <button
        :disabled="!createForm.name.trim() || createRoleMutation.isPending.value"
        class="inline-flex items-center gap-1.5 rounded-md bg-primary px-4 py-2 text-xs text-white disabled:opacity-50"
        @click="submitCreateRole"
      >
        <IconLoader2 v-if="createRoleMutation.isPending.value" :size="12" class="animate-spin" />
        Create role
      </button>
    </template>
  </AppDialog>

  <!-- Edit role -->
  <AppDialog v-model="editRoleDialog" title="Edit role" :description="editingRole ? `Editing '${editingRole.name}'` : ''" size="sm">
    <div class="grid gap-3">
      <label class="text-xs font-medium">
        Name <span class="text-danger">*</span>
        <input
          v-model="editForm.name"
          class="mt-1.5 h-9 w-full rounded-md border px-3 text-sm"
          maxlength="50"
        />
      </label>
      <label class="text-xs font-medium">
        Description
        <input
          v-model="editForm.description"
          class="mt-1.5 h-9 w-full rounded-md border px-3 text-sm"
          maxlength="255"
        />
      </label>
      <label class="text-xs font-medium">
        Badge color
        <div class="mt-1.5 flex items-center gap-2">
          <input type="color" v-model="editForm.color" class="h-9 w-12 cursor-pointer rounded border p-1" />
          <span class="text-xs text-text-muted font-mono">{{ editForm.color }}</span>
        </div>
      </label>
    </div>
    <template #footer="{ close }">
      <button class="rounded-md border px-4 py-2 text-xs" @click="close">Cancel</button>
      <button
        :disabled="!editForm.name.trim() || editRoleMutation.isPending.value"
        class="inline-flex items-center gap-1.5 rounded-md bg-primary px-4 py-2 text-xs text-white disabled:opacity-50"
        @click="submitEditRole"
      >
        <IconLoader2 v-if="editRoleMutation.isPending.value" :size="12" class="animate-spin" />
        Save changes
      </button>
    </template>
  </AppDialog>

  <!-- Delete role -->
  <AppDialog v-model="deleteRoleDialog" title="Delete role" size="sm">
    <p class="text-sm text-text-muted">
      Delete <strong class="text-text">{{ deletingRole?.name }}</strong>? This will remove the role from
      <strong>{{ deletingRole?.users_count }}</strong> user{{ (deletingRole?.users_count ?? 0) !== 1 ? "s" : "" }}
      and cannot be undone.
    </p>
    <template #footer="{ close }">
      <button class="rounded-md border px-4 py-2 text-xs" @click="close">Cancel</button>
      <button
        :disabled="deleteRoleMutation.isPending.value"
        class="inline-flex items-center gap-1.5 rounded-md bg-danger px-4 py-2 text-xs text-white disabled:opacity-50"
        @click="submitDeleteRole"
      >
        <IconLoader2 v-if="deleteRoleMutation.isPending.value" :size="12" class="animate-spin" />
        Delete role
      </button>
    </template>
  </AppDialog>
</template>
