<script setup lang="ts">
import { computed, ref } from "vue";
import { useRoute } from "vue-router";
import { useI18n } from "vue-i18n";
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
import { translateKnownText } from "@/i18n/knownText";
import { withLang } from "@/i18n/routeLang";

const route = useRoute();
const { t } = useI18n();
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
        <h1 class="text-2xl font-semibold tracking-tight">{{ t("users.title") }}</h1>
        <p class="mt-1 text-sm text-text-muted">{{ t("users.description") }}</p>
      </div>
      <div class="flex gap-2">
        <AppTour />
        <button
          class="inline-flex h-9 items-center gap-1.5 rounded-md border bg-surface px-3 text-xs"
        >
          <IconDownload :size="14" />{{ t("common.export") }}</button
        ><RouterLink
          :to="withLang('/app/users/permissions', route.query.lang)"
          class="inline-flex h-9 items-center rounded-md border bg-surface px-3 text-xs"
          >{{ t("users.permissionMatrix") }}</RouterLink
        ><button
          class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-xs font-medium text-white"
          @click="userDialog = true"
        >
          <IconPlus :size="14" />{{ t("users.newUser") }}
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
          :placeholder="t('users.searchPlaceholder')"
          class="h-9 w-full rounded-md border pl-9 pr-3 text-xs"
        />
      </div>
      <select v-model="roleFilter" class="h-9 rounded-md border bg-surface px-3 text-xs">
        <option value="All roles">{{ t("users.allRoles") }}</option>
        <option value="Admin">{{ t("roles.admin") }}</option>
        <option value="Head">{{ t("roles.head") }}</option>
        <option value="Staff">{{ t("roles.staff") }}</option></select
      ><select v-model="statusFilter" class="h-9 rounded-md border bg-surface px-3 text-xs">
        <option value="All statuses">{{ t("users.allStatuses") }}</option>
        <option value="Active">{{ t("status.active") }}</option>
        <option value="Disabled">{{ t("status.disabled") }}</option>
      </select>
    </section>
    <div class="overflow-x-auto rounded-lg border bg-surface" data-tour="page-content">
      <table class="w-full text-left text-xs">
        <thead class="bg-surface-muted text-2xs uppercase text-text-muted">
          <tr>
            <th
              v-for="heading in [
                t('users.username'),
                t('users.fullName'),
                t('common.email'),
                t('users.role'),
                t('users.mfa'),
                t('users.status'),
                t('users.lastLogin'),
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
                translateKnownText(t, String(user[3]))
              }}</span>
            </td>
            <td class="px-3 py-3">
              <span
                :class="[
                  'rounded-full px-2 py-0.5',
                  user[4] ? 'bg-success-soft text-success' : 'bg-warning-soft text-warning',
                ]"
                >{{ user[4] ? t("status.enabled") : t("status.off") }}</span
              >
            </td>
            <td class="px-3 py-3">
              <span
                :class="[
                  'rounded-full px-2 py-0.5',
                  user[5] ? 'bg-success-soft text-success' : 'bg-danger-soft text-danger',
                ]"
                >{{ user[5] ? t("status.active") : t("status.disabled") }}</span
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
                  <IconKey :size="12" />{{ t("users.reset") }}
                </button>
                <button
                  class="text-left text-text-muted"
                  @click="confirmAccount(user[5] ? 'Deactivate' : 'Activate', String(user[1]))"
                >
                  {{ user[5] ? t("users.deactivate") : t("users.activate") }}
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      <footer class="flex justify-between border-t px-3 py-2.5 text-xs text-text-muted">
        <span>{{ t("users.showingStaffUsers", { count: filtered.length }) }}</span><span>{{ t("pagination.pageOf", { page: 1, pages: 1 }) }}</span>
      </footer>
    </div>
    <AppDialog
      v-model="userDialog"
      :title="t('users.createStaffUser')"
      :description="t('users.createStaffUserDescription')"
      size="lg"
      ><div class="grid gap-4 sm:grid-cols-2">
        <label class="text-xs font-medium"
          >{{ t("users.fullName") }}<input
            class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm"
            :placeholder="t('users.fullName')" /></label
        ><label class="text-xs font-medium"
          >{{ t("users.username") }}<input
            class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm"
            :placeholder="t('users.usernamePlaceholder')" /></label
        ><label class="text-xs font-medium"
          >{{ t("common.email") }}<input
            type="email"
            class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm"
            placeholder="name@unifast.gov.ph" /></label
        ><label class="text-xs font-medium"
          >{{ t("users.role") }}<select class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm">
            <option>{{ t("roles.staff") }}</option>
            <option>{{ t("roles.head") }}</option>
            <option>{{ t("roles.admin") }}</option>
          </select></label
        ><label class="flex items-center gap-2 text-xs sm:col-span-2"
          ><input type="checkbox" checked />{{ t("users.requirePasswordMfa") }}</label
        >
      </div>
      <template #footer="{ close }"
        ><button class="rounded-md border px-4 py-2 text-xs" @click="close">{{ t("common.cancel") }}</button
        ><button class="rounded-md bg-primary px-4 py-2 text-xs text-white" @click="close">
          {{ t("users.createUser") }}
        </button></template
      ></AppDialog
    >
    <AppDialog
      v-model="accountDialog"
      :title="t('users.accountActionTitle', { action: translateKnownText(t, accountAction) })"
      :description="t('users.accountActionDescription', { action: translateKnownText(t, accountAction), name: accountName })"
      size="sm"
      ><label v-if="accountAction === 'Reset password'" class="text-xs font-medium"
        >{{ t("users.resetMethod") }}<select class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm">
          <option>{{ t("users.sendPasswordResetEmail") }}</option>
          <option>{{ t("users.generateTemporaryPassword") }}</option>
          <option>{{ t("users.forceResetAtNextLogin") }}</option>
        </select></label
      >
      <p v-else class="text-sm text-text-muted">
        {{ t("users.confirmAccountAction", { action: translateKnownText(t, accountAction.toLowerCase()) }) }}
      </p>
      <template #footer="{ close }"
        ><button class="rounded-md border px-4 py-2 text-xs" @click="close">{{ t("common.cancel") }}</button
        ><button class="rounded-md bg-primary px-4 py-2 text-xs text-white" @click="close">
          {{ t("common.confirm") }}
        </button></template
      ></AppDialog
    >
  </div>
  <div v-else>
    <RouterLink
      :to="withLang('/app/users', route.query.lang)"
      class="mb-3 inline-flex items-center gap-1 text-xs text-text-muted hover:text-text"
      ><IconArrowLeft :size="13" />{{ t("common.back") }}</RouterLink
    >
    <header class="mb-4 flex flex-wrap items-start justify-between gap-3" data-tour="page-header">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">{{ t("users.permissionMatrix") }}</h1>
        <p class="mt-1 text-sm text-text-muted">{{ t("users.permissionMatrixDescription") }}</p>
      </div>
      <AppTour />
    </header>
    <section v-for="group in permissionGroups" :key="group.module" class="mb-4">
      <p class="mb-1.5 text-2xs font-medium uppercase tracking-wide text-text-muted">
        {{ translateKnownText(t, group.module) }}
      </p>
      <div class="overflow-hidden rounded-lg border bg-surface">
        <table class="w-full text-left text-xs">
          <thead class="bg-surface-muted text-2xs uppercase text-text-muted">
            <tr>
              <th class="px-3 py-2.5">{{ t("users.permission") }}</th>
              <th v-for="role in ['Admin', 'Head', 'Staff']" :key="role" class="px-3 py-2.5">
                {{ translateKnownText(t, role) }}
              </th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr v-for="permission in group.permissions" :key="permission">
              <td class="px-3 py-3 capitalize">{{ translateKnownText(t, permission) }}</td>
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
