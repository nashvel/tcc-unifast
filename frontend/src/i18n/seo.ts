import type { Router } from "vue-router";
import { watch } from "vue";
import { i18n } from "@/i18n";
import { authSession } from "@/auth/session";

const routeSeoKeys: Record<string, { title: string; description: string }> = {
  "/login": { title: "seo.login.title", description: "seo.login.description" },
  "/forgot-password": { title: "seo.forgot.title", description: "seo.forgot.description" },
  "/activate": { title: "seo.activate.title", description: "seo.activate.description" },
  "/app": { title: "seo.adminDashboard.title", description: "seo.adminDashboard.description" },
  "/student": { title: "seo.studentDashboard.title", description: "seo.studentDashboard.description" },
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

export function updateSeo(path: string): void {
  if (typeof document === "undefined") return;

  const { t, locale } = i18n.global;
  const seo = bestSeoMatch(path);
  let title = t(seo.title);
  const description = t(seo.description);

  if (path.startsWith("/app") && authSession.user?.role === "developer") {
    title = title.replace("Admin Dashboard", "Developer Dashboard").replace("Admin", "Developer");
  }

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
