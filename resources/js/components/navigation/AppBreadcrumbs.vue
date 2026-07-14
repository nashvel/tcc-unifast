<script setup lang="ts">
import { computed } from "vue";
import { useRoute } from "vue-router";
import { ChevronRight, Home } from "lucide-vue-next";

const route = useRoute();
const labels: Record<string, string> = {
  app: "Dashboard",
  grantees: "Grantees",
  masterlist: "Masterlist",
  eligibility: "Eligibility",
  documents: "Documents",
  batches: "Batches",
  announcements: "Announcements",
  academic: "Academic Records",
  reports: "Reports",
  files: "File Manager",
  support: "Support",
  users: "Users & Access",
  audit: "Audit Log",
  security: "Security",
  appearance: "Appearance",
  settings: "Settings",
  "style-guide": "Style Guide",
  new: "New",
  logs: "Logs",
  permissions: "Permissions",
  preview: "Preview",
  generate: "Generate",
  edit: "Edit",
};

const crumbs = computed(() => {
  const segments = route.path.split("/").filter(Boolean);
  return segments.map((segment, index) => {
    const isIdentifier = /^\d+$/.test(segment) || /^[0-9a-f-]{8,}$/i.test(segment);
    return {
      label: isIdentifier
        ? "Detail"
        : (labels[segment] ??
          segment.replaceAll("-", " ").replace(/\b\w/g, (character) => character.toUpperCase())),
      href: `/${segments.slice(0, index + 1).join("/")}`,
    };
  });
});
</script>

<template>
  <nav
    v-if="crumbs.length"
    aria-label="Breadcrumb"
    class="mb-4 inline-flex max-w-full items-center overflow-hidden rounded-full border border-border/60 bg-surface/60 px-2.5 py-1 text-xs text-text-muted shadow-sm backdrop-blur-sm"
  >
    <ol class="flex min-w-0 items-center gap-0.5">
      <li class="flex shrink-0 items-center">
        <RouterLink
          :to="crumbs[0].href"
          :aria-label="`${crumbs[0].label} (home)`"
          class="flex h-6 w-6 items-center justify-center rounded-full hover:bg-surface-muted hover:text-text"
        >
          <Home :size="13" />
        </RouterLink>
      </li>
      <li
        v-for="(crumb, index) in crumbs.slice(1)"
        :key="crumb.href"
        class="flex min-w-0 items-center gap-0.5"
      >
        <ChevronRight :size="12" class="mx-0.5 shrink-0 text-text-soft/60" />
        <span
          v-if="index === crumbs.length - 2"
          aria-current="page"
          class="max-w-60 truncate rounded-full bg-primary/10 px-2 py-0.5 font-medium text-primary"
        >
          {{ crumb.label }}
        </span>
        <RouterLink
          v-else
          :to="crumb.href"
          class="max-w-40 truncate rounded-full px-2 py-0.5 hover:bg-surface-muted hover:text-text"
        >
          {{ crumb.label }}
        </RouterLink>
      </li>
    </ol>
  </nav>
</template>
