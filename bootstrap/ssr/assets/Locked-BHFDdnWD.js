import { defineComponent, resolveComponent, withCtx, unref, createTextVNode, createVNode, useSSRContext } from "vue";
import { ssrRenderComponent } from "vue/server-renderer";
import { IconLock } from "@tabler/icons-vue";
import { _ as _sfc_main$1 } from "./AuthLayout-BB9vvw2H.js";
const _sfc_main = /* @__PURE__ */ defineComponent({
  __name: "Locked",
  __ssrInlineRender: true,
  setup(__props) {
    return (_ctx, _push, _parent, _attrs) => {
      const _component_RouterLink = resolveComponent("RouterLink");
      _push(ssrRenderComponent(_sfc_main$1, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="text-center"${_scopeId}><span class="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-full bg-danger-soft text-danger"${_scopeId}>`);
            _push2(ssrRenderComponent(unref(IconLock), { size: 22 }, null, _parent2, _scopeId));
            _push2(`</span><h1 class="text-xl font-semibold"${_scopeId}>Account locked or inactive</h1><p class="mx-auto mt-1 max-w-xs text-sm text-text-muted"${_scopeId}> Your account has been temporarily disabled. Please contact the UniFAST Office or your school&#39;s TES coordinator for assistance. </p>`);
            _push2(ssrRenderComponent(_component_RouterLink, {
              to: "/login",
              class: "mt-5 inline-block text-sm font-medium text-primary hover:underline"
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(`← Back to sign in`);
                } else {
                  return [
                    createTextVNode("← Back to sign in")
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(`</div>`);
          } else {
            return [
              createVNode("div", { class: "text-center" }, [
                createVNode("span", { class: "mx-auto mb-3 grid h-12 w-12 place-items-center rounded-full bg-danger-soft text-danger" }, [
                  createVNode(unref(IconLock), { size: 22 })
                ]),
                createVNode("h1", { class: "text-xl font-semibold" }, "Account locked or inactive"),
                createVNode("p", { class: "mx-auto mt-1 max-w-xs text-sm text-text-muted" }, " Your account has been temporarily disabled. Please contact the UniFAST Office or your school's TES coordinator for assistance. "),
                createVNode(_component_RouterLink, {
                  to: "/login",
                  class: "mt-5 inline-block text-sm font-medium text-primary hover:underline"
                }, {
                  default: withCtx(() => [
                    createTextVNode("← Back to sign in")
                  ]),
                  _: 1
                })
              ])
            ];
          }
        }),
        _: 1
      }, _parent));
    };
  }
});
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/auth/Locked.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
