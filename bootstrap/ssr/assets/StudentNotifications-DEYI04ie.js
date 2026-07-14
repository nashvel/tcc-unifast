import { defineComponent, ref, withCtx, createVNode, useSSRContext } from "vue";
import { ssrRenderAttrs, ssrRenderComponent, ssrRenderList, ssrRenderClass, ssrInterpolate } from "vue/server-renderer";
import { _ as _sfc_main$1 } from "./PageHeader-DV7ufis9.js";
const _sfc_main = /* @__PURE__ */ defineComponent({
  __name: "StudentNotifications",
  __ssrInlineRender: true,
  setup(__props) {
    const items = ref([
      {
        id: 1,
        title: "Document approved",
        body: "Your PSA Birth Certificate was verified and accepted.",
        time: "May 12, 2025, 10:14 AM",
        type: "success",
        read: false
      },
      {
        id: 2,
        title: "Resubmission required",
        body: "Please upload a clearer 2x2 ID Picture.",
        time: "May 11, 2025, 3:48 PM",
        type: "warning",
        read: false
      },
      {
        id: 3,
        title: "Orientation reminder",
        body: "Scholarship orientation begins May 15 at the TCC AVR.",
        time: "May 10, 2025, 9:00 AM",
        type: "info",
        read: true
      }
    ]);
    const tones = {
      info: "bg-info",
      success: "bg-success",
      warning: "bg-warning",
      danger: "bg-danger"
    };
    return (_ctx, _push, _parent, _attrs) => {
      _push(`<div${ssrRenderAttrs(_attrs)}>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        title: "Notifications",
        description: "Account activity, validation updates, and reminders."
      }, {
        actions: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<button class="h-9 rounded-md border px-3 text-xs"${_scopeId}> Mark all read </button>`);
          } else {
            return [
              createVNode("button", {
                class: "h-9 rounded-md border px-3 text-xs",
                onClick: ($event) => items.value.forEach((item) => item.read = true)
              }, " Mark all read ", 8, ["onClick"])
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(`<ul class="space-y-2"><!--[-->`);
      ssrRenderList(items.value, (item) => {
        _push(`<li class="${ssrRenderClass([
          "flex gap-3 rounded-lg border bg-surface p-3",
          !item.read && "border-primary/30 bg-primary-soft/20"
        ])}"><i class="${ssrRenderClass(["mt-2 h-2 w-2 shrink-0 rounded-full", tones[item.type]])}"></i><div class="min-w-0 flex-1"><div class="flex justify-between gap-2"><p class="text-sm font-medium">${ssrInterpolate(item.title)}</p><span class="text-micro text-text-soft">${ssrInterpolate(item.time)}</span></div><p class="text-xs text-text-muted">${ssrInterpolate(item.body)}</p></div>`);
        if (!item.read) {
          _push(`<button class="self-start text-micro text-primary"> Mark read </button>`);
        } else {
          _push(`<!---->`);
        }
        _push(`</li>`);
      });
      _push(`<!--]--></ul></div>`);
    };
  }
});
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/modules/notifications/StudentNotifications.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
