<script setup lang="ts">
import { computed } from "vue";
import { useRoute } from "vue-router";
import { useI18n } from "vue-i18n";
import { ChevronRight, Home } from "lucide-vue-next";
import { withLang } from "@/i18n/routeLang";

const route = useRoute();
const { t } = useI18n();

const labelKeys: Record<string, string> = {
  app: "common.dashboard",
  grantees: "nav.grantees",
  masterlist: "nav.masterlist",
  onboarding: "nav.onboardingCenter",
  eligibility: "nav.eligibility",
  documents: "nav.documents",
  batches: "nav.batches",
  announcements: "nav.announcements",
  academic: "nav.academicRecords",
  reports: "nav.reports",
  billing: "nav.billing",
  distribution: "nav.distributionReport",
  files: "nav.fileManager",
  support: "nav.support",
  users: "nav.users",
  audit: "nav.auditLog",
  security: "nav.security",
  appearance: "nav.appearance",
  settings: "common.settings",
  "style-guide": "nav.styleGuide",
  new: "nav.new",
  logs: "nav.logs",
  permissions: "nav.permissions",
  preview: "nav.preview",
  generate: "nav.generate",
  edit: "nav.edit",
};

const crumbs = computed(() => {
  const segments = route.path.split("/").filter(Boolean);
  return segments.map((segment, index) => {
    const isIdentifier = /^\d+$/.test(segment) || /^[0-9a-f-]{8,}$/i.test(segment);
    const fallback = segment
      .replaceAll("-", " ")
      .replace(/\b\w/g, (character) => character.toUpperCase());

    return {
      label: isIdentifier ? t("common.details") : t(labelKeys[segment] ?? "", fallback),
      href: `/${segments.slice(0, index + 1).join("/")}`,
    };
  });
});
</script>

<template>
  <nav
    v-if="crumbs.length"
    :aria-label="t('nav.breadcrumb')"
    class="mb-4 inline-flex max-w-full items-center overflow-hidden rounded-full border border-border/60 bg-surface/60 px-2.5 py-1 text-xs text-text-muted shadow-sm backdrop-blur-sm"
  >
    <ol class="flex min-w-0 items-center gap-0.5">
      <li class="flex shrink-0 items-center">
        <RouterLink
          :to="withLang(crumbs[0].href, route.query.lang)"
          :aria-label="t('nav.homeBreadcrumb', { label: crumbs[0].label })"
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
          :to="withLang(crumb.href, route.query.lang)"
          class="max-w-40 truncate rounded-full px-2 py-0.5 hover:bg-surface-muted hover:text-text"
        >
          {{ crumb.label }}
        </RouterLink>
      </li>
    </ol>
  </nav>
</template>
