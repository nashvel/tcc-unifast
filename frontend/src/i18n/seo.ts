import type { Router } from "vue-router";
import { watch } from "vue";
import { i18n } from "@/i18n";
import { authSession } from "@/auth/session";

const APP_BRAND = "UniFAST TES";

const routeSeoKeys: Record<string, { title: string; description: string }> = {
  "/login": { title: "seo.login.title", description: "seo.login.description" },
  "/forgot-password": { title: "seo.forgot.title", description: "seo.forgot.description" },
  "/activate": { title: "seo.activate.title", description: "seo.activate.description" },
  "/app": { title: "seo.adminDashboard.title", description: "seo.adminDashboard.description" },
  "/student": { title: "seo.studentDashboard.title", description: "seo.studentDashboard.description" },
};

/** Path segment → i18n key for staff/admin page titles under `/app`. */
const appSegmentTitleKeys: Record<string, string> = {
  announcements: "nav.announcements",
  reports: "nav.reports",
  billing: "nav.billing",
  distribution: "nav.distributionReport",
  support: "nav.support",
  audit: "nav.auditLog",
  security: "nav.security",
  users: "nav.users",
  settings: "common.settings",
  appearance: "nav.appearance",
  "style-guide": "nav.styleGuide",
  masterlist: "nav.masterlist",
  onboarding: "nav.onboardingCenter",
  grantees: "nav.grantees",
  batches: "nav.batches",
  programs: "nav.programs",
  academic: "nav.academicRecords",
  documents: "nav.documents",
  "face-reviews": "nav.faceReviews",
  eligibility: "nav.eligibility",
  files: "nav.fileManager",
  memory: "nav.securityMemory",
  permissions: "nav.permissions",
  package: "nav.package",
  playground: "developer.sidebar.playground",
  changelogs: "developer.sidebar.changelogs",
  rbac: "developer.sidebar.rbac",
  "api-docs": "developer.sidebar.apiDocs",
  "flow-chart": "developer.sidebar.flowCharts",
  database: "developer.sidebar.database",
  terms: "developer.sidebar.terms",
  faqs: "developer.sidebar.faqs",
  collaborators: "developer.sidebar.collaborators",
};

function ensureMeta(selector: string, attr: "name" | "property", value: string): HTMLMetaElement {
  let element = document.head.querySelector<HTMLMetaElement>(selector);
  if (!element) {
    element = document.createElement("meta");
    element.setAttribute(attr, value);
    document.head.appendChild(element);
  }
  return element;
}

function bestSeoMatch(path: string) {
  return routeSeoKeys[path] ?? routeSeoKeys[`/${path.split("/").filter(Boolean)[0]}`] ?? routeSeoKeys["/app"];
}

function isIdentifier(segment: string) {
  return /^\d+$/.test(segment) || /^[0-9a-f-]{8,}$/i.test(segment);
}

function titleCase(segment: string) {
  return segment.replaceAll("-", " ").replace(/\b\w/g, (character) => character.toUpperCase());
}

function withBrand(pageLabel: string) {
  return `${pageLabel} - ${APP_BRAND}`;
}

/** Resolve a human page label from the deepest meaningful `/app` segment. */
function resolveAppPageLabel(path: string, t: (key: string) => string): string | null {
  const segments = path.split("/").filter(Boolean);
  if (segments[0] !== "app" || segments.length <= 1) return null;

  for (let i = segments.length - 1; i >= 1; i -= 1) {
    const segment = segments[i];
    if (isIdentifier(segment) || segment === "developer") continue;
    const key = appSegmentTitleKeys[segment];
    return key ? t(key) : titleCase(segment);
  }

  return null;
}

function resolveAppTitle(path: string, t: (key: string) => string): string {
  const role = authSession.user?.role;
  const isDeveloperRoute = path.startsWith("/app/developer");
  const pageLabel = resolveAppPageLabel(path, t);

  // Developer branding only on developer area (and developer home).
  if (role === "developer" && (isDeveloperRoute || !pageLabel)) {
    return pageLabel ? withBrand(pageLabel) : t("seo.developerDashboard.title");
  }

  // Staff / admin (and any other /app user): page-specific title, never developer branding.
  if (pageLabel) return withBrand(pageLabel);
  return t("seo.adminDashboard.title");
}

export function updateSeo(path: string): void {
  if (typeof document === "undefined") return;

  const { t, locale } = i18n.global;
  const seo = bestSeoMatch(path);
  const description = t(seo.description);
  const title = path.startsWith("/app") ? resolveAppTitle(path, t) : t(seo.title);

  document.documentElement.lang = locale.value;
  document.title = title;
  ensureMeta('meta[name="description"]', "name", "description").content = description;
  ensureMeta('meta[property="og:title"]', "property", "og:title").content = title;
  ensureMeta('meta[property="og:description"]', "property", "og:description").content = description;
}

export function installSeoUpdates(router: Router): void {
  router.afterEach((to) => updateSeo(to.path));
  watch(i18n.global.locale, () => updateSeo(router.currentRoute.value.path));
}
