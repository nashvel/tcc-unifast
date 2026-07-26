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
import logo from "@/assets/system-logo.png";
import { withLang } from "@/i18n/routeLang";

const route = useRoute();
const router = useRouter();
const { t } = useI18n();

type NavItem = {
  label: string;
  path?: string;
  icon?: typeof IconDashboard;
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
      { label: "Terms & Conditions", path: "/app/developer/terms", icon: IconShield },
      { label: "FAQs", path: "/app/developer/faqs", icon: IconCode },
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
  <aside class="flex h-full w-56 shrink-0 flex-col" style="background-color: #0f0f0f; border-right: 1px solid #262626;">
    <!-- Logo -->
    <div class="flex h-14 shrink-0 items-center gap-2.5 px-4" style="border-bottom: 1px solid #262626;">
      <img :src="logo" class="size-7 object-contain" alt="TCC UniFAST" draggable="false" />
      <span class="text-sm font-semibold" style="color: #fafafa;">TCC UniFAST</span>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto px-2 py-2">
      <div v-for="(section, sIdx) in sections" :key="sIdx" class="mb-1">
        <!-- Section with title -->
        <template v-if="section.title">
          <button
            class="flex w-full items-center justify-between px-2.5 py-2 text-2xs font-medium uppercase tracking-wider"
            style="color: #737373;"
            @click="toggleSection(section.title!)"
          >
            {{ section.title }}
            <IconChevronDown
              :size="14"
              :class="['transition-transform', expanded[section.title!] ? 'rotate-0' : '-rotate-90']"
              style="color: #525252;"
            />
          </button>
          <ul v-show="expanded[section.title!]" class="space-y-0.5">
            <li v-for="item in section.items" :key="item.path">
              <button
                class="flex h-8 w-full items-center gap-2.5 rounded-md px-2.5 text-left text-sm transition-colors"
                :style="isActive(item.path!) ? 'background-color: #1a1a1a; color: #fafafa;' : 'color: #d4d4d4;'"
                @click="go(item.path!)"
              >
                <component
                  :is="item.icon"
                  :size="15"
                  :style="isActive(item.path!) ? 'color: #fafafa;' : 'color: #737373;'"
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
                :style="isActive(item.path!) ? 'background-color: #1a1a1a; color: #fafafa;' : 'color: #d4d4d4;'"
                @click="go(item.path!)"
              >
                <component
                  :is="item.icon"
                  :size="15"
                  :style="isActive(item.path!) ? 'color: #fafafa;' : 'color: #737373;'"
                />
                <span class="truncate">{{ item.label }}</span>
              </button>
            </li>
          </ul>
        </template>
      </div>
    </nav>

    <!-- Bottom -->
    <div class="px-2 py-2" style="border-top: 1px solid #262626;">
      <ul class="space-y-0.5">
        <li>
          <button
            class="flex h-8 w-full items-center gap-2.5 rounded-md px-2.5 text-left text-sm"
            style="color: #d4d4d4;"
          >
            <IconSettings :size="15" style="color: #737373;" />
            <span class="truncate">Integration</span>
          </button>
        </li>
        <li>
          <button
            class="flex h-8 w-full items-center gap-2.5 rounded-md px-2.5 text-left text-sm"
            style="color: #d4d4d4;"
            @click="$emit('logout')"
          >
            <IconLogout :size="15" style="color: #737373;" />
            <span class="truncate">Logout</span>
          </button>
        </li>
      </ul>
    </div>
  </aside>
</template>
