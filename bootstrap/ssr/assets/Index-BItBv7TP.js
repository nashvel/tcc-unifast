import { defineComponent, resolveComponent, withCtx, createTextVNode, openBlock, createBlock, Fragment, renderList, createVNode, toDisplayString, useSSRContext } from "vue";
import { ssrRenderAttrs, ssrRenderComponent, ssrRenderList, ssrInterpolate } from "vue/server-renderer";
import { _ as _sfc_main$1 } from "./PageHeader-DV7ufis9.js";
import { _ as _sfc_main$2 } from "./DataTable-BXM8pciO.js";
const _sfc_main = /* @__PURE__ */ defineComponent({
  __name: "Index",
  __ssrInlineRender: true,
  setup(__props) {
    const rows = [
      [1, "2024-00182", "Maria Angela Santos", "5 / 5", "Eligible"],
      [2, "2024-00194", "John Paul Ramirez", "4 / 5", "For evaluation"],
      [3, "2024-00207", "Nicole Anne Flores", "5 / 5", "Eligible"],
      [4, "2024-00231", "Christian Dela Cruz", "3 / 5", "Ineligible"]
    ];
    return (_ctx, _push, _parent, _attrs) => {
      const _component_RouterLink = resolveComponent("RouterLink");
      _push(`<div${ssrRenderAttrs(_attrs)}>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        title: "Eligibility Evaluation",
        description: "Rules-based decisions for the current period."
      }, null, _parent));
      _push(`<section class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4"><!--[-->`);
      ssrRenderList([
        ["Evaluated", "2,486"],
        ["Eligible", "2,184"],
        ["For evaluation", "184"],
        ["Ineligible", "118"]
      ], (i) => {
        _push(`<article class="rounded-lg border bg-surface p-4"><p class="text-xs text-text-muted">${ssrInterpolate(i[0])}</p><p class="mt-1 text-xl font-semibold">${ssrInterpolate(i[1])}</p></article>`);
      });
      _push(`<!--]--></section>`);
      _push(ssrRenderComponent(_sfc_main$2, { headings: ["Student #", "Grantee", "Criteria passed", "Decision", ""] }, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<!--[-->`);
            ssrRenderList(rows, (r) => {
              _push2(`<tr${_scopeId}><td class="px-3 py-3 font-mono"${_scopeId}>${ssrInterpolate(r[1])}</td><td class="px-3 py-3 font-medium"${_scopeId}>${ssrInterpolate(r[2])}</td><td class="px-3 py-3"${_scopeId}>${ssrInterpolate(r[3])}</td><td class="px-3 py-3"${_scopeId}><span class="rounded-full bg-success-soft px-2 py-0.5 text-micro text-success"${_scopeId}>${ssrInterpolate(r[4])}</span></td><td class="px-3 py-3 text-right"${_scopeId}>`);
              _push2(ssrRenderComponent(_component_RouterLink, {
                to: `/app/eligibility/${r[0]}`,
                class: "text-primary"
              }, {
                default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                  if (_push3) {
                    _push3(`Evaluate`);
                  } else {
                    return [
                      createTextVNode("Evaluate")
                    ];
                  }
                }),
                _: 2
              }, _parent2, _scopeId));
              _push2(`</td></tr>`);
            });
            _push2(`<!--]-->`);
          } else {
            return [
              (openBlock(), createBlock(Fragment, null, renderList(rows, (r) => {
                return createVNode("tr", {
                  key: r[0]
                }, [
                  createVNode("td", { class: "px-3 py-3 font-mono" }, toDisplayString(r[1]), 1),
                  createVNode("td", { class: "px-3 py-3 font-medium" }, toDisplayString(r[2]), 1),
                  createVNode("td", { class: "px-3 py-3" }, toDisplayString(r[3]), 1),
                  createVNode("td", { class: "px-3 py-3" }, [
                    createVNode("span", { class: "rounded-full bg-success-soft px-2 py-0.5 text-micro text-success" }, toDisplayString(r[4]), 1)
                  ]),
                  createVNode("td", { class: "px-3 py-3 text-right" }, [
                    createVNode(_component_RouterLink, {
                      to: `/app/eligibility/${r[0]}`,
                      class: "text-primary"
                    }, {
                      default: withCtx(() => [
                        createTextVNode("Evaluate")
                      ]),
                      _: 1
                    }, 8, ["to"])
                  ])
                ]);
              }), 64))
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(`</div>`);
    };
  }
});
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/modules/eligibility/Index.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
