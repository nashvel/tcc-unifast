import { defineComponent, ref, resolveComponent, withCtx, unref, createVNode, createTextVNode, openBlock, createBlock, Fragment, renderList, toDisplayString, createCommentVNode, withDirectives, vModelText, useSSRContext } from "vue";
import { ssrRenderAttrs, ssrRenderComponent, ssrRenderList, ssrInterpolate, ssrIncludeBooleanAttr, ssrRenderAttr } from "vue/server-renderer";
import { IconArrowLeft, IconFileSpreadsheet, IconBrandFacebook, IconMail, IconUserPlus, IconNote, IconId, IconDownload } from "@tabler/icons-vue";
import { _ as _sfc_main$1 } from "./PageHeader-DV7ufis9.js";
import { _ as _sfc_main$2 } from "./DataTable-BXM8pciO.js";
import { _ as _sfc_main$3 } from "./AppDialog-CSs1wZpw.js";
import { c as csrfToken } from "../ssr.js";
import "vue-router";
const _sfc_main = /* @__PURE__ */ defineComponent({
  __name: "Detail",
  __ssrInlineRender: true,
  setup(__props) {
    const memberDialog = ref(false);
    const notifyDialog = ref(false);
    const spreadsheetDialog = ref(false);
    const facebookDialog = ref(false);
    const idSampleDialog = ref(false);
    const postTemplate = ref(
      "TCC UniFAST TES Batch 1 grantees are now available for account activation. Please check your registered email for your temporary password and activate your student portal account. After login, upload your student ID and complete face verification."
    );
    const internalNote = ref(
      "Coordinate with registrar before public posting. Confirm bounced emails through SMTP logs."
    );
    const emailSubject = ref("Activate your TCC UniFAST TES student portal account");
    const emailMessage = ref(
      "Your student portal account has been created from the TES masterlist. Use the temporary password below to activate your account, then change your password and complete identity verification."
    );
    const notifying = ref(false);
    const notifyResult = ref("");
    const selectedStudent = ref([]);
    const idSampleFile = ref(null);
    const idSampleBusy = ref(false);
    const idSampleResult = ref("");
    const members = [
      ["STU-0001", "Maria Angela Santos", "student001@tcc.edu.ph", "2024-00182", "Inactive"],
      ["STU-0002", "John Paul Ramirez", "student002@tcc.edu.ph", "2024-00194", "Inactive"],
      ["STU-0003", "Nicole Anne Flores", "student003@tcc.edu.ph", "2024-00207", "Inactive"],
      ["STU-0004", "Christian Dela Cruz", "student004@tcc.edu.ph", "2024-00231", "Inactive"]
    ];
    async function notifyBatch(close) {
      notifying.value = true;
      notifyResult.value = "";
      try {
        const response = await fetch("/api/batches/1/activation-notifications", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": csrfToken(),
            Accept: "application/json"
          },
          body: JSON.stringify({ subject: emailSubject.value, message: emailMessage.value })
        });
        const payload = await response.json();
        if (!response.ok && response.status !== 207) {
          throw new Error(payload.message || "Unable to send activation emails.");
        }
        notifyResult.value = `Queued through ${payload.mailer}: ${payload.sent} sent, ${payload.failed?.length ?? 0} failed.`;
        if (!payload.failed?.length) window.setTimeout(close, 900);
      } catch (error) {
        notifyResult.value = error instanceof Error ? error.message : "Unable to send activation emails.";
      } finally {
        notifying.value = false;
      }
    }
    function openIdSample(member) {
      selectedStudent.value = member;
      idSampleFile.value = null;
      idSampleResult.value = "";
      idSampleDialog.value = true;
    }
    function chooseIdSample(event) {
      idSampleFile.value = event.target.files?.[0] ?? null;
      idSampleResult.value = "";
    }
    async function uploadIdSample(close) {
      if (!idSampleFile.value || !selectedStudent.value.length) {
        idSampleResult.value = "Choose an ID sample file first.";
        return;
      }
      idSampleBusy.value = true;
      idSampleResult.value = "";
      const body = new FormData();
      body.append("id_sample", idSampleFile.value);
      try {
        const response = await fetch(`/api/students/${selectedStudent.value[3]}/id-sample`, {
          method: "POST",
          headers: { "X-CSRF-TOKEN": csrfToken(), Accept: "application/json" },
          body
        });
        const payload = await response.json();
        if (!response.ok) throw new Error(payload.message || "Unable to upload ID sample.");
        idSampleResult.value = payload.message || "Reference ID sample saved.";
        window.setTimeout(close, 800);
      } catch (error) {
        idSampleResult.value = error instanceof Error ? error.message : "Unable to upload ID sample.";
      } finally {
        idSampleBusy.value = false;
      }
    }
    return (_ctx, _push, _parent, _attrs) => {
      const _component_RouterLink = resolveComponent("RouterLink");
      _push(`<div${ssrRenderAttrs(_attrs)}>`);
      _push(ssrRenderComponent(_component_RouterLink, {
        to: "/app/batches",
        class: "mb-3 inline-flex items-center gap-1 text-xs text-text-muted hover:text-primary"
      }, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(ssrRenderComponent(unref(IconArrowLeft), { size: 14 }, null, _parent2, _scopeId));
            _push2(`Back to batches `);
          } else {
            return [
              createVNode(unref(IconArrowLeft), { size: 14 }),
              createTextVNode("Back to batches ")
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(ssrRenderComponent(_sfc_main$1, {
        title: "TES 2025 — Batch 1",
        description: "AY 2025–2026 · 1st Semester · accounts created inactive"
      }, {
        actions: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<button class="inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-xs"${_scopeId}>`);
            _push2(ssrRenderComponent(unref(IconFileSpreadsheet), { size: 14 }, null, _parent2, _scopeId));
            _push2(`Generate spreadsheet </button><button class="inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-xs"${_scopeId}>`);
            _push2(ssrRenderComponent(unref(IconBrandFacebook), { size: 14 }, null, _parent2, _scopeId));
            _push2(`Facebook post </button><button class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-xs text-white"${_scopeId}>`);
            _push2(ssrRenderComponent(unref(IconMail), { size: 14 }, null, _parent2, _scopeId));
            _push2(`Notify activation </button><button class="inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-xs"${_scopeId}>`);
            _push2(ssrRenderComponent(unref(IconUserPlus), { size: 14 }, null, _parent2, _scopeId));
            _push2(`Add grantees </button>`);
          } else {
            return [
              createVNode("button", {
                class: "inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-xs",
                onClick: ($event) => spreadsheetDialog.value = true
              }, [
                createVNode(unref(IconFileSpreadsheet), { size: 14 }),
                createTextVNode("Generate spreadsheet ")
              ], 8, ["onClick"]),
              createVNode("button", {
                class: "inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-xs",
                onClick: ($event) => facebookDialog.value = true
              }, [
                createVNode(unref(IconBrandFacebook), { size: 14 }),
                createTextVNode("Facebook post ")
              ], 8, ["onClick"]),
              createVNode("button", {
                class: "inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-xs text-white",
                onClick: ($event) => notifyDialog.value = true
              }, [
                createVNode(unref(IconMail), { size: 14 }),
                createTextVNode("Notify activation ")
              ], 8, ["onClick"]),
              createVNode("button", {
                class: "inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-xs",
                onClick: ($event) => memberDialog.value = true
              }, [
                createVNode(unref(IconUserPlus), { size: 14 }),
                createTextVNode("Add grantees ")
              ], 8, ["onClick"])
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(`<section class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4"><!--[-->`);
      ssrRenderList([
        ["Grantees", "1,248"],
        ["Inactive accounts", "1,248"],
        ["Activation emails", "Ready"],
        ["Generated passwords", "Secure"]
      ], (item) => {
        _push(`<article class="rounded-lg border bg-surface p-4"><p class="text-xs text-text-muted">${ssrInterpolate(item[0])}</p><p class="mt-1 text-xl font-semibold">${ssrInterpolate(item[1])}</p></article>`);
      });
      _push(`<!--]--></section><section class="mb-4 grid gap-3 lg:grid-cols-3"><article class="rounded-lg border bg-surface p-4 lg:col-span-2"><h2 class="flex items-center gap-2 text-sm font-semibold">`);
      _push(ssrRenderComponent(unref(IconMail), { size: 16 }, null, _parent));
      _push(` Batch activation flow </h2><ol class="mt-3 grid gap-2 text-xs text-text-muted sm:grid-cols-3"><li class="rounded-md bg-surface-muted p-3"> 1. Accounts are created inactive from the uploaded masterlist. </li><li class="rounded-md bg-surface-muted p-3"> 2. SMTP sends activation link plus a random temporary password. </li><li class="rounded-md bg-surface-muted p-3"> 3. Students activate, verify identity, then upload Course History and COR. </li></ol></article><article class="rounded-lg border bg-surface p-4"><h2 class="flex items-center gap-2 text-sm font-semibold">`);
      _push(ssrRenderComponent(unref(IconNote), { size: 16 }, null, _parent));
      _push(` Staff notes </h2><textarea class="mt-3 min-h-24 w-full rounded-md border p-3 text-xs">${ssrInterpolate(internalNote.value)}</textarea></article></section>`);
      _push(ssrRenderComponent(_sfc_main$2, { headings: ["Student ID", "Student name", "Email", "Student number", "Account", "ID sample", ""] }, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<!--[-->`);
            ssrRenderList(members, (m) => {
              _push2(`<tr${_scopeId}><td class="px-3 py-3 font-mono"${_scopeId}>${ssrInterpolate(m[0])}</td><td class="px-3 py-3 font-medium"${_scopeId}>${ssrInterpolate(m[1])}</td><td class="px-3 py-3 text-text-muted"${_scopeId}>${ssrInterpolate(m[2])}</td><td class="px-3 py-3 font-mono text-text-muted"${_scopeId}>${ssrInterpolate(m[3])}</td><td class="px-3 py-3"${_scopeId}><span class="rounded-full bg-warning-soft px-2 py-0.5 text-micro text-warning"${_scopeId}>${ssrInterpolate(m[4])}</span></td><td class="px-3 py-3"${_scopeId}><button class="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-micro text-primary"${_scopeId}>`);
              _push2(ssrRenderComponent(unref(IconId), { size: 12 }, null, _parent2, _scopeId));
              _push2(` Add sample </button></td><td class="px-3 py-3 text-right"${_scopeId}>`);
              _push2(ssrRenderComponent(_component_RouterLink, {
                to: "/app/grantees/1",
                class: "text-primary"
              }, {
                default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                  if (_push3) {
                    _push3(`View`);
                  } else {
                    return [
                      createTextVNode("View")
                    ];
                  }
                }),
                _: 2
              }, _parent2, _scopeId));
              _push2(`</td></tr>`);
            });
            _push2(`<!--]-->`);
          } else {
            return [
              (openBlock(), createBlock(Fragment, null, renderList(members, (m) => {
                return createVNode("tr", {
                  key: m[0]
                }, [
                  createVNode("td", { class: "px-3 py-3 font-mono" }, toDisplayString(m[0]), 1),
                  createVNode("td", { class: "px-3 py-3 font-medium" }, toDisplayString(m[1]), 1),
                  createVNode("td", { class: "px-3 py-3 text-text-muted" }, toDisplayString(m[2]), 1),
                  createVNode("td", { class: "px-3 py-3 font-mono text-text-muted" }, toDisplayString(m[3]), 1),
                  createVNode("td", { class: "px-3 py-3" }, [
                    createVNode("span", { class: "rounded-full bg-warning-soft px-2 py-0.5 text-micro text-warning" }, toDisplayString(m[4]), 1)
                  ]),
                  createVNode("td", { class: "px-3 py-3" }, [
                    createVNode("button", {
                      class: "inline-flex items-center gap-1 rounded-md border px-2 py-1 text-micro text-primary",
                      onClick: ($event) => openIdSample(m)
                    }, [
                      createVNode(unref(IconId), { size: 12 }),
                      createTextVNode(" Add sample ")
                    ], 8, ["onClick"])
                  ]),
                  createVNode("td", { class: "px-3 py-3 text-right" }, [
                    createVNode(_component_RouterLink, {
                      to: "/app/grantees/1",
                      class: "text-primary"
                    }, {
                      default: withCtx(() => [
                        createTextVNode("View")
                      ]),
                      _: 1
                    })
                  ])
                ]);
              }), 64))
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(ssrRenderComponent(_sfc_main$3, {
        modelValue: memberDialog.value,
        "onUpdate:modelValue": ($event) => memberDialog.value = $event,
        title: "Add grantees to batch",
        description: "Select eligible grantees that are not already assigned to this batch.",
        size: "lg"
      }, {
        footer: withCtx(({ close }, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<button class="rounded-md border px-4 py-2 text-xs"${_scopeId}>Cancel</button><button class="rounded-md bg-primary px-4 py-2 text-xs text-white"${_scopeId}> Add selected </button>`);
          } else {
            return [
              createVNode("button", {
                class: "rounded-md border px-4 py-2 text-xs",
                onClick: close
              }, "Cancel", 8, ["onClick"]),
              createVNode("button", {
                class: "rounded-md bg-primary px-4 py-2 text-xs text-white",
                onClick: close
              }, " Add selected ", 8, ["onClick"])
            ];
          }
        }),
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<input class="h-10 w-full rounded-md border px-3 text-sm" placeholder="Search student number or name"${_scopeId}><div class="mt-3 divide-y rounded-md border"${_scopeId}><!--[-->`);
            ssrRenderList([
              "Angelica Reyes · 2024-00252",
              "Mark Anthony Garcia · 2024-00268",
              "Princess Mae Lim · 2024-00281"
            ], (name) => {
              _push2(`<label class="flex items-center gap-3 p-3 text-sm"${_scopeId}><input type="checkbox"${_scopeId}>${ssrInterpolate(name)}</label>`);
            });
            _push2(`<!--]--></div>`);
          } else {
            return [
              createVNode("input", {
                class: "h-10 w-full rounded-md border px-3 text-sm",
                placeholder: "Search student number or name"
              }),
              createVNode("div", { class: "mt-3 divide-y rounded-md border" }, [
                (openBlock(), createBlock(Fragment, null, renderList([
                  "Angelica Reyes · 2024-00252",
                  "Mark Anthony Garcia · 2024-00268",
                  "Princess Mae Lim · 2024-00281"
                ], (name) => {
                  return createVNode("label", {
                    key: name,
                    class: "flex items-center gap-3 p-3 text-sm"
                  }, [
                    createVNode("input", { type: "checkbox" }),
                    createTextVNode(toDisplayString(name), 1)
                  ]);
                }), 64))
              ])
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(ssrRenderComponent(_sfc_main$3, {
        modelValue: idSampleDialog.value,
        "onUpdate:modelValue": ($event) => idSampleDialog.value = $event,
        title: "Attach admin ID sample",
        description: `Upload the official reference ID/photo for ${selectedStudent.value[1] ?? "this student"}. Face verification can use this as the basis instead of asking the student for another reference sample.`,
        size: "md"
      }, {
        footer: withCtx(({ close }, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<button class="rounded-md border px-4 py-2 text-xs"${_scopeId}>Cancel</button><button class="rounded-md bg-primary px-4 py-2 text-xs text-white disabled:opacity-50"${ssrIncludeBooleanAttr(idSampleBusy.value) ? " disabled" : ""}${_scopeId}>${ssrInterpolate(idSampleBusy.value ? "Uploading..." : "Save ID sample")}</button>`);
          } else {
            return [
              createVNode("button", {
                class: "rounded-md border px-4 py-2 text-xs",
                onClick: close
              }, "Cancel", 8, ["onClick"]),
              createVNode("button", {
                class: "rounded-md bg-primary px-4 py-2 text-xs text-white disabled:opacity-50",
                disabled: idSampleBusy.value,
                onClick: ($event) => uploadIdSample(close)
              }, toDisplayString(idSampleBusy.value ? "Uploading..." : "Save ID sample"), 9, ["disabled", "onClick"])
            ];
          }
        }),
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="space-y-3"${_scopeId}><label class="block rounded-lg border-2 border-dashed border-border-strong p-4 text-center hover:border-primary"${_scopeId}><input type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" class="hidden"${_scopeId}>`);
            _push2(ssrRenderComponent(unref(IconId), {
              size: 26,
              class: "mx-auto text-primary"
            }, null, _parent2, _scopeId));
            _push2(`<b class="mt-2 block text-sm"${_scopeId}>Upload official ID sample</b><span class="mt-1 block text-xs text-text-muted"${_scopeId}>${ssrInterpolate(idSampleFile.value ? idSampleFile.value.name : "PDF or image from registrar/masterlist record")}</span></label><p class="rounded-md border bg-surface-muted p-2.5 text-xs text-text-muted"${_scopeId}> This file becomes the trusted reference image for face matching. It is stored privately in the app storage path and is not included in public spreadsheets or Facebook posts. </p>`);
            if (idSampleResult.value) {
              _push2(`<p class="text-xs text-text-muted"${_scopeId}>${ssrInterpolate(idSampleResult.value)}</p>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`</div>`);
          } else {
            return [
              createVNode("div", { class: "space-y-3" }, [
                createVNode("label", { class: "block rounded-lg border-2 border-dashed border-border-strong p-4 text-center hover:border-primary" }, [
                  createVNode("input", {
                    type: "file",
                    accept: ".pdf,.jpg,.jpeg,.png,.webp",
                    class: "hidden",
                    onChange: chooseIdSample
                  }, null, 32),
                  createVNode(unref(IconId), {
                    size: 26,
                    class: "mx-auto text-primary"
                  }),
                  createVNode("b", { class: "mt-2 block text-sm" }, "Upload official ID sample"),
                  createVNode("span", { class: "mt-1 block text-xs text-text-muted" }, toDisplayString(idSampleFile.value ? idSampleFile.value.name : "PDF or image from registrar/masterlist record"), 1)
                ]),
                createVNode("p", { class: "rounded-md border bg-surface-muted p-2.5 text-xs text-text-muted" }, " This file becomes the trusted reference image for face matching. It is stored privately in the app storage path and is not included in public spreadsheets or Facebook posts. "),
                idSampleResult.value ? (openBlock(), createBlock("p", {
                  key: 0,
                  class: "text-xs text-text-muted"
                }, toDisplayString(idSampleResult.value), 1)) : createCommentVNode("", true)
              ])
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(ssrRenderComponent(_sfc_main$3, {
        modelValue: notifyDialog.value,
        "onUpdate:modelValue": ($event) => notifyDialog.value = $event,
        title: "Notify Batch 1 to activate",
        description: "SMTP will send activation instructions with a safely generated temporary password for each inactive student.",
        size: "lg"
      }, {
        footer: withCtx(({ close }, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<button class="rounded-md border px-4 py-2 text-xs"${_scopeId}>Cancel</button><button class="rounded-md bg-primary px-4 py-2 text-xs text-white disabled:opacity-50"${ssrIncludeBooleanAttr(notifying.value) ? " disabled" : ""}${_scopeId}>${ssrInterpolate(notifying.value ? "Sending..." : "Queue SMTP notifications")}</button>`);
          } else {
            return [
              createVNode("button", {
                class: "rounded-md border px-4 py-2 text-xs",
                onClick: close
              }, "Cancel", 8, ["onClick"]),
              createVNode("button", {
                class: "rounded-md bg-primary px-4 py-2 text-xs text-white disabled:opacity-50",
                disabled: notifying.value,
                onClick: ($event) => notifyBatch(close)
              }, toDisplayString(notifying.value ? "Sending..." : "Queue SMTP notifications"), 9, ["disabled", "onClick"])
            ];
          }
        }),
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="space-y-3 text-sm"${_scopeId}><div class="rounded-md border bg-surface-muted p-3 text-xs text-text-muted"${_scopeId}> Example generated password format: <b class="text-text"${_scopeId}>TCC-8F4K-29QZ</b>. The real value is generated per student and never stored in the frontend. </div><label class="block text-xs font-medium"${_scopeId}> Email subject <input${ssrRenderAttr("value", emailSubject.value)} class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm"${_scopeId}></label><label class="block text-xs font-medium"${_scopeId}> Email message <textarea class="mt-1.5 min-h-28 w-full rounded-md border p-3 text-sm"${_scopeId}>${ssrInterpolate(emailMessage.value)}</textarea></label>`);
            if (notifyResult.value) {
              _push2(`<p class="rounded-md border bg-surface-muted p-2.5 text-xs text-text-muted"${_scopeId}>${ssrInterpolate(notifyResult.value)}</p>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`</div>`);
          } else {
            return [
              createVNode("div", { class: "space-y-3 text-sm" }, [
                createVNode("div", { class: "rounded-md border bg-surface-muted p-3 text-xs text-text-muted" }, [
                  createTextVNode(" Example generated password format: "),
                  createVNode("b", { class: "text-text" }, "TCC-8F4K-29QZ"),
                  createTextVNode(". The real value is generated per student and never stored in the frontend. ")
                ]),
                createVNode("label", { class: "block text-xs font-medium" }, [
                  createTextVNode(" Email subject "),
                  withDirectives(createVNode("input", {
                    "onUpdate:modelValue": ($event) => emailSubject.value = $event,
                    class: "mt-1.5 h-10 w-full rounded-md border px-3 text-sm"
                  }, null, 8, ["onUpdate:modelValue"]), [
                    [vModelText, emailSubject.value]
                  ])
                ]),
                createVNode("label", { class: "block text-xs font-medium" }, [
                  createTextVNode(" Email message "),
                  withDirectives(createVNode("textarea", {
                    "onUpdate:modelValue": ($event) => emailMessage.value = $event,
                    class: "mt-1.5 min-h-28 w-full rounded-md border p-3 text-sm"
                  }, null, 8, ["onUpdate:modelValue"]), [
                    [vModelText, emailMessage.value]
                  ])
                ]),
                notifyResult.value ? (openBlock(), createBlock("p", {
                  key: 0,
                  class: "rounded-md border bg-surface-muted p-2.5 text-xs text-text-muted"
                }, toDisplayString(notifyResult.value), 1)) : createCommentVNode("", true)
              ])
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(ssrRenderComponent(_sfc_main$3, {
        modelValue: spreadsheetDialog.value,
        "onUpdate:modelValue": ($event) => spreadsheetDialog.value = $event,
        title: "Generate Batch 1 spreadsheet",
        description: "Create a Google Sheets-ready list for Batch 1 publication and tracking.",
        size: "md"
      }, {
        footer: withCtx(({ close }, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<button class="rounded-md border px-4 py-2 text-xs"${_scopeId}>Cancel</button><button class="rounded-md bg-primary px-4 py-2 text-xs text-white"${_scopeId}> Generate spreadsheet </button>`);
          } else {
            return [
              createVNode("button", {
                class: "rounded-md border px-4 py-2 text-xs",
                onClick: close
              }, "Cancel", 8, ["onClick"]),
              createVNode("button", {
                class: "rounded-md bg-primary px-4 py-2 text-xs text-white",
                onClick: close
              }, " Generate spreadsheet ", 8, ["onClick"])
            ];
          }
        }),
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="space-y-2 text-xs text-text-muted"${_scopeId}><p${_scopeId}>`);
            _push2(ssrRenderComponent(unref(IconDownload), {
              size: 14,
              class: "mr-1 inline text-primary"
            }, null, _parent2, _scopeId));
            _push2(` Includes student ID, name, student number, and activation status. </p><p${_scopeId}>Confidential fields such as temporary passwords are excluded from public exports.</p></div>`);
          } else {
            return [
              createVNode("div", { class: "space-y-2 text-xs text-text-muted" }, [
                createVNode("p", null, [
                  createVNode(unref(IconDownload), {
                    size: 14,
                    class: "mr-1 inline text-primary"
                  }),
                  createTextVNode(" Includes student ID, name, student number, and activation status. ")
                ]),
                createVNode("p", null, "Confidential fields such as temporary passwords are excluded from public exports.")
              ])
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(ssrRenderComponent(_sfc_main$3, {
        modelValue: facebookDialog.value,
        "onUpdate:modelValue": ($event) => facebookDialog.value = $event,
        title: "Facebook upload template",
        description: "Prepare a predefined public post for Batch 1. Staff can edit and save new templates.",
        size: "lg"
      }, {
        footer: withCtx(({ close }, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<button class="rounded-md border px-4 py-2 text-xs"${_scopeId}> Save as new template </button><button class="rounded-md bg-primary px-4 py-2 text-xs text-white"${_scopeId}> Prepare Facebook upload </button>`);
          } else {
            return [
              createVNode("button", {
                class: "rounded-md border px-4 py-2 text-xs",
                onClick: close
              }, " Save as new template ", 8, ["onClick"]),
              createVNode("button", {
                class: "rounded-md bg-primary px-4 py-2 text-xs text-white",
                onClick: close
              }, " Prepare Facebook upload ", 8, ["onClick"])
            ];
          }
        }),
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="space-y-3"${_scopeId}><label class="block text-xs font-medium"${_scopeId}> Post template <textarea class="mt-1.5 min-h-36 w-full rounded-md border p-3 text-sm"${_scopeId}>${ssrInterpolate(postTemplate.value)}</textarea></label><label class="block text-xs font-medium"${_scopeId}> Template notes <input class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm" placeholder="Example: Use this when activation emails are already sent."${_scopeId}></label></div>`);
          } else {
            return [
              createVNode("div", { class: "space-y-3" }, [
                createVNode("label", { class: "block text-xs font-medium" }, [
                  createTextVNode(" Post template "),
                  withDirectives(createVNode("textarea", {
                    "onUpdate:modelValue": ($event) => postTemplate.value = $event,
                    class: "mt-1.5 min-h-36 w-full rounded-md border p-3 text-sm"
                  }, null, 8, ["onUpdate:modelValue"]), [
                    [vModelText, postTemplate.value]
                  ])
                ]),
                createVNode("label", { class: "block text-xs font-medium" }, [
                  createTextVNode(" Template notes "),
                  createVNode("input", {
                    class: "mt-1.5 h-10 w-full rounded-md border px-3 text-sm",
                    placeholder: "Example: Use this when activation emails are already sent."
                  })
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/modules/batches/Detail.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
