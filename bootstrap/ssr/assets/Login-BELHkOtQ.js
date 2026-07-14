import { defineComponent, ref, resolveComponent, mergeProps, unref, withCtx, createTextVNode, useSSRContext } from "vue";
import { ssrRenderAttrs, ssrRenderStyle, ssrRenderAttr, ssrInterpolate, ssrRenderComponent, ssrIncludeBooleanAttr, ssrRenderList } from "vue/server-renderer";
import { useRoute, useRouter } from "vue-router";
import { Mail, Lock, ArrowRight, UserRound } from "lucide-vue-next";
import { l as logo } from "./system-logo-DwBJYJLj.js";
import "../ssr.js";
const _sfc_main = /* @__PURE__ */ defineComponent({
  __name: "Login",
  __ssrInlineRender: true,
  setup(__props) {
    const route = useRoute();
    useRouter();
    const email = ref(""), password = ref(""), error = ref(""), busy = ref(false);
    const mode = route.path.includes("forgot") ? "forgot" : route.path.includes("activate") ? "activate" : "login";
    return (_ctx, _push, _parent, _attrs) => {
      const _component_RouterLink = resolveComponent("RouterLink");
      _push(`<div${ssrRenderAttrs(mergeProps({ class: "grid min-h-screen bg-surface lg:grid-cols-[1.05fr_.95fr]" }, _attrs))}><section class="relative hidden overflow-hidden bg-sidebar-bg p-12 text-white lg:flex lg:flex-col"><div class="absolute inset-0 opacity-15" style="${ssrRenderStyle({ "background": "radial-gradient(circle at 20% 20%, #d1a15c, transparent 35%),\n            radial-gradient(circle at 80% 80%, #fff, transparent 28%)" })}"></div><div class="relative flex items-center gap-3"><div class="h-12 w-12 rounded-lg bg-white p-1.5"><img${ssrRenderAttr("src", unref(logo))} class="h-full w-full object-contain"></div><div><p class="text-lg font-semibold">UniFAST TES</p><p class="text-xs text-white/65">Grantee Management</p></div></div><div class="relative my-auto max-w-lg"><p class="text-xs font-semibold uppercase tracking-[.18em] text-[#d1a15c]"> Tagoloan Community College </p><h1 class="mt-4 text-4xl font-semibold leading-tight"> Making scholarship services simpler, faster, and more transparent. </h1><p class="mt-5 max-w-md text-sm leading-6 text-white/70"> A unified workspace for grantees, documents, eligibility, releases, and academic monitoring. </p></div><p class="relative text-xs text-white/45"> TCC Scholarship Services Office · Secure demo environment </p></section><main class="flex items-center justify-center p-6"><div class="w-full max-w-md"><div class="mb-8 flex items-center gap-3 lg:hidden"><img${ssrRenderAttr("src", unref(logo))} class="h-11 w-11 object-contain"><div><p class="font-semibold">UniFAST TES</p><p class="text-xs text-text-muted">Tagoloan Community College</p></div></div><h2 class="text-xl font-semibold tracking-tight">${ssrInterpolate(unref(mode) === "forgot" ? "Reset your password" : unref(mode) === "activate" ? "Activate your account" : "Sign in to your account")}</h2><p class="mt-1 text-sm text-text-muted">${ssrInterpolate(unref(mode) === "login" ? "Use a seeded account or choose a demo role below." : "Enter your institutional email to continue.")}</p><form class="mt-6 space-y-4"><label class="block"><span class="mb-1.5 block text-xs font-medium">Email <b class="text-danger">*</b></span><div class="relative">`);
      _push(ssrRenderComponent(unref(Mail), {
        size: 15,
        class: "absolute left-3 top-1/2 -translate-y-1/2 text-text-soft"
      }, null, _parent));
      _push(`<input${ssrRenderAttr("value", email.value)} type="email" placeholder="you@unifast.gov.ph" class="h-10 w-full rounded-md border bg-surface pl-9 pr-3 text-sm"></div></label>`);
      if (unref(mode) === "login") {
        _push(`<label class="block"><span class="mb-1.5 block text-xs font-medium">Password <b class="text-danger">*</b></span><div class="relative">`);
        _push(ssrRenderComponent(unref(Lock), {
          size: 15,
          class: "absolute left-3 top-1/2 -translate-y-1/2 text-text-soft"
        }, null, _parent));
        _push(`<input${ssrRenderAttr("value", password.value)} type="password" placeholder="••••••••" class="h-10 w-full rounded-md border bg-surface pl-9 pr-3 text-sm"></div></label>`);
      } else {
        _push(`<!---->`);
      }
      if (error.value) {
        _push(`<p class="text-xs text-danger">${ssrInterpolate(error.value)}</p>`);
      } else {
        _push(`<!---->`);
      }
      _push(`<button${ssrIncludeBooleanAttr(busy.value) ? " disabled" : ""} class="flex h-10 w-full items-center justify-center gap-2 rounded-md bg-primary text-sm font-medium text-white hover:bg-primary-hover">${ssrInterpolate(busy.value ? "Signing in…" : unref(mode) === "login" ? "Sign in" : "Continue")}`);
      _push(ssrRenderComponent(unref(ArrowRight), { size: 15 }, null, _parent));
      _push(`</button></form>`);
      if (unref(mode) === "login") {
        _push(`<div class="mt-4 flex justify-between text-xs text-primary">`);
        _push(ssrRenderComponent(_component_RouterLink, { to: "/forgot-password" }, {
          default: withCtx((_, _push2, _parent2, _scopeId) => {
            if (_push2) {
              _push2(`Forgot password?`);
            } else {
              return [
                createTextVNode("Forgot password?")
              ];
            }
          }),
          _: 1
        }, _parent));
        _push(ssrRenderComponent(_component_RouterLink, { to: "/activate" }, {
          default: withCtx((_, _push2, _parent2, _scopeId) => {
            if (_push2) {
              _push2(`Activate your account`);
            } else {
              return [
                createTextVNode("Activate your account")
              ];
            }
          }),
          _: 1
        }, _parent));
        _push(`</div>`);
      } else {
        _push(`<div class="mt-4 text-xs">`);
        _push(ssrRenderComponent(_component_RouterLink, {
          to: "/login",
          class: "text-primary"
        }, {
          default: withCtx((_, _push2, _parent2, _scopeId) => {
            if (_push2) {
              _push2(`← Back to sign in`);
            } else {
              return [
                createTextVNode("← Back to sign in")
              ];
            }
          }),
          _: 1
        }, _parent));
        _push(`</div>`);
      }
      if (unref(mode) === "login") {
        _push(`<div class="mt-8 border-t pt-5"><p class="mb-3 text-2xs font-semibold uppercase tracking-wider text-text-soft"> Seeded demo accounts · password: password </p><div class="grid grid-cols-2 gap-2"><!--[-->`);
        ssrRenderList(["Administrator", "Office Head", "UniFAST Staff", "Student"], (role) => {
          _push(`<button type="button" class="flex items-center gap-2 rounded-md border p-3 text-left hover:bg-surface-muted">`);
          _push(ssrRenderComponent(unref(UserRound), {
            size: 15,
            class: "text-text-muted"
          }, null, _parent));
          _push(`<span class="text-xs font-medium">${ssrInterpolate(role)}</span></button>`);
        });
        _push(`<!--]--></div></div>`);
      } else {
        _push(`<!---->`);
      }
      _push(`</div></main></div>`);
    };
  }
});
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/auth/Login.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
