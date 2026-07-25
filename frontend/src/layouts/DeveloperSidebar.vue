<script setup lang="ts">
import { computed, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import {
  IconChevronDown,
  IconDashboard,
  IconLogout,
  IconCode,
  IconShield,
  IconTerminal,
  IconUsers,
  IconLifebuoy,
  IconGitBranch,
  IconDatabase,
  IconSettings,
  IconUserCog,
  IconHistory,
} from "@tabler/icons-vue";
import { withLang } from "@/i18n/routeLang";

const route = useRoute();
const router = useRouter();
const { t } = useI18n();

type NavItem = {
  label: string;
  path?: string;
  icon?: typeof IconDashboard;
  children?: NavItem[];
};

const sections = ref<{ title?: string; items: NavItem[] }[]>([
  {
    items: [{ label: "Dashboard", path: "/app", icon: IconDashboard }],
  },
  {
    title: "System",
    items: [
      { label: "RBAC & Permissions", path: "/app/developer/rbac", icon: IconShield },
      { label: "API Documentation", path: "/app/developer/api-docs", icon: IconCode },
      { label: "System Flow Charts", path: "/app/developer/flow-chart", icon: IconGitBranch },
      { label: "Database", path: "/app/developer/database", icon: IconDatabase },
    ],
  },
  {
    title: "Operations",
    items: [
      { label: "Support Tickets", path: "/app/developer/support", icon: IconLifebuoy },
      { label: "Developer Audit", path: "/app/developer/audit", icon: IconHistory },
      { label: "Collaborators", path: "/app/developer/collaborators", icon: IconUsers },
    ],
  },
  {
    title: "Administration",
    items: [
      { label: "Users & Roles", path: "/app/developer/users", icon: IconUserCog },
      { label: "Settings", path: "/app/developer/settings", icon: IconSettings },
    ],
  },
]);

const expanded = ref<Record<string, boolean>>({
  System: true,
  Operations: true,
  Administration: true,
});

function toggleSection(title: string) {
  expanded.value[title] = !expanded.value[title];
}

function isActive(path: string) {
  return path === "/app"
    ? route.path === path
    : route.path === path || route.path.startsWith(`${path}/`);
}

function go(path: string) {
  router.push(withLang(path, route.query.lang));
}
</script>

<template>
  <aside class="flex h-full w-56 shrink-0 flex-col bg-[var(--sidebar-bg)]">
    <!-- Logo -->
    <div class="flex h-14 shrink-0 items-center gap-2.5 px-4">
      <div class="flex size-7 items-center justify-center rounded-lg bg-[var(--primary)]">
        <span class="text-xs font-bold text-white">T</span>
      </div>
      <span class="text-sm font-semibold text-[var(--sidebar-text)]">TCC UniFAST</span>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto px-2 py-2">
      <div v-for="(section, sIdx) in sections" :key="sIdx" class="mb-1">
        <!-- Section with title -->
        <template v-if="section.title">
          <button
            class="flex w-full items-center justify-between px-2.5 py-2 text-2xs font-medium uppercase tracking-wider text-[var(--sidebar-text-muted)] hover:text-[var(--sidebar-text)]"
            @click="toggleSection(section.title!)"
          >
            {{ section.title }}
            <IconChevronDown
              :size="14"
              :class="[
                'transition-transform',
                expanded[section.title!] ? 'rotate-0' : '-rotate-90',
              ]"
            />
          </button>
          <ul v-show="expanded[section.title!]" class="space-y-0.5">
            <li v-for="item in section.items" :key="item.path">
              <button
                class="flex h-8 w-full items-center gap-2.5 rounded-md px-2.5 text-left text-sm transition-colors"
                :class="
                  isActive(item.path!)
                    ? 'bg-[var(--surface-muted)] font-medium text-[var(--sidebar-text)]'
                    : 'text-[var(--sidebar-text-muted)] hover:bg-[var(--surface-muted)] hover:text-[var(--sidebar-text)]'
                "
                @click="go(item.path!)"
              >
                <component
                  :is="item.icon"
                  :size="15"
                  :class="isActive(item.path!) ? 'text-[var(--primary)]' : 'text-[var(--sidebar-text-muted)]'"
                />
                <span class="truncate">{{ item.label }}</span>
              </button>
            </li>
          </ul>
        </template>

        <!-- Section without title (flat items) -->
        <template v-else>
          <ul class="space-y-0.5">
            <li v-for="item in section.items" :key="item.path">
              <button
                class="flex h-8 w-full items-center gap-2.5 rounded-md px-2.5 text-left text-sm transition-colors"
                :class="
                  isActive(item.path!)
                    ? 'bg-[var(--surface-muted)] font-medium text-[var(--sidebar-text)]'
                    : 'text-[var(--sidebar-text-muted)] hover:bg-[var(--surface-muted)] hover:text-[var(--sidebar-text)]'
                "
                @click="go(item.path!)"
              >
                <component
                  :is="item.icon"
                  :size="15"
                  :class="isActive(item.path!) ? 'text-[var(--primary)]' : 'text-[var(--sidebar-text-muted)]'"
                />
                <span class="truncate">{{ item.label }}</span>
              </button>
            </li>
          </ul>
        </template>
      </div>
    </nav>

    <!-- Bottom -->
    <div class="border-t border-[var(--border)] px-2 py-2">
      <ul class="space-y-0.5">
        <li>
          <button
            class="flex h-8 w-full items-center gap-2.5 rounded-md px-2.5 text-left text-sm text-[var(--sidebar-text-muted)] hover:bg-[var(--surface-muted)] hover:text-[var(--sidebar-text)]"
          >
            <IconSettings :size="15" class="text-[var(--sidebar-text-muted)]" />
            <span class="truncate">Integration</span>
          </button>
        </li>
        <li>
          <button
            class="flex h-8 w-full items-center gap-2.5 rounded-md px-2.5 text-left text-sm text-[var(--sidebar-text-muted)] hover:bg-[var(--surface-muted)] hover:text-[var(--sidebar-text)]"
            @click="$emit('logout')"
          >
            <IconLogout :size="15" class="text-[var(--sidebar-text-muted)]" />
            <span class="truncate">Logout</span>
          </button>
        </li>
      </ul>
    </div>
  </aside>
</template>
