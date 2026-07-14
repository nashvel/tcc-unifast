import { defineComponent, ref, resolveComponent, withCtx, unref, createVNode, createTextVNode, resolveDynamicComponent, useSSRContext } from "vue";
import { ssrRenderAttrs, ssrRenderComponent, ssrRenderList, ssrInterpolate, ssrRenderClass, ssrRenderVNode } from "vue/server-renderer";
import { IconArrowLeft, IconCheck, IconX } from "@tabler/icons-vue";
import { _ as _sfc_main$1 } from "./PageHeader-DV7ufis9.js";
const _sfc_main = /* @__PURE__ */ defineComponent({
  __name: "Detail",
  __ssrInlineRender: true,
  setup(__props) {
    const decision = ref("");
    const criteria = [
      ["Enrolled at an eligible institution", true],
      ["Filipino citizen", true],
      ["Meets household income threshold", true],
      ["No other government scholarship", true],
      ["Academic retention requirement", false]
    ];
    return (_ctx, _push, _parent, _attrs) => {
      const _component_RouterLink = resolveComponent("RouterLink");
      _push(`<div${ssrRenderAttrs(_attrs)}>`);
      _push(ssrRenderComponent(_component_RouterLink, {
        to: "/app/eligibility",
        class: "mb-3 inline-flex items-center gap-1 text-xs text-text-muted"
      }, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(ssrRenderComponent(unref(IconArrowLeft), { size: 14 }, null, _parent2, _scopeId));
            _push2(`Eligibility queue`);
          } else {
            return [
              createVNode(unref(IconArrowLeft), { size: 14 }),
              createTextVNode("Eligibility queue")
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(ssrRenderComponent(_sfc_main$1, {
        title: "Eligibility — Maria Angela Santos",
        description: "2024-00182 · BS Information Technology"
      }, null, _parent));
      _push(`<section class="grid gap-4 lg:grid-cols-[2fr_1fr]"><div class="rounded-lg border bg-surface"><header class="border-b p-4"><h2 class="font-semibold">Eligibility criteria</h2></header><!--[-->`);
      ssrRenderList(criteria, (c) => {
        _push(`<div class="flex items-center justify-between border-b p-4 last:border-0"><span class="text-sm">${ssrInterpolate(c[0])}</span><span class="${ssrRenderClass(c[1] ? "text-success" : "text-danger")}">`);
        ssrRenderVNode(_push, createVNode(resolveDynamicComponent(c[1] ? unref(IconCheck) : unref(IconX)), { size: 18 }, null), _parent);
        _push(`</span></div>`);
      });
      _push(`<!--]--></div><aside class="rounded-lg border bg-surface p-4"><h2 class="text-sm font-semibold">Staff decision</h2><p class="mt-2 text-xs leading-5 text-text-muted"> Record a final decision. This mock action is retained on this screen only. </p><textarea class="mt-4 min-h-24 w-full rounded-md border p-3 text-xs" placeholder="Decision notes"></textarea><div class="mt-3 grid grid-cols-2 gap-2"><button class="rounded-md border border-danger px-3 py-2 text-xs text-danger"> Ineligible</button><button class="rounded-md bg-primary px-3 py-2 text-xs text-white"> Eligible </button></div>`);
      if (decision.value) {
        _push(`<p class="mt-3 rounded-md bg-primary-soft p-2 text-xs text-primary"> Mock decision: ${ssrInterpolate(decision.value)}</p>`);
      } else {
        _push(`<!---->`);
      }
      _push(`</aside></section></div>`);
    };
  }
});
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/modules/eligibility/Detail.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
