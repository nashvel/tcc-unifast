<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import { useQuery, useQueryClient } from "@tanstack/vue-query";
import {
  IconBell,
  IconChevronDown,
  IconCommand,
  IconLogout,
  IconMenu2,
  IconMoon,
  IconSearch,
  IconSun,
  IconUserCircle,
} from "@tabler/icons-vue";
import {
  adminNavigation,
  developerNavigation,
  lockedStudentNavigation,
  staffNavigation,
  studentNavigation,
} from "@/constants/navigation";
import logo from "@/assets/system-logo.png";
import AppBreadcrumbs from "@/components/navigation/AppBreadcrumbs.vue";
import LanguageSwitcher from "@/components/LanguageSwitcher.vue";
import DeveloperSidebar from "./DeveloperSidebar.vue";
import DiceBearAvatar from "@/components/ui/DiceBearAvatar.vue";
import OfflineBanner from "@/components/ui/OfflineBanner.vue";
import { authSession } from "@/auth/session";
import { logout } from "@/api/auth";
import { apiFetch, type PaginatedResponse } from "@/api";
import { queryKeys } from "@/api/queryKeys";
import { studentVerification } from "@/auth/studentVerification";
import { ensureEcho, useNotificationChannel } from "@/composables/useEcho";
import { withLang } from "@/i18n/routeLang";

ensureEcho();

type ShellNotification = {
  id: number;
  title: string;
  body: string;
  type: string;
  read: boolean;
  time: string | null;
};

const route = useRoute();
const router = useRouter();
const { t } = useI18n();
const queryClient = useQueryClient();
const mobile = ref(false);
const profile = ref(false);
const notifications = ref(false);
const isStudent = computed(() => route.path.startsWith("/student"));
const isDeveloper = computed(() => authSession.user?.role === "developer");
const role = computed(() => authSession.user?.role ?? "student");
const user = computed(() => authSession.user);
const staffNotifyEnabled = computed(
  () => !isStudent.value && ["admin", "head", "staff", "developer"].includes(String(role.value)),
);

const staffNotificationsQuery = useQuery({
  queryKey: queryKeys.notifications,
  enabled: staffNotifyEnabled,
  queryFn: () => apiFetch<PaginatedResponse<ShellNotification>>("/api/notifications?per_page=20"),
});

const staffItems = computed(() => staffNotificationsQuery.data.value?.data ?? []);
const unreadCount = computed(() => staffItems.value.filter((item) => !item.read).length);

useNotificationChannel((payload) => {
  if (!staffNotifyEnabled.value) return;
  queryClient.setQueryData<PaginatedResponse<ShellNotification>>(queryKeys.notifications, (current) => {
    const nextItem: ShellNotification = {
      id: payload.id,
      title: payload.title,
      body: payload.body,
      type: payload.type,
      read: payload.read,
      time: payload.time,
    };
    if (!current) {
      return {
        data: [nextItem],
        meta: { current_page: 1, last_page: 1, per_page: 20, total: 1, from: 1, to: 1 },
      };
    }
    return { ...current, data: [nextItem, ...current.data.filter((row) => row.id !== nextItem.id)] };
  });
});

watch(notifications, (open) => {
  if (open && staffNotifyEnabled.value) void staffNotificationsQuery.refetch();
});

async function markAllStaffRead() {
  await apiFetch("/api/notifications/read-all", { method: "POST" });
  queryClient.setQueryData<PaginatedResponse<ShellNotification>>(queryKeys.notifications, (current) => {
    if (!current) return current;
    return { ...current, data: current.data.map((row) => ({ ...row, read: true })) };
  });
}

const dark = ref(
  typeof localStorage !== "undefined"
    ? localStorage.getItem("theme") === "dark" || isDeveloper.value
    : isDeveloper.value,
);

const sections = computed(() => {
  if (isStudent.value)
    return studentVerification.verified ? studentNavigation : lockedStudentNavigation;
  if (role.value === "developer") return developerNavigation;
  if (role.value === "admin") return adminNavigation;
  return staffNavigation;
});

function isActive(path: string) {
  return path === "/app" || path === "/student"
    ? route.path === path
    : route.path === path || route.path.startsWith(`${path}/`);
}
function go(path: string) {
  mobile.value = false;
  router.push(withLang(path, route.query.lang));
}
function toggleTheme() {
  dark.value = !dark.value;
  if (typeof document !== "undefined")
    document.documentElement.classList.toggle("dark", dark.value);
  if (typeof localStorage !== "undefined")
    localStorage.setItem("theme", dark.value ? "dark" : "light");
}
async function signOut() {
  await logout();
  authSession.user = null;
  await router.push(withLang("/login", route.query.lang));
}

// Force dark mode for developers
watch(
  isDeveloper,
  (val) => {
    if (!val && typeof document !== "undefined") {
      document.documentElement.classList.remove("dev-dark");
    }
  },
  { immediate: true },
);

if (dark.value && typeof document !== "undefined") {
  document.documentElement.classList.add("dark");
}
</script>

<template>
  <div :class="['min-h-screen', isDeveloper ? 'bg-[var(--bg)]' : 'bg-bg']">
    <OfflineBanner />
    <div v-if="mobile" class="fixed inset-0 z-40 bg-black/30 lg:hidden" @click="mobile = false" />

    <!-- Developer Sidebar -->
    <aside
      v-if="isDeveloper"
      :class="[
        'fixed inset-y-0 left-0 z-50 transition-transform lg:translate-x-0',
        mobile ? 'translate-x-0' : '-translate-x-full',
      ]"
    >
      <DeveloperSidebar @logout="signOut" />
    </aside>

    <!-- Standard Sidebar for other roles -->
    <aside
      v-else
      :class="[
        'fixed inset-y-0 left-0 z-50 flex w-60 shrink-0 flex-col border-r bg-sidebar-bg transition-transform lg:translate-x-0',
        mobile ? 'translate-x-0' : '-translate-x-full',
      ]"
    >
      <div class="flex h-14 shrink-0 items-center gap-2 border-b px-4">
        <img
          :src="logo"
          :alt="t('app.college')"
          class="h-8 w-8 shrink-0 select-none object-contain"
          draggable="false"
        />
        <div class="min-w-0 leading-tight">
          <p class="truncate text-sm font-semibold text-sidebar-text">
            {{ isStudent ? t("app.studentPortal") : t("app.name") }}
          </p>
          <p class="truncate text-2xs text-sidebar-text-muted">
            {{ isStudent ? t("app.name") : t("app.granteeManagement") }}
          </p>
        </div>
      </div>
      <nav class="flex-1 overflow-y-auto py-3">
        <div v-for="(section, sectionIndex) in sections" :key="sectionIndex" class="mb-3 px-2">
          <p
            v-if="section.labelKey"
            class="mb-1 px-2 text-2xs font-semibold uppercase tracking-wider text-sidebar-text-muted"
          >
            {{ t(section.labelKey) }}
          </p>
          <ul class="space-y-0.5">
            <li v-for="item in section.items" :key="item.path">
              <button
                class="flex h-8 w-full items-center gap-2 rounded-md px-2 text-left text-sm transition-colors"
                :class="
                  isActive(item.path)
                    ? 'bg-sidebar-active font-medium text-[#f5e6c4]'
                    : 'text-sidebar-text hover:bg-sidebar-active'
                "
                @click="go(item.path)"
              >
                <component
                  :is="item.icon"
                  :size="16"
                  :class="isActive(item.path) ? 'text-[#f5e6c4]' : 'text-sidebar-text-muted'"
                />
                <span class="truncate">{{ t(item.labelKey) }}</span>
              </button>
            </li>
          </ul>
        </div>
      </nav>
    </aside>

    <!-- Header -->
    <div :class="['min-h-screen', isDeveloper ? 'lg:pl-56' : 'lg:pl-60']">
      <header
        :class="[
          'sticky top-0 z-30 flex h-14 items-center gap-2 border-b px-4',
          isDeveloper ? 'border-[var(--border)] bg-[var(--bg)]' : 'bg-surface',
        ]"
      >
        <button
          :class="[
            'rounded-md p-1.5 lg:hidden',
            isDeveloper ? 'hover:bg-[var(--surface-muted)] text-[var(--text-muted)]' : 'hover:bg-surface-muted',
          ]"
          :aria-label="t('nav.openMenu')"
          @click="mobile = true"
        >
          <IconMenu2 :size="18" />
        </button>
        <img :src="logo" class="h-7 w-7 object-contain lg:hidden" alt="TCC" />

        <button
          :class="[
            'flex h-9 max-w-md flex-1 items-center gap-2 rounded-md border px-2.5 text-left text-sm',
            isDeveloper
              ? 'border-[var(--border)] bg-[var(--surface)] text-[var(--text-muted)] hover:bg-[var(--surface-muted)]'
              : 'border bg-surface text-text-muted hover:bg-surface-muted',
          ]"
        >
          <IconSearch :size="15" />
          <span class="flex-1 truncate">{{ t("shell.searchJump") }}</span>
          <kbd
            :class="[
              'hidden items-center gap-0.5 rounded border px-1 py-0.5 text-2xs sm:inline-flex',
              isDeveloper ? 'border-[var(--border)] text-[var(--text-soft)]' : 'border bg-surface text-text-soft',
            ]"
            ><IconCommand :size="10" /> K</kbd
          >
        </button>

        <div class="flex-1" />

        <LanguageSwitcher :dark="isDeveloper" />

        <button
          v-if="!isDeveloper"
          class="rounded-md p-2 hover:bg-surface-muted"
          @click="toggleTheme"
        >
          <IconSun v-if="dark" :size="18" /><IconMoon v-else :size="18" />
        </button>

        <div class="relative">
          <button
            :class="[
              'relative rounded-md p-2',
              isDeveloper ? 'hover:bg-[var(--surface-muted)] text-[var(--text-muted)]' : 'hover:bg-surface-muted',
            ]"
            :aria-label="t('shell.notifications')"
            @click="
              notifications = !notifications;
              profile = false;
            "
          >
            <IconBell :size="18" /><span
              v-if="staffNotifyEnabled && unreadCount > 0"
              class="absolute right-1 top-1 grid h-4 min-w-4 place-items-center rounded-full bg-danger px-1 text-2xs text-white"
              >{{ unreadCount > 9 ? "9+" : unreadCount }}</span
            >
          </button>
          <div
            v-if="notifications"
            :class="[
              'absolute right-0 mt-1 w-80 rounded-lg border shadow-xl',
              isDeveloper ? 'border-[var(--border)] bg-[var(--surface)]' : 'border bg-surface',
            ]"
          >
            <div
              :class="[
                'flex h-10 items-center justify-between border-b px-3',
                isDeveloper ? 'border-[var(--border)]' : '',
              ]"
            >
              <p :class="['text-sm font-semibold', isDeveloper ? 'text-[var(--text)]' : '']">
                {{ t("shell.notifications") }}
              </p>
              <button
                v-if="staffNotifyEnabled && unreadCount > 0"
                :class="['text-xs', isDeveloper ? 'text-white' : 'text-primary']"
                @click="markAllStaffRead"
              >
                {{ t("shell.markAllRead") }}
              </button>
            </div>
            <div
              v-if="staffNotifyEnabled"
              :class="[
                'max-h-72 space-y-3 overflow-y-auto p-3 text-xs',
                isDeveloper ? 'text-[var(--text-muted)]' : '',
              ]"
            >
              <p v-if="staffNotificationsQuery.isLoading.value" class="text-text-muted">Loading…</p>
              <p v-else-if="staffItems.length === 0" class="text-text-muted">No notifications yet.</p>
              <div v-for="item in staffItems" :key="item.id" :class="item.read ? 'opacity-70' : ''">
                <p>
                  <b>{{ item.title }}</b
                  ><br /><span :class="isDeveloper ? 'text-[var(--text-soft)]' : 'text-text-muted'">{{
                    item.body
                  }}</span>
                </p>
                <p
                  v-if="item.time"
                  :class="['mt-0.5 text-2xs', isDeveloper ? 'text-[var(--text-soft)]' : 'text-text-soft']"
                >
                  {{ item.time }}
                </p>
              </div>
            </div>
            <div v-else :class="['space-y-3 p-3 text-xs', isDeveloper ? 'text-[var(--text-muted)]' : '']">
              <p>
                <b>{{ t("shell.documentsValidated") }}</b
                ><br /><span :class="isDeveloper ? 'text-[var(--text-soft)]' : 'text-text-muted'">{{
                  t("shell.documentsReady")
                }}</span>
              </p>
              <p>
                <b>{{ t("shell.batchClosingSoon") }}</b
                ><br /><span :class="isDeveloper ? 'text-[var(--text-soft)]' : 'text-text-muted'">{{
                  t("shell.batchClosingDetail")
                }}</span>
              </p>
            </div>
          </div>
        </div>

        <div class="relative">
          <button
            :class="[
              'flex items-center gap-2 rounded-md py-1 pl-1 pr-2',
              isDeveloper ? 'hover:bg-[var(--surface-muted)]' : 'hover:bg-surface-muted',
            ]"
            @click="
              profile = !profile;
              notifications = false;
            "
          >
            <DiceBearAvatar
              :seed="isStudent ? 'student@tcc.edu.ph' : 'admin@unifast.gov.ph'"
              :alt="isStudent ? 'Maria Santos' : t('shell.systemDeveloper')"
              :size="28"
            />
            <span class="hidden text-left leading-tight sm:block"
              ><span :class="['block text-xs font-medium', isDeveloper ? 'text-[var(--text)]' : '']">{{
                user?.name ?? (isStudent ? "Maria Santos" : t("shell.systemDeveloper"))
              }}</span
              ><span
                :class="[
                  'block text-2xs capitalize',
                  isDeveloper ? 'text-[var(--text-muted)]' : 'text-text-muted',
                ]"
                >{{ isStudent ? t("shell.student") : role }}</span
              ></span
            ><IconChevronDown
              :size="14"
              :class="isDeveloper ? 'text-[var(--text-soft)]' : 'text-text-muted'"
            />
          </button>
          <div
            v-if="profile"
            :class="[
              'absolute right-0 mt-1 w-56 rounded-lg border p-1 shadow-xl',
              isDeveloper ? 'border-[var(--border)] bg-[var(--surface)]' : 'border bg-surface',
            ]"
          >
            <div :class="['border-b px-2.5 py-2', isDeveloper ? 'border-[var(--border)]' : '']">
              <p :class="['text-sm font-medium', isDeveloper ? 'text-[var(--text)]' : '']">
                {{ user?.name ?? (isStudent ? "Maria Santos" : t("shell.systemDeveloper")) }}
              </p>
              <p :class="['text-xs', isDeveloper ? 'text-[var(--text-muted)]' : 'text-text-muted']">
                {{ user?.email ?? (isStudent ? "student@tcc.edu.ph" : "admin@unifast.gov.ph") }}
              </p>
            </div>
            <button
              :class="[
                'flex w-full items-center gap-2 rounded px-2.5 py-1.5 text-sm',
                isDeveloper ? 'text-[var(--text)] hover:bg-[var(--surface-muted)]' : 'hover:bg-surface-muted',
              ]"
            >
              <IconUserCircle :size="15" /> {{ t("common.profile") }}</button
            ><button
              :class="[
                'flex w-full items-center gap-2 rounded px-2.5 py-1.5 text-sm text-danger',
                isDeveloper ? 'hover:bg-[var(--surface-muted)]' : 'hover:bg-surface-muted',
              ]"
              @click="signOut"
            >
              <IconLogout :size="15" /> {{ t("common.signOut") }}
            </button>
          </div>
        </div>
      </header>

      <main class="mx-auto w-full max-w-[1400px] p-4 sm:p-6" data-cloud="page-content">
        <AppBreadcrumbs v-if="!isStudent && !isDeveloper" />
        <RouterView v-slot="{ Component }"
          ><Transition name="page" mode="out-in"
            ><component :is="Component" :key="route.fullPath" class="page-enter" /></Transition
        ></RouterView>
      </main>
    </div>
  </div>
</template>
