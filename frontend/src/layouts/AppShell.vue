<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
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
import DiceBearAvatar from "@/components/ui/DiceBearAvatar.vue";
import OfflineBanner from "@/components/ui/OfflineBanner.vue";
import { authSession } from "@/auth/session";
import { logout } from "@/api/auth";
import { studentVerification } from "@/auth/studentVerification";
import { ensureEcho } from "@/composables/useEcho";
import { withLang } from "@/i18n/routeLang";

ensureEcho();

const route = useRoute();
const router = useRouter();
const { t } = useI18n();
const mobile = ref(false);
const profile = ref(false);
const notifications = ref(false);
const isStudent = computed(() => route.path.startsWith("/student"));
const isDeveloper = computed(() => authSession.user?.role === "developer");
const role = computed(() => authSession.user?.role ?? "student");
const user = computed(() => authSession.user);

const dark = ref(
  typeof localStorage !== "undefined"
    ? localStorage.getItem("theme") === "dark" || isDeveloper.value
    : isDeveloper.value,
);

const sections = computed(() => {
  if (isStudent.value)
    return studentVerification.verified ? studentNavigation : lockedStudentNavigation;
  if (role.value === "developer") return developerNavigation;
  if (role.value === "admin") return staffNavigation;
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
watch(isDeveloper, (val) => {
  if (val && typeof document !== "undefined") {
    dark.value = true;
    document.documentElement.classList.add("dev-dark");
    document.documentElement.classList.remove("dark");
    localStorage.setItem("theme", "dark");
  } else if (typeof document !== "undefined") {
    document.documentElement.classList.remove("dev-dark");
  }
}, { immediate: true });

if (isDeveloper.value && typeof document !== "undefined") {
  document.documentElement.classList.add("dev-dark");
  document.documentElement.classList.remove("dark");
} else if (dark.value && typeof document !== "undefined") {
  document.documentElement.classList.add("dark");
}
</script>

<template>
  <div :class="['min-h-screen', isDeveloper ? 'bg-[#0a0e17]' : 'bg-bg']">
    <OfflineBanner />
    <div v-if="mobile" class="fixed inset-0 z-40 bg-black/30 lg:hidden" @click="mobile = false" />

    <!-- Developer Sidebar: BeastInsights-style -->
    <aside
      v-if="isDeveloper"
      :class="[
        'fixed inset-y-0 left-0 z-50 flex w-56 shrink-0 flex-col border-r border-[var(--border)] bg-[var(--sidebar-bg)] transition-transform lg:translate-x-0',
        mobile ? 'translate-x-0' : '-translate-x-full',
      ]"
    >
      <div class="flex h-14 shrink-0 items-center gap-2.5 px-4">
        <div class="flex size-7 items-center justify-center rounded-lg bg-[var(--primary)]">
          <span class="text-xs font-bold text-white">T</span>
        </div>
        <span class="text-sm font-semibold text-[var(--sidebar-text)]">TCC UniFAST</span>
      </div>

      <nav class="flex-1 overflow-y-auto py-2 px-2">
        <div v-for="(section, sectionIndex) in sections" :key="sectionIndex" class="mb-4">
          <p
            v-if="section.labelKey"
            class="mb-1.5 px-2 text-2xs font-medium uppercase tracking-wider text-[var(--sidebar-text-muted)]"
          >
            {{ t(section.labelKey) }}
          </p>
          <ul class="space-y-0.5">
            <li v-for="item in section.items" :key="item.path">
              <button
                class="flex h-8 w-full items-center gap-2.5 rounded-md px-2.5 text-left text-sm transition-colors"
                :class="
                  isActive(item.path)
                    ? 'bg-[var(--surface-muted)] font-medium text-[var(--sidebar-text)]'
                    : 'text-[var(--sidebar-text-muted)] hover:bg-[var(--surface-muted)] hover:text-[var(--sidebar-text)]'
                "
                @click="go(item.path)"
              >
                <component
                  :is="item.icon"
                  :size="15"
                  :class="isActive(item.path) ? 'text-[var(--primary)]' : 'text-[var(--sidebar-text-muted)]'"
                />
                <span class="truncate">{{ t(item.labelKey) }}</span>
              </button>
            </li>
          </ul>
        </div>
      </nav>
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
          isDeveloper
            ? 'border-[var(--border)] bg-[var(--bg)]'
            : 'bg-surface',
        ]"
      >
        <button
          :class="['rounded-md p-1.5 lg:hidden', isDeveloper ? 'hover:bg-[var(--surface-muted)] text-[var(--text-muted)]' : 'hover:bg-surface-muted']"
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
              isDeveloper
                ? 'border-[var(--border)] text-[var(--text-soft)]'
                : 'border bg-surface text-text-soft',
            ]"
            ><IconCommand :size="10" /> K</kbd
          >
        </button>

        <div class="flex-1" />

        <LanguageSwitcher />

        <!-- Theme toggle hidden for developers (forced dark) -->
        <button
          v-if="!isDeveloper"
          class="rounded-md p-2 hover:bg-surface-muted"
          @click="toggleTheme"
        >
          <IconSun v-if="dark" :size="18" /><IconMoon v-else :size="18" />
        </button>

        <div class="relative">
          <button
            :class="['relative rounded-md p-2', isDeveloper ? 'hover:bg-[var(--surface-muted)] text-[var(--text-muted)]' : 'hover:bg-surface-muted']"
            :aria-label="t('shell.notifications')"
            @click="notifications = !notifications; profile = false;"
          >
            <IconBell :size="18" /><span
              class="absolute right-1 top-1 grid h-4 min-w-4 place-items-center rounded-full bg-danger px-1 text-2xs text-white"
              >3</span
            >
          </button>
          <div
            v-if="notifications"
            :class="[
              'absolute right-0 mt-1 w-80 rounded-lg border shadow-xl',
              isDeveloper
                ? 'border-[var(--border)] bg-[var(--surface)]'
                : 'border bg-surface',
            ]"
          >
            <div :class="['flex h-10 items-center justify-between border-b px-3', isDeveloper ? 'border-[var(--border)]' : '']">
              <p :class="['text-sm font-semibold', isDeveloper ? 'text-[var(--text)]' : '']">{{ t("shell.notifications") }}</p>
              <button :class="['text-xs', isDeveloper ? 'text-[var(--primary)]' : 'text-primary']">{{ t("shell.markAllRead") }}</button>
            </div>
            <div :class="['space-y-3 p-3 text-xs', isDeveloper ? 'text-[var(--text-muted)]' : '']">
              <p>
                <b>{{ t("shell.documentsValidated") }}</b><br /><span :class="isDeveloper ? 'text-[var(--text-soft)]' : 'text-text-muted'">{{ t("shell.documentsReady") }}</span>
              </p>
              <p>
                <b>{{ t("shell.batchClosingSoon") }}</b><br /><span :class="isDeveloper ? 'text-[var(--text-soft)]' : 'text-text-muted'">{{ t("shell.batchClosingDetail") }}</span>
              </p>
            </div>
          </div>
        </div>

        <div class="relative">
          <button
            :class="['flex items-center gap-2 rounded-md py-1 pl-1 pr-2', isDeveloper ? 'hover:bg-[var(--surface-muted)]' : 'hover:bg-surface-muted']"
            @click="profile = !profile; notifications = false;"
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
              ><span :class="['block text-2xs capitalize', isDeveloper ? 'text-[var(--text-muted)]' : 'text-text-muted']">{{
                isStudent ? t("shell.student") : role
              }}</span></span
            ><IconChevronDown :size="14" :class="isDeveloper ? 'text-[var(--text-soft)]' : 'text-text-muted'" />
          </button>
          <div
            v-if="profile"
            :class="[
              'absolute right-0 mt-1 w-56 rounded-lg border p-1 shadow-xl',
              isDeveloper
                ? 'border-[var(--border)] bg-[var(--surface)]'
                : 'border bg-surface',
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
              :class="['flex w-full items-center gap-2 rounded px-2.5 py-1.5 text-sm', isDeveloper ? 'text-[var(--text)] hover:bg-[var(--surface-muted)]' : 'hover:bg-surface-muted']"
            >
              <IconUserCircle :size="15" /> {{ t("common.profile") }}</button
            ><button
              :class="['flex w-full items-center gap-2 rounded px-2.5 py-1.5 text-sm text-danger', isDeveloper ? 'hover:bg-[var(--surface-muted)]' : 'hover:bg-surface-muted']"
              @click="signOut"
            >
              <IconLogout :size="15" /> {{ t("common.signOut") }}
            </button>
          </div>
        </div>
      </header>

      <main class="mx-auto w-full max-w-[1400px] p-4 sm:p-6" data-tour="page-content">
        <AppBreadcrumbs v-if="!isStudent && !isDeveloper" />
        <RouterView v-slot="{ Component }"
          ><Transition name="page" mode="out-in"
            ><component :is="Component" :key="route.fullPath" class="page-enter" /></Transition
        ></RouterView>
      </main>
    </div>
  </div>
</template>
