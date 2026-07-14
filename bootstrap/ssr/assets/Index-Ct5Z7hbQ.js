import { defineComponent, resolveComponent, withCtx, createVNode, resolveDynamicComponent, openBlock, createBlock, toDisplayString, useSSRContext } from "vue";
import { ssrRenderAttrs, ssrRenderComponent, ssrRenderList, ssrRenderVNode, ssrInterpolate } from "vue/server-renderer";
import { IconUsersGroup, IconFolders, IconFileCheck, IconSchool, IconChecklist, IconHistory, IconReportAnalytics } from "@tabler/icons-vue";
import { _ as _sfc_main$1 } from "./PageHeader-DV7ufis9.js";
const _sfc_main = /* @__PURE__ */ defineComponent({
  __name: "Index",
  __ssrInlineRender: true,
  setup(__props) {
    const reports = [
      ["Grantee List", IconUsersGroup, "Complete grantee roster with personal & academic details."],
      ["Batch Report", IconFolders, "Per-batch summary, progress, and outcomes."],
      ["Document Validation", IconFileCheck, "Validation outcomes by document type and risk."],
      ["Academic Tracking", IconSchool, "GWA trends, retention, and at-risk grantees."],
      ["Eligibility Report", IconChecklist, "Eligible/ineligible distribution and reasons."],
      ["Audit Trail", IconHistory, "Filtered audit logs export."],
      ["Office Report", IconReportAnalytics, "Consolidated UniFAST Office performance metrics."]
    ];
    return (_ctx, _push, _parent, _attrs) => {
      const _component_RouterLink = resolveComponent("RouterLink");
      _push(`<div${ssrRenderAttrs(_attrs)}>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        title: "Reports",
        description: "Generate, preview, and export operational reports."
      }, null, _parent));
      _push(`<section class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3"><!--[-->`);
      ssrRenderList(reports, (report) => {
        _push(ssrRenderComponent(_component_RouterLink, {
          key: report[0],
          to: "/app/reports/generate",
          class: "rounded-lg border bg-surface p-4 hover:border-primary/40 hover:bg-primary-soft/10"
        }, {
          default: withCtx((_, _push2, _parent2, _scopeId) => {
            if (_push2) {
              _push2(`<div class="mb-2 grid h-9 w-9 place-items-center rounded-md bg-primary-soft text-primary"${_scopeId}>`);
              ssrRenderVNode(_push2, createVNode(resolveDynamicComponent(report[1]), { size: 18 }, null), _parent2, _scopeId);
              _push2(`</div><p class="text-sm font-semibold"${_scopeId}>${ssrInterpolate(report[0])}</p><p class="mt-1 text-xs text-text-muted"${_scopeId}>${ssrInterpolate(report[2])}</p>`);
            } else {
              return [
                createVNode("div", { class: "mb-2 grid h-9 w-9 place-items-center rounded-md bg-primary-soft text-primary" }, [
                  (openBlock(), createBlock(resolveDynamicComponent(report[1]), { size: 18 }))
                ]),
                createVNode("p", { class: "text-sm font-semibold" }, toDisplayString(report[0]), 1),
                createVNode("p", { class: "mt-1 text-xs text-text-muted" }, toDisplayString(report[2]), 1)
              ];
            }
          }),
          _: 2
        }, _parent));
      });
      _push(`<!--]--></section></div>`);
    };
  }
});
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/modules/reports/Index.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
