import { defineComponent, resolveComponent, withCtx, unref, createVNode, createTextVNode, useSSRContext } from "vue";
import { ssrRenderAttrs, ssrRenderComponent, ssrRenderList, ssrInterpolate } from "vue/server-renderer";
import { IconArrowLeft, IconSchool, IconCheck } from "@tabler/icons-vue";
import { _ as _sfc_main$1 } from "./PageHeader-DV7ufis9.js";
const _sfc_main = /* @__PURE__ */ defineComponent({
  __name: "Detail",
  __ssrInlineRender: true,
  setup(__props) {
    const terms = [
      ["1st Semester AY 2025–2026", "1.42", "21 units", "Verified"],
      ["2nd Semester AY 2024–2025", "1.58", "24 units", "Verified"],
      ["1st Semester AY 2024–2025", "1.66", "21 units", "Verified"]
    ];
    return (_ctx, _push, _parent, _attrs) => {
      const _component_RouterLink = resolveComponent("RouterLink");
      _push(`<div${ssrRenderAttrs(_attrs)}>`);
      _push(ssrRenderComponent(_component_RouterLink, {
        to: "/app/academic",
        class: "mb-3 inline-flex items-center gap-1 text-xs text-text-muted"
      }, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(ssrRenderComponent(unref(IconArrowLeft), { size: 14 }, null, _parent2, _scopeId));
            _push2(`Academic records`);
          } else {
            return [
              createVNode(unref(IconArrowLeft), { size: 14 }),
              createTextVNode("Academic records")
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(ssrRenderComponent(_sfc_main$1, {
        title: "Maria Angela Santos",
        description: "2024-00182 · BS Information Technology"
      }, null, _parent));
      _push(`<section class="grid gap-4 lg:grid-cols-[2fr_1fr]"><div class="rounded-lg border bg-surface"><header class="border-b p-4"><h2 class="font-semibold">Term history</h2></header><!--[-->`);
      ssrRenderList(terms, (term) => {
        _push(`<div class="flex flex-wrap items-center justify-between gap-3 border-b p-4 last:border-0"><div><p class="text-sm font-medium">${ssrInterpolate(term[0])}</p><p class="mt-1 text-xs text-text-muted">${ssrInterpolate(term[2])}</p></div><div class="text-right"><p class="text-lg font-semibold">${ssrInterpolate(term[1])} GWA</p><span class="text-xs text-success">${ssrInterpolate(term[3])}</span></div></div>`);
      });
      _push(`<!--]--></div><aside class="space-y-4"><article class="rounded-lg border bg-surface p-4">`);
      _push(ssrRenderComponent(unref(IconSchool), {
        size: 20,
        class: "text-primary"
      }, null, _parent));
      _push(`<h2 class="mt-3 text-sm font-semibold">Retention evaluation</h2><p class="mt-2 text-xs leading-5 text-text-muted"> GWA and enrolled units meet the current TES retention requirements. </p><p class="mt-4 flex items-center gap-1 text-xs font-medium text-success">`);
      _push(ssrRenderComponent(unref(IconCheck), { size: 14 }, null, _parent));
      _push(`Compliant </p></article><article class="rounded-lg border bg-surface p-4"><p class="text-xs text-text-muted">Current standing</p><p class="mt-1 text-xl font-semibold">Regular</p></article></aside></section></div>`);
    };
  }
});
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/modules/academic/Detail.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
