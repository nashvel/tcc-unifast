import { defineComponent, useSSRContext } from "vue";
import { ssrRenderAttrs, ssrRenderComponent, ssrRenderList, ssrRenderClass, ssrInterpolate } from "vue/server-renderer";
import { _ as _sfc_main$1 } from "./PageHeader-DV7ufis9.js";
const _sfc_main = /* @__PURE__ */ defineComponent({
  __name: "Index",
  __ssrInlineRender: true,
  setup(__props) {
    const colors = [
      ["Primary", "bg-primary"],
      ["Gold", "bg-gold"],
      ["Success", "bg-success"],
      ["Warning", "bg-warning"],
      ["Danger", "bg-danger"],
      ["Info", "bg-info"]
    ];
    return (_ctx, _push, _parent, _attrs) => {
      _push(`<div${ssrRenderAttrs(_attrs)}>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        title: "UI Style Guide",
        description: "Internal reference for approved tokens, components, and states."
      }, null, _parent));
      _push(`<div class="space-y-4"><section class="rounded-lg border bg-surface p-5"><h2 class="text-sm font-semibold">Color tokens</h2><p class="mt-1 text-xs text-text-muted"> Use semantic tokens for consistent meaning across modules. </p><div class="mt-4 grid grid-cols-3 gap-3 md:grid-cols-6"><!--[-->`);
      ssrRenderList(colors, (c) => {
        _push(`<div><div class="${ssrRenderClass(["h-14 rounded-md", c[1]])}"></div><p class="mt-2 text-xs">${ssrInterpolate(c[0])}</p></div>`);
      });
      _push(`<!--]--></div></section><section class="rounded-lg border bg-surface p-5"><h2 class="text-sm font-semibold">Typography</h2><div class="mt-4 space-y-4"><div><p class="text-2xl font-semibold tracking-tight">Page title</p><p class="text-xs text-text-muted">24px semibold</p></div><div><p class="text-lg font-semibold">Section title</p><p class="text-xs text-text-muted">18px semibold</p></div><div><p class="text-sm">Body text for readable application content.</p><p class="text-xs text-text-muted">14px regular</p></div></div></section><section class="rounded-lg border bg-surface p-5"><h2 class="text-sm font-semibold">Buttons and badges</h2><div class="mt-4 flex flex-wrap items-center gap-3"><button class="rounded-md bg-primary px-4 py-2 text-xs text-white">Primary action</button><button class="rounded-md border px-4 py-2 text-xs">Secondary</button><span class="rounded-full bg-success-soft px-2 py-1 text-xs text-success">Approved</span><span class="rounded-full bg-warning-soft px-2 py-1 text-xs text-warning">Pending</span><span class="rounded-full bg-danger-soft px-2 py-1 text-xs text-danger">Rejected</span></div></section><section class="rounded-lg border bg-surface p-5"><h2 class="text-sm font-semibold">Form controls</h2><div class="mt-4 grid max-w-xl gap-4 sm:grid-cols-2"><label class="text-xs font-medium">Text input<input class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm" placeholder="Placeholder"></label><label class="text-xs font-medium">Select<select class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm"><option>Option one</option></select></label></div></section></div></div>`);
    };
  }
});
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/modules/style-guide/Index.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
