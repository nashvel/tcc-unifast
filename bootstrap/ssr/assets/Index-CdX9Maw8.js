import { defineComponent, ref, computed, withCtx, unref, createVNode, createTextVNode, resolveDynamicComponent, useSSRContext } from "vue";
import { ssrRenderAttrs, ssrRenderComponent, ssrRenderList, ssrInterpolate, ssrRenderAttr, ssrIncludeBooleanAttr, ssrLooseContain, ssrLooseEqual, ssrRenderVNode } from "vue/server-renderer";
import { IconUpload, IconSearch, IconPhoto, IconFileTypePdf, IconFile } from "@tabler/icons-vue";
import { _ as _sfc_main$1 } from "./PageHeader-DV7ufis9.js";
const _sfc_main = /* @__PURE__ */ defineComponent({
  __name: "Index",
  __ssrInlineRender: true,
  setup(__props) {
    const query = ref("");
    const category = ref("all");
    const files = [
      ["Certificate of Enrollment.pdf", "document", "Maria Angela Santos", "1.8 MB", "May 12, 2026"],
      ["PSA Birth Certificate.jpg", "image", "Nicole Anne Flores", "2.4 MB", "May 11, 2026"],
      ["Grades Transcript.pdf", "document", "John Paul Ramirez", "980 KB", "May 10, 2026"],
      ["Profile Photo.png", "image", "Christian Dela Cruz", "420 KB", "May 9, 2026"]
    ];
    const rows = computed(
      () => files.filter(
        (f) => (category.value === "all" || f[1] === category.value) && `${f[0]} ${f[2]}`.toLowerCase().includes(query.value.toLowerCase())
      )
    );
    return (_ctx, _push, _parent, _attrs) => {
      _push(`<div${ssrRenderAttrs(_attrs)}>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        title: "File Manager",
        description: "Browse and organize uploaded scholarship files."
      }, {
        actions: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<button class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-xs text-white"${_scopeId}>`);
            _push2(ssrRenderComponent(unref(IconUpload), { size: 14 }, null, _parent2, _scopeId));
            _push2(`Upload file </button>`);
          } else {
            return [
              createVNode("button", { class: "inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-xs text-white" }, [
                createVNode(unref(IconUpload), { size: 14 }),
                createTextVNode("Upload file ")
              ])
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(`<section class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4"><!--[-->`);
      ssrRenderList([
        ["All files", "2,486"],
        ["Documents", "1,842"],
        ["Images", "612"],
        ["Storage used", "4.8 GB"]
      ], (c) => {
        _push(`<article class="rounded-lg border bg-surface p-4"><p class="text-xs text-text-muted">${ssrInterpolate(c[0])}</p><p class="mt-1 text-xl font-semibold">${ssrInterpolate(c[1])}</p></article>`);
      });
      _push(`<!--]--></section><section class="mb-3 flex flex-wrap gap-2"><div class="relative min-w-64 flex-1">`);
      _push(ssrRenderComponent(unref(IconSearch), {
        size: 14,
        class: "absolute left-3 top-1/2 -translate-y-1/2 text-text-soft"
      }, null, _parent));
      _push(`<input${ssrRenderAttr("value", query.value)} class="h-9 w-full rounded-md border pl-9 pr-3 text-xs" placeholder="Search files or owners"></div><select class="h-9 rounded-md border bg-surface px-3 text-xs"><option value="all"${ssrIncludeBooleanAttr(Array.isArray(category.value) ? ssrLooseContain(category.value, "all") : ssrLooseEqual(category.value, "all")) ? " selected" : ""}>All categories</option><option value="document"${ssrIncludeBooleanAttr(Array.isArray(category.value) ? ssrLooseContain(category.value, "document") : ssrLooseEqual(category.value, "document")) ? " selected" : ""}>Documents</option><option value="image"${ssrIncludeBooleanAttr(Array.isArray(category.value) ? ssrLooseContain(category.value, "image") : ssrLooseEqual(category.value, "image")) ? " selected" : ""}>Images</option></select></section><section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"><!--[-->`);
      ssrRenderList(rows.value, (f) => {
        _push(`<article class="rounded-lg border bg-surface p-4">`);
        ssrRenderVNode(_push, createVNode(resolveDynamicComponent(f[1] === "image" ? unref(IconPhoto) : f[0].endsWith(".pdf") ? unref(IconFileTypePdf) : unref(IconFile)), {
          size: 28,
          class: "text-primary"
        }, null), _parent);
        _push(`<h2 class="mt-4 truncate text-sm font-semibold">${ssrInterpolate(f[0])}</h2><p class="mt-1 text-xs text-text-muted">${ssrInterpolate(f[2])}</p><div class="mt-4 flex justify-between border-t pt-3 text-micro text-text-muted"><span>${ssrInterpolate(f[3])}</span><span>${ssrInterpolate(f[4])}</span></div><div class="mt-3 flex gap-3 text-xs text-primary"><button>Preview</button><button>Download</button></div></article>`);
      });
      _push(`<!--]--></section>`);
      if (!rows.value.length) {
        _push(`<p class="rounded-lg border p-8 text-center text-sm text-text-muted"> No files found. </p>`);
      } else {
        _push(`<!---->`);
      }
      _push(`</div>`);
    };
  }
});
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/modules/files/Index.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
