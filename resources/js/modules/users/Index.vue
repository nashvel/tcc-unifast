<script setup lang="ts">
import { computed, ref } from "vue";
import { useRoute } from "vue-router";
import {
  IconArrowLeft,
  IconCheck,
  IconDownload,
  IconKey,
  IconPlus,
  IconSearch,
  IconX,
} from "@tabler/icons-vue";
import AppDialog from "@/components/dialogs/AppDialog.vue";
import AppTour from "@/components/tour/AppTour.vue";

const route = useRoute();
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
const isMatrix = computed(
  () => route.params.section === "permissions" || route.path.endsWith("/users/permissions"),
);
const users = [
  [
    "sysadmin",
    "System Administrator",
    "admin@unifast.gov.ph",
    "Admin",
    true,
    true,
    "Jul 11, 2026, 7:41 PM",
  ],
  [
    "office.head",
    "Office Head",
    "head@unifast.gov.ph",
    "Head",
    true,
    true,
    "Jul 11, 2026, 5:12 PM",
  ],
  [
    "unifast.staff",
    "UniFAST Staff",
    "staff@unifast.gov.ph",
    "Staff",
    false,
    true,
    "Jul 11, 2026, 4:48 PM",
  ],
  [
    "reviewer.01",
    "Document Reviewer",
    "reviewer@unifast.gov.ph",
    "Staff",
    false,
    false,
    "Jul 8, 2026, 9:16 AM",
  ],
];
const filtered = computed(() =>
  users.filter((user) => {
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
const permissionGroups = [
  { module: "Operations", permissions: ["view masterlist", "manage batches", "manage grantees"] },
  {
    module: "Validation",
    permissions: ["validate documents", "review academics", "run eligibility"],
  },
  {
    module: "Communication",
    permissions: ["publish announcements", "generate reports", "manage support"],
  },
  {
    module: "Administration",
    permissions: ["view audit trail", "manage users", "change settings"],
  },
];
const allowed = (role: string, permission: string) =>
  role === "Head" ||
  (role === "Admin"
    ? [
        "publish announcements",
        "generate reports",
        "manage support",
        "view audit trail",
        "manage users",
        "change settings",
      ].includes(permission)
    : !["manage users", "change settings"].includes(permission));
</script>

<template>
  <div v-if="!isMatrix">
    <header class="mb-4 flex flex-wrap items-start justify-between gap-3" data-tour="page-header">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">Users & Access</h1>
        <p class="mt-1 text-sm text-text-muted">Manage staff accounts, roles, and permissions.</p>
      </div>
      <div class="flex gap-2">
        <AppTour />
        <button
          class="inline-flex h-9 items-center gap-1.5 rounded-md border bg-surface px-3 text-xs"
        >
          <IconDownload :size="14" />Export</button
        ><RouterLink
          to="/app/users/permissions"
          class="inline-flex h-9 items-center rounded-md border bg-surface px-3 text-xs"
          >Permission matrix</RouterLink
        ><button
          class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-xs font-medium text-white"
          @click="userDialog = true"
        >
          <IconPlus :size="14" />New user
        </button>
      </div>
    </header>
    <section class="mb-4 grid grid-cols-1 gap-2 rounded-lg border bg-surface p-3 md:grid-cols-4">
      <div class="relative md:col-span-2">
        <IconSearch
          :size="14"
          class="absolute left-3 top-1/2 -translate-y-1/2 text-text-soft"
        /><input
          v-model="search"
          placeholder="Search by name, username, or email"
          class="h-9 w-full rounded-md border pl-9 pr-3 text-xs"
        />
      </div>
      <select v-model="roleFilter" class="h-9 rounded-md border bg-surface px-3 text-xs">
        <option>All roles</option>
        <option>Admin</option>
        <option>Head</option>
        <option>Staff</option></select
      ><select v-model="statusFilter" class="h-9 rounded-md border bg-surface px-3 text-xs">
        <option>All statuses</option>
        <option>Active</option>
        <option>Disabled</option>
      </select>
    </section>
    <div class="overflow-x-auto rounded-lg border bg-surface" data-tour="page-content">
      <table class="w-full text-left text-xs">
        <thead class="bg-surface-muted text-2xs uppercase text-text-muted">
          <tr>
            <th
              v-for="heading in [
                'Username',
                'Full Name',
                'Email',
                'Role',
                'MFA',
                'Status',
                'Last login',
                '',
              ]"
              :key="heading"
              :class="['px-3 py-2.5', heading === '' ? 'w-40 text-right' : '']"
            >
              {{ heading }}
            </th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <tr v-for="user in filtered" :key="user[0] as string">
            <td class="px-3 py-3 font-mono">{{ user[0] }}</td>
            <td class="px-3 py-3 font-medium">{{ user[1] }}</td>
            <td class="px-3 py-3 text-text-muted">{{ user[2] }}</td>
            <td class="px-3 py-3">
              <span class="rounded-full bg-primary-soft px-2 py-0.5 text-primary">{{
                user[3]
              }}</span>
            </td>
            <td class="px-3 py-3">
              <span
                :class="[
                  'rounded-full px-2 py-0.5',
                  user[4] ? 'bg-success-soft text-success' : 'bg-warning-soft text-warning',
                ]"
                >{{ user[4] ? "Enabled" : "Off" }}</span
              >
            </td>
            <td class="px-3 py-3">
              <span
                :class="[
                  'rounded-full px-2 py-0.5',
                  user[5] ? 'bg-success-soft text-success' : 'bg-danger-soft text-danger',
                ]"
                >{{ user[5] ? "Active" : "Disabled" }}</span
              >
            </td>
            <td class="whitespace-nowrap px-3 py-3 text-text-muted">{{ user[6] }}</td>
            <td class="w-40 whitespace-nowrap px-3 py-3">
              <div
                class="ml-auto grid w-36 grid-cols-[4rem_5rem] items-center justify-end text-left"
              >
                <button
                  class="inline-flex items-center gap-1 text-primary"
                  @click="confirmAccount('Reset password', String(user[1]))"
                >
                  <IconKey :size="12" />Reset
                </button>
                <button
                  class="text-left text-text-muted"
                  @click="confirmAccount(user[5] ? 'Deactivate' : 'Activate', String(user[1]))"
                >
                  {{ user[5] ? "Deactivate" : "Activate" }}
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      <footer class="flex justify-between border-t px-3 py-2.5 text-xs text-text-muted">
        <span>Showing {{ filtered.length }} staff users</span><span>Page 1 of 1</span>
      </footer>
    </div>
    <AppDialog
      v-model="userDialog"
      title="Create staff user"
      description="Add an account and assign its initial role and access."
      size="lg"
      ><div class="grid gap-4 sm:grid-cols-2">
        <label class="text-xs font-medium"
          >Full name<input
            class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm"
            placeholder="Full name" /></label
        ><label class="text-xs font-medium"
          >Username<input
            class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm"
            placeholder="username" /></label
        ><label class="text-xs font-medium"
          >Email<input
            type="email"
            class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm"
            placeholder="name@unifast.gov.ph" /></label
        ><label class="text-xs font-medium"
          >Role<select class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm">
            <option>Staff</option>
            <option>Head</option>
            <option>Admin</option>
          </select></label
        ><label class="flex items-center gap-2 text-xs sm:col-span-2"
          ><input type="checkbox" checked />Require password change and MFA enrollment on first
          sign-in</label
        >
      </div>
      <template #footer="{ close }"
        ><button class="rounded-md border px-4 py-2 text-xs" @click="close">Cancel</button
        ><button class="rounded-md bg-primary px-4 py-2 text-xs text-white" @click="close">
          Create user
        </button></template
      ></AppDialog
    >
    <AppDialog
      v-model="accountDialog"
      :title="`${accountAction} account`"
      :description="`${accountAction} for ${accountName}. This mock action does not persist.`"
      size="sm"
      ><label v-if="accountAction === 'Reset password'" class="text-xs font-medium"
        >Reset method<select class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm">
          <option>Send password reset email</option>
          <option>Generate temporary password</option>
          <option>Force reset at next login</option>
        </select></label
      >
      <p v-else class="text-sm text-text-muted">
        Confirm that you want to {{ accountAction.toLowerCase() }} this user account.
      </p>
      <template #footer="{ close }"
        ><button class="rounded-md border px-4 py-2 text-xs" @click="close">Cancel</button
        ><button class="rounded-md bg-primary px-4 py-2 text-xs text-white" @click="close">
          Confirm
        </button></template
      ></AppDialog
    >
  </div>
  <div v-else>
    <RouterLink
      to="/app/users"
      class="mb-3 inline-flex items-center gap-1 text-xs text-text-muted hover:text-text"
      ><IconArrowLeft :size="13" />Back</RouterLink
    >
    <header class="mb-4 flex flex-wrap items-start justify-between gap-3" data-tour="page-header">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">Permission Matrix</h1>
        <p class="mt-1 text-sm text-text-muted">Module-level permissions per role.</p>
      </div>
      <AppTour />
    </header>
    <section v-for="group in permissionGroups" :key="group.module" class="mb-4">
      <p class="mb-1.5 text-2xs font-medium uppercase tracking-wide text-text-muted">
        {{ group.module }}
      </p>
      <div class="overflow-hidden rounded-lg border bg-surface">
        <table class="w-full text-left text-xs">
          <thead class="bg-surface-muted text-2xs uppercase text-text-muted">
            <tr>
              <th class="px-3 py-2.5">Permission</th>
              <th v-for="role in ['Admin', 'Head', 'Staff']" :key="role" class="px-3 py-2.5">
                {{ role }}
              </th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr v-for="permission in group.permissions" :key="permission">
              <td class="px-3 py-3 capitalize">{{ permission }}</td>
              <td v-for="role in ['Admin', 'Head', 'Staff']" :key="role" class="px-3 py-3">
                <IconCheck v-if="allowed(role, permission)" :size="14" class="text-success" /><IconX
                  v-else
                  :size="14"
                  class="text-text-soft"
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>
