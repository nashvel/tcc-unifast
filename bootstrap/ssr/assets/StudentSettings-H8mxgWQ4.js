import { defineComponent, ref, resolveComponent, withCtx, unref, createVNode, createTextVNode, resolveDynamicComponent, useSSRContext } from "vue";
import { ssrRenderAttrs, ssrRenderComponent, ssrRenderList, ssrRenderClass, ssrRenderVNode, ssrInterpolate } from "vue/server-renderer";
import { IconLogout, IconUser, IconKey, IconHistory, IconLifebuoy, IconCheck, IconDeviceLaptop } from "@tabler/icons-vue";
import { _ as _sfc_main$1 } from "./PageHeader-DV7ufis9.js";
import { _ as _sfc_main$2 } from "./DiceBearAvatar-C3Eyt9zS.js";
const _sfc_main = /* @__PURE__ */ defineComponent({
  __name: "StudentSettings",
  __ssrInlineRender: true,
  setup(__props) {
    const section = ref("profile");
    const saved = ref(false);
    const passwordUpdated = ref(false);
    const nav = [
      ["profile", "Profile", "Personal information", IconUser],
      ["security", "Security", "Change password", IconKey],
      ["sessions", "Sessions", "Sign-in activity", IconHistory]
    ];
    return (_ctx, _push, _parent, _attrs) => {
      const _component_RouterLink = resolveComponent("RouterLink");
      _push(`<div${ssrRenderAttrs(_attrs)}>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        title: "Settings",
        description: "Manage your profile, password, and sign-in activity."
      }, {
        actions: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(ssrRenderComponent(_component_RouterLink, {
              to: "/login",
              class: "inline-flex h-9 items-center gap-1.5 rounded-md px-3 text-xs hover:bg-surface-muted"
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(ssrRenderComponent(unref(IconLogout), { size: 14 }, null, _parent3, _scopeId2));
                  _push3(`Sign out`);
                } else {
                  return [
                    createVNode(unref(IconLogout), { size: 14 }),
                    createTextVNode("Sign out")
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
          } else {
            return [
              createVNode(_component_RouterLink, {
                to: "/login",
                class: "inline-flex h-9 items-center gap-1.5 rounded-md px-3 text-xs hover:bg-surface-muted"
              }, {
                default: withCtx(() => [
                  createVNode(unref(IconLogout), { size: 14 }),
                  createTextVNode("Sign out")
                ]),
                _: 1
              })
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(`<div class="grid gap-4 lg:grid-cols-[220px_1fr]"><nav class="h-fit rounded-lg border bg-surface p-2"><!--[-->`);
      ssrRenderList(nav, (item) => {
        _push(`<button class="${ssrRenderClass([
          "mb-0.5 flex w-full items-start gap-2 rounded-md px-2.5 py-2 text-left",
          section.value === item[0] ? "bg-sidebar-active text-[#f5e6c4]" : "hover:bg-surface-muted"
        ])}">`);
        ssrRenderVNode(_push, createVNode(resolveDynamicComponent(item[3]), {
          size: 16,
          class: "mt-0.5"
        }, null), _parent);
        _push(`<span><b class="block text-sm">${ssrInterpolate(item[1])}</b><span class="text-micro opacity-80">${ssrInterpolate(item[2])}</span></span></button>`);
      });
      _push(`<!--]--></nav><main>`);
      if (section.value === "profile") {
        _push(`<section class="rounded-lg border bg-surface"><h2 class="border-b px-4 py-3 text-sm font-semibold">Edit Profile</h2><div class="grid gap-5 p-5 md:grid-cols-[auto_1fr]"><div>`);
        _push(ssrRenderComponent(_sfc_main$2, {
          seed: "mc.delacruz@tcc.edu.ph",
          size: 80,
          alt: "Maria Clara Dela Cruz"
        }, null, _parent));
        _push(`<button class="mt-2 w-full text-xs text-primary">Change avatar</button></div><form class="max-w-xl space-y-4"><label class="block"><span class="mb-1.5 block text-xs font-medium">Full name *</span><input value="Maria Clara Dela Cruz" disabled class="h-9 w-full rounded-md border bg-surface-muted px-3 text-sm text-text-muted"><span class="mt-1 block text-micro text-text-muted"> Your name is based on the official masterlist and cannot be edited here. </span></label><div class="rounded-lg border border-info/30 bg-info-soft p-3 text-xs"><p class="flex items-center gap-2 font-semibold text-info">`);
        _push(ssrRenderComponent(unref(IconLifebuoy), { size: 14 }, null, _parent));
        _push(` Name issue? </p><p class="mt-1 text-text-muted"> Contact support or the UniFAST office if your name is misspelled or does not match your official records. </p><a href="mailto:registrar@tcc.edu.ph?subject=Student%20name%20correction%20request" class="mt-2 inline-flex rounded-md border bg-surface px-3 py-1.5 text-micro font-medium text-primary"> Contact support </a></div><label class="block"><span class="mb-1.5 block text-xs font-medium">Email</span><input value="mc.delacruz@tcc.edu.ph" disabled class="h-9 w-full rounded-md border bg-surface-muted px-3 text-sm text-text-muted"></label><label class="block"><span class="mb-1.5 block text-xs font-medium">Contact number</span><input value="+63 917 123 4567" class="h-9 w-full rounded-md border px-3 text-sm"></label>`);
        if (saved.value) {
          _push(`<p class="inline-flex items-center gap-1 text-xs text-success">`);
          _push(ssrRenderComponent(unref(IconCheck), { size: 12 }, null, _parent));
          _push(`Profile updated. </p>`);
        } else {
          _push(`<!---->`);
        }
        _push(`<button class="block h-9 rounded-md bg-primary px-3 text-xs text-white"> Save profile </button></form></div></section>`);
      } else if (section.value === "security") {
        _push(`<section class="rounded-lg border bg-surface"><h2 class="border-b px-4 py-3 text-sm font-semibold">Change Password</h2><form class="max-w-xl space-y-4 p-4"><!--[-->`);
        ssrRenderList(["Current password", "New password", "Confirm new password"], (label) => {
          _push(`<label class="block"><span class="mb-1.5 block text-xs font-medium">${ssrInterpolate(label)} *</span><input type="password" placeholder="••••••••" class="h-9 w-full rounded-md border px-3"></label>`);
        });
        _push(`<!--]--><div><div class="flex justify-between text-micro"><span class="text-text-muted">Strength</span><span>Strong</span></div><div class="mt-1 h-1.5 rounded-full bg-surface-muted"><div class="h-full w-4/5 rounded-full bg-success"></div></div></div>`);
        if (passwordUpdated.value) {
          _push(`<p class="inline-flex items-center gap-1 text-xs text-success">`);
          _push(ssrRenderComponent(unref(IconCheck), { size: 12 }, null, _parent));
          _push(`Password updated successfully. </p>`);
        } else {
          _push(`<!---->`);
        }
        _push(`<button class="block h-9 rounded-md bg-primary px-3 text-xs text-white"> Update password </button></form></section>`);
      } else {
        _push(`<!--[--><section class="mb-3 rounded-lg border bg-surface p-4"><div class="flex gap-3"><span class="grid h-10 w-10 place-items-center rounded-md bg-primary-soft text-primary">`);
        _push(ssrRenderComponent(unref(IconDeviceLaptop), { size: 18 }, null, _parent));
        _push(`</span><div><p class="text-sm font-medium">Current session · Windows PC</p><p class="text-micro text-text-muted">Chrome · Manila, Philippines · Active now</p></div></div></section><section class="rounded-lg border bg-surface"><h2 class="border-b px-4 py-3 text-sm font-semibold">Login Activity</h2><div class="divide-y px-4"><!--[-->`);
        ssrRenderList([
          ["Windows PC", "Jul 12, 2026, 9:14 AM", "192.168.1.14"],
          ["Android device", "Jul 10, 2026, 6:35 PM", "192.168.1.22"],
          ["Windows PC", "Jul 8, 2026, 8:02 AM", "192.168.1.14"]
        ], (event) => {
          _push(`<div class="flex items-center gap-3 py-3">`);
          _push(ssrRenderComponent(unref(IconDeviceLaptop), {
            size: 16,
            class: "text-text-muted"
          }, null, _parent));
          _push(`<div><p class="text-sm font-medium">${ssrInterpolate(event[0])}</p><p class="text-micro text-text-muted">${ssrInterpolate(event[1])} · ${ssrInterpolate(event[2])}</p></div></div>`);
        });
        _push(`<!--]--></div></section><!--]-->`);
      }
      _push(`</main></div></div>`);
    };
  }
});
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/modules/settings/StudentSettings.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
