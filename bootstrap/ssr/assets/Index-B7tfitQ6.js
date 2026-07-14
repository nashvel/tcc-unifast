import { defineComponent, ref, unref, createVNode, resolveDynamicComponent, useSSRContext } from "vue";
import { ssrRenderAttrs, ssrRenderComponent, ssrRenderList, ssrRenderClass, ssrRenderVNode, ssrInterpolate } from "vue/server-renderer";
import { IconSun, IconMoon, IconCheck } from "@tabler/icons-vue";
import { _ as _sfc_main$1 } from "./PageHeader-DV7ufis9.js";
const _sfc_main = /* @__PURE__ */ defineComponent({
  __name: "Index",
  __ssrInlineRender: true,
  setup(__props) {
    const theme = ref("light");
    const density = ref("comfortable");
    const saved = ref(false);
    return (_ctx, _push, _parent, _attrs) => {
      _push(`<div${ssrRenderAttrs(_attrs)}>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        title: "Appearance",
        description: "Choose how the UniFAST TES workspace looks on this device."
      }, null, _parent));
      _push(`<section class="max-w-3xl space-y-4"><article class="rounded-lg border bg-surface p-5"><h2 class="text-sm font-semibold">Theme</h2><div class="mt-4 grid grid-cols-2 gap-3"><!--[-->`);
      ssrRenderList([
        { id: "light", label: "Light", icon: unref(IconSun) },
        { id: "dark", label: "Dark", icon: unref(IconMoon) }
      ], (t) => {
        _push(`<button class="${ssrRenderClass([
          "relative rounded-lg border p-4 text-left",
          theme.value === t.id ? "border-primary ring-1 ring-primary" : ""
        ])}">`);
        ssrRenderVNode(_push, createVNode(resolveDynamicComponent(t.icon), { size: 20 }, null), _parent);
        _push(`<p class="mt-3 text-sm font-medium">${ssrInterpolate(t.label)}</p>`);
        if (theme.value === t.id) {
          _push(ssrRenderComponent(unref(IconCheck), {
            size: 16,
            class: "absolute right-3 top-3 text-primary"
          }, null, _parent));
        } else {
          _push(`<!---->`);
        }
        _push(`</button>`);
      });
      _push(`<!--]--></div></article><article class="rounded-lg border bg-surface p-5"><h2 class="text-sm font-semibold">Interface density</h2><div class="mt-4 flex gap-2"><!--[-->`);
      ssrRenderList(["compact", "comfortable", "spacious"], (d) => {
        _push(`<button class="${ssrRenderClass([
          "rounded-md border px-3 py-2 text-xs capitalize",
          density.value === d ? "bg-primary text-white" : ""
        ])}">${ssrInterpolate(d)}</button>`);
      });
      _push(`<!--]--></div></article><div class="flex items-center justify-end gap-3">`);
      if (saved.value) {
        _push(`<span class="text-xs text-success">Preferences saved.</span>`);
      } else {
        _push(`<!---->`);
      }
      _push(`<button class="rounded-md bg-primary px-4 py-2 text-xs text-white"> Save preferences </button></div></section></div>`);
    };
  }
});
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/modules/appearance/Index.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
