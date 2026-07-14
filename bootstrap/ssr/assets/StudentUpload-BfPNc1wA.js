import { defineComponent, ref, unref, useSSRContext } from "vue";
import { ssrRenderAttrs, ssrRenderComponent, ssrRenderList, ssrRenderAttr, ssrIncludeBooleanAttr, ssrLooseContain, ssrLooseEqual, ssrInterpolate } from "vue/server-renderer";
import { useRouter, useRoute } from "vue-router";
import { IconUpload, IconFile, IconX } from "@tabler/icons-vue";
import { _ as _sfc_main$1 } from "./PageHeader-DV7ufis9.js";
import "../ssr.js";
const _sfc_main = /* @__PURE__ */ defineComponent({
  __name: "StudentUpload",
  __ssrInlineRender: true,
  setup(__props) {
    useRouter();
    const route = useRoute();
    const documentTypes = ["Course History", "COR"];
    const initialType = typeof route.query.type === "string" && documentTypes.includes(route.query.type) ? route.query.type : documentTypes[0];
    const documentType = ref(initialType);
    const file = ref(null);
    const error = ref("");
    const busy = ref(false);
    const result = ref(null);
    return (_ctx, _push, _parent, _attrs) => {
      _push(`<div${ssrRenderAttrs(_attrs)}>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        title: "Upload Document",
        description: "Submit your Course History or COR for OCR-assisted validation."
      }, null, _parent));
      _push(`<div class="grid gap-4 lg:grid-cols-3"><section class="space-y-4 rounded-lg border bg-surface p-4 lg:col-span-2"><div class="rounded-lg border bg-surface-muted px-3 py-2.5"><label class="block text-xs font-medium text-text-muted" for="document_type">Document type</label><select id="document_type" class="mt-1 h-9 w-full rounded-md border bg-surface px-3 text-sm font-semibold"><!--[-->`);
      ssrRenderList(documentTypes, (item) => {
        _push(`<option${ssrRenderAttr("value", item)}${ssrIncludeBooleanAttr(Array.isArray(documentType.value) ? ssrLooseContain(documentType.value, item) : ssrLooseEqual(documentType.value, item)) ? " selected" : ""}>${ssrInterpolate(item)}</option>`);
      });
      _push(`<!--]--></select></div><label class="block"><span class="mb-1.5 block text-xs font-medium">File</span><span class="relative grid min-h-40 cursor-pointer place-items-center rounded-lg border-2 border-dashed border-border-strong p-5 text-center hover:border-primary"><input type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" class="absolute inset-0 cursor-pointer opacity-0">`);
      if (!file.value) {
        _push(`<span>`);
        _push(ssrRenderComponent(unref(IconUpload), {
          size: 28,
          class: "mx-auto text-primary"
        }, null, _parent));
        _push(`<b class="mt-2 block text-sm">Choose a file or drag it here</b><span class="mt-1 block text-xs text-text-muted">PDF up to 20 MB; images up to 10 MB</span></span>`);
      } else {
        _push(`<span class="flex items-center gap-3">`);
        _push(ssrRenderComponent(unref(IconFile), {
          size: 26,
          class: "text-primary"
        }, null, _parent));
        _push(`<span class="text-left"><b class="block text-sm">${ssrInterpolate(file.value.name)}</b><span class="text-xs text-text-muted">${ssrInterpolate((file.value.size / 1024).toFixed(1))} KB</span></span><button type="button" class="rounded p-1 hover:bg-surface-muted">`);
        _push(ssrRenderComponent(unref(IconX), { size: 16 }, null, _parent));
        _push(`</button></span>`);
      }
      _push(`</span></label>`);
      if (error.value) {
        _push(`<p class="text-xs text-danger">${ssrInterpolate(error.value)}</p>`);
      } else {
        _push(`<!---->`);
      }
      if (result.value) {
        _push(`<div class="rounded-lg border border-success/30 bg-success-soft p-3"><p class="text-xs font-semibold text-success">OCR completed</p>`);
        if (result.value.confidence !== null) {
          _push(`<p class="mt-1 text-xs text-text-muted">Average confidence: ${ssrInterpolate(result.value.confidence.toFixed(1))}%</p>`);
        } else {
          _push(`<!---->`);
        }
        _push(`<p class="mt-2 max-h-40 overflow-auto whitespace-pre-wrap text-xs text-text-muted">${ssrInterpolate(result.value.text)}</p></div>`);
      } else {
        _push(`<!---->`);
      }
      _push(`<div class="flex justify-end gap-2"><button class="h-9 rounded-md border px-3 text-xs">Cancel</button><button class="h-9 rounded-md bg-primary px-3 text-xs font-medium text-white disabled:opacity-50"${ssrIncludeBooleanAttr(busy.value) ? " disabled" : ""}>${ssrInterpolate(busy.value ? "Processing OCR..." : result.value ? "Back to documents" : "Submit for validation")}</button></div></section><aside class="h-fit rounded-lg border bg-surface p-4"><h2 class="text-sm font-semibold">Tips for accepted documents</h2><ul class="mt-2 list-inside list-disc space-y-1.5 text-xs text-text-muted"><li>Upload the official Course History or COR PDF when available.</li><li>Use a clear scan or photo if the document is printed.</li><li>Ensure student details, subjects, and semester labels are legible.</li><li>OCR assists review but does not determine authenticity.</li></ul></aside></div></div>`);
    };
  }
});
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/modules/uploads/StudentUpload.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
