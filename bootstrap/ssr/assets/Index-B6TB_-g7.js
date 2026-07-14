import { defineComponent, ref, computed, resolveComponent, withCtx, unref, createVNode, createTextVNode, resolveDynamicComponent, toDisplayString, useSSRContext } from "vue";
import { ssrRenderAttrs, ssrRenderComponent, ssrRenderClass, ssrRenderList, ssrRenderVNode, ssrInterpolate, ssrRenderAttr, ssrIncludeBooleanAttr } from "vue/server-renderer";
import { IconPlus, IconInbox, IconStar, IconClock, IconSend, IconArchive, IconSearch, IconChevronRight, IconArrowLeft, IconArrowRight, IconBug, IconHelp, IconMessage } from "@tabler/icons-vue";
import { t as tickets } from "./mockAdmin-BGBs67j0.js";
import { _ as _sfc_main$2 } from "./DiceBearAvatar-C3Eyt9zS.js";
import { _ as _sfc_main$1 } from "./PageHeader-DV7ufis9.js";
const _sfc_main = /* @__PURE__ */ defineComponent({
  __name: "Index",
  __ssrInlineRender: true,
  setup(__props) {
    const query = ref("");
    const folder = ref("Inbox");
    const selected = ref(0);
    const reply = ref("");
    const sent = ref(false);
    const boardTickets = ref([
      {
        id: "SUP-185",
        title: "Unable to replace uploaded transcript",
        requester: "Maria Santos",
        priority: "High",
        status: "Open",
        sla: "48m"
      },
      {
        id: "SUP-181",
        title: "Student account activation failed",
        requester: "Ana Reyes",
        priority: "Urgent",
        status: "Open",
        sla: "12m"
      },
      {
        id: "SUP-179",
        title: "Question about eligibility result",
        requester: "John Ramirez",
        priority: "Normal",
        status: "In progress",
        sla: "3h"
      },
      {
        id: "SUP-176",
        title: "Incorrect period on exported report",
        requester: "Admin User",
        priority: "High",
        status: "In progress",
        sla: "2h"
      },
      {
        id: "SUP-172",
        title: "Request to update contact number",
        requester: "Nicole Flores",
        priority: "Normal",
        status: "Waiting",
        sla: "1d"
      },
      {
        id: "SUP-168",
        title: "Duplicate masterlist record",
        requester: "TES Staff",
        priority: "High",
        status: "Resolved",
        sla: "Met"
      }
    ]);
    const boardColumns = ["Open", "In progress", "Waiting", "Resolved"];
    const boardRows = (status) => boardTickets.value.filter((ticket) => ticket.status === status);
    const rows = computed(
      () => tickets.filter(
        (ticket) => `${ticket[0]} ${ticket[4]} ${ticket[1]}`.toLowerCase().includes(query.value.toLowerCase())
      )
    );
    const current = computed(() => rows.value[selected.value] ?? rows.value[0]);
    const folders = [
      ["Inbox", IconInbox, 8],
      ["Assigned to me", IconStar, 3],
      ["In progress", IconClock, 4],
      ["Sent", IconSend, 0],
      ["Archived", IconArchive, 0]
    ];
    const categoryIcon = (category) => category === "bug" ? IconBug : category === "question" ? IconHelp : IconMessage;
    const preview = (category) => category === "bug" ? "I encountered an issue while working with a submitted record. Please help me resolve it." : category === "question" ? "I need clarification about the latest result shown in my account." : "Please review this account update request when available.";
    return (_ctx, _push, _parent, _attrs) => {
      const _component_RouterLink = resolveComponent("RouterLink");
      _push(`<div${ssrRenderAttrs(_attrs)}>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        title: "Support Inbox",
        description: "Read, assign, and respond to support conversations."
      }, {
        actions: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(ssrRenderComponent(_component_RouterLink, {
              to: "/app/support/new",
              class: "inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-xs text-white"
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(ssrRenderComponent(unref(IconPlus), { size: 14 }, null, _parent3, _scopeId2));
                  _push3(`New ticket`);
                } else {
                  return [
                    createVNode(unref(IconPlus), { size: 14 }),
                    createTextVNode("New ticket")
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
          } else {
            return [
              createVNode(_component_RouterLink, {
                to: "/app/support/new",
                class: "inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-xs text-white"
              }, {
                default: withCtx(() => [
                  createVNode(unref(IconPlus), { size: 14 }),
                  createTextVNode("New ticket")
                ]),
                _: 1
              })
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(`<section class="${ssrRenderClass([
        "grid min-h-[650px] overflow-hidden rounded-xl border bg-surface",
        folder.value === "Assigned to me" ? "lg:grid-cols-[190px_minmax(0,1fr)]" : "lg:grid-cols-[190px_340px_minmax(0,1fr)]"
      ])}"><aside class="border-b p-3 lg:border-b-0 lg:border-r"><p class="mb-2 px-2 text-2xs font-semibold uppercase tracking-wider text-text-soft"> Mailbox </p><!--[-->`);
      ssrRenderList(folders, (item) => {
        _push(`<button class="${ssrRenderClass([
          "flex h-9 w-full items-center gap-2 rounded-md px-2.5 text-left text-xs",
          folder.value === item[0] ? "bg-primary-soft font-medium text-primary" : "text-text-muted hover:bg-surface-muted"
        ])}">`);
        ssrRenderVNode(_push, createVNode(resolveDynamicComponent(item[1]), { size: 15 }, null), _parent);
        _push(`<span class="flex-1">${ssrInterpolate(item[0])}</span>`);
        if (item[2]) {
          _push(`<span class="rounded-full bg-primary px-1.5 py-0.5 text-micro text-white">${ssrInterpolate(item[2])}</span>`);
        } else {
          _push(`<!---->`);
        }
        _push(`</button>`);
      });
      _push(`<!--]--><div class="mt-5 border-t pt-4"><p class="mb-2 px-2 text-2xs font-semibold uppercase tracking-wider text-text-soft"> Labels </p><!--[-->`);
      ssrRenderList([
        ["Urgent", "bg-danger"],
        ["Technical", "bg-warning"],
        ["Question", "bg-info"]
      ], (label) => {
        _push(`<p class="flex items-center gap-2 px-2 py-1.5 text-xs text-text-muted"><i class="${ssrRenderClass(["size-2 rounded-full", label[1]])}"></i>${ssrInterpolate(label[0])}</p>`);
      });
      _push(`<!--]--></div></aside>`);
      if (folder.value !== "Assigned to me") {
        _push(`<!--[--><div class="border-b lg:border-b-0 lg:border-r"><div class="border-b p-3"><div class="relative">`);
        _push(ssrRenderComponent(unref(IconSearch), {
          size: 14,
          class: "absolute left-3 top-1/2 -translate-y-1/2 text-text-soft"
        }, null, _parent));
        _push(`<input${ssrRenderAttr("value", query.value)} class="h-9 w-full rounded-md border pl-9 pr-3 text-xs" placeholder="Search conversations"></div></div><div class="max-h-[590px] overflow-y-auto"><!--[-->`);
        ssrRenderList(rows.value, (ticket, index) => {
          _push(`<button class="${ssrRenderClass([
            "w-full border-b p-3 text-left transition-colors",
            selected.value === index ? "bg-primary-soft/70" : "hover:bg-surface-muted/60"
          ])}"><div class="flex items-start gap-2.5">`);
          _push(ssrRenderComponent(_sfc_main$2, {
            seed: String(ticket[4]),
            alt: String(ticket[4]),
            size: 30
          }, null, _parent));
          _push(`<div class="min-w-0 flex-1"><div class="flex items-center justify-between gap-2"><p class="truncate text-xs font-semibold">${ssrInterpolate(ticket[4])}</p><span class="shrink-0 text-micro text-text-soft">${ssrInterpolate(String(ticket[6]).split(",")[0])}</span></div><p class="mt-1 truncate text-xs font-medium">${ssrInterpolate(ticket[0])}</p><p class="mt-1 line-clamp-2 text-micro leading-4 text-text-muted">${ssrInterpolate(preview(String(ticket[1])))}</p><div class="mt-2 flex items-center gap-2">`);
          ssrRenderVNode(_push, createVNode(resolveDynamicComponent(categoryIcon(String(ticket[1]))), {
            size: 11,
            class: "text-text-soft"
          }, null), _parent);
          _push(`<span class="${ssrRenderClass([
            "rounded-full px-1.5 py-0.5 text-micro",
            ticket[2] === "High" ? "bg-danger-soft text-danger" : "bg-info-soft text-info"
          ])}">${ssrInterpolate(ticket[2])}</span><span class="text-micro text-text-soft">${ssrInterpolate(ticket[3])}</span></div></div></div></button>`);
        });
        _push(`<!--]-->`);
        if (!rows.value.length) {
          _push(`<p class="p-8 text-center text-xs text-text-muted"> No conversations found. </p>`);
        } else {
          _push(`<!---->`);
        }
        _push(`</div></div>`);
        if (current.value) {
          _push(`<div class="flex min-w-0 flex-col"><header class="flex items-start justify-between gap-3 border-b p-4"><div><div class="flex items-center gap-2"><h2 class="text-base font-semibold">${ssrInterpolate(current.value[0])}</h2><span class="rounded-full bg-warning-soft px-2 py-0.5 text-micro text-warning">${ssrInterpolate(current.value[3])}</span></div><p class="mt-1 text-xs text-text-muted"> SUP-2026-0184 · ${ssrInterpolate(current.value[1])} · ${ssrInterpolate(current.value[2])} priority </p></div>`);
          _push(ssrRenderComponent(_component_RouterLink, {
            to: "/app/support/1",
            class: "inline-flex items-center gap-1 text-xs text-primary"
          }, {
            default: withCtx((_, _push2, _parent2, _scopeId) => {
              if (_push2) {
                _push2(`Open full view`);
                _push2(ssrRenderComponent(unref(IconChevronRight), { size: 14 }, null, _parent2, _scopeId));
              } else {
                return [
                  createTextVNode("Open full view"),
                  createVNode(unref(IconChevronRight), { size: 14 })
                ];
              }
            }),
            _: 1
          }, _parent));
          _push(`</header><div class="flex-1 space-y-5 overflow-y-auto p-5"><article class="flex gap-3">`);
          _push(ssrRenderComponent(_sfc_main$2, {
            seed: String(current.value[4]),
            alt: String(current.value[4]),
            size: 34
          }, null, _parent));
          _push(`<div class="min-w-0 flex-1"><div class="flex justify-between gap-2"><p class="text-sm font-semibold">${ssrInterpolate(current.value[4])}</p><p class="text-micro text-text-soft">${ssrInterpolate(current.value[6])}</p></div><div class="mt-2 rounded-lg rounded-tl-none bg-surface-muted p-4 text-sm leading-6 text-text-muted">${ssrInterpolate(preview(String(current.value[1])))}</div></div></article><article class="flex gap-3">`);
          _push(ssrRenderComponent(_sfc_main$2, {
            seed: "TES Support",
            alt: "TES Support",
            size: 34
          }, null, _parent));
          _push(`<div class="min-w-0 flex-1"><p class="text-sm font-semibold">TES Support</p><div class="mt-2 rounded-lg rounded-tl-none border p-4 text-sm leading-6 text-text-muted"> Thanks for contacting support. We are reviewing the ticket and will update you here. </div></div></article></div><form class="border-t p-4"><textarea class="min-h-24 w-full resize-none rounded-md border p-3 text-sm" placeholder="Write a reply…">${ssrInterpolate(reply.value)}</textarea><div class="mt-2 flex items-center justify-between">`);
          if (sent.value) {
            _push(`<span class="text-xs text-success">Mock reply sent.</span>`);
          } else {
            _push(`<span class="text-micro text-text-soft">Replies remain mocked in this prototype.</span>`);
          }
          _push(`<button class="inline-flex items-center gap-1.5 rounded-md bg-primary px-4 py-2 text-xs text-white">`);
          _push(ssrRenderComponent(unref(IconSend), { size: 14 }, null, _parent));
          _push(`Send reply </button></div></form></div>`);
        } else {
          _push(`<!---->`);
        }
        _push(`<!--]-->`);
      } else {
        _push(`<div class="min-w-0 bg-bg/50 p-4"><div class="mb-4 flex flex-wrap items-center justify-between gap-3"><div><h2 class="text-base font-semibold">My ticket board</h2><p class="mt-1 text-xs text-text-muted"> Tickets assigned to you, grouped by workflow status. </p></div><div class="relative w-full sm:w-64">`);
        _push(ssrRenderComponent(unref(IconSearch), {
          size: 14,
          class: "absolute left-3 top-1/2 -translate-y-1/2 text-text-soft"
        }, null, _parent));
        _push(`<input class="h-9 w-full rounded-md border bg-surface pl-9 pr-3 text-xs" placeholder="Search assigned tickets"></div></div><div class="grid gap-3 overflow-x-auto pb-2 xl:grid-cols-4"><!--[-->`);
        ssrRenderList(boardColumns, (column, columnIndex) => {
          _push(`<section class="min-w-64 rounded-lg border bg-surface-muted/60 p-2.5"><header class="mb-2 flex items-center justify-between px-1"><div class="flex items-center gap-2"><i class="${ssrRenderClass([
            "size-2 rounded-full",
            column === "Open" ? "bg-info" : column === "In progress" ? "bg-warning" : column === "Waiting" ? "bg-gold" : "bg-success"
          ])}"></i><h3 class="text-xs font-semibold">${ssrInterpolate(column)}</h3></div><span class="rounded-full bg-surface px-2 py-0.5 text-micro text-text-muted">${ssrInterpolate(boardRows(column).length)}</span></header><div class="space-y-2"><!--[-->`);
          ssrRenderList(boardRows(column), (ticket) => {
            _push(`<article class="rounded-lg border bg-surface p-3 shadow-sm transition hover:border-primary/40 hover:shadow"><div class="flex items-center justify-between"><span class="font-mono text-micro text-text-soft">${ssrInterpolate(ticket.id)}</span><span class="${ssrRenderClass([
              "rounded-full px-1.5 py-0.5 text-micro",
              ticket.priority === "Urgent" ? "bg-danger-soft text-danger" : ticket.priority === "High" ? "bg-warning-soft text-warning" : "bg-info-soft text-info"
            ])}">${ssrInterpolate(ticket.priority)}</span></div>`);
            _push(ssrRenderComponent(_component_RouterLink, {
              to: "/app/support/1",
              class: "mt-2 block text-xs font-semibold leading-5 hover:text-primary"
            }, {
              default: withCtx((_, _push2, _parent2, _scopeId) => {
                if (_push2) {
                  _push2(`${ssrInterpolate(ticket.title)}`);
                } else {
                  return [
                    createTextVNode(toDisplayString(ticket.title), 1)
                  ];
                }
              }),
              _: 2
            }, _parent));
            _push(`<div class="mt-3 flex items-center gap-2">`);
            _push(ssrRenderComponent(_sfc_main$2, {
              seed: ticket.requester,
              alt: ticket.requester,
              size: 24
            }, null, _parent));
            _push(`<span class="min-w-0 flex-1 truncate text-micro text-text-muted">${ssrInterpolate(ticket.requester)}</span><span class="${ssrRenderClass([
              "text-micro",
              ticket.sla === "12m" || ticket.sla === "48m" ? "text-danger" : "text-text-soft"
            ])}">SLA ${ssrInterpolate(ticket.sla)}</span></div><div class="mt-3 flex justify-between border-t pt-2"><button${ssrIncludeBooleanAttr(columnIndex === 0) ? " disabled" : ""} class="rounded p-1 text-text-muted hover:bg-surface-muted disabled:opacity-25" title="Move left">`);
            _push(ssrRenderComponent(unref(IconArrowLeft), { size: 13 }, null, _parent));
            _push(`</button><button${ssrIncludeBooleanAttr(columnIndex === boardColumns.length - 1) ? " disabled" : ""} class="rounded p-1 text-text-muted hover:bg-surface-muted disabled:opacity-25" title="Move right">`);
            _push(ssrRenderComponent(unref(IconArrowRight), { size: 13 }, null, _parent));
            _push(`</button></div></article>`);
          });
          _push(`<!--]-->`);
          if (!boardRows(column).length) {
            _push(`<div class="rounded-md border border-dashed p-6 text-center text-micro text-text-soft"> No tickets </div>`);
          } else {
            _push(`<!---->`);
          }
          _push(`</div></section>`);
        });
        _push(`<!--]--></div></div>`);
      }
      _push(`</section></div>`);
    };
  }
});
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/modules/support/Index.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
