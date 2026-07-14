import { defineComponent, ref, computed, resolveComponent, withCtx, unref, createVNode, createTextVNode, openBlock, createBlock, Fragment, renderList, toDisplayString, createCommentVNode, useSSRContext } from "vue";
import { ssrRenderAttrs, ssrRenderComponent, ssrRenderList, ssrInterpolate, ssrRenderClass, ssrRenderAttr } from "vue/server-renderer";
import { IconUpload, IconArrowRight, IconFileSpreadsheet, IconAlertTriangle, IconSearch, IconCheck, IconUsers, IconMail } from "@tabler/icons-vue";
import { _ as _sfc_main$2 } from "./DataTable-BXM8pciO.js";
import { _ as _sfc_main$1 } from "./PageHeader-DV7ufis9.js";
import { _ as _sfc_main$3 } from "./AppDialog-CSs1wZpw.js";
const masterlist = [
  [
    "STU-0001",
    "Maria Clara Dela Cruz",
    "maria.delacruz@student.tcc.edu.ph",
    "2024-00123",
    "active"
  ],
  [
    "STU-0002",
    "Juan Miguel Santos",
    "juan.santos@student.tcc.edu.ph",
    "2024-00124",
    "pending_activation"
  ],
  [
    "STU-0003",
    "Andrea Nicole Reyes",
    "andrea.reyes@student.tcc.edu.ph",
    "2023-08812",
    "active"
  ],
  [
    "STU-0004",
    "Joshua Tan",
    "joshua.tan@student.tcc.edu.ph",
    "2024-00567",
    "inactive"
  ],
  [
    "STU-0005",
    "Patricia Mae Lim",
    "patricia.lim@student.tcc.edu.ph",
    "2024-00568",
    "pending_activation"
  ],
  ["STU-0006", "Bea Mendoza", "bea.mendoza@student.tcc.edu.ph", "2024-00569", "inactive"],
  [
    "STU-0007",
    "Sophia Aquino",
    "sophia.aquino@student.tcc.edu.ph",
    "2024-00571",
    "duplicate"
  ],
  ["", "Liam Perez", "liam.perez@student.tcc.edu.ph", "2024-00572", "invalid"]
];
const _sfc_main = /* @__PURE__ */ defineComponent({
  __name: "Index",
  __ssrInlineRender: true,
  setup(__props) {
    const previewed = ref(false);
    const query = ref("");
    const confirmDialog = ref(false);
    const rows = computed(
      () => masterlist.filter((row) => {
        return `${row[0]} ${row[1]} ${row[2]} ${row[3]}`.toLowerCase().includes(query.value.toLowerCase());
      })
    );
    const counts = computed(() => ({
      total: masterlist.length,
      active: masterlist.filter((row) => row[4] === "active").length,
      pending: masterlist.filter((row) => row[4] === "pending_activation").length,
      inactive: masterlist.filter((row) => row[4] === "inactive").length,
      duplicate: masterlist.filter((row) => row[4] === "duplicate").length,
      invalid: masterlist.filter((row) => row[4] === "invalid").length
    }));
    const importableCount = computed(
      () => counts.value.total - counts.value.duplicate - counts.value.invalid
    );
    return (_ctx, _push, _parent, _attrs) => {
      const _component_RouterLink = resolveComponent("RouterLink");
      _push(`<div${ssrRenderAttrs(_attrs)}>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        title: "Masterlist Import",
        description: "Head uploads the TES masterlist. For the mockup, it only needs student ID, student name, email, and student number."
      }, {
        actions: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<button class="inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-xs"${_scopeId}>`);
            _push2(ssrRenderComponent(unref(IconUpload), { size: 14 }, null, _parent2, _scopeId));
            _push2(` Download template </button><button class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-xs font-medium text-white"${_scopeId}>`);
            _push2(ssrRenderComponent(unref(IconArrowRight), { size: 14 }, null, _parent2, _scopeId));
            _push2(` Process import </button>`);
          } else {
            return [
              createVNode("button", { class: "inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-xs" }, [
                createVNode(unref(IconUpload), { size: 14 }),
                createTextVNode(" Download template ")
              ]),
              createVNode("button", {
                class: "inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-xs font-medium text-white",
                onClick: ($event) => previewed.value = true
              }, [
                createVNode(unref(IconArrowRight), { size: 14 }),
                createTextVNode(" Process import ")
              ], 8, ["onClick"])
            ];
          }
        }),
        _: 1
      }, _parent));
      if (!previewed.value) {
        _push(`<div class="grid gap-4 lg:grid-cols-3"><button class="flex min-h-64 flex-col items-center justify-center rounded-lg border-2 border-dashed bg-surface p-8 text-center transition hover:border-primary/50 hover:bg-primary/5 lg:col-span-2"><span class="mb-3 grid size-12 place-items-center rounded-full bg-primary-soft text-primary">`);
        _push(ssrRenderComponent(unref(IconFileSpreadsheet), { size: 24 }, null, _parent));
        _push(`</span><span class="text-sm font-semibold">Drop your masterlist here or browse</span><span class="mt-1 text-xs text-text-muted">CSV or XLSX up to 20MB</span></button><aside class="rounded-lg border bg-surface p-4"><h2 class="text-sm font-semibold">Import rules</h2><ul class="mt-3 list-inside list-disc space-y-2 text-xs leading-5 text-text-muted"><li>The Office Head uploads the student masterlist.</li><li>Required columns: student ID, student name, email, and student number.</li><li>Student accounts are auto-generated from those records.</li><li>All new accounts are inactive by default.</li><li>Students activate by verifying their identity and contact details.</li><li>Duplicate student numbers are flagged and skipped.</li><li>Missing or malformed required data is marked invalid.</li></ul></aside></div>`);
      } else {
        _push(`<div class="space-y-4"><section class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6"><!--[-->`);
        ssrRenderList([
          ["Total rows", counts.value.total, "text-text"],
          ["Active", counts.value.active, "text-success"],
          ["Pending activation", counts.value.pending, "text-warning"],
          ["Inactive", counts.value.inactive, "text-text-muted"],
          ["Duplicate", counts.value.duplicate, "text-info"],
          ["Invalid", counts.value.invalid, "text-danger"]
        ], (item) => {
          _push(`<article class="rounded-lg border bg-surface p-3"><p class="text-xs text-text-muted">${ssrInterpolate(item[0])}</p><p class="${ssrRenderClass(["mt-0.5 text-xl font-semibold tabular-nums", item[2]])}">${ssrInterpolate(item[1])}</p></article>`);
        });
        _push(`<!--]--></section><div class="flex items-start gap-2 rounded-lg border border-warning/30 bg-warning-soft p-3 text-xs">`);
        _push(ssrRenderComponent(unref(IconAlertTriangle), {
          size: 15,
          class: "mt-0.5 shrink-0 text-warning"
        }, null, _parent));
        _push(`<div><p class="font-medium text-warning">Some rows need attention</p><p class="mt-0.5 text-text-muted">${ssrInterpolate(counts.value.duplicate)} duplicate and ${ssrInterpolate(counts.value.invalid)} invalid row detected. They will not create accounts. </p></div></div><section class="rounded-lg border bg-surface p-3"><div class="relative">`);
        _push(ssrRenderComponent(unref(IconSearch), {
          size: 14,
          class: "absolute left-3 top-1/2 -translate-y-1/2 text-text-soft"
        }, null, _parent));
        _push(`<input${ssrRenderAttr("value", query.value)} class="h-9 w-full rounded-md border pl-9 pr-3 text-xs" placeholder="Search by student ID, name, email, or student number"></div></section>`);
        _push(ssrRenderComponent(_sfc_main$2, { headings: ["Student ID", "Student name", "Student email", "Student number"] }, {
          footer: withCtx((_, _push2, _parent2, _scopeId) => {
            if (_push2) {
              _push2(`<footer class="flex justify-between border-t px-3 py-2.5 text-xs text-text-muted"${_scopeId}><span${_scopeId}>Showing ${ssrInterpolate(rows.value.length)} of ${ssrInterpolate(unref(masterlist).length)}</span><span${_scopeId}>Page 1 of 1</span></footer>`);
            } else {
              return [
                createVNode("footer", { class: "flex justify-between border-t px-3 py-2.5 text-xs text-text-muted" }, [
                  createVNode("span", null, "Showing " + toDisplayString(rows.value.length) + " of " + toDisplayString(unref(masterlist).length), 1),
                  createVNode("span", null, "Page 1 of 1")
                ])
              ];
            }
          }),
          default: withCtx((_, _push2, _parent2, _scopeId) => {
            if (_push2) {
              _push2(`<!--[-->`);
              ssrRenderList(rows.value, (row) => {
                _push2(`<tr${_scopeId}><td class="px-3 py-3 font-mono"${_scopeId}>`);
                if (row[0]) {
                  _push2(`<span${_scopeId}>${ssrInterpolate(row[0])}</span>`);
                } else {
                  _push2(`<span class="italic text-danger"${_scopeId}>missing</span>`);
                }
                _push2(`</td><td class="px-3 py-3 font-medium"${_scopeId}>${ssrInterpolate(row[1])}</td><td class="px-3 py-3 text-text-muted"${_scopeId}>${ssrInterpolate(row[2])}</td><td class="px-3 py-3 text-text-muted"${_scopeId}>${ssrInterpolate(row[3])}</td></tr>`);
              });
              _push2(`<!--]-->`);
              if (!rows.value.length) {
                _push2(`<tr${_scopeId}><td colspan="4" class="p-8 text-center text-text-muted"${_scopeId}>No matching rows found.</td></tr>`);
              } else {
                _push2(`<!---->`);
              }
            } else {
              return [
                (openBlock(true), createBlock(Fragment, null, renderList(rows.value, (row) => {
                  return openBlock(), createBlock("tr", {
                    key: String(row[0] || row[1])
                  }, [
                    createVNode("td", { class: "px-3 py-3 font-mono" }, [
                      row[0] ? (openBlock(), createBlock("span", { key: 0 }, toDisplayString(row[0]), 1)) : (openBlock(), createBlock("span", {
                        key: 1,
                        class: "italic text-danger"
                      }, "missing"))
                    ]),
                    createVNode("td", { class: "px-3 py-3 font-medium" }, toDisplayString(row[1]), 1),
                    createVNode("td", { class: "px-3 py-3 text-text-muted" }, toDisplayString(row[2]), 1),
                    createVNode("td", { class: "px-3 py-3 text-text-muted" }, toDisplayString(row[3]), 1)
                  ]);
                }), 128)),
                !rows.value.length ? (openBlock(), createBlock("tr", { key: 0 }, [
                  createVNode("td", {
                    colspan: "4",
                    class: "p-8 text-center text-text-muted"
                  }, "No matching rows found.")
                ])) : createCommentVNode("", true)
              ];
            }
          }),
          _: 1
        }, _parent));
        _push(`<div class="flex justify-end gap-2"><button class="h-9 rounded-md border px-3 text-xs"> Cancel </button><button class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-xs font-medium text-white">`);
        _push(ssrRenderComponent(unref(IconCheck), { size: 14 }, null, _parent));
        _push(` Confirm import (${ssrInterpolate(importableCount.value)} accounts) </button></div><section class="grid gap-3 rounded-xl border bg-surface p-4 lg:grid-cols-[1fr_auto]"><div><h2 class="text-sm font-semibold">Next flow after upload</h2><p class="mt-1 text-xs text-text-muted"> Import creates inactive student accounts from the masterlist. When Batch 1 is ready, the Head can notify students by SMTP with a safely generated temporary password. </p></div><div class="flex flex-wrap gap-2">`);
        _push(ssrRenderComponent(_component_RouterLink, {
          to: "/app/batches/1",
          class: "inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-xs"
        }, {
          default: withCtx((_, _push2, _parent2, _scopeId) => {
            if (_push2) {
              _push2(ssrRenderComponent(unref(IconUsers), { size: 14 }, null, _parent2, _scopeId));
              _push2(` Open Batch 1 `);
            } else {
              return [
                createVNode(unref(IconUsers), { size: 14 }),
                createTextVNode(" Open Batch 1 ")
              ];
            }
          }),
          _: 1
        }, _parent));
        _push(ssrRenderComponent(_component_RouterLink, {
          to: "/app/batches/1",
          class: "inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-xs font-medium text-white"
        }, {
          default: withCtx((_, _push2, _parent2, _scopeId) => {
            if (_push2) {
              _push2(ssrRenderComponent(unref(IconMail), { size: 14 }, null, _parent2, _scopeId));
              _push2(` Notify Batch 1 activation `);
            } else {
              return [
                createVNode(unref(IconMail), { size: 14 }),
                createTextVNode(" Notify Batch 1 activation ")
              ];
            }
          }),
          _: 1
        }, _parent));
        _push(`</div></section></div>`);
      }
      _push(ssrRenderComponent(_sfc_main$3, {
        modelValue: confirmDialog.value,
        "onUpdate:modelValue": ($event) => confirmDialog.value = $event,
        title: "Confirm masterlist import",
        description: `${importableCount.value} accounts will be created. Duplicate and invalid rows will be skipped.`,
        size: "sm"
      }, {
        footer: withCtx(({ close }, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<button class="rounded-md border px-4 py-2 text-xs"${_scopeId}>Cancel</button><button class="rounded-md bg-primary px-4 py-2 text-xs text-white"${_scopeId}> Confirm import </button>`);
          } else {
            return [
              createVNode("button", {
                class: "rounded-md border px-4 py-2 text-xs",
                onClick: close
              }, "Cancel", 8, ["onClick"]),
              createVNode("button", {
                class: "rounded-md bg-primary px-4 py-2 text-xs text-white",
                onClick: close
              }, " Confirm import ", 8, ["onClick"])
            ];
          }
        }),
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="space-y-2 text-sm text-text-muted"${_scopeId}><p${_scopeId}>New student accounts will start inactive and require identity verification.</p><label class="flex items-center gap-2 text-xs"${_scopeId}><input type="checkbox"${_scopeId}>I reviewed the duplicate and invalid rows.</label></div>`);
          } else {
            return [
              createVNode("div", { class: "space-y-2 text-sm text-text-muted" }, [
                createVNode("p", null, "New student accounts will start inactive and require identity verification."),
                createVNode("label", { class: "flex items-center gap-2 text-xs" }, [
                  createVNode("input", { type: "checkbox" }),
                  createTextVNode("I reviewed the duplicate and invalid rows.")
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/modules/masterlist/Index.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
