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
import { isStudentOnboardingRoute, studentHomePath } from "@/auth/onboardingResume";
import { queryClient } from "@/lib/queryClient";
import { i18n } from "@/i18n";
import { installLanguageRouting, withLang } from "@/i18n/routeLang";
import { installSeoUpdates } from "@/i18n/seo";

const appChildren: RouteRecordRaw[] = [
  { path: "", component: () => import("@/modules/dashboard/Index.vue") },
  { path: "announcements", component: () => import("@/modules/announcements/Index.vue") },
  { path: "social-posts", component: () => import("@/modules/social-posts/Index.vue") },
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
  { path: "integrations/workspace", component: () => import("@/modules/continuity/Workspace.vue") },
  { path: "activation-seeder", component: () => import("@/modules/activation-seeder/ActivationSeeder.vue") },
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
  {
    path: "documents/package/:granteeId/:batchId",
    component: () => import("@/modules/documents/Detail.vue"),
    meta: { breadcrumbLabel: "Package" },
  },
  { path: "documents/:id", component: () => import("@/modules/documents/Detail.vue") },
  { path: "face-reviews", component: () => import("@/modules/identity/FaceReviews.vue") },
  { path: "face-reviews/:id", component: () => import("@/modules/identity/FaceReviews.vue") },
  { path: "eligibility", component: () => import("@/modules/eligibility/Index.vue") },
  { path: "eligibility/:id", component: () => import("@/modules/eligibility/Detail.vue") },
  { path: "files", component: () => import("@/modules/files/Index.vue") },
  // Dynamic Forms
  { path: "forms", component: () => import("@/modules/forms/Index.vue") },
  { path: "forms/new", component: () => import("@/modules/forms/Builder.vue") },
  { path: "forms/:id/edit", component: () => import("@/modules/forms/Builder.vue") },
  { path: "forms/:id/responses", component: () => import("@/modules/forms/Builder.vue") },
  { path: "forms/:id/security", component: () => import("@/modules/forms/SecurityLog.vue") },
];

const studentChildren: RouteRecordRaw[] = [
  { path: "", component: () => import("@/modules/dashboard/StudentDashboard.vue") },
  { path: "kyc", component: () => import("@/modules/kyc/StudentKyc.vue") },
  { path: "onboarding", component: () => import("@/modules/identity/OnboardingIndex.vue") },
  { path: "onboarding/id-scan", component: () => import("@/modules/identity/OnboardingIdScan.vue") },
  { path: "onboarding/liveness", component: () => import("@/modules/identity/OnboardingLiveness.vue") },
  {
    path: "onboarding/pending-review",
    component: () => import("@/modules/identity/OnboardingPendingReview.vue"),
  },
  {
    // Terminal onboarding step: password is chosen only after identity is verified.
    path: "onboarding/set-password",
    component: () => import("@/modules/identity/OnboardingSetPassword.vue"),
    meta: { breadcrumbLabel: "Set password" },
  },
  {
    path: "verify",
    redirect: (to) => {
      // Legacy deep link: real identity is onboarding; vault liveness lives on documents.
      if (authSession.user?.account_status === "pending_identity") {
        return withLang("/student/onboarding", to.query.lang);
      }
      return withLang("/student/documents", to.query.lang);
    },
  },
  { path: "submissions", redirect: (to) => withLang("/student/documents", to.query.lang) },
  { path: "profile", component: () => import("@/modules/profile/Index.vue") },
  { path: "documents", component: () => import("@/modules/documents/StudentDocuments.vue") },
  { path: "upload", redirect: (to) => withLang("/student/documents", to.query.lang) },
  { path: "announcements", component: () => import("@/modules/announcements/StudentIndex.vue") },
  { path: "announcements/:id", component: () => import("@/modules/announcements/StudentDetail.vue") },
  {
    path: "notifications",
    component: () => import("@/modules/notifications/StudentNotifications.vue"),
  },
  { path: "settings", redirect: (to) => withLang("/student/profile?tab=settings", to.query.lang) },
  // Student Forms
  { path: "forms", component: () => import("@/modules/forms/StudentForms.vue") },
  { path: "forms/:id", component: () => import("@/modules/forms/StudentFormRenderer.vue") },
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
      { path: "/", component: () => import("@/public/Landing.vue") },
      { path: "/unifast", component: () => import("@/public/UnifastInfo.vue") },
      { path: "/tagoloan-community-college", component: () => import("@/public/TccInfo.vue") },
      { path: "/login", component: () => import("@/auth/Login.vue") },
      { path: "/forgot-password", component: () => import("@/auth/ForgotPassword.vue") },
      { path: "/activate", component: () => import("@/auth/Activate.vue") },
      { path: "/activate/:token", component: () => import("@/auth/Activate.vue") },
      {
        // Self-service recovery for an expired activation link.
        path: "/activation/resend",
        component: () => import("@/auth/ActivationResend.vue"),
      },
      { path: "/activate-success", component: () => import("@/auth/ActivateSuccess.vue") },
      { path: "/locked", component: () => import("@/auth/Locked.vue") },
      { path: "/help/support", component: () => import("@/public/HelpSupport.vue") },
      { path: "/app", component: () => import("@/layouts/AppShell.vue"), children: appChildren },
      {
        path: "/student",
        component: () => import("@/layouts/AppShell.vue"),
        children: studentChildren,
      },
      // Public form — no authentication shell
      {
        path: "/forms/public/:token",
        component: () => import("@/modules/forms/PublicForm.vue"),
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
    if (to.path === "/login") {
      return withLang(studentHomePath(user), to.query.lang);
    }
    if (user.role === "student" && to.path.startsWith("/app")) return withLang("/student", to.query.lang);
    if (user.role !== "student" && to.path.startsWith("/student")) return withLang("/app", to.query.lang);

    // identity_verified and identity_rejected are pre-credential states: the
    // student has no password yet, so the app proper stays out of reach.
    const incompleteStatuses = [
      "unverified",
      "pending_kyc",
      "pending_identity",
      "pending_face_review",
      "identity_verified",
      "identity_rejected",
    ];
    if (
      user.role === "student" &&
      to.path.startsWith("/student") &&
      incompleteStatuses.includes(user.account_status ?? "") &&
      !isStudentOnboardingRoute(to.path)
    ) {
      return withLang(studentHomePath(user), to.query.lang);
    }

    // Keep students on the correct onboarding step (KYC → ID scan → liveness).
    if (
      user.role === "student" &&
      (user.account_status === "pending_kyc" || user.account_status === "unverified") &&
      to.path.startsWith("/student/onboarding")
    ) {
      return withLang("/student/kyc", to.query.lang);
    }
    if (
      user.role === "student" &&
      to.path === "/student/onboarding/liveness" &&
      user.onboarding_next_step &&
      user.onboarding_next_step !== "liveness"
    ) {
      return withLang(studentHomePath(user), to.query.lang);
    }
    if (
      user.role === "student" &&
      to.path === "/student/onboarding/id-scan" &&
      user.onboarding_next_step &&
      !["id_scan", "liveness"].includes(user.onboarding_next_step)
    ) {
      return withLang(studentHomePath(user), to.query.lang);
    }
    if (
      user.role === "student" &&
      user.account_status === "pending_face_review" &&
      to.path.startsWith("/student/onboarding") &&
      to.path !== "/student/onboarding/pending-review"
    ) {
      return withLang("/student/onboarding/pending-review", to.query.lang);
    }
    // Leave pending-review once staff approve/reject (or status is no longer pending).
    if (
      user.role === "student" &&
      to.path === "/student/onboarding/pending-review" &&
      user.account_status !== "pending_face_review"
    ) {
      return withLang(studentHomePath(user), to.query.lang);
    }
    if (
      user.role === "student" &&
      user.account_status === "pending_identity" &&
      to.path === "/student/kyc" &&
      user.onboarding_next_step &&
      user.onboarding_next_step !== "kyc"
    ) {
      return withLang(studentHomePath(user), to.query.lang);
    }

    // Set-password is reachable only once identity is verified…
    if (
      user.role === "student" &&
      to.path === "/student/onboarding/set-password" &&
      user.onboarding_next_step !== "credentials"
    ) {
      return withLang(studentHomePath(user), to.query.lang);
    }
    // …and a verified-but-uncredentialed student must not slip past it.
    if (
      user.role === "student" &&
      user.onboarding_next_step === "credentials" &&
      to.path !== "/student/onboarding/set-password"
    ) {
      return withLang("/student/onboarding/set-password", to.query.lang);
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
