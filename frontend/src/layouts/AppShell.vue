<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
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
  IconPalette,
  IconSearch,
  IconSun,
  IconUserCircle,
  IconX,
} from "@tabler/icons-vue";
import {
  adminNavigation,
  developerNavigation,
  lockedStudentNavigation,
  staffNavigation,
  studentNavigation,
  type NavigationSection,
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
import { ensureEcho, useNotificationChannel } from "@/composables/useEcho";
import { withLang } from "@/i18n/routeLang";
import { useTheme } from "@/composables/useTheme";
import { useNotificationList, useMarkAllNotificationsRead } from "@/composables/useNotifications";

ensureEcho();

const { isFlare, toggleFlare } = useTheme();

const SEARCH_MAX_LEN = 100;

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
const jumpOpen = ref(false);
const jumpQuery = ref("");
const jumpInput = ref<HTMLInputElement | null>(null);
const signingOut = ref(false);
const logoutToast = ref(false);
const isStudent = computed(() => route.path.startsWith("/student"));
const isDeveloper = computed(() => authSession.user?.role === "developer");
const role = computed(() => authSession.user?.role ?? "student");
const user = computed(() => authSession.user);
const staffNotifyEnabled = computed(
  () => !isStudent.value && ["admin", "head", "staff", "developer"].includes(String(role.value)),
);

const profilePath = computed(() => {
  if (isStudent.value) return "/student/profile";
  if (role.value === "developer") return "/app/developer/settings";
  return "/app/settings";
});

type JumpItem = { label: string; path: string; icon: NavigationSection["items"][number]["icon"] };

function closeMenus() {
  profile.value = false;
  notifications.value = false;
  jumpOpen.value = false;
}

function openJump() {
  profile.value = false;
  notifications.value = false;
  jumpOpen.value = true;
  void nextTick(() => jumpInput.value?.focus());
}

function closeJump() {
  jumpOpen.value = false;
  jumpQuery.value = "";
}

function onJumpQueryInput(event: Event) {
  const value = (event.target as HTMLInputElement).value.slice(0, SEARCH_MAX_LEN);
  jumpQuery.value = value;
}

function onGlobalKeydown(event: KeyboardEvent) {
  const key = event.key.toLowerCase();
  if ((event.metaKey || event.ctrlKey) && key === "k") {
    event.preventDefault();
    if (jumpOpen.value) closeJump();
    else openJump();
    return;
  }
  if (event.key === "Escape") {
    if (jumpOpen.value) {
      closeJump();
      return;
    }
    closeMenus();
  }
}

onMounted(() => {
  window.addEventListener("keydown", onGlobalKeydown);
});
onBeforeUnmount(() => {
  window.removeEventListener("keydown", onGlobalKeydown);
});

const staffNotificationsQuery = useQuery({
  queryKey: queryKeys.notifications,
  enabled: staffNotifyEnabled,
  queryFn: () => apiFetch<PaginatedResponse<ShellNotification>>("/api/notifications?per_page=20"),
});

const staffItems = computed(() => staffNotificationsQuery.data.value?.data ?? []);
const unreadCount = computed(() => staffItems.value.filter((item) => !item.read).length);

// Student notifications (bell)
const { query: studentNotifsQuery, items: studentNotifItems } = useNotificationList();
const markAllStudentRead = useMarkAllNotificationsRead();
const studentUnreadCount = computed(() => studentNotifItems.value.filter((n) => !n.read).length);

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
  if (!open) return;
  if (staffNotifyEnabled.value) void staffNotificationsQuery.refetch();
  if (isStudent.value) void studentNotifsQuery.refetch();
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
  if (isStudent.value) {
    // Full nav only after KYC + identity onboarding (server account_status).
    if (authSession.user?.account_status === "active") return studentNavigation;
    const onboardingPath =
      authSession.user?.onboarding_path ||
      (authSession.user?.account_status === "pending_face_review"
        ? "/student/onboarding/pending-review"
        : authSession.user?.account_status === "pending_identity"
          ? "/student/onboarding"
          : "/student/kyc");
    return [
      {
        items: [
          {
            labelKey: "nav.completeOnboarding",
            path: onboardingPath,
            icon: lockedStudentNavigation[0].items[0].icon,
          },
          lockedStudentNavigation[0].items[1],
        ],
      },
    ];
  }
  if (role.value === "developer") return developerNavigation;
  return staffNavigation;
});

const studentMobileNavItems = computed(() => {
  if (!isStudent.value) return [];
  return sections.value.flatMap((section) => section.items);
});

const jumpItems = computed((): JumpItem[] => {
  const q = jumpQuery.value.trim().toLowerCase().slice(0, SEARCH_MAX_LEN);
  const items: JumpItem[] = [];
  for (const section of sections.value) {
    for (const item of section.items) {
      const label = t(item.labelKey);
      if (!q || label.toLowerCase().includes(q) || item.path.toLowerCase().includes(q)) {
        items.push({ label, path: item.path, icon: item.icon });
      }
    }
  }
  return items.slice(0, 12);
});

function jumpTo(path: string) {
  // Allowlist: only paths from the role's navigation sections.
  const allowed = new Set(
    sections.value.flatMap((section) => section.items.map((item) => item.path)),
  );
  if (!allowed.has(path)) return;
  closeJump();
  go(path);
}

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
  if (typeof document !== "undefined") {
    if (isDeveloper.value) {
      document.documentElement.classList.toggle("dev-dark", dark.value);
      document.documentElement.classList.remove("dark");
    } else {
      document.documentElement.classList.toggle("dark", dark.value);
      document.documentElement.classList.remove("dev-dark");
    }
  }
  if (typeof localStorage !== "undefined")
    localStorage.setItem("theme", dark.value ? "dark" : "light");
}
async function signOut() {
  if (signingOut.value) return;
  signingOut.value = true;
  logoutToast.value = true;
  closeMenus();
  closeJump();
  try {
    await logout();
  } finally {
    authSession.user = null;
    queryClient.clear();
    signingOut.value = false;
    await router.push(withLang("/login", route.query.lang));
    logoutToast.value = false;
  }
}

async function goToProfile() {
  profile.value = false; // close the dropdown
  if (isStudent.value) {
    await router.push(withLang("/student/profile", route.query.lang));
  } else {
    await router.push(withLang("/app/settings", route.query.lang));
  }
}

// Apply dev-dark for developers on mount
watch(
  isDeveloper,
  (val) => {
    if (typeof document === "undefined") return;
    if (val) {
      document.documentElement.classList.add("dev-dark");
      document.documentElement.classList.remove("dark");
    } else {
      document.documentElement.classList.remove("dev-dark");
      if (dark.value) {
        document.documentElement.classList.add("dark");
      }
    }
  },
  { immediate: true },
);

if (dark.value && typeof document !== "undefined") {
  if (isDeveloper.value) {
    document.documentElement.classList.add("dev-dark");
  } else {
    document.documentElement.classList.add("dark");
  }
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
        !isStudent && mobile ? 'translate-x-0' : '-translate-x-full',
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
      <nav class="scrollbar-none flex-1 overflow-y-auto py-3">
        <div v-for="(section, sectionIndex) in sections" :key="sectionIndex" class="mb-3 px-2">
          
          <!-- Collapsible Section (if it has a label) -->
          <details v-if="section.labelKey" class="group" open>
            <summary class="flex cursor-pointer select-none items-center justify-between px-2 py-1 outline-none list-none [&::-webkit-details-marker]:hidden mb-1 transition-opacity hover:opacity-80">
              <span class="text-2xs font-semibold uppercase tracking-wider text-sidebar-text-muted">{{ t(section.labelKey) }}</span>
              <IconChevronDown :size="14" class="text-sidebar-text-muted opacity-50 transition-transform group-open:rotate-180" />
            </summary>
            <ul class="space-y-0.5 mt-1">
              <li v-for="item in section.items" :key="item.path">
                <button
                  class="flex h-8 w-full items-center gap-2 rounded-md px-2 text-left text-sm transition-colors"
                  :class="
                    isActive(item.path)
                      ? 'bg-sidebar-active font-medium text-[var(--sidebar-active-text,#f5e6c4)]'
                      : 'text-sidebar-text hover:bg-sidebar-active'
                  "
                  @click="go(item.path)"
                >
                  <component
                    :is="item.icon"
                    :size="16"
                    :class="isActive(item.path) ? 'text-[var(--sidebar-active-text,#f5e6c4)]' : 'text-sidebar-text-muted'"
                  />
                  <span class="truncate">{{ t(item.labelKey) }}</span>
                </button>
              </li>
            </ul>
          </details>

          <!-- Flat Section (if no label, e.g. Dashboard) -->
          <ul v-else class="space-y-0.5">
            <li v-for="item in section.items" :key="item.path">
              <button
                class="flex h-8 w-full items-center gap-2 rounded-md px-2 text-left text-sm transition-colors"
                :class="
                  isActive(item.path)
                    ? 'bg-sidebar-active font-medium text-[var(--sidebar-active-text,#f5e6c4)]'
                    : 'text-sidebar-text hover:bg-sidebar-active'
                "
                @click="go(item.path)"
              >
                <component
                  :is="item.icon"
                  :size="16"
                  :class="isActive(item.path) ? 'text-[var(--sidebar-active-text,#f5e6c4)]' : 'text-sidebar-text-muted'"
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
          'sticky top-0 z-50 flex h-14 items-center gap-2 border-b px-4',
          isDeveloper ? 'border-[var(--border)] bg-[var(--bg)]' : 'bg-surface',
        ]"
      >
        <button
          v-if="!isStudent"
          type="button"
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

        <div class="relative flex-1 max-w-md">
          <div
            :class="[
              'flex h-9 w-full items-center gap-2 rounded-md border px-2.5 text-sm transition-colors z-50 relative',
              isDeveloper
                ? 'border-[var(--border)] bg-[var(--surface)] text-[var(--text)] focus-within:border-primary focus-within:ring-1 focus-within:ring-primary'
                : 'border bg-surface text-text focus-within:border-primary focus-within:ring-1 focus-within:ring-primary',
            ]"
          >
            <IconSearch :size="15" class="text-text-muted" />
            <input
              ref="jumpInput"
              type="search"
              autocomplete="off"
              spellcheck="false"
              maxlength="100"
              class="h-full w-full bg-transparent outline-none placeholder:text-text-muted"
              :placeholder="'Search students, batches, programs...'"
              :value="jumpQuery"
              @input="onJumpQueryInput"
              @focus="openJump"
              @keydown.enter.prevent="jumpItems[0] && jumpTo(jumpItems[0].path)"
            />
            <kbd
              :class="[
                'hidden items-center gap-0.5 rounded border px-1 py-0.5 text-2xs sm:inline-flex',
                isDeveloper ? 'border-[var(--border)] text-[var(--text-soft)]' : 'border bg-surface text-text-soft',
              ]"
              ><IconCommand :size="10" /> K</kbd>
          </div>

          <!-- Inline dropdown for search results -->
          <div
            v-if="jumpOpen"
            :class="[
              'absolute left-0 right-0 top-full z-[60] mt-1 overflow-hidden rounded-lg border shadow-xl',
              isDeveloper ? 'border-[var(--border)] bg-[var(--surface)]' : 'border bg-surface',
            ]"
          >
            <ul class="max-h-72 overflow-y-auto p-1" role="listbox">
              <li v-if="jumpItems.length === 0" class="px-3 py-4 text-center text-xs text-text-muted">
                {{ t("shell.noJumpResults") }}
              </li>
              <li v-for="item in jumpItems" :key="item.path">
                <button
                  type="button"
                  role="option"
                  class="flex w-full items-center gap-2 rounded-md px-2.5 py-2 text-left text-sm hover:bg-surface-muted"
                  @click="jumpTo(item.path)"
                >
                  <component :is="item.icon" :size="15" class="shrink-0 text-text-muted" />
                  <span class="min-w-0 flex-1 truncate">{{ item.label }}</span>
                  <span class="truncate text-2xs text-text-soft">{{ item.path }}</span>
                </button>
              </li>
            </ul>
          </div>
        </div>

        <div class="flex-1" />

        <!-- Flare theme toggle -->
        <button
          v-if="!isDeveloper"
          id="btn-toggle-flare-theme"
          type="button"
          class="rounded-md p-2 hover:bg-surface-muted transition-colors"
          :title="isFlare ? 'Switch to Default theme' : 'Switch to Flare theme'"
          :aria-label="isFlare ? 'Switch to Default theme' : 'Switch to Flare theme'"
          @click="toggleFlare"
        >
          <IconPalette
            :size="18"
            :class="isFlare ? 'text-[#FF6115]' : 'text-text-muted'"
            :stroke-width="isFlare ? 2.5 : 1.8"
          />
        </button>

        <button
          type="button"
          class="rounded-md p-2"
          :class="isDeveloper ? 'hover:bg-[var(--surface-muted)]' : 'hover:bg-surface-muted'"
          @click="toggleTheme"
        >
          <IconSun v-if="dark" :size="18" /><IconMoon v-else :size="18" />
        </button>

        <div class="relative z-50">
          <button
            type="button"
            :class="[
              'relative rounded-md p-2',
              isDeveloper ? 'hover:bg-[var(--surface-muted)] text-[var(--text-muted)]' : 'hover:bg-surface-muted',
            ]"
            :aria-label="t('shell.notifications')"
            :aria-expanded="notifications"
            @click="
              notifications = !notifications;
              profile = false;
            "
          >
            <IconBell :size="18" />
            <!-- Staff badge -->
            <span
              v-if="staffNotifyEnabled && unreadCount > 0"
              class="absolute right-1 top-1 grid h-4 min-w-4 place-items-center rounded-full bg-danger px-1 text-2xs text-white"
            >{{ unreadCount > 9 ? "9+" : unreadCount }}</span>
            <!-- Student badge -->
            <span
              v-if="isStudent && studentUnreadCount > 0"
              class="absolute right-1 top-1 grid h-4 min-w-4 place-items-center rounded-full bg-danger px-1 text-2xs text-white"
            >{{ studentUnreadCount > 9 ? "9+" : studentUnreadCount }}</span>
          </button>

          <div
            v-if="notifications"
            :class="[
              'absolute right-0 z-50 mt-1 w-80 rounded-lg border shadow-xl',
              isDeveloper ? 'border-[var(--border)] bg-[var(--surface)]' : 'border bg-surface',
            ]"
          >
            <!-- Header row -->
            <div
              :class="[
                'flex h-10 items-center justify-between border-b px-3',
                isDeveloper ? 'border-[var(--border)]' : '',
              ]"
            >
              <p :class="['text-sm font-semibold', isDeveloper ? 'text-[var(--text)]' : '']">
                {{ t("shell.notifications") }}
              </p>
              <!-- Mark all read: staff -->
              <button
                v-if="staffNotifyEnabled && unreadCount > 0"
                :class="['text-xs', isDeveloper ? 'text-white' : 'text-primary']"
                @click="markAllStaffRead"
              >
                {{ t("shell.markAllRead") }}
              </button>
              <!-- Mark all read: student -->
              <button
                v-if="isStudent && studentUnreadCount > 0"
                class="text-xs text-primary"
                @click="markAllStudentRead.mutate()"
              >
                {{ t("shell.markAllRead") }}
              </button>
            </div>

            <!-- Staff notifications -->
            <div
              v-if="staffNotifyEnabled"
              :class="[
                'max-h-72 space-y-3 overflow-y-auto p-3 text-xs',
                isDeveloper ? 'text-[var(--text-muted)]' : '',
              ]"
            >
              <p v-if="staffNotificationsQuery.isLoading.value" class="text-text-muted">Loading…</p>
              <p v-else-if="staffItems.length === 0" class="py-4 text-center text-text-muted">No notifications yet.</p>
              <div v-for="item in staffItems" :key="item.id" :class="item.read ? 'opacity-60' : ''">
                <p>
                  <b>{{ item.title }}</b><br />
                  <span :class="isDeveloper ? 'text-[var(--text-soft)]' : 'text-text-muted'">{{ item.body }}</span>
                </p>
                <p
                  v-if="item.time"
                  :class="['mt-0.5 text-2xs', isDeveloper ? 'text-[var(--text-soft)]' : 'text-text-soft']"
                >
                  {{ item.time }}
                </p>
              </div>
            </div>

            <!-- Student notifications -->
            <div v-else-if="isStudent" class="max-h-72 space-y-3 overflow-y-auto p-3 text-xs">
              <p v-if="studentNotifsQuery.isLoading.value" class="text-text-muted">Loading…</p>
              <p v-else-if="studentNotifItems.length === 0" class="py-4 text-center text-text-muted">
                No notifications yet.
              </p>
              <div
                v-for="item in studentNotifItems"
                :key="item.id"
                :class="['rounded-lg border p-2.5 transition', item.read ? 'opacity-60' : 'border-primary/20 bg-primary/5']"
              >
                <p class="font-semibold text-text">{{ item.title }}</p>
                <p class="mt-0.5 text-text-muted">{{ item.body }}</p>
                <p v-if="item.created_at" class="mt-1 text-2xs text-text-soft">
                  {{ new Date(item.created_at).toLocaleDateString("en-PH", { month: "short", day: "numeric" }) }}
                </p>
              </div>
            </div>

            <!-- Fallback empty for other roles -->
            <div v-else class="p-4 text-center text-xs text-text-muted">
              No notifications available.
            </div>
          </div>
        </div>


        <div class="relative z-50">
          <button
            type="button"
            :class="[
              'flex items-center gap-2 rounded-md py-1 pl-1 pr-2',
              isDeveloper ? 'hover:bg-[var(--surface-muted)]' : 'hover:bg-surface-muted',
            ]"
            :aria-expanded="profile"
            :aria-haspopup="true"
            @click="
              profile = !profile;
              notifications = false;
            "
          >
            <DiceBearAvatar
              :seed="user?.email || (isStudent ? 'student@tcc.edu.ph' : 'admin@unifast.gov.ph')"
              :alt="user?.name || (isStudent ? 'Student' : t('shell.systemDeveloper'))"
              :size="28"
            />
            <span class="hidden text-left leading-tight sm:block"
              ><span :class="['block text-xs font-medium', isDeveloper ? 'text-[var(--text)]' : '']">{{
                user?.name ?? (isStudent ? "Student" : t("shell.systemDeveloper"))
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
              'absolute right-0 z-[60] mt-1 w-56 rounded-lg border p-1 shadow-xl',
              isDeveloper ? 'border-[var(--border)] bg-[var(--surface)]' : 'border bg-surface',
            ]"
            role="menu"
          >
            <div :class="['border-b px-2.5 py-2', isDeveloper ? 'border-[var(--border)]' : '']">
              <p :class="['text-sm font-medium', isDeveloper ? 'text-[var(--text)]' : '']">
                {{ user?.name ?? (isStudent ? "Student" : t("shell.systemDeveloper")) }}
              </p>
              <p :class="['text-xs', isDeveloper ? 'text-[var(--text-muted)]' : 'text-text-muted']">
                {{ user?.email ?? (isStudent ? "student@tcc.edu.ph" : "admin@unifast.gov.ph") }}
              </p>
            </div>
            <button
              type="button"
              role="menuitem"
              :class="[
                'flex w-full items-center gap-2 rounded px-2.5 py-1.5 text-sm',
                isDeveloper ? 'text-[var(--text)] hover:bg-[var(--surface-muted)]' : 'hover:bg-surface-muted',
              ]"
              @click="goToProfile"
            >
              <IconUserCircle :size="15" /> {{ t("common.profile") }}
            </button>
            <div :class="['my-1 flex justify-start border-y px-2.5 py-2', isDeveloper ? 'border-[var(--border)]' : 'border-surface-muted']">
              <LanguageSwitcher :dark="isDeveloper" class="w-full" />
            </div>
            <button
              type="button"
              role="menuitem"
              :disabled="signingOut"
              :class="[
                'flex w-full items-center gap-2 rounded px-2.5 py-1.5 text-sm text-danger disabled:opacity-50',
                isDeveloper ? 'hover:bg-[var(--surface-muted)]' : 'hover:bg-surface-muted',
              ]"
              @click.stop="signOut"
            >
              <IconLogout :size="15" />
              {{ signingOut ? t("common.signingOut") : t("common.signOut") }}
            </button>
          </div>
        </div>
      </header>

      <!-- Outside-click catcher: below header menus (z-50), above page content. -->
      <div
        v-if="profile || notifications || jumpOpen"
        class="fixed inset-0 z-[45]"
        aria-hidden="true"
        @click="closeMenus"
      />

      <main :class="['relative z-0 mx-auto w-full max-w-[1400px] p-4 sm:p-6', isStudent ? 'pb-24 lg:pb-6' : '']" data-cloud="page-content">
        <AppBreadcrumbs v-if="!isStudent && !isDeveloper" />
        <RouterView v-slot="{ Component }"
          ><Transition name="page" mode="out-in"
            ><component :is="Component" :key="route.fullPath" class="page-enter" /></Transition
        ></RouterView>
      </main>

      <!-- Student Mobile Bottom Navbar -->
      <nav
        v-if="isStudent"
        class="fixed bottom-0 left-0 right-0 z-40 flex h-16 items-center justify-around border-t bg-surface/95 backdrop-blur-md px-1 shadow-lg lg:hidden"
      >
        <button
          v-for="item in studentMobileNavItems"
          :key="item.path"
          type="button"
          class="flex flex-1 flex-col items-center justify-center py-1 text-2xs transition-colors"
          :class="isActive(item.path) ? 'font-semibold text-primary' : 'text-text-muted hover:text-text'"
          @click="go(item.path)"
        >
          <component
            :is="item.icon"
            :size="20"
            :class="isActive(item.path) ? 'text-primary' : 'text-text-muted'"
          />
          <span class="mt-1 truncate max-w-[68px] text-center leading-tight">{{ t(item.labelKey) }}</span>
        </button>
      </nav>
    </div>

    <!-- Logout toast overlay -->
    <Teleport to="body">
      <Transition name="fade">
        <div
          v-if="logoutToast"
          class="fixed inset-0 z-[200] flex items-center justify-center bg-black/50 backdrop-blur-sm"
        >
          <div class="flex flex-col items-center gap-4 rounded-2xl bg-white px-10 py-8 shadow-2xl">
            <svg
              class="h-8 w-8 animate-spin text-primary"
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
            >
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            <p class="text-sm font-semibold text-text">{{ t("common.signingOut") }}</p>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
