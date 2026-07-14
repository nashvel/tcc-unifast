import { defineComponent, ref, resolveComponent, withCtx, unref, createVNode, createTextVNode, useSSRContext } from "vue";
import { ssrRenderAttrs, ssrRenderComponent, ssrRenderAttr, ssrInterpolate, ssrIncludeBooleanAttr, ssrLooseContain, ssrLooseEqual } from "vue/server-renderer";
import { IconArrowLeft, IconDeviceFloppy } from "@tabler/icons-vue";
import { _ as _sfc_main$1 } from "./PageHeader-DV7ufis9.js";
const _sfc_main = /* @__PURE__ */ defineComponent({
  __name: "Edit",
  __ssrInlineRender: true,
  setup(__props) {
    const title = ref("TES application deadline reminder");
    const body = ref(
      "Please complete and submit all required documents before the announced deadline."
    );
    const audience = ref("All grantees");
    const saved = ref(false);
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
        title: "Edit Announcement",
        description: "Update content, audience, and publishing details."
      }, null, _parent));
      _push(`<form class="max-w-3xl rounded-lg border bg-surface p-5"><label class="block text-xs font-medium">Title<input${ssrRenderAttr("value", title.value)} class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm"></label><label class="mt-4 block text-xs font-medium">Message<textarea class="mt-1.5 min-h-44 w-full rounded-md border p-3 text-sm">${ssrInterpolate(body.value)}</textarea></label><label class="mt-4 block text-xs font-medium">Audience<select class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm"><option${ssrIncludeBooleanAttr(Array.isArray(audience.value) ? ssrLooseContain(audience.value, null) : ssrLooseEqual(audience.value, null)) ? " selected" : ""}>All grantees</option><option${ssrIncludeBooleanAttr(Array.isArray(audience.value) ? ssrLooseContain(audience.value, null) : ssrLooseEqual(audience.value, null)) ? " selected" : ""}>Staff only</option><option${ssrIncludeBooleanAttr(Array.isArray(audience.value) ? ssrLooseContain(audience.value, null) : ssrLooseEqual(audience.value, null)) ? " selected" : ""}>Selected batch</option></select></label><div class="mt-5 flex items-center justify-between">`);
      if (saved.value) {
        _push(`<span class="text-xs text-success">Changes saved in this mock.</span>`);
      } else {
        _push(`<span></span>`);
      }
      _push(`<button class="inline-flex items-center gap-1.5 rounded-md bg-primary px-4 py-2 text-xs text-white">`);
      _push(ssrRenderComponent(unref(IconDeviceFloppy), { size: 15 }, null, _parent));
      _push(`Save changes </button></div></form></div>`);
    };
  }
});
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/modules/announcements/Edit.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
