import { defineComponent, ref, onMounted, computed, resolveComponent, unref, withCtx, createTextVNode, openBlock, createBlock, Fragment, renderList, createVNode, toDisplayString, createCommentVNode, useSSRContext } from "vue";
import { ssrRenderAttrs, ssrRenderComponent, ssrRenderAttr, ssrRenderList, ssrInterpolate } from "vue/server-renderer";
import { IconSearch } from "@tabler/icons-vue";
import { _ as _sfc_main$1 } from "./PageHeader-DV7ufis9.js";
import { _ as _sfc_main$2 } from "./DataTable-BXM8pciO.js";
const _sfc_main = /* @__PURE__ */ defineComponent({
  __name: "Index",
  __ssrInlineRender: true,
  setup(__props) {
    const query = ref("");
    const docs = ref([]);
    const loading = ref(true);
    onMounted(async () => {
      try {
        const r = await fetch("/api/document-submissions");
        docs.value = (await r.json()).data || [];
      } finally {
        loading.value = false;
      }
    });
    const rows = computed(() => docs.value.filter((d) => `${d.student_name} ${d.student_id} ${d.document_type}`.toLowerCase().includes(query.value.toLowerCase())));
    return (_ctx, _push, _parent, _attrs) => {
      const _component_RouterLink = resolveComponent("RouterLink");
      _push(`<div${ssrRenderAttrs(_attrs)}>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        title: "Document Validation Queue",
        description: "Review live student submissions and OCR-assisted results."
      }, null, _parent));
      _push(`<div class="relative mb-3 max-w-xl">`);
      _push(ssrRenderComponent(unref(IconSearch), {
        size: 14,
        class: "absolute left-3 top-1/2 -translate-y-1/2 text-text-soft"
      }, null, _parent));
      _push(`<input${ssrRenderAttr("value", query.value)} class="h-9 w-full rounded-md border pl-9 pr-3 text-xs" placeholder="Search grantee or document"></div>`);
      _push(ssrRenderComponent(_sfc_main$2, { headings: ["Grantee", "Student #", "Document", "Submitted", "Status", "Risk", ""] }, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<!--[-->`);
            ssrRenderList(rows.value, (d) => {
              _push2(`<tr${_scopeId}><td class="px-3 py-3 font-medium"${_scopeId}>${ssrInterpolate(d.student_name)}</td><td class="px-3 py-3 font-mono"${_scopeId}>${ssrInterpolate(d.student_id)}</td><td class="px-3 py-3 text-text-muted"${_scopeId}>${ssrInterpolate(d.document_type)}</td><td class="px-3 py-3 text-text-muted"${_scopeId}>${ssrInterpolate(new Date(d.created_at).toLocaleString())}</td><td class="px-3 py-3 capitalize"${_scopeId}>${ssrInterpolate(d.status.replaceAll("_", " "))}</td><td class="px-3 py-3"${_scopeId}><span class="rounded-full bg-warning-soft px-2 py-0.5 text-micro text-warning"${_scopeId}>${ssrInterpolate(d.risk_level)}</span></td><td class="px-3 py-3 text-right"${_scopeId}>`);
              _push2(ssrRenderComponent(_component_RouterLink, {
                to: `/app/documents/${d.id}`,
                class: "text-primary"
              }, {
                default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                  if (_push3) {
                    _push3(`Review`);
                  } else {
                    return [
                      createTextVNode("Review")
                    ];
                  }
                }),
                _: 2
              }, _parent2, _scopeId));
              _push2(`</td></tr>`);
            });
            _push2(`<!--]-->`);
            if (!loading.value && !rows.value.length) {
              _push2(`<tr${_scopeId}><td colspan="7" class="px-3 py-8 text-center text-text-muted"${_scopeId}>No submissions in the queue.</td></tr>`);
            } else {
              _push2(`<!---->`);
            }
          } else {
            return [
              (openBlock(true), createBlock(Fragment, null, renderList(rows.value, (d) => {
                return openBlock(), createBlock("tr", {
                  key: d.id
                }, [
                  createVNode("td", { class: "px-3 py-3 font-medium" }, toDisplayString(d.student_name), 1),
                  createVNode("td", { class: "px-3 py-3 font-mono" }, toDisplayString(d.student_id), 1),
                  createVNode("td", { class: "px-3 py-3 text-text-muted" }, toDisplayString(d.document_type), 1),
                  createVNode("td", { class: "px-3 py-3 text-text-muted" }, toDisplayString(new Date(d.created_at).toLocaleString()), 1),
                  createVNode("td", { class: "px-3 py-3 capitalize" }, toDisplayString(d.status.replaceAll("_", " ")), 1),
                  createVNode("td", { class: "px-3 py-3" }, [
                    createVNode("span", { class: "rounded-full bg-warning-soft px-2 py-0.5 text-micro text-warning" }, toDisplayString(d.risk_level), 1)
                  ]),
                  createVNode("td", { class: "px-3 py-3 text-right" }, [
                    createVNode(_component_RouterLink, {
                      to: `/app/documents/${d.id}`,
                      class: "text-primary"
                    }, {
                      default: withCtx(() => [
                        createTextVNode("Review")
                      ]),
                      _: 1
                    }, 8, ["to"])
                  ])
                ]);
              }), 128)),
              !loading.value && !rows.value.length ? (openBlock(), createBlock("tr", { key: 0 }, [
                createVNode("td", {
                  colspan: "7",
                  class: "px-3 py-8 text-center text-text-muted"
                }, "No submissions in the queue.")
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/modules/documents/Index.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
