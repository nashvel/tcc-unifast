import { defineComponent, resolveComponent, withCtx, unref, createVNode, createTextVNode, useSSRContext } from "vue";
import { ssrRenderAttrs, ssrRenderComponent, ssrRenderList, ssrInterpolate } from "vue/server-renderer";
import { IconPlus, IconMessage, IconMail, IconDeviceMobile } from "@tabler/icons-vue";
import { a as announcements } from "./mockAdmin-BGBs67j0.js";
import { _ as _sfc_main$1 } from "./PageHeader-DV7ufis9.js";
const _sfc_main = /* @__PURE__ */ defineComponent({
  __name: "Index",
  __ssrInlineRender: true,
  setup(__props) {
    return (_ctx, _push, _parent, _attrs) => {
      const _component_RouterLink = resolveComponent("RouterLink");
      _push(`<div${ssrRenderAttrs(_attrs)}>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        title: "Announcements",
        description: "Broadcast updates to grantees by audience and channel."
      }, {
        actions: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(ssrRenderComponent(_component_RouterLink, {
              to: "/app/announcements/new",
              class: "inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-xs font-medium text-white"
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(ssrRenderComponent(unref(IconPlus), { size: 15 }, null, _parent3, _scopeId2));
                  _push3(`New announcement `);
                } else {
                  return [
                    createVNode(unref(IconPlus), { size: 15 }),
                    createTextVNode("New announcement ")
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
          } else {
            return [
              createVNode(_component_RouterLink, {
                to: "/app/announcements/new",
                class: "inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-xs font-medium text-white"
              }, {
                default: withCtx(() => [
                  createVNode(unref(IconPlus), { size: 15 }),
                  createTextVNode("New announcement ")
                ]),
                _: 1
              })
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(`<section class="space-y-2"><!--[-->`);
      ssrRenderList(unref(announcements), (item) => {
        _push(`<article class="flex flex-col justify-between gap-3 rounded-lg border bg-surface p-4 sm:flex-row sm:items-center"><div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><p class="text-sm font-semibold">${ssrInterpolate(item.title)}</p><span class="rounded-full bg-success-soft px-2 py-0.5 text-micro text-success">${ssrInterpolate(item.status)}</span></div><p class="mt-1 text-xs text-text-muted">${ssrInterpolate(item.body)}</p><div class="mt-2 flex flex-wrap gap-2"><span class="rounded-full bg-primary-soft px-2 py-0.5 text-micro text-primary">${ssrInterpolate(item.audience)}</span><!--[-->`);
        ssrRenderList(item.channels, (channel) => {
          _push(`<span class="inline-flex items-center gap-1 rounded-full bg-info-soft px-2 py-0.5 text-micro text-info">`);
          if (channel === "In-app") {
            _push(ssrRenderComponent(unref(IconMessage), { size: 10 }, null, _parent));
          } else if (channel === "Email") {
            _push(ssrRenderComponent(unref(IconMail), { size: 10 }, null, _parent));
          } else {
            _push(ssrRenderComponent(unref(IconDeviceMobile), { size: 10 }, null, _parent));
          }
          _push(`${ssrInterpolate(channel)}</span>`);
        });
        _push(`<!--]--><span class="text-micro text-text-soft">${ssrInterpolate(item.date)}</span></div></div><div class="flex gap-2">`);
        _push(ssrRenderComponent(_component_RouterLink, {
          to: "/app/announcements/1/edit",
          class: "h-8 rounded-md border px-3 py-2 text-xs"
        }, {
          default: withCtx((_, _push2, _parent2, _scopeId) => {
            if (_push2) {
              _push2(`Edit`);
            } else {
              return [
                createTextVNode("Edit")
              ];
            }
          }),
          _: 2
        }, _parent));
        _push(ssrRenderComponent(_component_RouterLink, {
          to: "/app/announcements/logs",
          class: "h-8 rounded-md border px-3 py-2 text-xs"
        }, {
          default: withCtx((_, _push2, _parent2, _scopeId) => {
            if (_push2) {
              _push2(`Logs`);
            } else {
              return [
                createTextVNode("Logs")
              ];
            }
          }),
          _: 2
        }, _parent));
        _push(`</div></article>`);
      });
      _push(`<!--]--></section></div>`);
    };
  }
});
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/modules/announcements/Index.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
