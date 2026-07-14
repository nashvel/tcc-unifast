import { defineComponent, computed, ref, resolveComponent, withCtx, unref, createVNode, createTextVNode, resolveDynamicComponent, useSSRContext } from "vue";
import { ssrRenderAttrs, ssrRenderComponent, ssrInterpolate, ssrRenderList, ssrRenderClass, ssrRenderVNode } from "vue/server-renderer";
import { useRoute } from "vue-router";
import { IconArrowLeft, IconUser, IconFileText, IconHistory, IconNote, IconSchool, IconCheck } from "@tabler/icons-vue";
import { _ as _sfc_main$1 } from "./PageHeader-DV7ufis9.js";
import { _ as _sfc_main$2 } from "./DiceBearAvatar-C3Eyt9zS.js";
import { g as grantees } from "./data-EfcXN-D_.js";
const _sfc_main = /* @__PURE__ */ defineComponent({
  __name: "Detail",
  __ssrInlineRender: true,
  setup(__props) {
    const route = useRoute();
    const grantee = computed(() => grantees.find((item) => item.id === route.params.id) ?? grantees[0]);
    const tab = ref("overview");
    const notes = ref(grantee.value.notes ?? "");
    return (_ctx, _push, _parent, _attrs) => {
      const _component_RouterLink = resolveComponent("RouterLink");
      _push(`<div${ssrRenderAttrs(_attrs)}>`);
      _push(ssrRenderComponent(_component_RouterLink, {
        to: "/app/grantees",
        class: "mb-3 inline-flex items-center gap-1 text-xs text-text-muted"
      }, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(ssrRenderComponent(unref(IconArrowLeft), { size: 13 }, null, _parent2, _scopeId));
            _push2(`Back to grantees`);
          } else {
            return [
              createVNode(unref(IconArrowLeft), { size: 13 }),
              createTextVNode("Back to grantees")
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(ssrRenderComponent(_sfc_main$1, {
        title: grantee.value.name,
        description: `${grantee.value.studentNumber} · ${grantee.value.program}`
      }, null, _parent));
      _push(`<section class="mb-4 flex flex-wrap items-center gap-4 rounded-lg border bg-surface p-4">`);
      _push(ssrRenderComponent(_sfc_main$2, {
        seed: grantee.value.email,
        alt: grantee.value.name,
        size: 56
      }, null, _parent));
      _push(`<div class="min-w-0 flex-1"><p class="text-sm font-semibold">${ssrInterpolate(grantee.value.email)}</p><p class="text-xs text-text-muted">${ssrInterpolate(grantee.value.contact)} · Year ${ssrInterpolate(grantee.value.yearLevel)}</p></div><div class="grid grid-cols-3 gap-5 text-center"><div><p class="text-lg font-semibold">${ssrInterpolate(grantee.value.gwa)}</p><p class="text-micro text-text-muted">GWA</p></div><div><p class="text-lg font-semibold">${ssrInterpolate(grantee.value.completion)}%</p><p class="text-micro text-text-muted">Profile</p></div><div><p class="text-lg font-semibold capitalize">${ssrInterpolate(grantee.value.risk)}</p><p class="text-micro text-text-muted">Risk</p></div></div></section><nav class="mb-4 flex gap-1 border-b"><!--[-->`);
      ssrRenderList([
        ["overview", "Overview", unref(IconUser)],
        ["requirements", "Requirements", unref(IconFileText)],
        ["history", "History", unref(IconHistory)],
        ["notes", "Notes", unref(IconNote)]
      ], (item) => {
        _push(`<button class="${ssrRenderClass([
          "inline-flex items-center gap-1.5 border-b-2 px-3 py-2 text-xs",
          tab.value === item[0] ? "border-primary text-primary" : "border-transparent text-text-muted"
        ])}">`);
        ssrRenderVNode(_push, createVNode(resolveDynamicComponent(item[2]), { size: 14 }, null), _parent);
        _push(`${ssrInterpolate(item[1])}</button>`);
      });
      _push(`<!--]--></nav>`);
      if (tab.value === "overview") {
        _push(`<div class="grid gap-4 lg:grid-cols-2"><!--[-->`);
        ssrRenderList([
          {
            title: "Personal Information",
            icon: unref(IconUser),
            rows: [
              ["Full name", grantee.value.name],
              ["Birthdate", grantee.value.birthdate],
              ["Email", grantee.value.email],
              ["Contact", grantee.value.contact]
            ]
          },
          {
            title: "Academic Information",
            icon: unref(IconSchool),
            rows: [
              ["University", grantee.value.university],
              ["Program", grantee.value.program],
              ["Year level", String(grantee.value.yearLevel)],
              ["Batch", grantee.value.batch]
            ]
          }
        ], (group) => {
          _push(`<section class="rounded-lg border bg-surface p-4"><h2 class="mb-3 flex items-center gap-2 text-sm font-semibold">`);
          ssrRenderVNode(_push, createVNode(resolveDynamicComponent(group.icon), {
            size: 16,
            class: "text-primary"
          }, null), _parent);
          _push(`${ssrInterpolate(group.title)}</h2><dl class="divide-y"><!--[-->`);
          ssrRenderList(group.rows, (row) => {
            _push(`<div class="grid grid-cols-3 py-2 text-xs"><dt class="text-text-muted">${ssrInterpolate(row[0])}</dt><dd class="col-span-2">${ssrInterpolate(row[1])}</dd></div>`);
          });
          _push(`<!--]--></dl></section>`);
        });
        _push(`<!--]--></div>`);
      } else if (tab.value === "requirements") {
        _push(`<section class="rounded-lg border bg-surface p-4"><h2 class="text-sm font-semibold">Submitted Requirements</h2><ul class="mt-3 divide-y"><!--[-->`);
        ssrRenderList([
          "PSA Birth Certificate",
          "Certificate of Enrollment",
          "Grades (Transcript)",
          "Income Tax Return",
          "2x2 ID Picture"
        ], (item) => {
          _push(`<li class="flex items-center justify-between py-3 text-sm"><span class="flex items-center gap-2">`);
          _push(ssrRenderComponent(unref(IconFileText), {
            size: 15,
            class: "text-primary"
          }, null, _parent));
          _push(`${ssrInterpolate(item)}</span><span class="inline-flex items-center gap-1 rounded-full bg-success-soft px-2 py-0.5 text-micro text-success">`);
          _push(ssrRenderComponent(unref(IconCheck), { size: 11 }, null, _parent));
          _push(`Approved</span></li>`);
        });
        _push(`<!--]--></ul></section>`);
      } else if (tab.value === "history") {
        _push(`<section class="rounded-lg border bg-surface p-4"><h2 class="text-sm font-semibold">Validation History</h2><ol class="mt-4 space-y-4"><!--[-->`);
        ssrRenderList([
          ["Document validation completed", "May 12, 2025 · UniFAST Staff"],
          ["Eligibility evaluation passed", "May 10, 2025 · Rules engine"],
          ["Account activated", "May 4, 2025 · Student"]
        ], (event) => {
          _push(`<li class="flex gap-3"><span class="mt-1 h-2 w-2 rounded-full bg-primary"></span><div><p class="text-sm font-medium">${ssrInterpolate(event[0])}</p><p class="text-xs text-text-muted">${ssrInterpolate(event[1])}</p></div></li>`);
        });
        _push(`<!--]--></ol></section>`);
      } else {
        _push(`<section class="rounded-lg border bg-surface p-4"><h2 class="text-sm font-semibold">Staff Notes</h2><textarea rows="6" placeholder="Add an internal note…" class="mt-3 w-full rounded-md border bg-surface p-3 text-sm">${ssrInterpolate(notes.value)}</textarea><div class="mt-2 flex justify-end"><button class="rounded-md bg-primary px-3 py-2 text-xs text-white">Save note</button></div></section>`);
      }
      _push(`</div>`);
    };
  }
});
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/modules/grantees/Detail.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
