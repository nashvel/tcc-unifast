import { defineComponent, computed, resolveComponent, mergeProps, withCtx, unref, createVNode, createTextVNode, toDisplayString, useSSRContext, ref, resolveDynamicComponent, Transition, openBlock, createBlock } from "vue";
import { ssrRenderAttrs, ssrRenderComponent, ssrRenderList, ssrInterpolate, ssrRenderClass, ssrRenderAttr, ssrRenderVNode } from "vue/server-renderer";
import { useRoute, useRouter } from "vue-router";
import { IconDashboard, IconShieldCheck, IconUserCircle, IconFileCheck, IconUpload, IconSpeakerphone, IconBell, IconSettings, IconReportAnalytics, IconLifebuoy, IconHistory, IconUserCog, IconFileImport, IconFolders, IconUsersGroup, IconFolder, IconSchool, IconChecklist, IconMenu2, IconSearch, IconCommand, IconSun, IconMoon, IconChevronDown, IconLogout } from "@tabler/icons-vue";
import { l as logo } from "./system-logo-DwBJYJLj.js";
import { Home, ChevronRight } from "lucide-vue-next";
import { _ as _sfc_main$2 } from "./DiceBearAvatar-C3Eyt9zS.js";
import { a as authSession, s as studentVerification } from "../ssr.js";
const adminNavigation = [
  { items: [{ label: "Dashboard", path: "/app", icon: IconDashboard }] },
  {
    label: "Communication",
    items: [
      { label: "Announcements", path: "/app/announcements", icon: IconSpeakerphone },
      { label: "Monitoring & Reports", path: "/app/reports", icon: IconReportAnalytics },
      { label: "Support Tickets", path: "/app/support", icon: IconLifebuoy }
    ]
  },
  {
    label: "Administration",
    items: [
      { label: "Audit Trail", path: "/app/audit", icon: IconHistory },
      { label: "Security Findings", path: "/app/security", icon: IconShieldCheck },
      { label: "Security Memory", path: "/app/security/memory", icon: IconShieldCheck },
      { label: "Users & Roles", path: "/app/users", icon: IconUserCog },
      { label: "Settings", path: "/app/settings", icon: IconSettings }
    ]
  }
];
const staffNavigation = [
  { items: [{ label: "Dashboard", path: "/app", icon: IconDashboard }] },
  {
    label: "Operations",
    items: [
      { label: "Masterlist", path: "/app/masterlist", icon: IconFileImport },
      { label: "Batches", path: "/app/batches", icon: IconFolders },
      { label: "Grantees", path: "/app/grantees", icon: IconUsersGroup }
    ]
  },
  {
    label: "Validation",
    items: [
      { label: "Document Validation", path: "/app/documents", icon: IconFileCheck },
      { label: "File Manager", path: "/app/files", icon: IconFolder },
      { label: "Academic Records", path: "/app/academic", icon: IconSchool },
      { label: "Eligibility", path: "/app/eligibility", icon: IconChecklist }
    ]
  },
  {
    label: "Communication",
    items: [
      { label: "Announcements", path: "/app/announcements", icon: IconSpeakerphone },
      { label: "Reports", path: "/app/reports", icon: IconReportAnalytics },
      { label: "Support Tickets", path: "/app/support", icon: IconLifebuoy }
    ]
  },
  {
    label: "Administration",
    items: [
      { label: "Audit Trail", path: "/app/audit", icon: IconHistory },
      { label: "Settings", path: "/app/settings", icon: IconSettings }
    ]
  }
];
const studentNavigation = [
  {
    items: [
      { label: "Dashboard", path: "/student", icon: IconDashboard },
      { label: "Verify Identity", path: "/student/verify", icon: IconShieldCheck },
      { label: "Profile", path: "/student/profile", icon: IconUserCircle },
      { label: "Required Documents", path: "/student/documents", icon: IconFileCheck },
      { label: "Upload Documents", path: "/student/upload", icon: IconUpload },
      { label: "Announcements", path: "/student/announcements", icon: IconSpeakerphone },
      { label: "Notifications", path: "/student/notifications", icon: IconBell },
      { label: "Settings", path: "/student/settings", icon: IconSettings }
    ]
  }
];
const lockedStudentNavigation = [
  {
    items: [
      { label: "Dashboard", path: "/student", icon: IconDashboard },
      { label: "Verify Identity", path: "/student/verify", icon: IconShieldCheck },
      { label: "Settings", path: "/student/settings", icon: IconSettings }
    ]
  }
];
const _sfc_main$1 = /* @__PURE__ */ defineComponent({
  __name: "AppBreadcrumbs",
  __ssrInlineRender: true,
  setup(__props) {
    const route = useRoute();
    const labels = {
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
      edit: "Edit"
    };
    const crumbs = computed(() => {
      const segments = route.path.split("/").filter(Boolean);
      return segments.map((segment, index) => {
        const isIdentifier = /^\d+$/.test(segment) || /^[0-9a-f-]{8,}$/i.test(segment);
        return {
          label: isIdentifier ? "Detail" : labels[segment] ?? segment.replaceAll("-", " ").replace(/\b\w/g, (character) => character.toUpperCase()),
          href: `/${segments.slice(0, index + 1).join("/")}`
        };
      });
    });
    return (_ctx, _push, _parent, _attrs) => {
      const _component_RouterLink = resolveComponent("RouterLink");
      if (crumbs.value.length) {
        _push(`<nav${ssrRenderAttrs(mergeProps({
          "aria-label": "Breadcrumb",
          class: "mb-4 inline-flex max-w-full items-center overflow-hidden rounded-full border border-border/60 bg-surface/60 px-2.5 py-1 text-xs text-text-muted shadow-sm backdrop-blur-sm"
        }, _attrs))}><ol class="flex min-w-0 items-center gap-0.5"><li class="flex shrink-0 items-center">`);
        _push(ssrRenderComponent(_component_RouterLink, {
          to: crumbs.value[0].href,
          "aria-label": `${crumbs.value[0].label} (home)`,
          class: "flex h-6 w-6 items-center justify-center rounded-full hover:bg-surface-muted hover:text-text"
        }, {
          default: withCtx((_, _push2, _parent2, _scopeId) => {
            if (_push2) {
              _push2(ssrRenderComponent(unref(Home), { size: 13 }, null, _parent2, _scopeId));
            } else {
              return [
                createVNode(unref(Home), { size: 13 })
              ];
            }
          }),
          _: 1
        }, _parent));
        _push(`</li><!--[-->`);
        ssrRenderList(crumbs.value.slice(1), (crumb, index) => {
          _push(`<li class="flex min-w-0 items-center gap-0.5">`);
          _push(ssrRenderComponent(unref(ChevronRight), {
            size: 12,
            class: "mx-0.5 shrink-0 text-text-soft/60"
          }, null, _parent));
          if (index === crumbs.value.length - 2) {
            _push(`<span aria-current="page" class="max-w-60 truncate rounded-full bg-primary/10 px-2 py-0.5 font-medium text-primary">${ssrInterpolate(crumb.label)}</span>`);
          } else {
            _push(ssrRenderComponent(_component_RouterLink, {
              to: crumb.href,
              class: "max-w-40 truncate rounded-full px-2 py-0.5 hover:bg-surface-muted hover:text-text"
            }, {
              default: withCtx((_, _push2, _parent2, _scopeId) => {
                if (_push2) {
                  _push2(`${ssrInterpolate(crumb.label)}`);
                } else {
                  return [
                    createTextVNode(toDisplayString(crumb.label), 1)
                  ];
                }
              }),
              _: 2
            }, _parent));
          }
          _push(`</li>`);
        });
        _push(`<!--]--></ol></nav>`);
      } else {
        _push(`<!---->`);
      }
    };
  }
});
const _sfc_setup$1 = _sfc_main$1.setup;
_sfc_main$1.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/components/navigation/AppBreadcrumbs.vue");
  return _sfc_setup$1 ? _sfc_setup$1(props, ctx) : void 0;
};
const _sfc_main = /* @__PURE__ */ defineComponent({
  __name: "AppShell",
  __ssrInlineRender: true,
  setup(__props) {
    const route = useRoute();
    useRouter();
    const mobile = ref(false);
    const profile = ref(false);
    const notifications = ref(false);
    const dark = ref(typeof localStorage !== "undefined" && localStorage.getItem("theme") === "dark");
    const isStudent = computed(() => route.path.startsWith("/student"));
    const role = computed(() => authSession.user?.role ?? "student");
    const user = computed(() => authSession.user);
    const sections = computed(() => {
      if (isStudent.value) return studentVerification.verified ? studentNavigation : lockedStudentNavigation;
      if (role.value === "admin") return adminNavigation;
      if (role.value === "head") return staffNavigation;
      return staffNavigation;
    });
    function isActive(path) {
      return path === "/app" || path === "/student" ? route.path === path : route.path === path || route.path.startsWith(`${path}/`);
    }
    if (dark.value && typeof document !== "undefined") document.documentElement.classList.add("dark");
    return (_ctx, _push, _parent, _attrs) => {
      const _component_RouterView = resolveComponent("RouterView");
      _push(`<div${ssrRenderAttrs(mergeProps({ class: "min-h-screen bg-bg" }, _attrs))}>`);
      if (mobile.value) {
        _push(`<div class="fixed inset-0 z-40 bg-black/30 lg:hidden"></div>`);
      } else {
        _push(`<!---->`);
      }
      _push(`<aside class="${ssrRenderClass([
        "fixed inset-y-0 left-0 z-50 flex w-60 shrink-0 flex-col border-r bg-sidebar-bg transition-transform lg:translate-x-0",
        mobile.value ? "translate-x-0" : "-translate-x-full"
      ])}"><div class="flex h-14 shrink-0 items-center gap-2 border-b px-4"><img${ssrRenderAttr("src", unref(logo))} alt="Tagoloan Community College" class="h-8 w-8 shrink-0 select-none object-contain" draggable="false"><div class="min-w-0 leading-tight"><p class="truncate text-sm font-semibold text-sidebar-text">${ssrInterpolate(isStudent.value ? "Student Portal" : "UniFAST TES")}</p><p class="truncate text-2xs text-sidebar-text-muted">${ssrInterpolate(isStudent.value ? "UniFAST TES" : "Grantee Management")}</p></div></div><nav class="flex-1 overflow-y-auto py-3"><!--[-->`);
      ssrRenderList(sections.value, (section, sectionIndex) => {
        _push(`<div class="mb-3 px-2">`);
        if (section.label) {
          _push(`<p class="mb-1 px-2 text-2xs font-semibold uppercase tracking-wider text-sidebar-text-muted">${ssrInterpolate(section.label)}</p>`);
        } else {
          _push(`<!---->`);
        }
        _push(`<ul class="space-y-0.5"><!--[-->`);
        ssrRenderList(section.items, (item) => {
          _push(`<li><button class="${ssrRenderClass([
            isActive(item.path) ? "bg-sidebar-active font-medium text-[#f5e6c4]" : "text-sidebar-text hover:bg-sidebar-active",
            "flex h-8 w-full items-center gap-2 rounded-md px-2 text-left text-sm transition-colors"
          ])}">`);
          ssrRenderVNode(_push, createVNode(resolveDynamicComponent(item.icon), {
            size: 16,
            class: isActive(item.path) ? "text-[#f5e6c4]" : "text-sidebar-text-muted"
          }, null), _parent);
          _push(`<span class="truncate">${ssrInterpolate(item.label)}</span></button></li>`);
        });
        _push(`<!--]--></ul></div>`);
      });
      _push(`<!--]--></nav></aside><div class="min-h-screen lg:pl-60"><header class="sticky top-0 z-30 flex h-14 items-center gap-2 border-b bg-surface px-3"><button class="rounded-md p-1.5 hover:bg-surface-muted lg:hidden">`);
      _push(ssrRenderComponent(unref(IconMenu2), { size: 18 }, null, _parent));
      _push(`</button><img${ssrRenderAttr("src", unref(logo))} class="h-7 w-7 object-contain lg:hidden" alt="TCC"><button class="flex h-9 max-w-md flex-1 items-center gap-2 rounded-md border bg-surface px-2.5 text-left text-text-muted hover:bg-surface-muted">`);
      _push(ssrRenderComponent(unref(IconSearch), { size: 15 }, null, _parent));
      _push(`<span class="flex-1 truncate text-sm">Search or jump to…</span><kbd class="hidden items-center gap-0.5 rounded border bg-surface px-1 py-0.5 text-2xs text-text-soft sm:inline-flex">`);
      _push(ssrRenderComponent(unref(IconCommand), { size: 10 }, null, _parent));
      _push(` K</kbd></button><div class="flex-1"></div>`);
      if (!isStudent.value && role.value === "admin") {
        _push(`<span class="hidden rounded-full bg-primary-soft px-2 py-1 text-2xs font-medium text-primary md:inline-flex">Monitor mode</span>`);
      } else {
        _push(`<!---->`);
      }
      _push(`<button class="rounded-md p-2 hover:bg-surface-muted">`);
      if (dark.value) {
        _push(ssrRenderComponent(unref(IconSun), { size: 18 }, null, _parent));
      } else {
        _push(ssrRenderComponent(unref(IconMoon), { size: 18 }, null, _parent));
      }
      _push(`</button><div class="relative"><button class="relative rounded-md p-2 hover:bg-surface-muted">`);
      _push(ssrRenderComponent(unref(IconBell), { size: 18 }, null, _parent));
      _push(`<span class="absolute right-1 top-1 grid h-4 min-w-4 place-items-center rounded-full bg-danger px-1 text-2xs text-white">3</span></button>`);
      if (notifications.value) {
        _push(`<div class="absolute right-0 mt-1 w-80 rounded-lg border bg-surface shadow-xl"><div class="flex h-10 items-center justify-between border-b px-3"><p class="text-sm font-semibold">Notifications</p><button class="text-xs text-primary">Mark all read</button></div><div class="space-y-3 p-3 text-xs"><p><b>Documents validated</b><br><span class="text-text-muted">12 records are ready for eligibility review.</span></p><p><b>Batch closing soon</b><br><span class="text-text-muted">TES Semester 2 closes in five days.</span></p></div></div>`);
      } else {
        _push(`<!---->`);
      }
      _push(`</div><div class="relative"><button class="flex items-center gap-2 rounded-md py-1 pl-1 pr-2 hover:bg-surface-muted">`);
      _push(ssrRenderComponent(_sfc_main$2, {
        seed: isStudent.value ? "student@tcc.edu.ph" : "admin@unifast.gov.ph",
        alt: isStudent.value ? "Maria Santos" : "System Administrator",
        size: 28
      }, null, _parent));
      _push(`<span class="hidden text-left leading-tight sm:block"><span class="block text-xs font-medium">${ssrInterpolate(user.value?.name ?? (isStudent.value ? "Maria Santos" : "System Administrator"))}</span><span class="block text-2xs capitalize text-text-muted">${ssrInterpolate(isStudent.value ? "student" : role.value)}</span></span>`);
      _push(ssrRenderComponent(unref(IconChevronDown), {
        size: 14,
        class: "text-text-muted"
      }, null, _parent));
      _push(`</button>`);
      if (profile.value) {
        _push(`<div class="absolute right-0 mt-1 w-56 rounded-lg border bg-surface p-1 shadow-xl"><div class="border-b px-2.5 py-2"><p class="text-sm font-medium">${ssrInterpolate(user.value?.name ?? (isStudent.value ? "Maria Santos" : "System Administrator"))}</p><p class="text-xs text-text-muted">${ssrInterpolate(user.value?.email ?? (isStudent.value ? "student@tcc.edu.ph" : "admin@unifast.gov.ph"))}</p></div><button class="flex w-full items-center gap-2 rounded px-2.5 py-1.5 text-sm hover:bg-surface-muted">`);
        _push(ssrRenderComponent(unref(IconUserCircle), { size: 15 }, null, _parent));
        _push(` Profile</button><button class="flex w-full items-center gap-2 rounded px-2.5 py-1.5 text-sm text-danger hover:bg-surface-muted">`);
        _push(ssrRenderComponent(unref(IconLogout), { size: 15 }, null, _parent));
        _push(` Sign out </button></div>`);
      } else {
        _push(`<!---->`);
      }
      _push(`</div></header><main class="mx-auto w-full max-w-[1400px] p-4 sm:p-6">`);
      if (!isStudent.value) {
        _push(ssrRenderComponent(_sfc_main$1, null, null, _parent));
      } else {
        _push(`<!---->`);
      }
      _push(ssrRenderComponent(_component_RouterView, null, {
        default: withCtx(({ Component }, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(``);
            ssrRenderVNode(_push2, createVNode(resolveDynamicComponent(Component), {
              key: unref(route).fullPath,
              class: "page-enter"
            }, null), _parent2, _scopeId);
          } else {
            return [
              createVNode(Transition, {
                name: "page",
                mode: "out-in"
              }, {
                default: withCtx(() => [
                  (openBlock(), createBlock(resolveDynamicComponent(Component), {
                    key: unref(route).fullPath,
                    class: "page-enter"
                  }))
                ]),
                _: 2
              }, 1024)
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(`</main></div></div>`);
    };
  }
});
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/layouts/AppShell.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
