import { defineComponent, ref, computed, resolveComponent, unref, withCtx, createTextVNode, openBlock, createBlock, Fragment, renderList, createVNode, toDisplayString, useSSRContext } from "vue";
import { ssrRenderAttrs, ssrRenderComponent, ssrRenderAttr, ssrRenderList, ssrInterpolate } from "vue/server-renderer";
import { IconSearch } from "@tabler/icons-vue";
import { _ as _sfc_main$1 } from "./PageHeader-DV7ufis9.js";
import { _ as _sfc_main$2 } from "./DataTable-BXM8pciO.js";
const _sfc_main = /* @__PURE__ */ defineComponent({
  __name: "Index",
  __ssrInlineRender: true,
  setup(__props) {
    const query = ref("");
    const records = [
      [
        1,
        "2024-00182",
        "Maria Angela Santos",
        "BS Information Technology",
        "1.42",
        "Regular",
        "Compliant"
      ],
      [
        2,
        "2024-00194",
        "John Paul Ramirez",
        "BS Business Administration",
        "1.88",
        "Regular",
        "Compliant"
      ],
      [3, "2024-00207", "Nicole Anne Flores", "BS Education", "2.31", "Regular", "For review"],
      [4, "2024-00231", "Christian Dela Cruz", "BS Criminology", "2.76", "Probation", "At risk"]
    ];
    const filtered = computed(
      () => records.filter(
        (r) => `${r[1]} ${r[2]} ${r[3]}`.toLowerCase().includes(query.value.toLowerCase())
      )
    );
    return (_ctx, _push, _parent, _attrs) => {
      const _component_RouterLink = resolveComponent("RouterLink");
      _push(`<div${ssrRenderAttrs(_attrs)}>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        title: "Academic Records",
        description: "Per-grantee academic tracking and retention rule evaluation."
      }, null, _parent));
      _push(`<div class="relative mb-3 max-w-lg">`);
      _push(ssrRenderComponent(unref(IconSearch), {
        size: 14,
        class: "absolute left-3 top-1/2 -translate-y-1/2 text-text-soft"
      }, null, _parent));
      _push(`<input${ssrRenderAttr("value", query.value)} class="h-9 w-full rounded-md border pl-9 pr-3 text-xs" placeholder="Search academic records"></div>`);
      _push(ssrRenderComponent(_sfc_main$2, { headings: ["Student #", "Grantee", "Program", "GWA", "Standing", "Retention", ""] }, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<!--[-->`);
            ssrRenderList(filtered.value, (r) => {
              _push2(`<tr${_scopeId}><td class="px-3 py-3 font-mono"${_scopeId}>${ssrInterpolate(r[1])}</td><td class="px-3 py-3 font-medium"${_scopeId}>${ssrInterpolate(r[2])}</td><td class="px-3 py-3 text-text-muted"${_scopeId}>${ssrInterpolate(r[3])}</td><td class="px-3 py-3"${_scopeId}>${ssrInterpolate(r[4])}</td><td class="px-3 py-3"${_scopeId}>${ssrInterpolate(r[5])}</td><td class="px-3 py-3"${_scopeId}><span class="rounded-full bg-success-soft px-2 py-0.5 text-micro text-success"${_scopeId}>${ssrInterpolate(r[6])}</span></td><td class="px-3 py-3 text-right"${_scopeId}>`);
              _push2(ssrRenderComponent(_component_RouterLink, {
                to: `/app/academic/${r[0]}`,
                class: "text-primary"
              }, {
                default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                  if (_push3) {
                    _push3(`Review`);
                  } else {
                    return [
                      createTextVNode("Review")
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
              (openBlock(true), createBlock(Fragment, null, renderList(filtered.value, (r) => {
                return openBlock(), createBlock("tr", {
                  key: r[0]
                }, [
                  createVNode("td", { class: "px-3 py-3 font-mono" }, toDisplayString(r[1]), 1),
                  createVNode("td", { class: "px-3 py-3 font-medium" }, toDisplayString(r[2]), 1),
                  createVNode("td", { class: "px-3 py-3 text-text-muted" }, toDisplayString(r[3]), 1),
                  createVNode("td", { class: "px-3 py-3" }, toDisplayString(r[4]), 1),
                  createVNode("td", { class: "px-3 py-3" }, toDisplayString(r[5]), 1),
                  createVNode("td", { class: "px-3 py-3" }, [
                    createVNode("span", { class: "rounded-full bg-success-soft px-2 py-0.5 text-micro text-success" }, toDisplayString(r[6]), 1)
                  ]),
                  createVNode("td", { class: "px-3 py-3 text-right" }, [
                    createVNode(_component_RouterLink, {
                      to: `/app/academic/${r[0]}`,
                      class: "text-primary"
                    }, {
                      default: withCtx(() => [
                        createTextVNode("Review")
                      ]),
                      _: 1
                    }, 8, ["to"])
                  ])
                ]);
              }), 128))
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/modules/academic/Index.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
