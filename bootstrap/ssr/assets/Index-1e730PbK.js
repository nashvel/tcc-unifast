import { defineComponent, ref, resolveComponent, withCtx, unref, createVNode, createTextVNode, toDisplayString, useSSRContext } from "vue";
import { ssrRenderAttrs, ssrRenderComponent, ssrRenderList, ssrInterpolate, ssrRenderStyle } from "vue/server-renderer";
import { IconPlus, IconUsers } from "@tabler/icons-vue";
import { _ as _sfc_main$2 } from "./AppDialog-CSs1wZpw.js";
import { _ as _sfc_main$1 } from "./PageHeader-DV7ufis9.js";
const _sfc_main = /* @__PURE__ */ defineComponent({
  __name: "Index",
  __ssrInlineRender: true,
  setup(__props) {
    const batchDialog = ref(false);
    const batches = [
      {
        id: 1,
        name: "TES 2025 — Batch 1",
        year: "AY 2025–2026",
        semester: "1st Semester",
        count: 1248,
        status: "Inactive accounts",
        progress: 0
      },
      {
        id: 3,
        name: "TES 2025 — Batch 03",
        year: "AY 2024–2025",
        semester: "2nd Semester",
        count: 1106,
        status: "Released",
        progress: 100
      },
      {
        id: 2,
        name: "TES 2025 — Batch 02",
        year: "AY 2024–2025",
        semester: "1st Semester",
        count: 984,
        status: "Completed",
        progress: 100
      }
    ];
    return (_ctx, _push, _parent, _attrs) => {
      const _component_RouterLink = resolveComponent("RouterLink");
      _push(`<div${ssrRenderAttrs(_attrs)}>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        title: "Batches",
        description: "Manage TES grantee batches per academic period."
      }, {
        actions: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<button class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-xs text-white"${_scopeId}>`);
            _push2(ssrRenderComponent(unref(IconPlus), { size: 14 }, null, _parent2, _scopeId));
            _push2(`New batch </button>`);
          } else {
            return [
              createVNode("button", {
                class: "inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-xs text-white",
                onClick: ($event) => batchDialog.value = true
              }, [
                createVNode(unref(IconPlus), { size: 14 }),
                createTextVNode("New batch ")
              ], 8, ["onClick"])
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(`<section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"><!--[-->`);
      ssrRenderList(batches, (batch) => {
        _push(ssrRenderComponent(_component_RouterLink, {
          key: batch.id,
          to: `/app/batches/${batch.id}`,
          class: "rounded-lg border bg-surface p-5 transition hover:border-primary/40 hover:shadow-sm"
        }, {
          default: withCtx((_, _push2, _parent2, _scopeId) => {
            if (_push2) {
              _push2(`<div class="flex items-start justify-between"${_scopeId}><span class="grid size-10 place-items-center rounded-md bg-primary-soft text-primary"${_scopeId}>`);
              _push2(ssrRenderComponent(unref(IconUsers), { size: 19 }, null, _parent2, _scopeId));
              _push2(`</span><span class="rounded-full bg-warning-soft px-2 py-1 text-micro font-semibold text-warning"${_scopeId}>${ssrInterpolate(batch.status)}</span></div><h2 class="mt-5 text-sm font-semibold"${_scopeId}>${ssrInterpolate(batch.name)}</h2><p class="mt-1 text-xs text-text-muted"${_scopeId}>${ssrInterpolate(batch.year)} · ${ssrInterpolate(batch.semester)}</p><div class="mt-5 h-1.5 overflow-hidden rounded-full bg-primary-soft"${_scopeId}><div class="h-full bg-primary" style="${ssrRenderStyle({ width: `${batch.progress}%` })}"${_scopeId}></div></div><div class="mt-3 flex justify-between text-xs text-text-muted"${_scopeId}><span${_scopeId}>${ssrInterpolate(batch.count.toLocaleString())} grantees</span><span${_scopeId}>${ssrInterpolate(batch.progress)}% activated</span></div>`);
            } else {
              return [
                createVNode("div", { class: "flex items-start justify-between" }, [
                  createVNode("span", { class: "grid size-10 place-items-center rounded-md bg-primary-soft text-primary" }, [
                    createVNode(unref(IconUsers), { size: 19 })
                  ]),
                  createVNode("span", { class: "rounded-full bg-warning-soft px-2 py-1 text-micro font-semibold text-warning" }, toDisplayString(batch.status), 1)
                ]),
                createVNode("h2", { class: "mt-5 text-sm font-semibold" }, toDisplayString(batch.name), 1),
                createVNode("p", { class: "mt-1 text-xs text-text-muted" }, toDisplayString(batch.year) + " · " + toDisplayString(batch.semester), 1),
                createVNode("div", { class: "mt-5 h-1.5 overflow-hidden rounded-full bg-primary-soft" }, [
                  createVNode("div", {
                    class: "h-full bg-primary",
                    style: { width: `${batch.progress}%` }
                  }, null, 4)
                ]),
                createVNode("div", { class: "mt-3 flex justify-between text-xs text-text-muted" }, [
                  createVNode("span", null, toDisplayString(batch.count.toLocaleString()) + " grantees", 1),
                  createVNode("span", null, toDisplayString(batch.progress) + "% activated", 1)
                ])
              ];
            }
          }),
          _: 2
        }, _parent));
      });
      _push(`<!--]--></section>`);
      _push(ssrRenderComponent(_sfc_main$2, {
        modelValue: batchDialog.value,
        "onUpdate:modelValue": ($event) => batchDialog.value = $event,
        title: "Create TES batch",
        description: "Set the academic period and initial batch configuration.",
        size: "lg"
      }, {
        footer: withCtx(({ close }, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<button class="rounded-md border px-4 py-2 text-xs"${_scopeId}>Cancel</button><button class="rounded-md bg-primary px-4 py-2 text-xs text-white"${_scopeId}> Create batch </button>`);
          } else {
            return [
              createVNode("button", {
                class: "rounded-md border px-4 py-2 text-xs",
                onClick: close
              }, "Cancel", 8, ["onClick"]),
              createVNode("button", {
                class: "rounded-md bg-primary px-4 py-2 text-xs text-white",
                onClick: close
              }, " Create batch ", 8, ["onClick"])
            ];
          }
        }),
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="grid gap-4 sm:grid-cols-2"${_scopeId}><label class="text-xs font-medium sm:col-span-2"${_scopeId}> Batch name <input class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm" placeholder="TES 2026 — Batch 01"${_scopeId}></label><label class="text-xs font-medium"${_scopeId}> Academic year <select class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm"${_scopeId}><option${_scopeId}>AY 2026–2027</option><option${_scopeId}>AY 2025–2026</option></select></label><label class="text-xs font-medium"${_scopeId}> Semester <select class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm"${_scopeId}><option${_scopeId}>1st Semester</option><option${_scopeId}>2nd Semester</option><option${_scopeId}>Summer</option></select></label><label class="text-xs font-medium"${_scopeId}> Grant amount <input class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm" placeholder="₱20,000"${_scopeId}></label><label class="text-xs font-medium"${_scopeId}> Initial status <select class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm"${_scopeId}><option${_scopeId}>Inactive accounts</option><option${_scopeId}>Activation notified</option></select></label></div>`);
          } else {
            return [
              createVNode("div", { class: "grid gap-4 sm:grid-cols-2" }, [
                createVNode("label", { class: "text-xs font-medium sm:col-span-2" }, [
                  createTextVNode(" Batch name "),
                  createVNode("input", {
                    class: "mt-1.5 h-10 w-full rounded-md border px-3 text-sm",
                    placeholder: "TES 2026 — Batch 01"
                  })
                ]),
                createVNode("label", { class: "text-xs font-medium" }, [
                  createTextVNode(" Academic year "),
                  createVNode("select", { class: "mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm" }, [
                    createVNode("option", null, "AY 2026–2027"),
                    createVNode("option", null, "AY 2025–2026")
                  ])
                ]),
                createVNode("label", { class: "text-xs font-medium" }, [
                  createTextVNode(" Semester "),
                  createVNode("select", { class: "mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm" }, [
                    createVNode("option", null, "1st Semester"),
                    createVNode("option", null, "2nd Semester"),
                    createVNode("option", null, "Summer")
                  ])
                ]),
                createVNode("label", { class: "text-xs font-medium" }, [
                  createTextVNode(" Grant amount "),
                  createVNode("input", {
                    class: "mt-1.5 h-10 w-full rounded-md border px-3 text-sm",
                    placeholder: "₱20,000"
                  })
                ]),
                createVNode("label", { class: "text-xs font-medium" }, [
                  createTextVNode(" Initial status "),
                  createVNode("select", { class: "mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm" }, [
                    createVNode("option", null, "Inactive accounts"),
                    createVNode("option", null, "Activation notified")
                  ])
                ])
              ])
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/modules/batches/Index.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
