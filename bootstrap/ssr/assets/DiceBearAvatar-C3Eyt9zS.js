import { defineComponent, computed, mergeProps, useSSRContext } from "vue";
import { ssrRenderAttrs } from "vue/server-renderer";
const _sfc_main = /* @__PURE__ */ defineComponent({
  __name: "DiceBearAvatar",
  __ssrInlineRender: true,
  props: {
    seed: {},
    size: { default: 28 },
    src: { default: null },
    alt: { default: "" }
  },
  setup(__props) {
    const props = __props;
    const avatarUrl = computed(() => {
      if (props.src) return props.src;
      const seed = encodeURIComponent((props.seed || "anonymous").trim().toLowerCase());
      return `https://api.dicebear.com/9.x/adventurer/svg?seed=${seed}&backgroundType=gradientLinear&radius=50`;
    });
    return (_ctx, _push, _parent, _attrs) => {
      _push(`<img${ssrRenderAttrs(mergeProps({
        src: avatarUrl.value,
        alt: __props.alt,
        width: __props.size,
        height: __props.size,
        loading: "lazy",
        decoding: "async",
        class: "shrink-0 rounded-full bg-surface-muted object-cover",
        style: { width: `${__props.size}px`, height: `${__props.size}px` }
      }, _attrs))}>`);
    };
  }
});
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/components/ui/DiceBearAvatar.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as _
};
