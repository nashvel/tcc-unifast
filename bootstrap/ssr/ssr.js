import { ssrRenderComponent, renderToString } from "vue/server-renderer";
import { resolveComponent, useSSRContext, reactive, createSSRApp, createApp } from "vue";
import { createRouter, createMemoryHistory, createWebHistory } from "vue-router";
const _export_sfc = (sfc, props) => {
  const target = sfc.__vccOpts || sfc;
  for (const [key2, val] of props) {
    target[key2] = val;
  }
  return target;
};
const _sfc_main = {};
function _sfc_ssrRender(_ctx, _push, _parent, _attrs) {
  const _component_RouterView = resolveComponent("RouterView");
  _push(ssrRenderComponent(_component_RouterView, _attrs, null, _parent));
}
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/App.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
const App = /* @__PURE__ */ _export_sfc(_sfc_main, [["ssrRender", _sfc_ssrRender]]);
const authSession = reactive({ user: null, loaded: false });
const csrfToken = () => typeof document === "undefined" ? "" : document.querySelector('meta[name="csrf-token"]')?.content ?? "";
const key = "tcc_student_identity_verified";
const hasStorage = () => typeof localStorage !== "undefined";
const studentVerification = reactive({
  verified: hasStorage() && localStorage.getItem(key) === "1"
});
const appChildren = [
  { path: "", component: () => import("./assets/AdminDashboard-Dr9xXGWM.js") },
  { path: "announcements", component: () => import("./assets/Index-Yu_kGTS_.js") },
  { path: "announcements/new", component: () => import("./assets/Create-B8gZbRJX.js") },
  { path: "announcements/logs", component: () => import("./assets/Logs-BXZyjr8y.js") },
  { path: "announcements/:id/edit", component: () => import("./assets/Edit-JcbAVkpW.js") },
  { path: "reports", component: () => import("./assets/Index-Ct5Z7hbQ.js") },
  { path: "reports/generate", component: () => import("./assets/Generate-B4ziq62x.js") },
  { path: "reports/preview", component: () => import("./assets/Preview-CVy2N_vG.js") },
  { path: "support", component: () => import("./assets/Index-B6TB_-g7.js") },
  { path: "support/new", component: () => import("./assets/Create-CGP67EMu.js") },
  { path: "support/:id", component: () => import("./assets/Detail-CavDRxH9.js") },
  { path: "audit", component: () => import("./assets/Index-anqej8l9.js") },
  { path: "security", component: () => import("./assets/Index-DrnzOq3J.js") },
  { path: "security/memory", component: () => import("./assets/Memory-CK2m9t8P.js") },
  { path: "users", component: () => import("./assets/Index-DWyqpQCA.js") },
  { path: "users/permissions", component: () => import("./assets/Permissions-BgjNKv_k.js") },
  { path: "settings", component: () => import("./assets/Index-CcZnYL6h.js") },
  { path: "appearance", component: () => import("./assets/Index-B7tfitQ6.js") },
  { path: "style-guide", component: () => import("./assets/Index-CCjyfG59.js") },
  { path: "masterlist", component: () => import("./assets/Index-B0lLf8nB.js") },
  { path: "grantees", component: () => import("./assets/Index-CFanQsDf.js") },
  { path: "grantees/:id", component: () => import("./assets/Detail-DiSwDd49.js") },
  { path: "batches", component: () => import("./assets/Index-1e730PbK.js") },
  { path: "batches/:id", component: () => import("./assets/Detail-CD0ympP0.js") },
  { path: "academic", component: () => import("./assets/Index-DZOsw52j.js") },
  { path: "academic/:id", component: () => import("./assets/Detail-CsTI5XzS.js") },
  { path: "documents", component: () => import("./assets/Index-Ccdg7OFg.js") },
  { path: "documents/:id", component: () => import("./assets/Detail-BE4afcEO.js") },
  { path: "eligibility", component: () => import("./assets/Index-BItBv7TP.js") },
  { path: "eligibility/:id", component: () => import("./assets/Detail-D2_hJ0Bh.js") },
  { path: "files", component: () => import("./assets/Index-CdX9Maw8.js") }
];
const studentChildren = [
  { path: "", component: () => import("./assets/StudentDashboard-CmhpksUK.js") },
  { path: "verify", component: () => import("./assets/StudentVerification-jPYVimRq.js") },
  { path: "submissions", redirect: "/student/documents" },
  { path: "profile", component: () => import("./assets/Index-CdIMJi0-.js") },
  { path: "documents", component: () => import("./assets/StudentDocuments-iwQZ2lYo.js") },
  { path: "upload", component: () => import("./assets/StudentUpload-BfPNc1wA.js") },
  { path: "announcements", component: () => import("./assets/StudentIndex-DZY9vLXF.js") },
  {
    path: "notifications",
    component: () => import("./assets/StudentNotifications-DEYI04ie.js")
  },
  { path: "settings", component: () => import("./assets/StudentSettings-H8mxgWQ4.js") }
];
function createAppRouter(ssr) {
  const router = createRouter({
    history: ssr ? createMemoryHistory() : createWebHistory(),
    routes: [
      { path: "/", redirect: "/login" },
      { path: "/login", component: () => import("./assets/Login-BELHkOtQ.js") },
      { path: "/forgot-password", component: () => import("./assets/ForgotPassword-CudKbL_N.js") },
      { path: "/activate", component: () => import("./assets/Activate-C5nxtf2v.js") },
      { path: "/activate-success", component: () => import("./assets/ActivateSuccess-Cmy2APF3.js") },
      { path: "/locked", component: () => import("./assets/Locked-BHFDdnWD.js") },
      { path: "/app", component: () => import("./assets/AppShell-BMpvGPru.js"), children: appChildren },
      {
        path: "/student",
        component: () => import("./assets/AppShell-BMpvGPru.js"),
        children: studentChildren
      },
      { path: "/:pathMatch(.*)*", redirect: "/login" }
    ],
    scrollBehavior: () => ({ top: 0 })
  });
  router.beforeEach(async (to) => {
    return true;
  });
  return router;
}
function createTccApp(ssr = false) {
  const app = (ssr ? createSSRApp : createApp)(App);
  const router = createAppRouter(ssr);
  app.use(router);
  return { app, router };
}
async function render(url) {
  const { app, router } = createTccApp(true);
  await router.push(url);
  await router.isReady();
  return await renderToString(app);
}
export {
  authSession as a,
  csrfToken as c,
  render,
  studentVerification as s
};
