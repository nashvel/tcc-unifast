import { defineComponent, ref, onMounted, withCtx, openBlock, createBlock, Fragment, renderList, createVNode, toDisplayString, createCommentVNode, useSSRContext } from "vue";
import { ssrRenderAttrs, ssrRenderComponent, ssrRenderList, ssrInterpolate } from "vue/server-renderer";
import { _ as _sfc_main$1 } from "./PageHeader-DV7ufis9.js";
import { _ as _sfc_main$2 } from "./DataTable-BXM8pciO.js";
const _sfc_main = /* @__PURE__ */ defineComponent({
  __name: "Index",
  __ssrInlineRender: true,
  setup(__props) {
    const logs = ref([]);
    onMounted(async () => {
      const r = await fetch("/api/demo-audit-logs");
      logs.value = (await r.json()).data || [];
    });
    return (_ctx, _push, _parent, _attrs) => {
      _push(`<div${ssrRenderAttrs(_attrs)}>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        title: "Audit Trail",
        description: "Live document submission and staff-review activity."
      }, null, _parent));
      _push(ssrRenderComponent(_sfc_main$2, { headings: ["Timestamp", "User", "Role", "Action", "Module", "Target", "IP"] }, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<!--[-->`);
            ssrRenderList(logs.value, (log) => {
              _push2(`<tr${_scopeId}><td class="px-3 py-3 text-text-muted"${_scopeId}>${ssrInterpolate(new Date(log.created_at).toLocaleString())}</td><td class="px-3 py-3 font-medium"${_scopeId}>${ssrInterpolate(log.actor)}</td><td class="px-3 py-3 text-text-muted"${_scopeId}>${ssrInterpolate(log.role)}</td><td class="px-3 py-3"${_scopeId}>${ssrInterpolate(log.action.replaceAll("_", " "))}</td><td class="px-3 py-3 text-text-muted"${_scopeId}>${ssrInterpolate(log.module)}</td><td class="px-3 py-3 text-text-muted"${_scopeId}>${ssrInterpolate(log.target)}</td><td class="px-3 py-3 font-mono text-text-muted"${_scopeId}>${ssrInterpolate(log.ip_address)}</td></tr>`);
            });
            _push2(`<!--]-->`);
            if (!logs.value.length) {
              _push2(`<tr${_scopeId}><td colspan="7" class="px-3 py-8 text-center text-text-muted"${_scopeId}>No audit events yet.</td></tr>`);
            } else {
              _push2(`<!---->`);
            }
          } else {
            return [
              (openBlock(true), createBlock(Fragment, null, renderList(logs.value, (log) => {
                return openBlock(), createBlock("tr", {
                  key: log.id
                }, [
                  createVNode("td", { class: "px-3 py-3 text-text-muted" }, toDisplayString(new Date(log.created_at).toLocaleString()), 1),
                  createVNode("td", { class: "px-3 py-3 font-medium" }, toDisplayString(log.actor), 1),
                  createVNode("td", { class: "px-3 py-3 text-text-muted" }, toDisplayString(log.role), 1),
                  createVNode("td", { class: "px-3 py-3" }, toDisplayString(log.action.replaceAll("_", " ")), 1),
                  createVNode("td", { class: "px-3 py-3 text-text-muted" }, toDisplayString(log.module), 1),
                  createVNode("td", { class: "px-3 py-3 text-text-muted" }, toDisplayString(log.target), 1),
                  createVNode("td", { class: "px-3 py-3 font-mono text-text-muted" }, toDisplayString(log.ip_address), 1)
                ]);
              }), 128)),
              !logs.value.length ? (openBlock(), createBlock("tr", { key: 0 }, [
                createVNode("td", {
                  colspan: "7",
                  class: "px-3 py-8 text-center text-text-muted"
                }, "No audit events yet.")
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/modules/audit/Index.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
