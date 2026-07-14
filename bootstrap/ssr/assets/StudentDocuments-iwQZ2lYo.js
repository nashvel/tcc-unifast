import { defineComponent, resolveComponent, mergeProps, withCtx, createVNode, resolveDynamicComponent, unref, openBlock, createBlock, toDisplayString, createCommentVNode, createTextVNode, useSSRContext } from "vue";
import { ssrRenderAttrs, ssrRenderComponent, ssrRenderList, ssrRenderClass, ssrInterpolate, ssrRenderVNode } from "vue/server-renderer";
import { IconBook2, IconUpload, IconChevronRight } from "@tabler/icons-vue";
import { _ as _sfc_main$1 } from "./PageHeader-DV7ufis9.js";
const _sfc_main = /* @__PURE__ */ defineComponent({
  __name: "StudentDocuments",
  __ssrInlineRender: true,
  setup(__props) {
    const documents = [
      {
        name: "Course History",
        status: "missing",
        label: "Upload",
        updated: "Not yet submitted",
        icon: IconBook2
      },
      {
        name: "COR",
        status: "missing",
        label: "Upload",
        updated: "Not yet submitted",
        icon: IconBook2
      }
    ];
    const groups = [
      {
        status: "missing",
        title: "Required documents",
        hint: "Upload your Course History and COR to complete your submission.",
        tone: "text-text-muted"
      },
      {
        status: "pending",
        title: "Under review",
        hint: "Our team is verifying these submissions.",
        tone: "text-info"
      },
      { status: "approved", title: "Approved", hint: "Verified and accepted.", tone: "text-success" }
    ];
    const statusClasses = {
      missing: "bg-primary-soft text-primary",
      pending: "bg-info-soft text-info",
      approved: "bg-success-soft text-success"
    };
    return (_ctx, _push, _parent, _attrs) => {
      const _component_RouterLink = resolveComponent("RouterLink");
      _push(`<div${ssrRenderAttrs(mergeProps({ class: "space-y-5 sm:space-y-6" }, _attrs))}>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        title: "Required Documents",
        description: "Track your Course History and COR submissions."
      }, null, _parent));
      _push(`<section class="rounded-2xl border bg-surface p-4 shadow-sm sm:p-5"><div class="flex items-start justify-between"><div><p class="text-xs font-medium uppercase tracking-wide text-text-muted"> Submission progress </p><p class="mt-1 text-3xl font-semibold"> 0 <span class="text-xl font-normal text-text-muted">/ 2</span></p><p class="text-xs text-text-muted">documents submitted</p></div><span class="rounded-full border px-3 py-1.5 text-xs font-semibold text-primary">0%</span></div><div class="mt-4 h-2 overflow-hidden rounded-full bg-surface-muted"><div class="h-full w-0 bg-primary"></div></div><div class="mt-4 grid grid-cols-3 gap-2 text-center"><!--[-->`);
      ssrRenderList([
        ["Approved", 0, "text-success"],
        ["Review", 0, "text-info"],
        ["To do", 2, "text-text-muted"]
      ], (stat) => {
        _push(`<div class="rounded-lg border py-2"><p class="${ssrRenderClass(["text-lg font-semibold", stat[2]])}">${ssrInterpolate(stat[1])}</p><p class="text-2xs uppercase text-text-soft">${ssrInterpolate(stat[0])}</p></div>`);
      });
      _push(`<!--]--></div></section><!--[-->`);
      ssrRenderList(groups, (group) => {
        _push(`<section class="space-y-2"><div class="flex justify-between px-1"><h2 class="${ssrRenderClass(["text-sm font-semibold", group.tone])}">${ssrInterpolate(group.title)} <span class="text-text-soft">(${ssrInterpolate(documents.filter((document) => document.status === group.status).length)})</span></h2><p class="hidden text-micro text-text-soft sm:block">${ssrInterpolate(group.hint)}</p></div><!--[-->`);
        ssrRenderList(documents.filter((item) => item.status === group.status), (document) => {
          _push(ssrRenderComponent(_component_RouterLink, {
            key: document.name,
            to: { path: "/student/upload", query: { type: document.name } },
            class: "group grid grid-cols-[auto_1fr_auto] items-center gap-3 rounded-xl border bg-surface p-3.5 hover:border-primary/40"
          }, {
            default: withCtx((_, _push2, _parent2, _scopeId) => {
              if (_push2) {
                _push2(`<span class="${ssrRenderClass(["grid h-10 w-10 place-items-center rounded-xl", statusClasses[document.status]])}"${_scopeId}>`);
                ssrRenderVNode(_push2, createVNode(resolveDynamicComponent(document.icon), { size: 20 }, null), _parent2, _scopeId);
                _push2(`</span><div${_scopeId}><p class="text-sm font-medium"${_scopeId}>${ssrInterpolate(document.name)}</p><p class="text-micro text-text-muted"${_scopeId}>${ssrInterpolate(document.updated)}</p></div><div class="flex items-center gap-2"${_scopeId}><span class="${ssrRenderClass([
                  "inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-micro font-medium",
                  statusClasses[document.status]
                ])}"${_scopeId}>`);
                if (document.status === "missing") {
                  _push2(ssrRenderComponent(unref(IconUpload), { size: 12 }, null, _parent2, _scopeId));
                } else {
                  _push2(`<!---->`);
                }
                _push2(`${ssrInterpolate(document.label)}</span>`);
                _push2(ssrRenderComponent(unref(IconChevronRight), {
                  size: 16,
                  class: "text-text-soft"
                }, null, _parent2, _scopeId));
                _push2(`</div>`);
              } else {
                return [
                  createVNode("span", {
                    class: ["grid h-10 w-10 place-items-center rounded-xl", statusClasses[document.status]]
                  }, [
                    (openBlock(), createBlock(resolveDynamicComponent(document.icon), { size: 20 }))
                  ], 2),
                  createVNode("div", null, [
                    createVNode("p", { class: "text-sm font-medium" }, toDisplayString(document.name), 1),
                    createVNode("p", { class: "text-micro text-text-muted" }, toDisplayString(document.updated), 1)
                  ]),
                  createVNode("div", { class: "flex items-center gap-2" }, [
                    createVNode("span", {
                      class: [
                        "inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-micro font-medium",
                        statusClasses[document.status]
                      ]
                    }, [
                      document.status === "missing" ? (openBlock(), createBlock(unref(IconUpload), {
                        key: 0,
                        size: 12
                      })) : createCommentVNode("", true),
                      createTextVNode(toDisplayString(document.label), 1)
                    ], 2),
                    createVNode(unref(IconChevronRight), {
                      size: 16,
                      class: "text-text-soft"
                    })
                  ])
                ];
              }
            }),
            _: 2
          }, _parent));
        });
        _push(`<!--]--></section>`);
      });
      _push(`<!--]--></div>`);
    };
  }
});
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/modules/documents/StudentDocuments.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
