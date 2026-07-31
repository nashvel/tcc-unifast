<script setup lang="ts">
import { computed } from "vue";
import { useRoute } from "vue-router";
import { useI18n } from "vue-i18n";
import { ChevronRight, Home } from "lucide-vue-next";
import { withLang } from "@/i18n/routeLang";
import { useBreadcrumbTail } from "@/composables/useBreadcrumbTail";

const route = useRoute();
const { t } = useI18n();
const { breadcrumbTailLabel } = useBreadcrumbTail();

const labelKeys: Record<string, string> = {
  app: "common.dashboard",
  grantees: "nav.grantees",
  masterlist: "nav.masterlist",
  onboarding: "nav.onboardingCenter",
  eligibility: "nav.eligibility",
  documents: "nav.documents",
  package: "nav.package",
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

function isIdentifier(segment: string) {
  return /^\d+$/.test(segment) || /^[0-9a-f-]{8,}$/i.test(segment);
}

function titleCase(segment: string) {
  return segment
    .replaceAll("-", " ")
    .replace(/\b\w/g, (character) => character.toUpperCase());
}

/**
 * Build crumbs from path segments, collapsing numeric/UUID params into the
 * preceding resource so package/:granteeId/:batchId does not become
 * "Details > Details".
 */
const crumbs = computed(() => {
  const segments = route.path.split("/").filter(Boolean);
  const items: { label: string; href: string }[] = [];
  const tail = breadcrumbTailLabel.value || route.meta.breadcrumbLabel || null;
  let index = 0;

  if (segments[0] === "app") {
    items.push({
      label: t(labelKeys.app),
      href: "/app",
    });
    index = 1;
  }

  while (index < segments.length) {
    const segment = segments[index];

    if (isIdentifier(segment)) {
      let end = index;
      while (end < segments.length && isIdentifier(segments[end])) end += 1;
      items.push({
        label: (typeof tail === "string" && tail) || t("common.details"),
        href: `/${segments.slice(0, end).join("/")}`,
      });
      index = end;
      continue;
    }

    let end = index + 1;
    while (end < segments.length && isIdentifier(segments[end])) end += 1;
    const isLast = end >= segments.length;
    const fallback = titleCase(segment);
    const key = labelKeys[segment];
    items.push({
      label:
        (isLast && typeof tail === "string" && tail) ||
        (key ? t(key) : fallback),
      href: `/${segments.slice(0, end).join("/")}`,
    });
    index = end;
  }

  return items;
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
