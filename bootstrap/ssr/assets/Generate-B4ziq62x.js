import { defineComponent, ref, resolveComponent, withCtx, unref, createVNode, createTextVNode, useSSRContext } from "vue";
import { ssrRenderAttrs, ssrRenderComponent, ssrIncludeBooleanAttr, ssrLooseContain, ssrLooseEqual, ssrInterpolate } from "vue/server-renderer";
import { IconArrowLeft, IconArrowRight } from "@tabler/icons-vue";
import { _ as _sfc_main$1 } from "./PageHeader-DV7ufis9.js";
const _sfc_main = /* @__PURE__ */ defineComponent({
  __name: "Generate",
  __ssrInlineRender: true,
  setup(__props) {
    const type = ref("Grantee masterlist");
    const period = ref("AY 2025–2026");
    const format = ref("PDF");
    return (_ctx, _push, _parent, _attrs) => {
      const _component_RouterLink = resolveComponent("RouterLink");
      _push(`<div${ssrRenderAttrs(_attrs)}>`);
      _push(ssrRenderComponent(_component_RouterLink, {
        to: "/app/reports",
        class: "mb-3 inline-flex items-center gap-1 text-xs text-text-muted"
      }, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(ssrRenderComponent(unref(IconArrowLeft), { size: 14 }, null, _parent2, _scopeId));
            _push2(`Reports`);
          } else {
            return [
              createVNode(unref(IconArrowLeft), { size: 14 }),
              createTextVNode("Reports")
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(ssrRenderComponent(_sfc_main$1, {
        title: "Generate Report",
        description: "Select parameters and preview before export."
      }, null, _parent));
      _push(`<section class="max-w-3xl rounded-lg border bg-surface p-5"><div class="grid gap-4 sm:grid-cols-2"><label class="text-xs font-medium">Report type<select class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm"><option${ssrIncludeBooleanAttr(Array.isArray(type.value) ? ssrLooseContain(type.value, null) : ssrLooseEqual(type.value, null)) ? " selected" : ""}>Grantee masterlist</option><option${ssrIncludeBooleanAttr(Array.isArray(type.value) ? ssrLooseContain(type.value, null) : ssrLooseEqual(type.value, null)) ? " selected" : ""}>Document validation</option><option${ssrIncludeBooleanAttr(Array.isArray(type.value) ? ssrLooseContain(type.value, null) : ssrLooseEqual(type.value, null)) ? " selected" : ""}>Eligibility summary</option><option${ssrIncludeBooleanAttr(Array.isArray(type.value) ? ssrLooseContain(type.value, null) : ssrLooseEqual(type.value, null)) ? " selected" : ""}>Academic compliance</option></select></label><label class="text-xs font-medium">Academic period<select class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm"><option${ssrIncludeBooleanAttr(Array.isArray(period.value) ? ssrLooseContain(period.value, null) : ssrLooseEqual(period.value, null)) ? " selected" : ""}>AY 2025–2026</option><option${ssrIncludeBooleanAttr(Array.isArray(period.value) ? ssrLooseContain(period.value, null) : ssrLooseEqual(period.value, null)) ? " selected" : ""}>AY 2024–2025</option></select></label><label class="text-xs font-medium">Batch<select class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm"><option>All batches</option><option>TES 2025 — Batch 04</option></select></label><label class="text-xs font-medium">Output format<select class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm"><option${ssrIncludeBooleanAttr(Array.isArray(format.value) ? ssrLooseContain(format.value, null) : ssrLooseEqual(format.value, null)) ? " selected" : ""}>PDF</option><option${ssrIncludeBooleanAttr(Array.isArray(format.value) ? ssrLooseContain(format.value, null) : ssrLooseEqual(format.value, null)) ? " selected" : ""}>CSV</option></select></label></div><div class="mt-5 rounded-md bg-surface-muted p-4 text-xs text-text-muted">${ssrInterpolate(type.value)} · ${ssrInterpolate(period.value)} · ${ssrInterpolate(format.value)}</div><div class="mt-5 flex justify-end">`);
      _push(ssrRenderComponent(_component_RouterLink, {
        to: "/app/reports/preview",
        class: "inline-flex items-center gap-1.5 rounded-md bg-primary px-4 py-2 text-xs text-white"
      }, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`Preview report`);
            _push2(ssrRenderComponent(unref(IconArrowRight), { size: 14 }, null, _parent2, _scopeId));
          } else {
            return [
              createTextVNode("Preview report"),
              createVNode(unref(IconArrowRight), { size: 14 })
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(`</div></section></div>`);
    };
  }
});
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/modules/reports/Generate.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
