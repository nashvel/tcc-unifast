import { createApp, type App as VueApp } from "vue";
import {
  createRouter,
  createWebHistory,
  type Router,
  type RouteLocationNormalized,
  type RouteRecordRaw,
} from "vue-router";
import { VueQueryPlugin } from "@tanstack/vue-query";
import App from "./App.vue";
import { authSession, loadAuthUser } from "@/auth/session";
import { queryClient } from "@/lib/queryClient";
import { i18n } from "@/i18n";
import { installLanguageRouting, withLang } from "@/i18n/routeLang";
import { installSeoUpdates } from "@/i18n/seo";

const appChildren: RouteRecordRaw[] = [
  { path: "", component: () => import("@/modules/dashboard/Index.vue") },
  { path: "announcements", component: () => import("@/modules/announcements/Index.vue") },
  { path: "announcements/new", component: () => import("@/modules/announcements/Create.vue") },
  { path: "announcements/logs", component: () => import("@/modules/announcements/Logs.vue") },
  { path: "announcements/:id/edit", component: () => import("@/modules/announcements/Edit.vue") },
  { path: "reports", component: () => import("@/modules/reports/Index.vue") },
  { path: "reports/generate", component: () => import("@/modules/reports/Generate.vue") },
  { path: "reports/preview", component: () => import("@/modules/reports/Preview.vue") },
  { path: "billing", component: () => import("@/modules/billing/Index.vue") },
  { path: "distribution", component: () => import("@/modules/distribution/Index.vue") },
  { path: "support", component: () => import("@/modules/support/Index.vue") },
  { path: "support/new", component: () => import("@/modules/support/Create.vue") },
  { path: "support/:id", component: () => import("@/modules/support/Detail.vue") },
  { path: "audit", component: () => import("@/modules/audit/Index.vue") },
  { path: "security", component: () => import("@/modules/security/Index.vue") },
  { path: "security/memory", component: () => import("@/modules/security/Memory.vue") },
  { path: "users", component: () => import("@/modules/users/Index.vue") },
  { path: "users/permissions", component: () => import("@/modules/users/Permissions.vue") },
  { path: "settings", component: () => import("@/modules/settings/Index.vue") },
  { path: "appearance", component: () => import("@/modules/appearance/Index.vue") },
  { path: "style-guide", component: () => import("@/modules/style-guide/Index.vue") },
  { path: "masterlist", component: () => import("@/modules/masterlist/Index.vue") },
{ path: "onboarding", component: () => import("@/modules/onboarding/Index.vue") },
  // Developer routes
  { path: "developer/playground", component: () => import("@/modules/developer/Playground.vue") },
  { path: "developer/changelogs", component: () => import("@/modules/developer/Changelog.vue") },
  { path: "developer/rbac", component: () => import("@/modules/developer/Rbac.vue") },
  { path: "developer/api-docs", component: () => import("@/modules/developer/ApiDocs.vue") },
  { path: "developer/flow-chart", component: () => import("@/modules/developer/FlowChart.vue") },
  { path: "developer/database", component: () => import("@/modules/developer/Database.vue") },
  { path: "developer/terms", component: () => import("@/modules/developer/Terms.vue") },
  { path: "developer/faqs", component: () => import("@/modules/developer/Faqs.vue") },
  { path: "developer/support", component: () => import("@/modules/developer/Support.vue") },
  { path: "developer/audit", component: () => import("@/modules/developer/Audit.vue") },
  { path: "developer/collaborators", component: () => import("@/modules/developer/Collaborators.vue") },
  { path: "developer/users", component: () => import("@/modules/users/Index.vue") },
  { path: "developer/users/permissions", component: () => import("@/modules/users/Permissions.vue") },
  { path: "developer/settings", component: () => import("@/modules/settings/Index.vue") },
  { path: "grantees", component: () => import("@/modules/grantees/Index.vue") },
  { path: "grantees/:id", component: () => import("@/modules/grantees/Detail.vue") },
  { path: "batches", component: () => import("@/modules/batches/Index.vue") },
  { path: "batches/:id", component: () => import("@/modules/batches/Detail.vue") },
  { path: "programs", component: () => import("@/modules/programs/Index.vue") },
  { path: "academic", component: () => import("@/modules/academic/Index.vue") },
  { path: "academic/:id", component: () => import("@/modules/academic/Detail.vue") },
  { path: "documents", component: () => import("@/modules/documents/Index.vue") },
  { path: "documents/:id", component: () => import("@/modules/documents/Detail.vue") },
  { path: "eligibility", component: () => import("@/modules/eligibility/Index.vue") },
  { path: "eligibility/:id", component: () => import("@/modules/eligibility/Detail.vue") },
  { path: "files", component: () => import("@/modules/files/Index.vue") },
];

const studentChildren: RouteRecordRaw[] = [
  { path: "", component: () => import("@/modules/dashboard/StudentDashboard.vue") },
  { path: "kyc", component: () => import("@/modules/kyc/StudentKyc.vue") },
  { path: "onboarding", component: () => import("@/modules/identity/OnboardingIndex.vue") },
  { path: "onboarding/id-scan", component: () => import("@/modules/identity/OnboardingIdScan.vue") },
  { path: "onboarding/liveness", component: () => import("@/modules/identity/OnboardingLiveness.vue") },
  { path: "verify", component: () => import("@/modules/verification/StudentVerification.vue") },
  { path: "submissions", redirect: (to) => withLang("/student/documents", to.query.lang) },
  { path: "profile", component: () => import("@/modules/profile/Index.vue") },
  { path: "documents", component: () => import("@/modules/documents/StudentDocuments.vue") },
  { path: "upload", redirect: (to) => withLang("/student/documents", to.query.lang) },
  { path: "announcements", component: () => import("@/modules/announcements/StudentIndex.vue") },
  {
    path: "notifications",
    component: () => import("@/modules/notifications/StudentNotifications.vue"),
  },
  { path: "settings", component: () => import("@/modules/settings/StudentSettings.vue") },
];

function scrollBehavior(
  to: RouteLocationNormalized,
  from: RouteLocationNormalized,
  savedPosition: { left: number; top: number } | null,
) {
  if (savedPosition) return savedPosition;
  if (to.hash) return { el: to.hash, behavior: "smooth" as const };
  // Same-path query/hash-only changes keep scroll (filters, pagination).
  if (to.path === from.path) return false;
  return { top: 0 };
}

function createAppRouter(): Router {
  const router = createRouter({
    history: createWebHistory(),
    routes: [
      { path: "/", redirect: (to) => withLang("/login", to.query.lang) },
      { path: "/login", component: () => import("@/auth/Login.vue") },
      { path: "/forgot-password", component: () => import("@/auth/ForgotPassword.vue") },
      { path: "/activate", component: () => import("@/auth/Activate.vue") },
      { path: "/activate/:token", component: () => import("@/auth/Activate.vue") },
      { path: "/activate-success", component: () => import("@/auth/ActivateSuccess.vue") },
      { path: "/locked", component: () => import("@/auth/Locked.vue") },
      { path: "/help/support", component: () => import("@/public/HelpSupport.vue") },
      { path: "/app", component: () => import("@/layouts/AppShell.vue"), children: appChildren },
      {
        path: "/student",
        component: () => import("@/layouts/AppShell.vue"),
        children: studentChildren,
      },
      { path: "/:pathMatch(.*)*", redirect: (to) => withLang("/login", to.query.lang) },
    ],
    scrollBehavior,
  });

  installLanguageRouting(router);

  router.beforeEach(async (to) => {
    const protectedArea = to.path.startsWith("/app") || to.path.startsWith("/student");
    if (!protectedArea && to.path !== "/login") return true;
    const user = authSession.loaded ? authSession.user : await loadAuthUser();
    if (!user) return protectedArea ? withLang("/login", to.query.lang) : true;
    if (to.path === "/login") return withLang(user.role === "student" ? "/student" : "/app", to.query.lang);
    if (user.role === "student" && to.path.startsWith("/app")) return withLang("/student", to.query.lang);
    if (user.role !== "student" && to.path.startsWith("/student")) return withLang("/app", to.query.lang);
    if (
      user.role === "student" &&
      to.path.startsWith("/student") &&
      ["unverified", "pending_kyc", "blocked"].includes(user.account_status ?? "") &&
      !["/student/kyc", "/student/settings"].includes(to.path)
    ) {
      return withLang("/student/kyc", to.query.lang);
    }
    if (
      user.role === "student" &&
      to.path.startsWith("/student") &&
      user.account_status === "pending_identity" &&
      !to.path.startsWith("/student/onboarding") &&
      !["/student/kyc", "/student/settings"].includes(to.path)
    ) {
      return withLang("/student/onboarding", to.query.lang);
    }
    return true;
  });

  installSeoUpdates(router);

  return router;
}

export function createTccApp(): { app: VueApp; router: Router } {
  const app = createApp(App);
  const router = createAppRouter();

  app.use(router);
  app.use(VueQueryPlugin, { queryClient });
  app.use(i18n);

  return { app, router };
}
