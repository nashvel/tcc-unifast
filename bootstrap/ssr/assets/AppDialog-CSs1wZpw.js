import { defineComponent, useModel, onMounted, onBeforeUnmount, unref, mergeModels, useSSRContext } from "vue";
import { ssrRenderTeleport, ssrRenderClass, ssrRenderAttr, ssrInterpolate, ssrRenderComponent, ssrRenderSlot } from "vue/server-renderer";
import { IconX } from "@tabler/icons-vue";
const _sfc_main = /* @__PURE__ */ defineComponent({
  __name: "AppDialog",
  __ssrInlineRender: true,
  props: /* @__PURE__ */ mergeModels({
    title: {},
    description: {},
    size: { default: "md" },
    closeable: { type: Boolean, default: true }
  }, {
    "modelValue": { type: Boolean, ...{ required: true } },
    "modelModifiers": {}
  }),
  emits: ["update:modelValue"],
  setup(__props) {
    const open = useModel(__props, "modelValue");
    const widths = { sm: "max-w-sm", md: "max-w-lg", lg: "max-w-2xl", xl: "max-w-4xl" };
    function close() {
      open.value = false;
    }
    function onKeydown(event) {
      if (event.key === "Escape") close();
    }
    onMounted(() => document.addEventListener("keydown", onKeydown));
    onBeforeUnmount(() => document.removeEventListener("keydown", onKeydown));
    return (_ctx, _push, _parent, _attrs) => {
      ssrRenderTeleport(_push, (_push2) => {
        if (open.value) {
          _push2(`<div class="fixed inset-0 z-[100] grid place-items-center overflow-y-auto bg-black/45 p-4" role="presentation"><section class="${ssrRenderClass([
            "my-auto w-full overflow-hidden rounded-xl border bg-surface shadow-2xl",
            widths[__props.size]
          ])}" role="dialog" aria-modal="true"${ssrRenderAttr("aria-label", __props.title)}><header class="flex items-start justify-between gap-4 border-b px-5 py-4"><div><h2 class="text-base font-semibold">${ssrInterpolate(__props.title)}</h2>`);
          if (__props.description) {
            _push2(`<p class="mt-1 text-xs leading-5 text-text-muted">${ssrInterpolate(__props.description)}</p>`);
          } else {
            _push2(`<!---->`);
          }
          _push2(`</div>`);
          if (__props.closeable) {
            _push2(`<button class="rounded-md p-1.5 text-text-muted hover:bg-surface-muted" aria-label="Close dialog">`);
            _push2(ssrRenderComponent(unref(IconX), { size: 17 }, null, _parent));
            _push2(`</button>`);
          } else {
            _push2(`<!---->`);
          }
          _push2(`</header><div class="max-h-[70vh] overflow-y-auto p-5">`);
          ssrRenderSlot(_ctx.$slots, "default", {}, null, _push2, _parent);
          _push2(`</div>`);
          if (_ctx.$slots.footer) {
            _push2(`<footer class="flex flex-wrap items-center justify-end gap-2 border-t bg-surface-muted/40 px-5 py-3">`);
            ssrRenderSlot(_ctx.$slots, "footer", { close }, null, _push2, _parent);
            _push2(`</footer>`);
          } else {
            _push2(`<!---->`);
          }
          _push2(`</section></div>`);
        } else {
          _push2(`<!---->`);
        }
      }, "body", false, _parent);
    };
  }
});
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/components/dialogs/AppDialog.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as _
};
