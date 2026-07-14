import { defineComponent, resolveComponent, withCtx, unref, createVNode, createTextVNode, openBlock, createBlock, Fragment, renderList, toDisplayString, useSSRContext } from "vue";
import { ssrRenderAttrs, ssrRenderComponent, ssrRenderList, ssrInterpolate } from "vue/server-renderer";
import { IconArrowLeft, IconPrinter, IconDownload } from "@tabler/icons-vue";
import { _ as _sfc_main$1 } from "./PageHeader-DV7ufis9.js";
import { _ as _sfc_main$2 } from "./DataTable-BXM8pciO.js";
const _sfc_main = /* @__PURE__ */ defineComponent({
  __name: "Preview",
  __ssrInlineRender: true,
  setup(__props) {
    const rows = [
      ["2024-00182", "Maria Angela Santos", "BS Information Technology", "Eligible", "Active"],
      [
        "2024-00194",
        "John Paul Ramirez",
        "BS Business Administration",
        "For evaluation",
        "Pending activation"
      ],
      ["2024-00207", "Nicole Anne Flores", "BS Education", "Eligible", "Active"]
    ];
    return (_ctx, _push, _parent, _attrs) => {
      const _component_RouterLink = resolveComponent("RouterLink");
      _push(`<div${ssrRenderAttrs(_attrs)}>`);
      _push(ssrRenderComponent(_component_RouterLink, {
        to: "/app/reports/generate",
        class: "mb-3 inline-flex items-center gap-1 text-xs text-text-muted"
      }, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(ssrRenderComponent(unref(IconArrowLeft), { size: 14 }, null, _parent2, _scopeId));
            _push2(`Report parameters`);
          } else {
            return [
              createVNode(unref(IconArrowLeft), { size: 14 }),
              createTextVNode("Report parameters")
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(ssrRenderComponent(_sfc_main$1, {
        title: "Report Preview",
        description: "TES Grantee Masterlist — AY 2025–2026"
      }, {
        actions: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<button class="inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-xs"${_scopeId}>`);
            _push2(ssrRenderComponent(unref(IconPrinter), { size: 14 }, null, _parent2, _scopeId));
            _push2(`Print</button><button class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-xs text-white"${_scopeId}>`);
            _push2(ssrRenderComponent(unref(IconDownload), { size: 14 }, null, _parent2, _scopeId));
            _push2(`Export PDF </button>`);
          } else {
            return [
              createVNode("button", { class: "inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-xs" }, [
                createVNode(unref(IconPrinter), { size: 14 }),
                createTextVNode("Print")
              ]),
              createVNode("button", { class: "inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-xs text-white" }, [
                createVNode(unref(IconDownload), { size: 14 }),
                createTextVNode("Export PDF ")
              ])
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(`<article class="rounded-lg border bg-white p-6 text-black shadow-sm"><header class="border-b pb-5 text-center"><p class="text-xs uppercase tracking-widest">Tagoloan Community College</p><h2 class="mt-2 text-xl font-semibold">TES Grantee Masterlist</h2><p class="mt-1 text-xs text-gray-500">Academic Year 2025–2026 · All batches</p></header><div class="my-5 grid grid-cols-3 gap-3 text-center"><div><p class="text-xl font-semibold">2,486</p><p class="text-xs text-gray-500">Total grantees</p></div><div><p class="text-xl font-semibold">2,184</p><p class="text-xs text-gray-500">Eligible</p></div><div><p class="text-xl font-semibold">2,301</p><p class="text-xs text-gray-500">Active</p></div></div>`);
      _push(ssrRenderComponent(_sfc_main$2, { headings: ["Student #", "Name", "Program", "Eligibility", "Account"] }, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<!--[-->`);
            ssrRenderList(rows, (r) => {
              _push2(`<tr${_scopeId}><td class="px-3 py-3 font-mono"${_scopeId}>${ssrInterpolate(r[0])}</td><td class="px-3 py-3 font-medium"${_scopeId}>${ssrInterpolate(r[1])}</td><td class="px-3 py-3"${_scopeId}>${ssrInterpolate(r[2])}</td><td class="px-3 py-3"${_scopeId}>${ssrInterpolate(r[3])}</td><td class="px-3 py-3"${_scopeId}>${ssrInterpolate(r[4])}</td></tr>`);
            });
            _push2(`<!--]-->`);
          } else {
            return [
              (openBlock(), createBlock(Fragment, null, renderList(rows, (r) => {
                return createVNode("tr", {
                  key: r[0]
                }, [
                  createVNode("td", { class: "px-3 py-3 font-mono" }, toDisplayString(r[0]), 1),
                  createVNode("td", { class: "px-3 py-3 font-medium" }, toDisplayString(r[1]), 1),
                  createVNode("td", { class: "px-3 py-3" }, toDisplayString(r[2]), 1),
                  createVNode("td", { class: "px-3 py-3" }, toDisplayString(r[3]), 1),
                  createVNode("td", { class: "px-3 py-3" }, toDisplayString(r[4]), 1)
                ]);
              }), 64))
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(`<footer class="mt-6 border-t pt-4 text-center text-xs text-gray-500"> Generated July 12, 2026 · UniFAST TES Grantee Management </footer></article></div>`);
    };
  }
});
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/modules/reports/Preview.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
