import { defineComponent, ref, onMounted, resolveComponent, withCtx, unref, createVNode, createTextVNode, mergeProps, useSSRContext } from "vue";
import { ssrRenderAttrs, ssrRenderComponent, ssrRenderAttr, ssrInterpolate, ssrIncludeBooleanAttr } from "vue/server-renderer";
import { useRoute } from "vue-router";
import { IconArrowLeft, IconFile, IconScan, IconShieldExclamation } from "@tabler/icons-vue";
import { _ as _sfc_main$1 } from "./PageHeader-DV7ufis9.js";
import "../ssr.js";
const _sfc_main = /* @__PURE__ */ defineComponent({
  __name: "Detail",
  __ssrInlineRender: true,
  setup(__props) {
    const route = useRoute();
    const item = ref(null);
    const notes = ref("");
    const busy = ref(false);
    const message = ref("");
    async function load() {
      const r = await fetch(`/api/document-submissions/${route.params.id}`);
      item.value = (await r.json()).data;
      notes.value = item.value?.review_notes || "";
    }
    onMounted(load);
    return (_ctx, _push, _parent, _attrs) => {
      const _component_RouterLink = resolveComponent("RouterLink");
      if (item.value) {
        _push(`<div${ssrRenderAttrs(_attrs)}>`);
        _push(ssrRenderComponent(_component_RouterLink, {
          to: "/app/documents",
          class: "mb-3 inline-flex items-center gap-1 text-xs text-text-muted"
        }, {
          default: withCtx((_, _push2, _parent2, _scopeId) => {
            if (_push2) {
              _push2(ssrRenderComponent(unref(IconArrowLeft), { size: 14 }, null, _parent2, _scopeId));
              _push2(`Validation queue`);
            } else {
              return [
                createVNode(unref(IconArrowLeft), { size: 14 }),
                createTextVNode("Validation queue")
              ];
            }
          }),
          _: 1
        }, _parent));
        _push(ssrRenderComponent(_sfc_main$1, {
          title: item.value.document_type,
          description: `From ${item.value.student_name} (${item.value.student_id})`
        }, null, _parent));
        _push(`<section class="grid gap-4 xl:grid-cols-[1.4fr_1fr]"><div class="rounded-lg border bg-surface p-4"><h2 class="flex items-center gap-2 text-sm font-semibold">`);
        _push(ssrRenderComponent(unref(IconFile), { size: 17 }, null, _parent));
        _push(`File preview</h2>`);
        if (item.value.mime_type === "application/pdf") {
          _push(`<iframe${ssrRenderAttr("src", item.value.file_url)} class="mt-4 h-[34rem] w-full rounded-md border"></iframe>`);
        } else {
          _push(`<img${ssrRenderAttr("src", item.value.file_url)}${ssrRenderAttr("alt", item.value.original_name)} class="mt-4 max-h-[34rem] w-full rounded-md bg-surface-muted object-contain">`);
        }
        _push(`</div><div class="space-y-4"><article class="rounded-lg border bg-surface p-4"><h2 class="flex items-center gap-2 text-sm font-semibold">`);
        _push(ssrRenderComponent(unref(IconScan), { size: 17 }, null, _parent));
        _push(`OCR extraction</h2>`);
        if (item.value.ocr_confidence !== null) {
          _push(`<p class="mt-3 text-xs text-text-muted">Average confidence: ${ssrInterpolate(item.value.ocr_confidence.toFixed(1))}%</p>`);
        } else {
          _push(`<!---->`);
        }
        _push(`<pre class="mt-3 max-h-72 overflow-auto whitespace-pre-wrap rounded bg-surface-muted p-3 text-xs">${ssrInterpolate(item.value.extracted_text || "No readable text detected.")}</pre></article><article class="rounded-lg border bg-surface p-4"><h2 class="flex items-center gap-2 text-sm font-semibold">`);
        _push(ssrRenderComponent(unref(IconShieldExclamation), { size: 17 }, null, _parent));
        _push(`Risk and metadata</h2><span class="mt-3 inline-block rounded-full bg-warning-soft px-2 py-1 text-xs text-warning">${ssrInterpolate(item.value.risk_level)} risk</span><pre class="mt-3 max-h-40 overflow-auto whitespace-pre-wrap text-micro text-text-muted">${ssrInterpolate(JSON.stringify(item.value.metadata_payload, null, 2) || "No metadata recorded.")}</pre></article><article class="rounded-lg border bg-surface p-4"><h2 class="text-sm font-semibold">Staff decision</h2><textarea class="mt-3 min-h-20 w-full rounded-md border p-3 text-xs" placeholder="Validation notes">${ssrInterpolate(notes.value)}</textarea><div class="mt-3 grid grid-cols-3 gap-2"><button${ssrIncludeBooleanAttr(busy.value) ? " disabled" : ""} class="rounded-md border px-2 py-2 text-xs">Return</button><button${ssrIncludeBooleanAttr(busy.value) ? " disabled" : ""} class="rounded-md border border-danger px-2 py-2 text-xs text-danger">Reject</button><button${ssrIncludeBooleanAttr(busy.value) ? " disabled" : ""} class="rounded-md bg-primary px-2 py-2 text-xs text-white">Approve</button></div>`);
        if (message.value) {
          _push(`<p class="mt-3 text-xs text-primary">${ssrInterpolate(message.value)}</p>`);
        } else {
          _push(`<!---->`);
        }
        _push(`<p class="mt-2 text-xs text-text-muted">Current status: ${ssrInterpolate(item.value.status.replaceAll("_", " "))}</p></article></div></section></div>`);
      } else {
        _push(`<p${ssrRenderAttrs(mergeProps({ class: "p-8 text-sm text-text-muted" }, _attrs))}>Loading submission…</p>`);
      }
    };
  }
});
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/modules/documents/Detail.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
