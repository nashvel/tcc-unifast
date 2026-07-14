import { defineComponent, ref, resolveComponent, withCtx, unref, createTextVNode, openBlock, createBlock, Fragment, createVNode, toDisplayString, withModifiers, withDirectives, vModelText, useSSRContext } from "vue";
import { ssrRenderComponent, ssrInterpolate, ssrRenderAttr } from "vue/server-renderer";
import { IconCheck } from "@tabler/icons-vue";
import { _ as _sfc_main$1 } from "./AuthLayout-BB9vvw2H.js";
const _sfc_main = /* @__PURE__ */ defineComponent({
  __name: "ForgotPassword",
  __ssrInlineRender: true,
  setup(__props) {
    const email = ref("");
    const sent = ref(false);
    return (_ctx, _push, _parent, _attrs) => {
      const _component_RouterLink = resolveComponent("RouterLink");
      _push(ssrRenderComponent(_sfc_main$1, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            if (sent.value) {
              _push2(`<!--[--><span class="mb-3 grid h-9 w-9 place-items-center rounded-full bg-success-soft text-success"${_scopeId}>`);
              _push2(ssrRenderComponent(unref(IconCheck), { size: 18 }, null, _parent2, _scopeId));
              _push2(`</span><h1 class="text-xl font-semibold"${_scopeId}>Check your email</h1><p class="mt-1 text-sm text-text-muted"${_scopeId}> If <b${_scopeId}>${ssrInterpolate(email.value)}</b> is registered, we&#39;ve sent password reset instructions. </p>`);
              _push2(ssrRenderComponent(_component_RouterLink, {
                to: "/login",
                class: "mt-5 inline-block text-sm text-primary hover:underline"
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
              _push2(`<!--]-->`);
            } else {
              _push2(`<!--[--><h1 class="text-xl font-semibold tracking-tight"${_scopeId}>Forgot password</h1><p class="mt-1 text-sm text-text-muted"${_scopeId}>Enter your email and we&#39;ll send you a reset link.</p><form class="mt-5 space-y-4"${_scopeId}><label class="block"${_scopeId}><span class="mb-1.5 block text-xs font-medium"${_scopeId}>Email *</span><input${ssrRenderAttr("value", email.value)} required type="email" placeholder="you@unifast.gov.ph" class="h-10 w-full rounded-md border bg-surface px-3 text-sm"${_scopeId}></label><button class="h-10 w-full rounded-md bg-primary text-sm font-medium text-white"${_scopeId}> Send reset link </button></form>`);
              _push2(ssrRenderComponent(_component_RouterLink, {
                to: "/login",
                class: "mt-4 inline-block text-sm text-primary hover:underline"
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
              _push2(`<!--]-->`);
            }
          } else {
            return [
              sent.value ? (openBlock(), createBlock(Fragment, { key: 0 }, [
                createVNode("span", { class: "mb-3 grid h-9 w-9 place-items-center rounded-full bg-success-soft text-success" }, [
                  createVNode(unref(IconCheck), { size: 18 })
                ]),
                createVNode("h1", { class: "text-xl font-semibold" }, "Check your email"),
                createVNode("p", { class: "mt-1 text-sm text-text-muted" }, [
                  createTextVNode(" If "),
                  createVNode("b", null, toDisplayString(email.value), 1),
                  createTextVNode(" is registered, we've sent password reset instructions. ")
                ]),
                createVNode(_component_RouterLink, {
                  to: "/login",
                  class: "mt-5 inline-block text-sm text-primary hover:underline"
                }, {
                  default: withCtx(() => [
                    createTextVNode("← Back to sign in")
                  ]),
                  _: 1
                })
              ], 64)) : (openBlock(), createBlock(Fragment, { key: 1 }, [
                createVNode("h1", { class: "text-xl font-semibold tracking-tight" }, "Forgot password"),
                createVNode("p", { class: "mt-1 text-sm text-text-muted" }, "Enter your email and we'll send you a reset link."),
                createVNode("form", {
                  class: "mt-5 space-y-4",
                  onSubmit: withModifiers(($event) => sent.value = true, ["prevent"])
                }, [
                  createVNode("label", { class: "block" }, [
                    createVNode("span", { class: "mb-1.5 block text-xs font-medium" }, "Email *"),
                    withDirectives(createVNode("input", {
                      "onUpdate:modelValue": ($event) => email.value = $event,
                      required: "",
                      type: "email",
                      placeholder: "you@unifast.gov.ph",
                      class: "h-10 w-full rounded-md border bg-surface px-3 text-sm"
                    }, null, 8, ["onUpdate:modelValue"]), [
                      [vModelText, email.value]
                    ])
                  ]),
                  createVNode("button", { class: "h-10 w-full rounded-md bg-primary text-sm font-medium text-white" }, " Send reset link ")
                ], 40, ["onSubmit"]),
                createVNode(_component_RouterLink, {
                  to: "/login",
                  class: "mt-4 inline-block text-sm text-primary hover:underline"
                }, {
                  default: withCtx(() => [
                    createTextVNode("← Back to sign in")
                  ]),
                  _: 1
                })
              ], 64))
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/auth/ForgotPassword.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
