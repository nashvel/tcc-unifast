<script setup lang="ts">
import { computed, ref } from "vue";
import { useRoute } from "vue-router";
import { useI18n } from "vue-i18n";
import {
  IconArrowLeft,
  IconCheck,
  IconEdit,
  IconLoader2,
  IconLock,
  IconPlus,
  IconSearch,
  IconShieldCheck,
  IconX,
} from "@tabler/icons-vue";
import AppTour from "@/components/tour/AppTour.vue";
import DiceBearAvatar from "@/components/ui/DiceBearAvatar.vue";
import UserPermissionsModal from "./UserPermissionsModal.vue";
import { withLang } from "@/i18n/routeLang";
import { useUserModules, useRbacRoles } from "@/composables/useRbac";
import type { RbacUserModuleRow, RbacRole } from "@/api/rbac";

const route = useRoute();
const { t } = useI18n();

// ─── Live RBAC data ───────────────────────────────────────────────────────────
const { roles, query: rolesQuery } = useRbacRoles();
const {
  query: userModulesQuery,
  modules: operationalModules,
  users: moduleUsers,
  developers: developerUsers,
  nonAssignable,
  desks,
  sortedDeskNames,
} = useUserModules();

const isModulesLoading = computed(
  () => userModulesQuery.isFetching.value || rolesQuery.isFetching.value,
);
const modulesLoadError = computed(
  () => userModulesQuery.error.value || rolesQuery.error.value,
);

// ─── Filter & Search ──────────────────────────────────────────────────────────
const search = ref("");
const roleFilter = ref("All roles");

const filteredUsers = computed(() => {
  return moduleUsers.value.filter((u) => {
    const term = search.value.trim().toLowerCase();
    const matchesSearch =
      !term ||
      u.name.toLowerCase().includes(term) ||
      u.email.toLowerCase().includes(term) ||
      u.role.toLowerCase().includes(term);

    const matchesRole =
      roleFilter.value === "All roles" ||
      u.role.toLowerCase() === roleFilter.value.toLowerCase();

    return matchesSearch && matchesRole;
  });
});

// ─── Modal State ──────────────────────────────────────────────────────────────
const permissionsModalOpen = ref(false);
const selectedUser = ref<RbacUserModuleRow | null>(null);

function openEditPermissions(user: RbacUserModuleRow) {
  selectedUser.value = user;
  permissionsModalOpen.value = true;
}

// Keep selectedUser synced with updated user list
const activeModalUser = computed(() => {
  if (!selectedUser.value) return null;
  return (
    moduleUsers.value.find((u) => u.id === selectedUser.value?.id) ??
    selectedUser.value
  );
});

function isSuperuserRole(role: RbacRole): boolean {
  return (
    role.name.toLowerCase() === "developer" ||
    (role.permissions as string[]).includes("*")
  );
}

function isGranteeRole(role: RbacRole): boolean {
  return role.name.toLowerCase() === "student";
}

function deskCoverage(user: RbacUserModuleRow, deskName: string): { active: number; total: number } {
  const deskMods = desks.value[deskName] || [];
  const active = deskMods.filter((m) => user.assigned_modules.includes(m.key)).length;
  return { active, total: deskMods.length };
}
</script>

<template>
  <div class="space-y-6">
    <!-- Back button -->
    <RouterLink
      :to="withLang(route.path.startsWith('/app/developer') ? '/app/developer/users' : '/app/users', route.query.lang)"
      class="inline-flex items-center gap-1.5 text-xs text-text-muted hover:text-text transition-colors"
    >
      <IconArrowLeft :size="14" />
      {{ t("common.back") }} to Users
    </RouterLink>

    <!-- Header -->
    <header class="flex flex-wrap items-start justify-between gap-3" data-tour="page-header">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">{{ t("users.permissionMatrix") }}</h1>
        <p class="mt-1 text-sm text-text-muted leading-relaxed">
          Manage operational module authority per staff account. Developers hold unalterable root superuser access.
        </p>
      </div>
      <div class="flex items-center gap-2">
        <span v-if="isModulesLoading" class="inline-flex items-center gap-1.5 text-xs text-text-muted">
          <IconLoader2 :size="13" class="animate-spin" /> Syncing permissions…
        </span>
        <span v-else-if="modulesLoadError" class="inline-flex items-center gap-1.5 text-xs text-danger">
          <IconX :size="13" /> Failed to load
          <button class="underline" @click="() => { userModulesQuery.refetch(); rolesQuery.refetch(); }">retry</button>
        </span>
        <AppTour />
      </div>
    </header>

    <!-- Operational & CRUD notice -->
    <div class="flex items-center justify-between rounded-lg border border-primary/20 bg-primary/5 px-3.5 py-2.5 text-xs text-text-muted">
      <div class="flex items-center gap-2">
        <IconShieldCheck :size="16" class="text-primary flex-shrink-0" />
        <span>
          <strong>Tableized Per-User Permission Model:</strong> Assign specific operational modules directly to individual staff accounts.
          Granting a module automatically includes all CRUD operations, sub-functions, and actions under that module.
        </span>
      </div>
    </div>

    <!-- Permanent Root Developer Superuser Callout (Non-Assignable) -->
    <div v-if="developerUsers && developerUsers.length" class="rounded-lg border border-purple-500/25 bg-purple-500/5 p-4 text-xs">
      <div class="flex items-start gap-3">
        <div class="mt-0.5 rounded-md bg-purple-500/10 p-1.5 text-purple-600 dark:text-purple-400">
          <IconLock :size="16" />
        </div>
        <div class="flex-1">
          <div class="flex items-center gap-2">
            <p class="font-semibold text-text">Root Developer Accounts</p>
            <span class="rounded bg-purple-500/15 text-purple-600 dark:text-purple-400 px-1.5 py-0.5 text-3xs font-medium uppercase tracking-wide">Permanent Superuser · Non-Assignable</span>
          </div>
          <p class="mt-0.5 text-2xs text-text-muted leading-relaxed">
            Developer accounts hold permanent, root-level wildcard access (<code>*</code>) across all system engines. By security policy, developer accounts cannot be assigned or modified in the operational matrix.
          </p>
          <div class="mt-2.5 flex flex-wrap items-center gap-2">
            <div
              v-for="dev in developerUsers"
              :key="dev.id"
              class="inline-flex items-center gap-1.5 rounded-md border border-purple-500/20 bg-surface px-2.5 py-1 text-2xs font-medium text-text shadow-xs"
            >
              <span class="h-2 w-2 rounded-full bg-purple-500"></span>
              <span class="font-semibold">{{ dev.name }}</span>
              <span class="text-text-muted font-mono text-3xs">({{ dev.email }})</span>
              <span class="ml-1 inline-flex items-center gap-0.5 text-purple-600 dark:text-purple-400 text-3xs font-medium">
                <IconCheck :size="11" /> permanent root access
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Search & Filter Controls -->
    <section class="grid grid-cols-1 gap-2 rounded-lg border bg-surface p-3 md:grid-cols-3">
      <div class="relative md:col-span-2">
        <IconSearch :size="14" class="absolute left-3 top-1/2 -translate-y-1/2 text-text-soft" />
        <input
          v-model="search"
          placeholder="Search staff user by name, email, or role…"
          class="h-9 w-full rounded-md border pl-9 pr-3 text-xs"
        />
      </div>
      <select v-model="roleFilter" class="h-9 rounded-md border bg-surface px-3 text-xs">
        <option value="All roles">All staff & admin roles</option>
        <option value="Admin">Admin</option>
        <option value="Staff">Staff</option>
      </select>
    </section>

    <!-- Loading skeleton -->
    <div v-if="isModulesLoading" class="overflow-hidden rounded-lg border bg-surface">
      <div v-for="i in 4" :key="i" class="flex items-center justify-between border-b p-4">
        <div class="flex items-center gap-3">
          <div class="h-10 w-10 rounded-full bg-surface-muted animate-pulse" />
          <div class="space-y-1.5">
            <div class="h-3.5 w-32 rounded bg-surface-muted animate-pulse" />
            <div class="h-2.5 w-48 rounded bg-surface-muted animate-pulse" />
          </div>
        </div>
        <div class="h-8 w-24 rounded bg-surface-muted animate-pulse" />
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- PER-USER TABLEIZED PERMISSIONS VIEW                                     -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="!modulesLoadError" class="overflow-hidden rounded-lg border bg-surface shadow-xs">
      <table class="w-full text-left text-xs">
        <thead class="bg-surface-muted text-2xs uppercase text-text-muted border-b">
          <tr>
            <th class="px-4 py-3 min-w-[240px]">Staff Member</th>
            <th class="px-4 py-3 min-w-[100px]">Role</th>
            <th class="px-4 py-3 min-w-[340px]">Desk Coverage</th>
            <th class="px-4 py-3 text-center min-w-[120px]">Assigned Modules</th>
            <th class="px-4 py-3 text-right min-w-[140px]">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <tr
            v-for="user in filteredUsers"
            :key="user.id"
            class="group hover:bg-surface-muted/30 transition-colors"
          >
            <!-- User profile -->
            <td class="px-4 py-3.5">
              <div class="flex items-center gap-3">
                <DiceBearAvatar :seed="user.email" :size="36" class="rounded-full shadow-2xs shrink-0" />
                <div class="min-w-0">
                  <div class="flex items-center gap-1.5">
                    <span class="font-semibold text-text truncate">{{ user.name }}</span>
                  </div>
                  <p class="text-2xs text-text-muted truncate font-mono mt-0.5">{{ user.email }}</p>
                </div>
              </div>
            </td>

            <!-- Role badge -->
            <td class="px-4 py-3.5">
              <span
                class="inline-flex items-center rounded-full px-2 py-0.5 text-3xs font-medium uppercase tracking-wide"
                :class="
                  user.role === 'admin'
                    ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20'
                    : 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20'
                "
              >
                {{ user.role }}
              </span>
            </td>

            <!-- Operational Desks Summary Badges -->
            <td class="px-4 py-3.5">
              <div class="flex flex-wrap items-center gap-1.5">
                <span
                  v-for="deskName in sortedDeskNames"
                  :key="deskName"
                  :class="[
                    'inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-3xs font-medium border transition-colors',
                    deskCoverage(user, deskName).active === deskCoverage(user, deskName).total
                      ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20'
                      : deskCoverage(user, deskName).active > 0
                      ? 'bg-primary/10 text-primary border-primary/20'
                      : 'bg-surface-muted/60 text-text-soft border-border/60'
                  ]"
                  :title="`${deskName}: ${deskCoverage(user, deskName).active} of ${deskCoverage(user, deskName).total} modules active`"
                >
                  <span class="capitalize">{{ deskName.replace(' Desk', '') }}</span>
                  <span class="font-mono">({{ deskCoverage(user, deskName).active }}/{{ deskCoverage(user, deskName).total }})</span>
                </span>
              </div>
            </td>

            <!-- Modules Counter & Mini Progress -->
            <td class="px-4 py-3.5 text-center">
              <div class="inline-flex flex-col items-center gap-1">
                <span class="font-semibold text-xs text-text">
                  {{ user.assigned_modules.length }} / {{ operationalModules.length }}
                </span>
                <div class="w-16 h-1.5 bg-surface-muted rounded-full overflow-hidden">
                  <div
                    class="h-full bg-primary rounded-full transition-all"
                    :style="{ width: `${(user.assigned_modules.length / (operationalModules.length || 1)) * 100}%` }"
                  />
                </div>
              </div>
            </td>

            <!-- Action: Edit Permissions Button -->
            <td class="px-4 py-3.5 text-right">
              <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-md border border-primary/30 bg-primary/5 hover:bg-primary/10 text-primary font-medium px-3 py-1.5 text-xs shadow-2xs transition-all cursor-pointer"
                @click="openEditPermissions(user)"
              >
                <IconShieldCheck :size="14" />
                <span>Edit Permissions</span>
              </button>
            </td>
          </tr>

          <tr v-if="filteredUsers.length === 0">
            <td colspan="5" class="py-8 text-center text-xs text-text-muted">
              No staff members found matching criteria.
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Table Footer -->
      <footer class="flex justify-between border-t px-4 py-3 text-xs text-text-muted">
        <span>Showing {{ filteredUsers.length }} assignable staff & admin accounts</span>
        <span>Per-User RBAC Matrix · Active</span>
      </footer>
    </div>

    <!-- Non-Assignable System Boundaries: Developer & Grantee Modules -->
    <section class="mt-8">
      <div class="mb-3">
        <h2 class="text-xs font-semibold uppercase tracking-wider text-text-muted">Strict Non-Assignable Modules Isolation</h2>
        <p class="mt-0.5 text-2xs text-text-muted">By security policy, developer engines and student portal workflows are strictly segregated and cannot be assigned to staff or admin.</p>
      </div>

      <div class="grid gap-4 md:grid-cols-2">
        <!-- Developer-Only Modules -->
        <div class="rounded-lg border border-purple-500/20 bg-purple-500/5 p-3.5">
          <div class="flex items-center gap-2 mb-2">
            <IconLock :size="14" class="text-purple-600 dark:text-purple-400" />
            <h3 class="text-xs font-semibold text-text">Developer Tools (Non-Assignable)</h3>
            <span class="rounded bg-purple-500/15 text-purple-600 dark:text-purple-400 px-1.5 py-0.5 text-3xs font-medium">Developer Only</span>
          </div>
          <p class="text-2xs text-text-muted mb-3 leading-relaxed">
            These engines belong exclusively to System Developers and cannot be delegated or assigned to staff or administrator accounts.
          </p>
          <div class="space-y-2">
            <div
              v-for="item in nonAssignable?.developer_modules || []"
              :key="item.key"
              class="rounded border border-purple-500/15 bg-surface/80 p-2.5 text-xs shadow-2xs"
            >
              <div class="flex items-center justify-between gap-2">
                <span class="font-medium text-text text-2xs">{{ item.name }}</span>
                <span class="inline-flex items-center gap-1 text-3xs text-purple-600 dark:text-purple-400 font-medium">
                  <IconLock :size="10" /> Non-assignable
                </span>
              </div>
              <p class="mt-0.5 text-3xs text-text-muted">{{ item.description }}</p>
            </div>
          </div>
        </div>

        <!-- Student/Grantee Portal Modules -->
        <div class="rounded-lg border border-amber-500/20 bg-amber-500/5 p-3.5">
          <div class="flex items-center gap-2 mb-2">
            <IconLock :size="14" class="text-amber-600 dark:text-amber-400" />
            <h3 class="text-xs font-semibold text-text">Student / Grantee Portal (Non-Assignable)</h3>
            <span class="rounded bg-amber-500/15 text-amber-600 dark:text-amber-400 px-1.5 py-0.5 text-3xs font-medium">Grantee Only</span>
          </div>
          <p class="text-2xs text-text-muted mb-3 leading-relaxed">
            These workflows belong strictly to student grantees for identity onboarding and file submission. They cannot be assigned to staff or admin.
          </p>
          <div class="space-y-2">
            <div
              v-for="item in nonAssignable?.grantee_modules || []"
              :key="item.key"
              class="rounded border border-amber-500/15 bg-surface/80 p-2.5 text-xs shadow-2xs"
            >
              <div class="flex items-center justify-between gap-2">
                <span class="font-medium text-text text-2xs">{{ item.name }}</span>
                <span class="inline-flex items-center gap-1 text-3xs text-amber-600 dark:text-amber-400 font-medium">
                  <IconLock :size="10" /> Non-assignable
                </span>
              </div>
              <p class="mt-0.5 text-3xs text-text-muted">{{ item.description }}</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Role management overview cards -->
    <section class="mt-6">
      <p class="mb-2 text-2xs font-medium uppercase tracking-wide text-text-muted">System Baseline Roles</p>
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
              <span class="truncate text-sm font-semibold capitalize">{{ role.name }}</span>
              <span
                v-if="isSuperuserRole(role)"
                class="inline-flex items-center gap-0.5 rounded bg-purple-500/10 text-purple-600 dark:text-purple-400 px-1.5 py-0.5 text-2xs font-medium"
              >
                <IconLock :size="9" /> superuser
              </span>
              <span
                v-else-if="isGranteeRole(role)"
                class="inline-flex items-center gap-0.5 rounded bg-amber-500/10 text-amber-600 dark:text-amber-400 px-1.5 py-0.5 text-2xs font-medium"
              >
                grantee
              </span>
              <span
                v-else
                class="inline-flex items-center gap-0.5 rounded bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 px-1.5 py-0.5 text-2xs font-medium"
              >
                operational
              </span>
            </div>
            <p v-if="role.description" class="mt-0.5 truncate text-2xs text-text-muted">
              {{ role.description }}
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- Modal for editing user permissions -->
    <UserPermissionsModal
      v-model="permissionsModalOpen"
      :user="activeModalUser"
    />
  </div>
</template>
