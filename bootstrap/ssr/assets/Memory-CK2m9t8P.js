import { defineComponent, ref, computed, unref, withCtx, openBlock, createBlock, Fragment, renderList, createVNode, toDisplayString, createTextVNode, createCommentVNode, useSSRContext } from "vue";
import { ssrRenderAttrs, ssrRenderComponent, ssrRenderList, ssrInterpolate, ssrRenderAttr } from "vue/server-renderer";
import { IconDatabase, IconSearch, IconShieldCheck, IconTrash } from "@tabler/icons-vue";
import { _ as _sfc_main$1 } from "./PageHeader-DV7ufis9.js";
import { _ as _sfc_main$2 } from "./DataTable-BXM8pciO.js";
const _sfc_main = /* @__PURE__ */ defineComponent({
  __name: "Memory",
  __ssrInlineRender: true,
  setup(__props) {
    const query = ref("");
    const records = ref([
      [
        "SEC-2026-0184",
        "Repeated failed login pattern",
        "Authentication",
        "System Administrator",
        "July 11, 2026",
        "Active"
      ],
      [
        "SEC-2026-0172",
        "Document hash duplicate",
        "File integrity",
        "Maria Santos",
        "July 9, 2026",
        "Retained"
      ],
      [
        "SEC-2026-0158",
        "Unusual export volume",
        "Data access",
        "Staff Account",
        "July 5, 2026",
        "Reviewed"
      ]
    ]);
    const rows = computed(
      () => records.value.filter(
        (record) => record.join(" ").toLowerCase().includes(query.value.toLowerCase())
      )
    );
    return (_ctx, _push, _parent, _attrs) => {
      _push(`<div${ssrRenderAttrs(_attrs)}>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        title: "Security Memory",
        description: "Review retained security signals and resolved detection context."
      }, null, _parent));
      _push(`<section class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4"><!--[-->`);
      ssrRenderList([
        ["Retained signals", "184"],
        ["Active patterns", "12"],
        ["Reviewed", "159"],
        ["Retention", "365 days"]
      ], (item) => {
        _push(`<article class="rounded-lg border bg-surface p-4">`);
        _push(ssrRenderComponent(unref(IconDatabase), {
          size: 17,
          class: "text-primary"
        }, null, _parent));
        _push(`<p class="mt-3 text-xs text-text-muted">${ssrInterpolate(item[0])}</p><p class="mt-1 text-lg font-semibold">${ssrInterpolate(item[1])}</p></article>`);
      });
      _push(`<!--]--></section><div class="relative mb-3 max-w-xl">`);
      _push(ssrRenderComponent(unref(IconSearch), {
        size: 14,
        class: "absolute left-3 top-1/2 -translate-y-1/2 text-text-soft"
      }, null, _parent));
      _push(`<input${ssrRenderAttr("value", query.value)} class="h-9 w-full rounded-md border pl-9 pr-3 text-xs" placeholder="Search retained security context"></div>`);
      _push(ssrRenderComponent(_sfc_main$2, { headings: ["Signal ID", "Summary", "Category", "Subject", "Last observed", "Status", ""] }, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<!--[-->`);
            ssrRenderList(rows.value, (record) => {
              _push2(`<tr${_scopeId}><td class="px-3 py-3 font-mono"${_scopeId}>${ssrInterpolate(record[0])}</td><td class="px-3 py-3 font-medium"${_scopeId}>${ssrInterpolate(record[1])}</td><td class="px-3 py-3 text-text-muted"${_scopeId}>${ssrInterpolate(record[2])}</td><td class="px-3 py-3"${_scopeId}>${ssrInterpolate(record[3])}</td><td class="px-3 py-3 text-text-muted"${_scopeId}>${ssrInterpolate(record[4])}</td><td class="px-3 py-3"${_scopeId}><span class="inline-flex items-center gap-1 rounded-full bg-success-soft px-2 py-0.5 text-micro text-success"${_scopeId}>`);
              _push2(ssrRenderComponent(unref(IconShieldCheck), { size: 11 }, null, _parent2, _scopeId));
              _push2(`${ssrInterpolate(record[5])}</span></td><td class="px-3 py-3 text-right"${_scopeId}><button class="text-text-soft hover:text-danger" aria-label="Remove retained signal"${_scopeId}>`);
              _push2(ssrRenderComponent(unref(IconTrash), { size: 14 }, null, _parent2, _scopeId));
              _push2(`</button></td></tr>`);
            });
            _push2(`<!--]-->`);
            if (!rows.value.length) {
              _push2(`<tr${_scopeId}><td colspan="7" class="p-8 text-center text-text-muted"${_scopeId}> No security memory records found. </td></tr>`);
            } else {
              _push2(`<!---->`);
            }
          } else {
            return [
              (openBlock(true), createBlock(Fragment, null, renderList(rows.value, (record) => {
                return openBlock(), createBlock("tr", {
                  key: record[0]
                }, [
                  createVNode("td", { class: "px-3 py-3 font-mono" }, toDisplayString(record[0]), 1),
                  createVNode("td", { class: "px-3 py-3 font-medium" }, toDisplayString(record[1]), 1),
                  createVNode("td", { class: "px-3 py-3 text-text-muted" }, toDisplayString(record[2]), 1),
                  createVNode("td", { class: "px-3 py-3" }, toDisplayString(record[3]), 1),
                  createVNode("td", { class: "px-3 py-3 text-text-muted" }, toDisplayString(record[4]), 1),
                  createVNode("td", { class: "px-3 py-3" }, [
                    createVNode("span", { class: "inline-flex items-center gap-1 rounded-full bg-success-soft px-2 py-0.5 text-micro text-success" }, [
                      createVNode(unref(IconShieldCheck), { size: 11 }),
                      createTextVNode(toDisplayString(record[5]), 1)
                    ])
                  ]),
                  createVNode("td", { class: "px-3 py-3 text-right" }, [
                    createVNode("button", {
                      class: "text-text-soft hover:text-danger",
                      "aria-label": "Remove retained signal"
                    }, [
                      createVNode(unref(IconTrash), { size: 14 })
                    ])
                  ])
                ]);
              }), 128)),
              !rows.value.length ? (openBlock(), createBlock("tr", { key: 0 }, [
                createVNode("td", {
                  colspan: "7",
                  class: "p-8 text-center text-text-muted"
                }, " No security memory records found. ")
              ])) : createCommentVNode("", true)
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/modules/security/Memory.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
