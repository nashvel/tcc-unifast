import { defineComponent, ref, computed, resolveComponent, withCtx, unref, createVNode, createTextVNode, toDisplayString, openBlock, createBlock, Fragment, renderList, createCommentVNode, useSSRContext } from "vue";
import { ssrRenderAttrs, ssrRenderComponent, ssrRenderAttr, ssrIncludeBooleanAttr, ssrLooseContain, ssrLooseEqual, ssrRenderList, ssrInterpolate, ssrRenderClass } from "vue/server-renderer";
import { IconDownload, IconSearch } from "@tabler/icons-vue";
import { _ as _sfc_main$1 } from "./PageHeader-DV7ufis9.js";
import { _ as _sfc_main$2 } from "./DataTable-BXM8pciO.js";
import { g as grantees } from "./data-EfcXN-D_.js";
const _sfc_main = /* @__PURE__ */ defineComponent({
  __name: "Index",
  __ssrInlineRender: true,
  setup(__props) {
    const query = ref("");
    const account = ref("all");
    const submission = ref("all");
    const eligibility = ref("all");
    const risk = ref("all");
    const filtered = computed(
      () => grantees.filter(
        (g) => (!query.value || `${g.name} ${g.studentNumber}`.toLowerCase().includes(query.value.toLowerCase())) && (account.value === "all" || g.account === account.value) && (submission.value === "all" || g.submission === submission.value) && (eligibility.value === "all" || g.eligibility === eligibility.value) && (risk.value === "all" || g.risk === risk.value)
      )
    );
    const tone = (value) => value.includes("active") || value === "approved" || value === "eligible" || value === "low" ? "bg-success-soft text-success" : value === "high" || value === "rejected" || value === "ineligible" || value === "locked" ? "bg-danger-soft text-danger" : value === "medium" || value === "pending_activation" ? "bg-warning-soft text-warning" : "bg-info-soft text-info";
    return (_ctx, _push, _parent, _attrs) => {
      const _component_RouterLink = resolveComponent("RouterLink");
      _push(`<div${ssrRenderAttrs(_attrs)}>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        title: "Grantees",
        description: "Search, filter, and manage TES grantee records."
      }, {
        actions: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<button class="inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-xs"${_scopeId}>`);
            _push2(ssrRenderComponent(unref(IconDownload), { size: 14 }, null, _parent2, _scopeId));
            _push2(`Export CSV </button>`);
          } else {
            return [
              createVNode("button", { class: "inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-xs" }, [
                createVNode(unref(IconDownload), { size: 14 }),
                createTextVNode("Export CSV ")
              ])
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(`<section class="mb-4 grid gap-2 rounded-lg border bg-surface p-3 md:grid-cols-6"><div class="relative md:col-span-2">`);
      _push(ssrRenderComponent(unref(IconSearch), {
        size: 14,
        class: "absolute left-3 top-1/2 -translate-y-1/2 text-text-soft"
      }, null, _parent));
      _push(`<input${ssrRenderAttr("value", query.value)} placeholder="Search by name or student #" class="h-9 w-full rounded-md border pl-9 pr-3 text-xs"></div><select class="rounded-md border bg-surface px-2 text-xs"><option>All batches</option></select><select class="rounded-md border bg-surface px-2 text-xs"><option value="all"${ssrIncludeBooleanAttr(Array.isArray(account.value) ? ssrLooseContain(account.value, "all") : ssrLooseEqual(account.value, "all")) ? " selected" : ""}>All accounts</option><option value="active"${ssrIncludeBooleanAttr(Array.isArray(account.value) ? ssrLooseContain(account.value, "active") : ssrLooseEqual(account.value, "active")) ? " selected" : ""}>Active</option><option value="inactive"${ssrIncludeBooleanAttr(Array.isArray(account.value) ? ssrLooseContain(account.value, "inactive") : ssrLooseEqual(account.value, "inactive")) ? " selected" : ""}>Inactive</option><option value="pending_activation"${ssrIncludeBooleanAttr(Array.isArray(account.value) ? ssrLooseContain(account.value, "pending_activation") : ssrLooseEqual(account.value, "pending_activation")) ? " selected" : ""}>Pending activation</option><option value="locked"${ssrIncludeBooleanAttr(Array.isArray(account.value) ? ssrLooseContain(account.value, "locked") : ssrLooseEqual(account.value, "locked")) ? " selected" : ""}>Locked</option></select><select class="rounded-md border bg-surface px-2 text-xs"><option value="all"${ssrIncludeBooleanAttr(Array.isArray(submission.value) ? ssrLooseContain(submission.value, "all") : ssrLooseEqual(submission.value, "all")) ? " selected" : ""}>All submissions</option><option value="approved"${ssrIncludeBooleanAttr(Array.isArray(submission.value) ? ssrLooseContain(submission.value, "approved") : ssrLooseEqual(submission.value, "approved")) ? " selected" : ""}>Approved</option><option value="submitted"${ssrIncludeBooleanAttr(Array.isArray(submission.value) ? ssrLooseContain(submission.value, "submitted") : ssrLooseEqual(submission.value, "submitted")) ? " selected" : ""}>Submitted</option><option value="under_review"${ssrIncludeBooleanAttr(Array.isArray(submission.value) ? ssrLooseContain(submission.value, "under_review") : ssrLooseEqual(submission.value, "under_review")) ? " selected" : ""}>Under review</option><option value="not_submitted"${ssrIncludeBooleanAttr(Array.isArray(submission.value) ? ssrLooseContain(submission.value, "not_submitted") : ssrLooseEqual(submission.value, "not_submitted")) ? " selected" : ""}>Not submitted</option></select><div class="grid grid-cols-2 gap-2 md:contents"><select class="rounded-md border bg-surface px-2 text-xs"><option value="all"${ssrIncludeBooleanAttr(Array.isArray(eligibility.value) ? ssrLooseContain(eligibility.value, "all") : ssrLooseEqual(eligibility.value, "all")) ? " selected" : ""}>All eligibility</option><option value="eligible"${ssrIncludeBooleanAttr(Array.isArray(eligibility.value) ? ssrLooseContain(eligibility.value, "eligible") : ssrLooseEqual(eligibility.value, "eligible")) ? " selected" : ""}>Eligible</option><option value="ineligible"${ssrIncludeBooleanAttr(Array.isArray(eligibility.value) ? ssrLooseContain(eligibility.value, "ineligible") : ssrLooseEqual(eligibility.value, "ineligible")) ? " selected" : ""}>Ineligible</option><option value="pending"${ssrIncludeBooleanAttr(Array.isArray(eligibility.value) ? ssrLooseContain(eligibility.value, "pending") : ssrLooseEqual(eligibility.value, "pending")) ? " selected" : ""}>Pending</option><option value="for_evaluation"${ssrIncludeBooleanAttr(Array.isArray(eligibility.value) ? ssrLooseContain(eligibility.value, "for_evaluation") : ssrLooseEqual(eligibility.value, "for_evaluation")) ? " selected" : ""}>For evaluation</option></select><select class="rounded-md border bg-surface px-2 text-xs"><option value="all"${ssrIncludeBooleanAttr(Array.isArray(risk.value) ? ssrLooseContain(risk.value, "all") : ssrLooseEqual(risk.value, "all")) ? " selected" : ""}>All risk</option><option value="low"${ssrIncludeBooleanAttr(Array.isArray(risk.value) ? ssrLooseContain(risk.value, "low") : ssrLooseEqual(risk.value, "low")) ? " selected" : ""}>Low</option><option value="medium"${ssrIncludeBooleanAttr(Array.isArray(risk.value) ? ssrLooseContain(risk.value, "medium") : ssrLooseEqual(risk.value, "medium")) ? " selected" : ""}>Medium</option><option value="high"${ssrIncludeBooleanAttr(Array.isArray(risk.value) ? ssrLooseContain(risk.value, "high") : ssrLooseEqual(risk.value, "high")) ? " selected" : ""}>High</option></select></div></section>`);
      _push(ssrRenderComponent(_sfc_main$2, { headings: [
        "Student #",
        "Name",
        "Program",
        "Batch",
        "Account",
        "Submission",
        "Eligibility",
        "Risk"
      ] }, {
        footer: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<footer class="flex justify-between border-t px-3 py-2.5 text-xs text-text-muted"${_scopeId}><span${_scopeId}>Showing ${ssrInterpolate(filtered.value.length)} of ${ssrInterpolate(unref(grantees).length)}</span><span${_scopeId}>Page 1 of 1</span></footer>`);
          } else {
            return [
              createVNode("footer", { class: "flex justify-between border-t px-3 py-2.5 text-xs text-text-muted" }, [
                createVNode("span", null, "Showing " + toDisplayString(filtered.value.length) + " of " + toDisplayString(unref(grantees).length), 1),
                createVNode("span", null, "Page 1 of 1")
              ])
            ];
          }
        }),
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<!--[-->`);
            ssrRenderList(filtered.value, (g) => {
              _push2(`<tr${_scopeId}><td class="px-3 py-3 font-mono"${_scopeId}>${ssrInterpolate(g.studentNumber)}</td><td class="px-3 py-3"${_scopeId}>`);
              _push2(ssrRenderComponent(_component_RouterLink, {
                to: `/app/grantees/${g.id}`,
                class: "font-medium hover:text-primary"
              }, {
                default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                  if (_push3) {
                    _push3(`${ssrInterpolate(g.name)}`);
                  } else {
                    return [
                      createTextVNode(toDisplayString(g.name), 1)
                    ];
                  }
                }),
                _: 2
              }, _parent2, _scopeId));
              _push2(`</td><td class="px-3 py-3 text-text-muted"${_scopeId}>${ssrInterpolate(g.program)}</td><td class="px-3 py-3 text-text-muted"${_scopeId}>${ssrInterpolate(g.batch)}</td><!--[-->`);
              ssrRenderList([g.account, g.submission, g.eligibility, g.risk], (value) => {
                _push2(`<td class="px-3 py-3"${_scopeId}><span class="${ssrRenderClass(["rounded-full px-2 py-0.5 text-micro capitalize", tone(value)])}"${_scopeId}>${ssrInterpolate(value.replaceAll("_", " "))}</span></td>`);
              });
              _push2(`<!--]--></tr>`);
            });
            _push2(`<!--]-->`);
            if (!filtered.value.length) {
              _push2(`<tr${_scopeId}><td colspan="8" class="p-8 text-center text-text-muted"${_scopeId}>No grantees found.</td></tr>`);
            } else {
              _push2(`<!---->`);
            }
          } else {
            return [
              (openBlock(true), createBlock(Fragment, null, renderList(filtered.value, (g) => {
                return openBlock(), createBlock("tr", {
                  key: g.id
                }, [
                  createVNode("td", { class: "px-3 py-3 font-mono" }, toDisplayString(g.studentNumber), 1),
                  createVNode("td", { class: "px-3 py-3" }, [
                    createVNode(_component_RouterLink, {
                      to: `/app/grantees/${g.id}`,
                      class: "font-medium hover:text-primary"
                    }, {
                      default: withCtx(() => [
                        createTextVNode(toDisplayString(g.name), 1)
                      ]),
                      _: 2
                    }, 1032, ["to"])
                  ]),
                  createVNode("td", { class: "px-3 py-3 text-text-muted" }, toDisplayString(g.program), 1),
                  createVNode("td", { class: "px-3 py-3 text-text-muted" }, toDisplayString(g.batch), 1),
                  (openBlock(true), createBlock(Fragment, null, renderList([g.account, g.submission, g.eligibility, g.risk], (value) => {
                    return openBlock(), createBlock("td", {
                      key: value,
                      class: "px-3 py-3"
                    }, [
                      createVNode("span", {
                        class: ["rounded-full px-2 py-0.5 text-micro capitalize", tone(value)]
                      }, toDisplayString(value.replaceAll("_", " ")), 3)
                    ]);
                  }), 128))
                ]);
              }), 128)),
              !filtered.value.length ? (openBlock(), createBlock("tr", { key: 0 }, [
                createVNode("td", {
                  colspan: "8",
                  class: "p-8 text-center text-text-muted"
                }, "No grantees found.")
              ])) : createCommentVNode("", true)
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(`</div>`);
    };
  }
});
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/modules/grantees/Index.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
