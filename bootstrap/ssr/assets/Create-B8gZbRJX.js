import { defineComponent, ref, resolveComponent, withCtx, unref, createVNode, createTextVNode, useSSRContext } from "vue";
import { ssrRenderAttrs, ssrRenderComponent, ssrRenderAttr, ssrInterpolate, ssrIncludeBooleanAttr, ssrLooseContain, ssrLooseEqual, ssrRenderList } from "vue/server-renderer";
import { IconArrowLeft, IconEye, IconSend } from "@tabler/icons-vue";
import { _ as _sfc_main$1 } from "./PageHeader-DV7ufis9.js";
const _sfc_main = /* @__PURE__ */ defineComponent({
  __name: "Create",
  __ssrInlineRender: true,
  setup(__props) {
    const title = ref("");
    const message = ref("");
    const audience = ref("All grantees");
    const channels = ref(["In-app", "Email"]);
    const published = ref(false);
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
        title: "Create Announcement",
        description: "Compose and publish an update to the selected audience."
      }, null, _parent));
      _push(`<form class="grid gap-4 xl:grid-cols-[2fr_1fr]"><section class="rounded-lg border bg-surface p-5"><label class="block text-xs font-medium">Title<input${ssrRenderAttr("value", title.value)} class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm" placeholder="Announcement title"></label><label class="mt-4 block text-xs font-medium">Message<textarea class="mt-1.5 min-h-52 w-full rounded-md border p-3 text-sm" placeholder="Write the announcement">${ssrInterpolate(message.value)}</textarea></label><div class="mt-4 grid gap-4 sm:grid-cols-2"><label class="text-xs font-medium">Audience<select class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm"><option${ssrIncludeBooleanAttr(Array.isArray(audience.value) ? ssrLooseContain(audience.value, null) : ssrLooseEqual(audience.value, null)) ? " selected" : ""}>All grantees</option><option${ssrIncludeBooleanAttr(Array.isArray(audience.value) ? ssrLooseContain(audience.value, null) : ssrLooseEqual(audience.value, null)) ? " selected" : ""}>Selected batch</option><option${ssrIncludeBooleanAttr(Array.isArray(audience.value) ? ssrLooseContain(audience.value, null) : ssrLooseEqual(audience.value, null)) ? " selected" : ""}>Staff only</option></select></label><label class="text-xs font-medium">Publish schedule<input type="datetime-local" class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm"></label></div><fieldset class="mt-4"><legend class="text-xs font-medium">Delivery channels</legend><div class="mt-2 flex flex-wrap gap-3"><!--[-->`);
      ssrRenderList(["In-app", "Email", "SMS"], (channel) => {
        _push(`<label class="flex items-center gap-2 text-xs"><input${ssrIncludeBooleanAttr(Array.isArray(channels.value) ? ssrLooseContain(channels.value, channel) : channels.value) ? " checked" : ""} type="checkbox"${ssrRenderAttr("value", channel)}>${ssrInterpolate(channel)}</label>`);
      });
      _push(`<!--]--></div></fieldset></section><aside class="h-fit rounded-lg border bg-surface p-5"><h2 class="flex items-center gap-2 text-sm font-semibold">`);
      _push(ssrRenderComponent(unref(IconEye), { size: 16 }, null, _parent));
      _push(`Preview</h2><div class="mt-4 rounded-md border p-4"><p class="text-sm font-semibold">${ssrInterpolate(title.value || "Announcement title")}</p><p class="mt-2 whitespace-pre-line text-xs leading-5 text-text-muted">${ssrInterpolate(message.value || "Your announcement message will appear here.")}</p><p class="mt-4 text-micro text-text-soft">${ssrInterpolate(audience.value)} · ${ssrInterpolate(channels.value.join(", "))}</p></div><button class="mt-4 inline-flex w-full items-center justify-center gap-1.5 rounded-md bg-primary px-4 py-2.5 text-xs text-white">`);
      _push(ssrRenderComponent(unref(IconSend), { size: 14 }, null, _parent));
      _push(`Publish announcement </button>`);
      if (published.value) {
        _push(`<p class="mt-3 text-center text-xs text-success"> Mock announcement published. </p>`);
      } else {
        _push(`<!---->`);
      }
      _push(`</aside></form></div>`);
    };
  }
});
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/modules/announcements/Create.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
