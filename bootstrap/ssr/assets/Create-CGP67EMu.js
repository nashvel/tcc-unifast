import { defineComponent, ref, computed, resolveComponent, withCtx, unref, createVNode, createTextVNode, useSSRContext } from "vue";
import { ssrRenderAttrs, ssrRenderComponent, ssrRenderList, ssrIncludeBooleanAttr, ssrLooseContain, ssrLooseEqual, ssrInterpolate } from "vue/server-renderer";
import { IconArrowLeft, IconPaperclip, IconInfoCircle, IconSend } from "@tabler/icons-vue";
import { _ as _sfc_main$1 } from "./PageHeader-DV7ufis9.js";
const _sfc_main = /* @__PURE__ */ defineComponent({
  __name: "Create",
  __ssrInlineRender: true,
  setup(__props) {
    const submitted = ref(false);
    const category = ref("Technical incident");
    const priority = ref("Normal");
    const impact = ref("Single user");
    const urgency = ref("Normal");
    const anonymous = ref(false);
    const categories = {
      "Technical incident": [
        "Page error",
        "Upload failure",
        "Report/export issue",
        "Performance",
        "Mobile/display issue",
        "Integration failure"
      ],
      "Account & access": [
        "Cannot sign in",
        "Activation",
        "Locked account",
        "Password reset",
        "Role/permission",
        "Suspicious access"
      ],
      "Data correction": [
        "Personal details",
        "Academic record",
        "Eligibility result",
        "Document metadata",
        "Batch assignment",
        "Duplicate record"
      ],
      "Service request": [
        "New user",
        "Access request",
        "Report request",
        "Bulk update",
        "Configuration change",
        "Training request"
      ],
      "Feature request": [
        "New feature",
        "Workflow improvement",
        "UI improvement",
        "Automation",
        "Integration"
      ],
      "Disbursement concern": [
        "Missing payment",
        "Incorrect amount",
        "Delayed release",
        "Payment status",
        "Bank/account details"
      ],
      "Security & privacy": [
        "Security incident",
        "Privacy concern",
        "Phishing",
        "Data exposure",
        "Lost device",
        "Vulnerability"
      ],
      "Complaint or appeal": [
        "Service complaint",
        "Eligibility appeal",
        "Document decision appeal",
        "Staff conduct",
        "Policy concern"
      ],
      "General inquiry": [
        "Program question",
        "Deadline",
        "Requirements",
        "Process guidance",
        "Other inquiry"
      ]
    };
    const subcategories = computed(() => categories[category.value] ?? []);
    const sla = computed(
      () => priority.value === "Critical" ? "15-minute response" : priority.value === "Urgent" ? "1-hour response" : priority.value === "High" ? "4-hour response" : "1-business-day response"
    );
    return (_ctx, _push, _parent, _attrs) => {
      const _component_RouterLink = resolveComponent("RouterLink");
      _push(`<div${ssrRenderAttrs(_attrs)}>`);
      _push(ssrRenderComponent(_component_RouterLink, {
        to: "/app/support",
        class: "mb-3 inline-flex items-center gap-1 text-xs text-text-muted"
      }, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(ssrRenderComponent(unref(IconArrowLeft), { size: 14 }, null, _parent2, _scopeId));
            _push2(`Support inbox`);
          } else {
            return [
              createVNode(unref(IconArrowLeft), { size: 14 }),
              createTextVNode("Support inbox")
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(ssrRenderComponent(_sfc_main$1, {
        title: "New support ticket",
        description: "Provide enough context for the support team to route and resolve the request."
      }, null, _parent));
      _push(`<form class="grid gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)]"><div class="space-y-4"><section class="rounded-xl border bg-surface p-5"><h2 class="text-sm font-semibold">Request classification</h2><div class="mt-4 grid gap-4 sm:grid-cols-2"><label class="text-xs font-medium sm:col-span-2">Subject<input required class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm" placeholder="Briefly describe the issue or request"></label><label class="text-xs font-medium">Category<select class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm"><!--[-->`);
      ssrRenderList(categories, (_, name) => {
        _push(`<option${ssrIncludeBooleanAttr(Array.isArray(category.value) ? ssrLooseContain(category.value, null) : ssrLooseEqual(category.value, null)) ? " selected" : ""}>${ssrInterpolate(name)}</option>`);
      });
      _push(`<!--]--></select></label><label class="text-xs font-medium">Scenario<select class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm"><!--[-->`);
      ssrRenderList(subcategories.value, (item) => {
        _push(`<option>${ssrInterpolate(item)}</option>`);
      });
      _push(`<!--]--></select></label><label class="text-xs font-medium">Related module<select class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm"><option>Dashboard</option><option>Masterlist</option><option>Grantees</option><option>Documents</option><option>Academic records</option><option>Eligibility</option><option>Batches</option><option>Reports</option><option>Users &amp; roles</option><option>Student portal</option><option>Not applicable</option></select></label><label class="text-xs font-medium">Related record<input class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm" placeholder="Student #, batch, report, or ticket ID"></label></div></section><section class="rounded-xl border bg-surface p-5"><h2 class="text-sm font-semibold">Impact and urgency</h2><div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4"><label class="text-xs font-medium">Priority<select class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm"><option${ssrIncludeBooleanAttr(Array.isArray(priority.value) ? ssrLooseContain(priority.value, null) : ssrLooseEqual(priority.value, null)) ? " selected" : ""}>Low</option><option${ssrIncludeBooleanAttr(Array.isArray(priority.value) ? ssrLooseContain(priority.value, null) : ssrLooseEqual(priority.value, null)) ? " selected" : ""}>Normal</option><option${ssrIncludeBooleanAttr(Array.isArray(priority.value) ? ssrLooseContain(priority.value, null) : ssrLooseEqual(priority.value, null)) ? " selected" : ""}>High</option><option${ssrIncludeBooleanAttr(Array.isArray(priority.value) ? ssrLooseContain(priority.value, null) : ssrLooseEqual(priority.value, null)) ? " selected" : ""}>Urgent</option><option${ssrIncludeBooleanAttr(Array.isArray(priority.value) ? ssrLooseContain(priority.value, null) : ssrLooseEqual(priority.value, null)) ? " selected" : ""}>Critical</option></select></label><label class="text-xs font-medium">Impact<select class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm"><option${ssrIncludeBooleanAttr(Array.isArray(impact.value) ? ssrLooseContain(impact.value, null) : ssrLooseEqual(impact.value, null)) ? " selected" : ""}>Single user</option><option${ssrIncludeBooleanAttr(Array.isArray(impact.value) ? ssrLooseContain(impact.value, null) : ssrLooseEqual(impact.value, null)) ? " selected" : ""}>Multiple users</option><option${ssrIncludeBooleanAttr(Array.isArray(impact.value) ? ssrLooseContain(impact.value, null) : ssrLooseEqual(impact.value, null)) ? " selected" : ""}>Whole office</option><option${ssrIncludeBooleanAttr(Array.isArray(impact.value) ? ssrLooseContain(impact.value, null) : ssrLooseEqual(impact.value, null)) ? " selected" : ""}>All users</option></select></label><label class="text-xs font-medium">Urgency<select class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm"><option${ssrIncludeBooleanAttr(Array.isArray(urgency.value) ? ssrLooseContain(urgency.value, null) : ssrLooseEqual(urgency.value, null)) ? " selected" : ""}>Low</option><option${ssrIncludeBooleanAttr(Array.isArray(urgency.value) ? ssrLooseContain(urgency.value, null) : ssrLooseEqual(urgency.value, null)) ? " selected" : ""}>Normal</option><option${ssrIncludeBooleanAttr(Array.isArray(urgency.value) ? ssrLooseContain(urgency.value, null) : ssrLooseEqual(urgency.value, null)) ? " selected" : ""}>High</option><option${ssrIncludeBooleanAttr(Array.isArray(urgency.value) ? ssrLooseContain(urgency.value, null) : ssrLooseEqual(urgency.value, null)) ? " selected" : ""}>Immediate</option></select></label><label class="text-xs font-medium">Environment<select class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm"><option>Production</option><option>Testing</option><option>Mobile</option><option>Not applicable</option></select></label></div></section><section class="rounded-xl border bg-surface p-5"><h2 class="text-sm font-semibold">Details</h2><div class="mt-4 space-y-4"><label class="block text-xs font-medium">Description<textarea required class="mt-1.5 min-h-36 w-full rounded-md border p-3 text-sm" placeholder="What happened, who is affected, and what outcome do you need?"></textarea></label><div class="grid gap-4 sm:grid-cols-2"><label class="text-xs font-medium">Steps to reproduce<textarea class="mt-1.5 min-h-28 w-full rounded-md border p-3 text-sm" placeholder="1. Open…
2. Select…
3. Error appears…"></textarea></label><label class="text-xs font-medium">Expected result<textarea class="mt-1.5 min-h-28 w-full rounded-md border p-3 text-sm" placeholder="Describe the expected behavior or correct data"></textarea></label></div><label class="block text-xs font-medium">Attachment <div class="mt-1.5 flex min-h-24 items-center justify-center rounded-md border border-dashed text-xs text-text-muted">`);
      _push(ssrRenderComponent(unref(IconPaperclip), {
        size: 15,
        class: "mr-2"
      }, null, _parent));
      _push(`Add screenshots, PDFs, logs, or supporting documents </div></label></div></section><section class="rounded-xl border bg-surface p-5"><h2 class="text-sm font-semibold">People and notifications</h2><div class="mt-4 grid gap-4 sm:grid-cols-2"><label class="text-xs font-medium">Requested for<input class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm" value="Admin User"></label><label class="text-xs font-medium">CC / watchers<input class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm" placeholder="Names or email addresses"></label></div><div class="mt-4 flex flex-wrap gap-5"><label class="flex items-center gap-2 text-xs"><input type="checkbox" checked>Email updates</label><label class="flex items-center gap-2 text-xs"><input type="checkbox" checked>In-app updates</label><label class="flex items-center gap-2 text-xs"><input${ssrIncludeBooleanAttr(Array.isArray(anonymous.value) ? ssrLooseContain(anonymous.value, null) : anonymous.value) ? " checked" : ""} type="checkbox">Submit confidentially</label></div></section></div><aside class="h-fit space-y-4 xl:sticky xl:top-20"><section class="rounded-xl border bg-surface p-5"><h2 class="text-sm font-semibold">Ticket summary</h2><dl class="mt-4 space-y-3 text-xs"><div class="flex justify-between"><dt class="text-text-muted">Category</dt><dd class="font-medium">${ssrInterpolate(category.value)}</dd></div><div class="flex justify-between"><dt class="text-text-muted">Priority</dt><dd class="font-medium">${ssrInterpolate(priority.value)}</dd></div><div class="flex justify-between"><dt class="text-text-muted">Impact</dt><dd class="font-medium">${ssrInterpolate(impact.value)}</dd></div><div class="flex justify-between"><dt class="text-text-muted">Urgency</dt><dd class="font-medium">${ssrInterpolate(urgency.value)}</dd></div><div class="flex justify-between"><dt class="text-text-muted">Visibility</dt><dd class="font-medium">${ssrInterpolate(anonymous.value ? "Confidential" : "Standard")}</dd></div></dl></section><section class="rounded-xl border border-info/30 bg-info-soft p-4"><p class="flex items-center gap-2 text-xs font-semibold text-info">`);
      _push(ssrRenderComponent(unref(IconInfoCircle), { size: 15 }, null, _parent));
      _push(`Expected service level </p><p class="mt-2 text-xs leading-5 text-text-muted">${ssrInterpolate(sla.value)} based on the selected priority. Critical security and widespread outages are escalated immediately. </p></section><button class="inline-flex w-full items-center justify-center gap-1.5 rounded-md bg-primary px-4 py-2.5 text-xs text-white">`);
      _push(ssrRenderComponent(unref(IconSend), { size: 14 }, null, _parent));
      _push(`Submit ticket </button>`);
      if (submitted.value) {
        _push(`<p class="rounded-md bg-success-soft p-3 text-center text-xs text-success"> Mock ticket SUP-2026-0185 created. </p>`);
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/modules/support/Create.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
