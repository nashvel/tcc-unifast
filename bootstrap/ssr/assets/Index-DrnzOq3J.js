import { defineComponent, withCtx, unref, createVNode, createTextVNode, resolveDynamicComponent, openBlock, createBlock, Fragment, renderList, toDisplayString, useSSRContext } from "vue";
import { ssrRenderAttrs, ssrRenderComponent, ssrRenderList, ssrRenderVNode, ssrInterpolate } from "vue/server-renderer";
import { IconDownload, IconAlertTriangle, IconShieldCheck, IconInfoCircle } from "@tabler/icons-vue";
import { f as findings } from "./mockAdmin-BGBs67j0.js";
import { _ as _sfc_main$1 } from "./PageHeader-DV7ufis9.js";
import { _ as _sfc_main$2 } from "./DataTable-BXM8pciO.js";
const _sfc_main = /* @__PURE__ */ defineComponent({
  __name: "Index",
  __ssrInlineRender: true,
  setup(__props) {
    const stats = [
      ["Open", 0, IconAlertTriangle],
      ["Fixed", 3, IconShieldCheck],
      ["Ignored", 0, IconInfoCircle],
      ["Scanners", 3, IconShieldCheck]
    ];
    return (_ctx, _push, _parent, _attrs) => {
      _push(`<div${ssrRenderAttrs(_attrs)}>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        title: "Security Findings",
        description: "Findings from every scanner wired into this project, including the workspace connector scan (Wiz)."
      }, {
        actions: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<button class="inline-flex h-9 items-center gap-1 rounded-md border px-3 text-xs"${_scopeId}>`);
            _push2(ssrRenderComponent(unref(IconDownload), { size: 14 }, null, _parent2, _scopeId));
            _push2(`Export CSV </button>`);
          } else {
            return [
              createVNode("button", { class: "inline-flex h-9 items-center gap-1 rounded-md border px-3 text-xs" }, [
                createVNode(unref(IconDownload), { size: 14 }),
                createTextVNode("Export CSV ")
              ])
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(`<div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-4"><!--[-->`);
      ssrRenderList(stats, (stat) => {
        _push(`<article class="flex items-center gap-3 rounded-lg border bg-surface p-3"><span class="grid h-9 w-9 place-items-center rounded-md bg-surface-muted text-primary">`);
        ssrRenderVNode(_push, createVNode(resolveDynamicComponent(stat[2]), { size: 18 }, null), _parent);
        _push(`</span><div><p class="text-micro uppercase text-text-muted">${ssrInterpolate(stat[0])}</p><p class="text-lg font-semibold">${ssrInterpolate(stat[1])}</p></div></article>`);
      });
      _push(`<!--]--></div><div class="mb-4 grid grid-cols-2 gap-2 rounded-lg border bg-surface p-3 md:grid-cols-5"><!--[-->`);
      ssrRenderList(["All scanners", "Any state", "Any severity"], (value) => {
        _push(`<select class="h-9 rounded-md border bg-surface px-3 text-xs"><option>${ssrInterpolate(value)}</option></select>`);
      });
      _push(`<!--]--><input placeholder="Search findings…" class="h-9 rounded-md border px-3 text-xs md:col-span-2"></div>`);
      _push(ssrRenderComponent(_sfc_main$2, { headings: ["Severity", "Finding", "Scanner", "State", "Detected", ""] }, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<!--[-->`);
            ssrRenderList(unref(findings), (finding) => {
              _push2(`<tr${_scopeId}><td class="px-3 py-3 text-warning"${_scopeId}>`);
              _push2(ssrRenderComponent(unref(IconAlertTriangle), {
                size: 12,
                class: "mr-1 inline"
              }, null, _parent2, _scopeId));
              _push2(`${ssrInterpolate(finding[0])}</td><td class="px-3 py-3 font-medium"${_scopeId}>${ssrInterpolate(finding[1])}</td><td class="px-3 py-3 text-text-muted"${_scopeId}>${ssrInterpolate(finding[2])}</td><td class="px-3 py-3 text-success"${_scopeId}>${ssrInterpolate(finding[3])}</td><td class="px-3 py-3 text-text-muted"${_scopeId}>${ssrInterpolate(finding[4])}</td><td class="px-3 py-3 text-primary"${_scopeId}>View</td></tr>`);
            });
            _push2(`<!--]-->`);
          } else {
            return [
              (openBlock(true), createBlock(Fragment, null, renderList(unref(findings), (finding) => {
                return openBlock(), createBlock("tr", {
                  key: finding[1]
                }, [
                  createVNode("td", { class: "px-3 py-3 text-warning" }, [
                    createVNode(unref(IconAlertTriangle), {
                      size: 12,
                      class: "mr-1 inline"
                    }),
                    createTextVNode(toDisplayString(finding[0]), 1)
                  ]),
                  createVNode("td", { class: "px-3 py-3 font-medium" }, toDisplayString(finding[1]), 1),
                  createVNode("td", { class: "px-3 py-3 text-text-muted" }, toDisplayString(finding[2]), 1),
                  createVNode("td", { class: "px-3 py-3 text-success" }, toDisplayString(finding[3]), 1),
                  createVNode("td", { class: "px-3 py-3 text-text-muted" }, toDisplayString(finding[4]), 1),
                  createVNode("td", { class: "px-3 py-3 text-primary" }, "View")
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/modules/security/Index.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
