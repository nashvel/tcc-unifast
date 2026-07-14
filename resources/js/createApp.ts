import { createApp as createClientApp, createSSRApp, type App as VueApp } from "vue";
import {
  createMemoryHistory,
  createRouter,
  createWebHistory,
  type Router,
  type RouteRecordRaw,
} from "vue-router";
import App from "./App.vue";
import { authSession, loadAuthUser } from "@/auth/session";
import { studentVerification } from "@/auth/studentVerification";

const appChildren: RouteRecordRaw[] = [
  { path: "", component: () => import("@/modules/dashboard/AdminDashboard.vue") },
  { path: "announcements", component: () => import("@/modules/announcements/Index.vue") },
  { path: "announcements/new", component: () => import("@/modules/announcements/Create.vue") },
  { path: "announcements/logs", component: () => import("@/modules/announcements/Logs.vue") },
  { path: "announcements/:id/edit", component: () => import("@/modules/announcements/Edit.vue") },
  { path: "reports", component: () => import("@/modules/reports/Index.vue") },
  { path: "reports/generate", component: () => import("@/modules/reports/Generate.vue") },
  { path: "reports/preview", component: () => import("@/modules/reports/Preview.vue") },
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
  { path: "grantees", component: () => import("@/modules/grantees/Index.vue") },
  { path: "grantees/:id", component: () => import("@/modules/grantees/Detail.vue") },
  { path: "batches", component: () => import("@/modules/batches/Index.vue") },
  { path: "batches/:id", component: () => import("@/modules/batches/Detail.vue") },
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
  { path: "verify", component: () => import("@/modules/verification/StudentVerification.vue") },
  { path: "submissions", redirect: "/student/documents" },
  { path: "profile", component: () => import("@/modules/profile/Index.vue") },
  { path: "documents", component: () => import("@/modules/documents/StudentDocuments.vue") },
  { path: "upload", component: () => import("@/modules/uploads/StudentUpload.vue") },
  { path: "announcements", component: () => import("@/modules/announcements/StudentIndex.vue") },
  {
    path: "notifications",
    component: () => import("@/modules/notifications/StudentNotifications.vue"),
  },
  { path: "settings", component: () => import("@/modules/settings/StudentSettings.vue") },
];

function createAppRouter(ssr: boolean): Router {
  const router = createRouter({
    history: ssr ? createMemoryHistory() : createWebHistory(),
    routes: [
      { path: "/", redirect: "/login" },
      { path: "/login", component: () => import("@/auth/Login.vue") },
      { path: "/forgot-password", component: () => import("@/auth/ForgotPassword.vue") },
      { path: "/activate", component: () => import("@/auth/Activate.vue") },
      { path: "/activate-success", component: () => import("@/auth/ActivateSuccess.vue") },
      { path: "/locked", component: () => import("@/auth/Locked.vue") },
      { path: "/app", component: () => import("@/layouts/AppShell.vue"), children: appChildren },
      {
        path: "/student",
        component: () => import("@/layouts/AppShell.vue"),
        children: studentChildren,
      },
      { path: "/:pathMatch(.*)*", redirect: "/login" },
    ],
    scrollBehavior: () => ({ top: 0 }),
  });

  router.beforeEach(async (to) => {
    if (import.meta.env.SSR) return true;

    const protectedArea = to.path.startsWith("/app") || to.path.startsWith("/student");
    if (!protectedArea && to.path !== "/login") return true;
    const user = authSession.loaded ? authSession.user : await loadAuthUser();
    if (!user) return protectedArea ? "/login" : true;
    if (to.path === "/login") return user.role === "student" ? "/student" : "/app";
    if (user.role === "student" && to.path.startsWith("/app")) return "/student";
    if (user.role !== "student" && to.path.startsWith("/student")) return "/app";
    if (
      user.role === "student" &&
      to.path.startsWith("/student") &&
      !studentVerification.verified &&
      !["/student", "/student/verify", "/student/settings"].includes(to.path)
    ) {
      return "/student/verify";
    }
    return true;
  });

  return router;
}

export function createTccApp(ssr = false): { app: VueApp; router: Router } {
  const app = (ssr ? createSSRApp : createClientApp)(App);
  const router = createAppRouter(ssr);

  app.use(router);

  return { app, router };
}
