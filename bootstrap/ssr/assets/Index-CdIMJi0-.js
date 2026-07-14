import { defineComponent, computed, resolveComponent, withCtx, unref, createVNode, createTextVNode, resolveDynamicComponent, useSSRContext } from "vue";
import { ssrRenderAttrs, ssrRenderComponent, ssrInterpolate, ssrRenderList, ssrRenderVNode, ssrRenderClass, ssrRenderAttr } from "vue/server-renderer";
import { IconPencil, IconId, IconUser, IconCheck, IconClock } from "@tabler/icons-vue";
import { _ as _sfc_main$1 } from "./PageHeader-DV7ufis9.js";
import { _ as _sfc_main$2 } from "./DiceBearAvatar-C3Eyt9zS.js";
const _sfc_main = /* @__PURE__ */ defineComponent({
  __name: "Index",
  __ssrInlineRender: true,
  setup(__props) {
    const completedAt = typeof localStorage !== "undefined" ? localStorage.getItem("unifast.mock.onboarding_completed_at") : null;
    const onboardingDone = computed(() => Boolean(completedAt));
    const personal = [
      ["Full name", "Maria Clara Dela Cruz"],
      ["Birthdate", "May 14, 2003"],
      ["Email", "mc.delacruz@tcc.edu.ph"],
      ["Contact", "+63 917 123 4567"]
    ];
    const academic = [
      ["University", "Tagoloan Community College"],
      ["Program", "BS Information Technology"],
      ["Year level", "2"],
      ["Student #", "2024-00182"]
    ];
    return (_ctx, _push, _parent, _attrs) => {
      const _component_RouterLink = resolveComponent("RouterLink");
      _push(`<div${ssrRenderAttrs(_attrs)}>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        title: "My Profile",
        description: "Personal and academic information on file. View only — manage edits from Settings."
      }, {
        actions: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(ssrRenderComponent(_component_RouterLink, {
              to: "/student/settings",
              class: "inline-flex h-9 items-center gap-1.5 rounded-md border bg-surface px-3 text-xs"
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(ssrRenderComponent(unref(IconPencil), { size: 14 }, null, _parent3, _scopeId2));
                  _push3(`Edit in Settings`);
                } else {
                  return [
                    createVNode(unref(IconPencil), { size: 14 }),
                    createTextVNode("Edit in Settings")
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
          } else {
            return [
              createVNode(_component_RouterLink, {
                to: "/student/settings",
                class: "inline-flex h-9 items-center gap-1.5 rounded-md border bg-surface px-3 text-xs"
              }, {
                default: withCtx(() => [
                  createVNode(unref(IconPencil), { size: 14 }),
                  createTextVNode("Edit in Settings")
                ]),
                _: 1
              })
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(`<section class="mb-4 flex items-center gap-3 rounded-lg border bg-surface p-4">`);
      _push(ssrRenderComponent(_sfc_main$2, {
        seed: "mc.delacruz@tcc.edu.ph",
        alt: "Maria Clara Dela Cruz",
        size: 56
      }, null, _parent));
      _push(`<div><p class="text-sm font-semibold">Maria Clara Dela Cruz</p><p class="text-xs text-text-muted">mc.delacruz@tcc.edu.ph</p></div></section><section class="mb-4 rounded-lg border bg-surface p-4"><div class="mb-3"><p class="text-sm font-semibold">Onboarding verification</p><p class="text-xs text-text-muted">${ssrInterpolate(onboardingDone.value ? "Identity verification completed." : "Complete your scans on next sign-in.")}</p></div><div class="grid gap-3 sm:grid-cols-2"><!--[-->`);
      ssrRenderList([
        ["ID scan", unref(IconId)],
        ["Face scan", unref(IconUser)]
      ], (item) => {
        _push(`<div class="flex items-center gap-3 rounded-md border p-3"><span class="grid h-9 w-9 place-items-center rounded-lg bg-primary-soft text-primary">`);
        ssrRenderVNode(_push, createVNode(resolveDynamicComponent(item[1]), { size: 18 }, null), _parent);
        _push(`</span><div class="flex-1"><p class="text-sm font-medium">${ssrInterpolate(item[0])}</p><p class="text-micro text-text-muted">${ssrInterpolate(onboardingDone.value ? "Verified" : "Pending")}</p></div><span class="${ssrRenderClass([
          "inline-flex items-center gap-1 rounded-full px-2 py-1 text-micro font-medium",
          onboardingDone.value ? "bg-success-soft text-success" : "bg-warning-soft text-warning"
        ])}">`);
        if (onboardingDone.value) {
          _push(ssrRenderComponent(unref(IconCheck), { size: 12 }, null, _parent));
        } else {
          _push(ssrRenderComponent(unref(IconClock), { size: 12 }, null, _parent));
        }
        _push(`${ssrInterpolate(onboardingDone.value ? "Completed" : "Pending")}</span></div>`);
      });
      _push(`<!--]--></div></section><div class="grid gap-4 lg:grid-cols-2"><!--[-->`);
      ssrRenderList([
        { title: "Personal", fields: personal },
        { title: "Academic", fields: academic }
      ], (group) => {
        _push(`<section class="space-y-3 rounded-lg border bg-surface p-4"><h2 class="text-sm font-semibold">${ssrInterpolate(group.title)}</h2><!--[-->`);
        ssrRenderList(group.fields, (field) => {
          _push(`<label class="block"><span class="mb-1.5 block text-xs font-medium">${ssrInterpolate(field[0])}</span><input${ssrRenderAttr("value", field[1])} disabled class="h-9 w-full rounded-md border bg-surface-muted px-3 text-sm text-text-muted"></label>`);
        });
        _push(`<!--]--></section>`);
      });
      _push(`<!--]--></div></div>`);
    };
  }
});
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/modules/profile/Index.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
