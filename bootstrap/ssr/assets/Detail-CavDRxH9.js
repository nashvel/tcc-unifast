import { defineComponent, ref, resolveComponent, withCtx, unref, createVNode, createTextVNode, useSSRContext } from "vue";
import { ssrRenderAttrs, ssrRenderComponent, ssrInterpolate, ssrIncludeBooleanAttr, ssrLooseContain, ssrLooseEqual, ssrRenderList, ssrRenderClass, ssrRenderAttr } from "vue/server-renderer";
import { IconArrowLeft, IconStar, IconArchive, IconDots, IconBellRinging, IconUserPlus, IconArrowsJoin, IconCheck, IconRefresh, IconBan, IconLock, IconPaperclip, IconSend } from "@tabler/icons-vue";
import { _ as _sfc_main$1 } from "./DiceBearAvatar-C3Eyt9zS.js";
const _sfc_main = /* @__PURE__ */ defineComponent({
  __name: "Detail",
  __ssrInlineRender: true,
  setup(__props) {
    const reply = ref("");
    const sent = ref(false);
    const mode = ref("reply");
    const status = ref("In progress");
    const assignee = ref("TES Support");
    const priority = ref("High");
    const feedback = ref("");
    const messages = [
      {
        sender: "Admin User",
        seed: "admin@unifast.gov.ph",
        time: "July 11, 2026 · 9:14 AM",
        body: "The exported eligibility report contains an incorrect period label. I selected AY 2025–2026, but the generated report shows the previous academic year."
      },
      {
        sender: "TES Support",
        seed: "TES Support",
        time: "July 11, 2026 · 10:02 AM",
        body: "Thanks for reporting this. We reproduced the issue and are reviewing the report parameters. We will update this conversation when the correction is ready."
      }
    ];
    return (_ctx, _push, _parent, _attrs) => {
      const _component_RouterLink = resolveComponent("RouterLink");
      _push(`<div${ssrRenderAttrs(_attrs)}><div class="mb-3 flex items-center justify-between">`);
      _push(ssrRenderComponent(_component_RouterLink, {
        to: "/app/support",
        class: "inline-flex items-center gap-1 text-xs text-text-muted hover:text-primary"
      }, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(ssrRenderComponent(unref(IconArrowLeft), { size: 14 }, null, _parent2, _scopeId));
            _push2(`Back to inbox`);
          } else {
            return [
              createVNode(unref(IconArrowLeft), { size: 14 }),
              createTextVNode("Back to inbox")
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(`<div class="flex gap-1"><button class="rounded-md border p-2 text-text-muted">`);
      _push(ssrRenderComponent(unref(IconStar), { size: 15 }, null, _parent));
      _push(`</button><button class="rounded-md border p-2 text-text-muted">`);
      _push(ssrRenderComponent(unref(IconArchive), { size: 15 }, null, _parent));
      _push(`</button><button class="rounded-md border p-2 text-text-muted">`);
      _push(ssrRenderComponent(unref(IconDots), { size: 15 }, null, _parent));
      _push(`</button></div></div><section class="mx-auto max-w-6xl overflow-hidden rounded-xl border bg-surface"><header class="border-b p-5"><div class="flex flex-wrap items-start justify-between gap-3"><div><div class="flex flex-wrap items-center gap-2"><h1 class="text-xl font-semibold">Incorrect period on eligibility report</h1><span class="rounded-full bg-warning-soft px-2 py-0.5 text-micro text-warning">${ssrInterpolate(status.value)}</span></div><p class="mt-1 text-xs text-text-muted"> SUP-2026-0184 · Technical incident · ${ssrInterpolate(priority.value)} priority </p></div><div class="flex flex-wrap gap-2"><select class="h-9 rounded-md border bg-surface px-3 text-xs"><option${ssrIncludeBooleanAttr(Array.isArray(assignee.value) ? ssrLooseContain(assignee.value, null) : ssrLooseEqual(assignee.value, null)) ? " selected" : ""}>TES Support</option><option${ssrIncludeBooleanAttr(Array.isArray(assignee.value) ? ssrLooseContain(assignee.value, null) : ssrLooseEqual(assignee.value, null)) ? " selected" : ""}>System Administrator</option><option${ssrIncludeBooleanAttr(Array.isArray(assignee.value) ? ssrLooseContain(assignee.value, null) : ssrLooseEqual(assignee.value, null)) ? " selected" : ""}>UniFAST Staff</option><option${ssrIncludeBooleanAttr(Array.isArray(assignee.value) ? ssrLooseContain(assignee.value, null) : ssrLooseEqual(assignee.value, null)) ? " selected" : ""}>Security Team</option><option${ssrIncludeBooleanAttr(Array.isArray(assignee.value) ? ssrLooseContain(assignee.value, null) : ssrLooseEqual(assignee.value, null)) ? " selected" : ""}>Unassigned</option></select><select class="h-9 rounded-md border bg-surface px-3 text-xs"><option${ssrIncludeBooleanAttr(Array.isArray(priority.value) ? ssrLooseContain(priority.value, null) : ssrLooseEqual(priority.value, null)) ? " selected" : ""}>Low</option><option${ssrIncludeBooleanAttr(Array.isArray(priority.value) ? ssrLooseContain(priority.value, null) : ssrLooseEqual(priority.value, null)) ? " selected" : ""}>Normal</option><option${ssrIncludeBooleanAttr(Array.isArray(priority.value) ? ssrLooseContain(priority.value, null) : ssrLooseEqual(priority.value, null)) ? " selected" : ""}>High</option><option${ssrIncludeBooleanAttr(Array.isArray(priority.value) ? ssrLooseContain(priority.value, null) : ssrLooseEqual(priority.value, null)) ? " selected" : ""}>Urgent</option><option${ssrIncludeBooleanAttr(Array.isArray(priority.value) ? ssrLooseContain(priority.value, null) : ssrLooseEqual(priority.value, null)) ? " selected" : ""}>Critical</option></select><select class="h-9 rounded-md border bg-surface px-3 text-xs"><option${ssrIncludeBooleanAttr(Array.isArray(status.value) ? ssrLooseContain(status.value, null) : ssrLooseEqual(status.value, null)) ? " selected" : ""}>Open</option><option${ssrIncludeBooleanAttr(Array.isArray(status.value) ? ssrLooseContain(status.value, null) : ssrLooseEqual(status.value, null)) ? " selected" : ""}>In progress</option><option${ssrIncludeBooleanAttr(Array.isArray(status.value) ? ssrLooseContain(status.value, null) : ssrLooseEqual(status.value, null)) ? " selected" : ""}>Waiting for requester</option><option${ssrIncludeBooleanAttr(Array.isArray(status.value) ? ssrLooseContain(status.value, null) : ssrLooseEqual(status.value, null)) ? " selected" : ""}>Waiting for third party</option><option${ssrIncludeBooleanAttr(Array.isArray(status.value) ? ssrLooseContain(status.value, null) : ssrLooseEqual(status.value, null)) ? " selected" : ""}>On hold</option><option${ssrIncludeBooleanAttr(Array.isArray(status.value) ? ssrLooseContain(status.value, null) : ssrLooseEqual(status.value, null)) ? " selected" : ""}>Resolved</option><option${ssrIncludeBooleanAttr(Array.isArray(status.value) ? ssrLooseContain(status.value, null) : ssrLooseEqual(status.value, null)) ? " selected" : ""}>Closed</option><option${ssrIncludeBooleanAttr(Array.isArray(status.value) ? ssrLooseContain(status.value, null) : ssrLooseEqual(status.value, null)) ? " selected" : ""}>Reopened</option></select></div></div><div class="mt-4 flex flex-wrap gap-2 border-t pt-4"><button class="inline-flex items-center gap-1.5 rounded-md border px-3 py-2 text-xs">`);
      _push(ssrRenderComponent(unref(IconBellRinging), { size: 14 }, null, _parent));
      _push(`Escalate</button><button class="inline-flex items-center gap-1.5 rounded-md border px-3 py-2 text-xs">`);
      _push(ssrRenderComponent(unref(IconUserPlus), { size: 14 }, null, _parent));
      _push(`Collaborator</button><button class="inline-flex items-center gap-1.5 rounded-md border px-3 py-2 text-xs">`);
      _push(ssrRenderComponent(unref(IconArrowsJoin), { size: 14 }, null, _parent));
      _push(`Merge</button><button class="inline-flex items-center gap-1.5 rounded-md border px-3 py-2 text-xs">`);
      _push(ssrRenderComponent(unref(IconCheck), { size: 14 }, null, _parent));
      _push(`Resolve</button><button class="inline-flex items-center gap-1.5 rounded-md border px-3 py-2 text-xs">`);
      _push(ssrRenderComponent(unref(IconRefresh), { size: 14 }, null, _parent));
      _push(`Reopen</button><button class="inline-flex items-center gap-1.5 rounded-md border px-3 py-2 text-xs text-danger">`);
      _push(ssrRenderComponent(unref(IconBan), { size: 14 }, null, _parent));
      _push(`Spam </button></div>`);
      if (feedback.value) {
        _push(`<p class="mt-3 rounded-md bg-info-soft p-2 text-xs text-info">${ssrInterpolate(feedback.value)}</p>`);
      } else {
        _push(`<!---->`);
      }
      _push(`</header><div class="min-h-[380px] space-y-6 p-5 sm:p-7"><!--[-->`);
      ssrRenderList(messages, (message) => {
        _push(`<article class="flex gap-3">`);
        _push(ssrRenderComponent(_sfc_main$1, {
          seed: message.seed,
          alt: message.sender,
          size: 36
        }, null, _parent));
        _push(`<div class="min-w-0 flex-1"><div class="flex justify-between gap-2"><p class="text-sm font-semibold">${ssrInterpolate(message.sender)}</p><p class="text-micro text-text-soft">${ssrInterpolate(message.time)}</p></div><div class="mt-2 rounded-lg rounded-tl-none border p-4 text-sm leading-6 text-text-muted">${ssrInterpolate(message.body)}</div></div></article>`);
      });
      _push(`<!--]--></div><section class="grid gap-3 border-t bg-surface-muted/25 p-5 sm:grid-cols-2 lg:grid-cols-4"><!--[-->`);
      ssrRenderList([
        ["Requester", "Admin User"],
        ["Assignee", assignee.value],
        ["SLA target", "4 hours · 2h 18m left"],
        ["Related record", "Eligibility report #REP-482"],
        ["Watchers", "Admin User, TES Head"],
        ["Channel", "Web portal"],
        ["Created", "July 11, 2026"],
        ["Last activity", "52 minutes ago"]
      ], (item) => {
        _push(`<div><p class="text-micro uppercase text-text-soft">${ssrInterpolate(item[0])}</p><p class="mt-1 text-xs font-medium">${ssrInterpolate(item[1])}</p></div>`);
      });
      _push(`<!--]--></section><form class="border-t bg-surface-muted/30 p-5"><div class="mb-2 flex gap-1"><button type="button" class="${ssrRenderClass([
        "rounded-md px-3 py-1.5 text-xs",
        mode.value === "reply" ? "bg-primary text-white" : "text-text-muted"
      ])}"> Public reply</button><button type="button" class="${ssrRenderClass([
        "inline-flex items-center gap-1 rounded-md px-3 py-1.5 text-xs",
        mode.value === "note" ? "bg-warning text-white" : "text-text-muted"
      ])}">`);
      _push(ssrRenderComponent(unref(IconLock), { size: 12 }, null, _parent));
      _push(`Internal note </button></div><div class="rounded-lg border bg-surface"><textarea class="min-h-28 w-full resize-none border-0 bg-transparent p-3 text-sm outline-none"${ssrRenderAttr(
        "placeholder",
        mode.value === "reply" ? "Reply to this conversation…" : "Add a private note for support staff…"
      )}>${ssrInterpolate(reply.value)}</textarea><div class="flex items-center justify-between border-t px-3 py-2"><button type="button" class="rounded p-1.5 text-text-muted">`);
      _push(ssrRenderComponent(unref(IconPaperclip), { size: 16 }, null, _parent));
      _push(`</button><button class="inline-flex items-center gap-1.5 rounded-md bg-primary px-4 py-2 text-xs text-white">`);
      _push(ssrRenderComponent(unref(IconSend), { size: 14 }, null, _parent));
      _push(`${ssrInterpolate(mode.value === "reply" ? "Send reply" : "Save note")}</button></div></div>`);
      if (sent.value) {
        _push(`<p class="mt-2 text-xs text-success"> Mock ${ssrInterpolate(mode.value === "reply" ? "reply sent" : "internal note saved")}. </p>`);
      } else {
        _push(`<!---->`);
      }
      _push(`</form></section></div>`);
    };
  }
});
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/modules/support/Detail.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
