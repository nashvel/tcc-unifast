import { defineComponent, ref, resolveComponent, mergeProps, unref, withCtx, createVNode, createTextVNode, resolveDynamicComponent, toDisplayString, useSSRContext } from "vue";
import { ssrRenderAttrs, ssrRenderComponent, ssrRenderList, ssrRenderStyle, ssrInterpolate, ssrRenderVNode, ssrRenderClass } from "vue/server-renderer";
import { IconLock, IconShieldCheck, IconPassword, IconId, IconUpload, IconFileCheck, IconSparkles, IconArrowRight, IconSchool, IconCalendarEvent, IconWorld, IconChevronLeft, IconChevronRight, IconClock } from "@tabler/icons-vue";
import { _ as _sfc_main$1 } from "./DiceBearAvatar-C3Eyt9zS.js";
import { s as studentVerification } from "../ssr.js";
import "vue-router";
const _sfc_main = /* @__PURE__ */ defineComponent({
  __name: "StudentDashboard",
  __ssrInlineRender: true,
  setup(__props) {
    const activityTab = ref("Time spent");
    const days = [
      ["Mon", 6],
      ["Tue", 4.5],
      ["Wed", 10],
      ["Thu", 7],
      ["Fri", 6.5],
      ["Sat", 5],
      ["Sun", 7.5]
    ];
    const progress = [
      {
        name: "Required Documents",
        meta: "Course History and COR required",
        value: 0,
        tone: "bg-primary",
        to: "/student/documents"
      },
      {
        name: "TES Application",
        meta: "Upload required documents",
        value: 0,
        tone: "bg-gold",
        to: "/student/upload"
      }
    ];
    const schedule = [
      {
        time: "8:00 AM",
        title: "Scholarship orientation",
        meta: "AVR · 8:00–10:00 AM",
        tone: "border-gold bg-gold-soft"
      },
      {
        time: "10:30 AM",
        title: "Document review deadline",
        meta: "Submit remaining requirement",
        tone: "border-primary bg-primary-soft"
      },
      {
        time: "2:00 PM",
        title: "Eligibility consultation",
        meta: "Student Services Office",
        tone: "border-info bg-info-soft"
      },
      {
        time: "3:30 PM",
        title: "TES financial literacy session",
        meta: "Online meeting",
        tone: "border-success bg-success-soft"
      }
    ];
    return (_ctx, _push, _parent, _attrs) => {
      const _component_RouterLink = resolveComponent("RouterLink");
      _push(`<div${ssrRenderAttrs(mergeProps({ class: "student-dashboard mx-auto max-w-[1280px] space-y-4" }, _attrs))}>`);
      if (!unref(studentVerification).verified) {
        _push(`<section class="rounded-2xl border bg-surface p-6 shadow-sm"><div class="grid gap-5 lg:grid-cols-[1fr_340px]"><div><span class="inline-flex items-center gap-2 rounded-full bg-warning-soft px-3 py-1 text-xs font-semibold text-warning">`);
        _push(ssrRenderComponent(unref(IconLock), { size: 14 }, null, _parent));
        _push(` Limited access </span><h1 class="mt-4 text-3xl font-semibold tracking-tight"> Your account is activated, but identity verification is still required. </h1><p class="mt-2 max-w-2xl text-sm text-text-muted"> Upload your student ID and complete the live face match. Once it passes, the dashboard, required documents, upload menu, announcements, and student services will unlock. </p><div class="mt-5 flex flex-wrap gap-2">`);
        _push(ssrRenderComponent(_component_RouterLink, {
          to: "/student/verify",
          class: "inline-flex h-10 items-center gap-2 rounded-md bg-primary px-4 text-sm font-medium text-white"
        }, {
          default: withCtx((_, _push2, _parent2, _scopeId) => {
            if (_push2) {
              _push2(ssrRenderComponent(unref(IconShieldCheck), { size: 16 }, null, _parent2, _scopeId));
              _push2(` Verify identity `);
            } else {
              return [
                createVNode(unref(IconShieldCheck), { size: 16 }),
                createTextVNode(" Verify identity ")
              ];
            }
          }),
          _: 1
        }, _parent));
        _push(ssrRenderComponent(_component_RouterLink, {
          to: "/student/settings",
          class: "inline-flex h-10 items-center gap-2 rounded-md border px-4 text-sm"
        }, {
          default: withCtx((_, _push2, _parent2, _scopeId) => {
            if (_push2) {
              _push2(ssrRenderComponent(unref(IconPassword), { size: 16 }, null, _parent2, _scopeId));
              _push2(` Change password `);
            } else {
              return [
                createVNode(unref(IconPassword), { size: 16 }),
                createTextVNode(" Change password ")
              ];
            }
          }),
          _: 1
        }, _parent));
        _push(`</div></div><aside class="rounded-xl border bg-surface-muted p-4"><h2 class="text-sm font-semibold">Student unlock checklist</h2><ol class="mt-3 space-y-3 text-xs text-text-muted"><li class="flex gap-2">`);
        _push(ssrRenderComponent(unref(IconId), {
          size: 15,
          class: "text-primary"
        }, null, _parent));
        _push(` Upload student ID.</li><li class="flex gap-2">`);
        _push(ssrRenderComponent(unref(IconShieldCheck), {
          size: 15,
          class: "text-primary"
        }, null, _parent));
        _push(` Pass live face match.</li><li class="flex gap-2">`);
        _push(ssrRenderComponent(unref(IconUpload), {
          size: 15,
          class: "text-primary"
        }, null, _parent));
        _push(` Upload Course History.</li><li class="flex gap-2">`);
        _push(ssrRenderComponent(unref(IconFileCheck), {
          size: 15,
          class: "text-primary"
        }, null, _parent));
        _push(` Upload COR for stronger validation.</li></ol></aside></div></section>`);
      } else {
        _push(`<!--[--><section class="rounded-xl border border-info/30 bg-info-soft p-3"><div class="flex flex-wrap items-center justify-between gap-3"><div><p class="text-sm font-semibold text-info">Identity verified</p><p class="mt-0.5 text-xs text-text-muted"> Next: change your temporary password and upload COR to strengthen validation. </p></div><div class="flex gap-2">`);
        _push(ssrRenderComponent(_component_RouterLink, {
          to: "/student/settings",
          class: "rounded-md border bg-surface px-3 py-2 text-xs"
        }, {
          default: withCtx((_, _push2, _parent2, _scopeId) => {
            if (_push2) {
              _push2(` Change password `);
            } else {
              return [
                createTextVNode(" Change password ")
              ];
            }
          }),
          _: 1
        }, _parent));
        _push(ssrRenderComponent(_component_RouterLink, {
          to: { path: "/student/upload", query: { type: "COR" } },
          class: "rounded-md bg-primary px-3 py-2 text-xs font-medium text-white"
        }, {
          default: withCtx((_, _push2, _parent2, _scopeId) => {
            if (_push2) {
              _push2(` Upload COR `);
            } else {
              return [
                createTextVNode(" Upload COR ")
              ];
            }
          }),
          _: 1
        }, _parent));
        _push(`</div></div></section><header class="student-dashboard-header"><p class="text-xs text-text-muted">Welcome, Maria</p><h1 class="mt-1 text-3xl font-semibold tracking-tight"> Manage <span class="text-primary">your scholarship journey</span></h1><div class="relative mt-4">`);
        _push(ssrRenderComponent(unref(IconSparkles), {
          size: 17,
          class: "absolute left-4 top-1/2 -translate-y-1/2 text-primary"
        }, null, _parent));
        _push(`<input class="h-12 w-full rounded-xl border bg-surface pl-12 pr-14 text-sm shadow-sm" placeholder="E.g. check my requirements, deadlines, or application status"><button class="absolute right-2 top-1/2 grid size-8 -translate-y-1/2 place-items-center rounded-full bg-primary text-white">`);
        _push(ssrRenderComponent(unref(IconArrowRight), { size: 15 }, null, _parent));
        _push(`</button></div></header><section class="grid grid-cols-2 gap-3 lg:grid-cols-4"><!--[-->`);
        ssrRenderList([
          {
            label: "Requirements completed",
            value: "0 / 2",
            icon: unref(IconFileCheck),
            foot: "Course History and COR required"
          },
          { label: "Applications", value: "1", icon: unref(IconSchool), foot: "Documents pending" },
          { label: "Upcoming events", value: "4", icon: unref(IconCalendarEvent), foot: "Next: May 15" },
          { label: "Announcements", value: "12", icon: unref(IconWorld), foot: "3 unread" }
        ], (card, cardIndex) => {
          _push(`<article class="student-summary-card rounded-xl border bg-surface p-4" style="${ssrRenderStyle({ animationDelay: `${80 + cardIndex * 70}ms` })}"><p class="text-xs text-text-muted">${ssrInterpolate(card.label)}</p><p class="mt-2 text-2xl font-semibold">${ssrInterpolate(card.value)}</p><div class="mt-5 flex items-center justify-between"><span class="inline-flex items-center gap-1.5 text-micro text-text-muted">`);
          ssrRenderVNode(_push, createVNode(resolveDynamicComponent(card.icon), {
            size: 14,
            class: "text-primary"
          }, null), _parent);
          _push(`${ssrInterpolate(card.foot)}</span><span class="text-micro font-medium text-success">↗ 4.6%</span></div></article>`);
        });
        _push(`<!--]--></section><section class="grid gap-4 xl:grid-cols-[minmax(0,2.1fr)_minmax(300px,1fr)]"><div class="space-y-4"><article class="student-panel rounded-xl border bg-surface p-5"><div class="flex items-center justify-between"><h2 class="text-base font-semibold">Scholarship activity</h2><select class="h-8 rounded-md border bg-surface px-2 text-xs"><option>Weekly</option><option>Monthly</option></select></div><nav class="mt-3 flex gap-5 border-b"><!--[-->`);
        ssrRenderList(["Time spent", "Requirements", "Applications", "Announcements"], (tab) => {
          _push(`<button class="${ssrRenderClass([
            "border-b-2 pb-2 text-xs",
            activityTab.value === tab ? "border-primary font-medium text-primary" : "border-transparent text-text-soft"
          ])}">${ssrInterpolate(tab)}</button>`);
        });
        _push(`<!--]--></nav><div class="mt-4 grid grid-cols-7 gap-2"><!--[-->`);
        ssrRenderList(days, (day, index) => {
          _push(`<div class="flex flex-col items-center"><div class="relative flex h-44 w-full items-end justify-center overflow-hidden rounded-md bg-surface-muted/70"><div class="${ssrRenderClass([
            "student-activity-bar w-full rounded-t-md",
            index === 2 ? "bg-primary" : "bg-primary-soft"
          ])}" style="${ssrRenderStyle({ height: `${Number(day[1]) * 9}%` })}"></div>`);
          if (index === 2) {
            _push(`<span class="absolute top-3 rounded-md bg-surface px-2 py-1 text-micro font-medium shadow">Wed<br><b>8h 30m</b></span>`);
          } else {
            _push(`<!---->`);
          }
          _push(`</div><p class="mt-2 text-micro text-text-muted">${ssrInterpolate(day[0])}</p></div>`);
        });
        _push(`<!--]--></div></article><article class="student-panel rounded-xl border bg-surface p-5"><h2 class="text-base font-semibold">Application progress</h2><div class="mt-4 grid grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)_110px_34px] items-center gap-3 border-b pb-2 text-micro uppercase text-text-soft"><span>Item</span><span>Status</span><span>Progress</span><span></span></div><!--[-->`);
        ssrRenderList(progress, (item) => {
          _push(ssrRenderComponent(_component_RouterLink, {
            key: item.name,
            to: item.to,
            class: "grid grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)_110px_34px] items-center gap-3 border-b py-3 last:border-0"
          }, {
            default: withCtx((_, _push2, _parent2, _scopeId) => {
              if (_push2) {
                _push2(`<span class="flex items-center gap-2 text-xs font-medium"${_scopeId}><span class="grid size-7 place-items-center rounded-md bg-primary-soft text-primary"${_scopeId}>`);
                _push2(ssrRenderComponent(unref(IconFileCheck), { size: 14 }, null, _parent2, _scopeId));
                _push2(`</span>${ssrInterpolate(item.name)}</span><span class="text-xs text-text-muted"${_scopeId}>${ssrInterpolate(item.meta)}</span><span class="flex items-center gap-2"${_scopeId}><i class="h-1.5 flex-1 overflow-hidden rounded-full bg-surface-muted"${_scopeId}><i class="${ssrRenderClass(["student-progress-fill block h-full", item.tone])}" style="${ssrRenderStyle({ width: `${item.value}%` })}"${_scopeId}></i></i><b class="text-micro"${_scopeId}>${ssrInterpolate(item.value)}%</b></span><span class="grid size-7 place-items-center rounded-md border"${_scopeId}>`);
                _push2(ssrRenderComponent(unref(IconArrowRight), { size: 13 }, null, _parent2, _scopeId));
                _push2(`</span>`);
              } else {
                return [
                  createVNode("span", { class: "flex items-center gap-2 text-xs font-medium" }, [
                    createVNode("span", { class: "grid size-7 place-items-center rounded-md bg-primary-soft text-primary" }, [
                      createVNode(unref(IconFileCheck), { size: 14 })
                    ]),
                    createTextVNode(toDisplayString(item.name), 1)
                  ]),
                  createVNode("span", { class: "text-xs text-text-muted" }, toDisplayString(item.meta), 1),
                  createVNode("span", { class: "flex items-center gap-2" }, [
                    createVNode("i", { class: "h-1.5 flex-1 overflow-hidden rounded-full bg-surface-muted" }, [
                      createVNode("i", {
                        class: ["student-progress-fill block h-full", item.tone],
                        style: { width: `${item.value}%` }
                      }, null, 6)
                    ]),
                    createVNode("b", { class: "text-micro" }, toDisplayString(item.value) + "%", 1)
                  ]),
                  createVNode("span", { class: "grid size-7 place-items-center rounded-md border" }, [
                    createVNode(unref(IconArrowRight), { size: 13 })
                  ])
                ];
              }
            }),
            _: 2
          }, _parent));
        });
        _push(`<!--]--></article></div><aside class="student-panel rounded-xl border bg-surface p-5"><div class="flex items-start justify-between"><div><h2 class="text-base font-semibold">Schedule</h2><p class="mt-1 text-xs text-text-muted">15 May, 2026</p></div><div class="flex gap-1"><button class="rounded-md border p-1.5">`);
        _push(ssrRenderComponent(unref(IconChevronLeft), { size: 14 }, null, _parent));
        _push(`</button><button class="rounded-md border p-1.5">`);
        _push(ssrRenderComponent(unref(IconChevronRight), { size: 14 }, null, _parent));
        _push(`</button></div></div><div class="relative mt-5 border-l pl-5"><div class="absolute left-[-1px] top-0 h-full w-px bg-border"></div><!--[-->`);
        ssrRenderList(schedule, (event, eventIndex) => {
          _push(`<article class="student-schedule-item relative mb-5" style="${ssrRenderStyle({ animationDelay: `${220 + eventIndex * 80}ms` })}"><span class="absolute -left-[26px] top-1 size-2.5 rounded-full border-2 border-surface bg-primary"></span><p class="text-micro text-text-soft">${ssrInterpolate(event.time)}</p><div class="${ssrRenderClass(["mt-1 rounded-lg border-l-2 p-3", event.tone])}"><p class="text-xs font-semibold">${ssrInterpolate(event.title)}</p><p class="mt-1 flex items-center gap-1 text-micro text-text-muted">`);
          _push(ssrRenderComponent(unref(IconClock), { size: 11 }, null, _parent));
          _push(`${ssrInterpolate(event.meta)}</p>`);
          if (event.title === "Scholarship orientation") {
            _push(`<div class="mt-2 flex -space-x-1"><!--[-->`);
            ssrRenderList(["Maria", "John", "Ana"], (seed) => {
              _push(ssrRenderComponent(_sfc_main$1, {
                key: seed,
                seed,
                alt: seed,
                size: 20
              }, null, _parent));
            });
            _push(`<!--]--></div>`);
          } else {
            _push(`<!---->`);
          }
          _push(`</div></article>`);
        });
        _push(`<!--]--></div>`);
        _push(ssrRenderComponent(_component_RouterLink, {
          to: "/student/announcements",
          class: "mt-2 flex items-center justify-center gap-1 rounded-md border py-2 text-xs text-primary"
        }, {
          default: withCtx((_, _push2, _parent2, _scopeId) => {
            if (_push2) {
              _push2(`View all events`);
              _push2(ssrRenderComponent(unref(IconArrowRight), { size: 13 }, null, _parent2, _scopeId));
            } else {
              return [
                createTextVNode("View all events"),
                createVNode(unref(IconArrowRight), { size: 13 })
              ];
            }
          }),
          _: 1
        }, _parent));
        _push(`</aside></section><!--]-->`);
      }
      _push(`</div>`);
    };
  }
});
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/modules/dashboard/StudentDashboard.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
