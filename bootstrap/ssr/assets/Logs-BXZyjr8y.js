import { defineComponent, resolveComponent, withCtx, unref, createVNode, createTextVNode, openBlock, createBlock, Fragment, renderList, toDisplayString, useSSRContext } from "vue";
import { ssrRenderAttrs, ssrRenderComponent, ssrRenderList, ssrInterpolate } from "vue/server-renderer";
import { IconArrowLeft } from "@tabler/icons-vue";
import { _ as _sfc_main$1 } from "./PageHeader-DV7ufis9.js";
import { _ as _sfc_main$2 } from "./DataTable-BXM8pciO.js";
const _sfc_main = /* @__PURE__ */ defineComponent({
  __name: "Logs",
  __ssrInlineRender: true,
  setup(__props) {
    const logs = [
      ["TES application deadline reminder", "Email", "2,318", "2,301", "17", "May 12, 2026 9:00 AM"],
      ["Scholarship orientation schedule", "In-app", "2,486", "2,486", "0", "May 10, 2026 2:15 PM"],
      ["System maintenance advisory", "SMS", "184", "179", "5", "May 8, 2026 5:30 PM"]
    ];
    return (_ctx, _push, _parent, _attrs) => {
      const _component_RouterLink = resolveComponent("RouterLink");
      _push(`<div${ssrRenderAttrs(_attrs)}>`);
      _push(ssrRenderComponent(_component_RouterLink, {
        to: "/app/announcements",
        class: "mb-3 inline-flex items-center gap-1 text-xs text-text-muted"
      }, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(ssrRenderComponent(unref(IconArrowLeft), { size: 14 }, null, _parent2, _scopeId));
            _push2(`Announcements`);
          } else {
            return [
              createVNode(unref(IconArrowLeft), { size: 14 }),
              createTextVNode("Announcements")
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(ssrRenderComponent(_sfc_main$1, {
        title: "Notification Logs",
        description: "Delivery results per channel for recent announcements."
      }, null, _parent));
      _push(ssrRenderComponent(_sfc_main$2, { headings: ["Announcement", "Channel", "Sent", "Delivered", "Failed", "Timestamp"] }, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<!--[-->`);
            ssrRenderList(logs, (l) => {
              _push2(`<tr${_scopeId}><td class="px-3 py-3 font-medium"${_scopeId}>${ssrInterpolate(l[0])}</td><td class="px-3 py-3"${_scopeId}>${ssrInterpolate(l[1])}</td><td class="px-3 py-3"${_scopeId}>${ssrInterpolate(l[2])}</td><td class="px-3 py-3 text-success"${_scopeId}>${ssrInterpolate(l[3])}</td><td class="px-3 py-3 text-danger"${_scopeId}>${ssrInterpolate(l[4])}</td><td class="px-3 py-3 text-text-muted"${_scopeId}>${ssrInterpolate(l[5])}</td></tr>`);
            });
            _push2(`<!--]-->`);
          } else {
            return [
              (openBlock(), createBlock(Fragment, null, renderList(logs, (l) => {
                return createVNode("tr", {
                  key: l[0] + l[1]
                }, [
                  createVNode("td", { class: "px-3 py-3 font-medium" }, toDisplayString(l[0]), 1),
                  createVNode("td", { class: "px-3 py-3" }, toDisplayString(l[1]), 1),
                  createVNode("td", { class: "px-3 py-3" }, toDisplayString(l[2]), 1),
                  createVNode("td", { class: "px-3 py-3 text-success" }, toDisplayString(l[3]), 1),
                  createVNode("td", { class: "px-3 py-3 text-danger" }, toDisplayString(l[4]), 1),
                  createVNode("td", { class: "px-3 py-3 text-text-muted" }, toDisplayString(l[5]), 1)
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/modules/announcements/Logs.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
