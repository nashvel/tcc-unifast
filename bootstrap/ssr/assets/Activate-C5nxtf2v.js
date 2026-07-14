import { defineComponent, ref, resolveComponent, withCtx, unref, createTextVNode, createVNode, toDisplayString, openBlock, createBlock, withModifiers, withDirectives, vModelText, createCommentVNode, useSSRContext } from "vue";
import { ssrRenderComponent, ssrInterpolate, ssrRenderAttr } from "vue/server-renderer";
import { useRouter } from "vue-router";
import { IconShieldCheck, IconMail, IconKey, IconAlertTriangle } from "@tabler/icons-vue";
import { _ as _sfc_main$1 } from "./AuthLayout-BB9vvw2H.js";
const _sfc_main = /* @__PURE__ */ defineComponent({
  __name: "Activate",
  __ssrInlineRender: true,
  setup(__props) {
    const router = useRouter();
    const step = ref("email");
    const email = ref("");
    const temporaryPassword = ref("");
    const password = ref("");
    const confirm = ref("");
    const error = ref("");
    function verifyInvite() {
      error.value = "";
      if (!email.value || !temporaryPassword.value) {
        error.value = "Enter your registered email and temporary password from the activation email.";
        return;
      }
      step.value = "password";
    }
    function activate() {
      error.value = "";
      if (password.value.length < 8) error.value = "Password must be at least 8 characters.";
      else if (password.value !== confirm.value) error.value = "Passwords do not match.";
      else router.push("/activate-success");
    }
    return (_ctx, _push, _parent, _attrs) => {
      const _component_RouterLink = resolveComponent("RouterLink");
      _push(ssrRenderComponent(_sfc_main$1, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="mb-3 flex items-center gap-2"${_scopeId}><span class="grid h-8 w-8 place-items-center rounded-md bg-primary-soft text-primary"${_scopeId}>`);
            _push2(ssrRenderComponent(unref(IconShieldCheck), { size: 16 }, null, _parent2, _scopeId));
            _push2(`</span><p class="text-micro font-semibold uppercase tracking-wider text-text-soft"${_scopeId}> Account Activation </p></div><h1 class="text-xl font-semibold tracking-tight"${_scopeId}>${ssrInterpolate(step.value === "email" ? "Activate your masterlist account" : "Create your own password")}</h1><p class="mt-1 text-sm text-text-muted"${_scopeId}>${ssrInterpolate(step.value === "email" ? "Your account was created inactive from the Head-uploaded masterlist. Use the temporary password sent to your registered email." : "Replace the temporary password before continuing to identity verification.")}</p>`);
            if (step.value === "email") {
              _push2(`<form class="mt-5 space-y-4"${_scopeId}><label class="block"${_scopeId}><span class="mb-1.5 block text-xs font-medium"${_scopeId}>Registered email *</span><span class="relative block"${_scopeId}>`);
              _push2(ssrRenderComponent(unref(IconMail), {
                size: 15,
                class: "absolute left-3 top-1/2 -translate-y-1/2 text-text-soft"
              }, null, _parent2, _scopeId));
              _push2(`<input${ssrRenderAttr("value", email.value)} type="email" placeholder="student001@tcc.edu.ph" class="h-10 w-full rounded-md border pl-9 pr-3 text-sm"${_scopeId}></span></label><label class="block"${_scopeId}><span class="mb-1.5 block text-xs font-medium"${_scopeId}>Temporary password *</span><span class="relative block"${_scopeId}>`);
              _push2(ssrRenderComponent(unref(IconKey), {
                size: 15,
                class: "absolute left-3 top-1/2 -translate-y-1/2 text-text-soft"
              }, null, _parent2, _scopeId));
              _push2(`<input${ssrRenderAttr("value", temporaryPassword.value)} placeholder="TCC-8F4K-29QZ" class="h-10 w-full rounded-md border pl-9 pr-3 text-sm"${_scopeId}></span></label>`);
              if (error.value) {
                _push2(`<div class="flex gap-2 rounded-md border border-danger/30 bg-danger-soft p-2.5 text-xs text-danger"${_scopeId}>`);
                _push2(ssrRenderComponent(unref(IconAlertTriangle), { size: 14 }, null, _parent2, _scopeId));
                _push2(`${ssrInterpolate(error.value)}</div>`);
              } else {
                _push2(`<!---->`);
              }
              _push2(`<button class="h-10 w-full rounded-md bg-primary text-sm font-medium text-white"${_scopeId}> Continue activation </button></form>`);
            } else {
              _push2(`<form class="mt-5 space-y-4"${_scopeId}><label class="block"${_scopeId}><span class="mb-1.5 block text-xs font-medium"${_scopeId}>New password *</span><input${ssrRenderAttr("value", password.value)} type="password" class="h-10 w-full rounded-md border px-3"${_scopeId}></label><label class="block"${_scopeId}><span class="mb-1.5 block text-xs font-medium"${_scopeId}>Confirm password *</span><input${ssrRenderAttr("value", confirm.value)} type="password" class="h-10 w-full rounded-md border px-3"${_scopeId}></label><p class="rounded-md border bg-surface-muted p-2.5 text-xs text-text-muted"${_scopeId}> After login, you still need to upload your student ID and pass live face verification before dashboard menus unlock. </p>`);
              if (error.value) {
                _push2(`<p class="text-xs text-danger"${_scopeId}>${ssrInterpolate(error.value)}</p>`);
              } else {
                _push2(`<!---->`);
              }
              _push2(`<button class="h-10 w-full rounded-md bg-primary text-sm font-medium text-white"${_scopeId}> Activate account </button></form>`);
            }
            _push2(ssrRenderComponent(_component_RouterLink, {
              to: "/login",
              class: "mt-4 inline-block text-sm text-primary hover:underline"
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(` ← Back to sign in `);
                } else {
                  return [
                    createTextVNode(" ← Back to sign in ")
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
          } else {
            return [
              createVNode("div", { class: "mb-3 flex items-center gap-2" }, [
                createVNode("span", { class: "grid h-8 w-8 place-items-center rounded-md bg-primary-soft text-primary" }, [
                  createVNode(unref(IconShieldCheck), { size: 16 })
                ]),
                createVNode("p", { class: "text-micro font-semibold uppercase tracking-wider text-text-soft" }, " Account Activation ")
              ]),
              createVNode("h1", { class: "text-xl font-semibold tracking-tight" }, toDisplayString(step.value === "email" ? "Activate your masterlist account" : "Create your own password"), 1),
              createVNode("p", { class: "mt-1 text-sm text-text-muted" }, toDisplayString(step.value === "email" ? "Your account was created inactive from the Head-uploaded masterlist. Use the temporary password sent to your registered email." : "Replace the temporary password before continuing to identity verification."), 1),
              step.value === "email" ? (openBlock(), createBlock("form", {
                key: 0,
                class: "mt-5 space-y-4",
                onSubmit: withModifiers(verifyInvite, ["prevent"])
              }, [
                createVNode("label", { class: "block" }, [
                  createVNode("span", { class: "mb-1.5 block text-xs font-medium" }, "Registered email *"),
                  createVNode("span", { class: "relative block" }, [
                    createVNode(unref(IconMail), {
                      size: 15,
                      class: "absolute left-3 top-1/2 -translate-y-1/2 text-text-soft"
                    }),
                    withDirectives(createVNode("input", {
                      "onUpdate:modelValue": ($event) => email.value = $event,
                      type: "email",
                      placeholder: "student001@tcc.edu.ph",
                      class: "h-10 w-full rounded-md border pl-9 pr-3 text-sm"
                    }, null, 8, ["onUpdate:modelValue"]), [
                      [vModelText, email.value]
                    ])
                  ])
                ]),
                createVNode("label", { class: "block" }, [
                  createVNode("span", { class: "mb-1.5 block text-xs font-medium" }, "Temporary password *"),
                  createVNode("span", { class: "relative block" }, [
                    createVNode(unref(IconKey), {
                      size: 15,
                      class: "absolute left-3 top-1/2 -translate-y-1/2 text-text-soft"
                    }),
                    withDirectives(createVNode("input", {
                      "onUpdate:modelValue": ($event) => temporaryPassword.value = $event,
                      placeholder: "TCC-8F4K-29QZ",
                      class: "h-10 w-full rounded-md border pl-9 pr-3 text-sm"
                    }, null, 8, ["onUpdate:modelValue"]), [
                      [vModelText, temporaryPassword.value]
                    ])
                  ])
                ]),
                error.value ? (openBlock(), createBlock("div", {
                  key: 0,
                  class: "flex gap-2 rounded-md border border-danger/30 bg-danger-soft p-2.5 text-xs text-danger"
                }, [
                  createVNode(unref(IconAlertTriangle), { size: 14 }),
                  createTextVNode(toDisplayString(error.value), 1)
                ])) : createCommentVNode("", true),
                createVNode("button", { class: "h-10 w-full rounded-md bg-primary text-sm font-medium text-white" }, " Continue activation ")
              ], 32)) : (openBlock(), createBlock("form", {
                key: 1,
                class: "mt-5 space-y-4",
                onSubmit: withModifiers(activate, ["prevent"])
              }, [
                createVNode("label", { class: "block" }, [
                  createVNode("span", { class: "mb-1.5 block text-xs font-medium" }, "New password *"),
                  withDirectives(createVNode("input", {
                    "onUpdate:modelValue": ($event) => password.value = $event,
                    type: "password",
                    class: "h-10 w-full rounded-md border px-3"
                  }, null, 8, ["onUpdate:modelValue"]), [
                    [vModelText, password.value]
                  ])
                ]),
                createVNode("label", { class: "block" }, [
                  createVNode("span", { class: "mb-1.5 block text-xs font-medium" }, "Confirm password *"),
                  withDirectives(createVNode("input", {
                    "onUpdate:modelValue": ($event) => confirm.value = $event,
                    type: "password",
                    class: "h-10 w-full rounded-md border px-3"
                  }, null, 8, ["onUpdate:modelValue"]), [
                    [vModelText, confirm.value]
                  ])
                ]),
                createVNode("p", { class: "rounded-md border bg-surface-muted p-2.5 text-xs text-text-muted" }, " After login, you still need to upload your student ID and pass live face verification before dashboard menus unlock. "),
                error.value ? (openBlock(), createBlock("p", {
                  key: 0,
                  class: "text-xs text-danger"
                }, toDisplayString(error.value), 1)) : createCommentVNode("", true),
                createVNode("button", { class: "h-10 w-full rounded-md bg-primary text-sm font-medium text-white" }, " Activate account ")
              ], 32)),
              createVNode(_component_RouterLink, {
                to: "/login",
                class: "mt-4 inline-block text-sm text-primary hover:underline"
              }, {
                default: withCtx(() => [
                  createTextVNode(" ← Back to sign in ")
                ]),
                _: 1
              })
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/auth/Activate.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
