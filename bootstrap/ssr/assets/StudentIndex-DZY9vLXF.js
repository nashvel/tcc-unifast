import { defineComponent, unref, useSSRContext } from "vue";
import { ssrRenderAttrs, ssrRenderComponent, ssrRenderList, ssrInterpolate } from "vue/server-renderer";
import { IconSpeakerphone } from "@tabler/icons-vue";
import { _ as _sfc_main$1 } from "./PageHeader-DV7ufis9.js";
const _sfc_main = /* @__PURE__ */ defineComponent({
  __name: "StudentIndex",
  __ssrInlineRender: true,
  setup(__props) {
    const announcements = [
      {
        title: "Scholarship orientation schedule",
        date: "May 12, 2025",
        author: "UniFAST Office",
        body: "Orientation for new TES grantees will be held at the TCC AVR on May 15, from 8:00 AM to 12:00 PM."
      },
      {
        title: "TES application deadline",
        date: "May 8, 2025",
        author: "Scholarship Services",
        body: "Complete and upload all pending requirements before May 31 to keep your application active."
      },
      {
        title: "Release schedule advisory",
        date: "May 2, 2025",
        author: "UniFAST Office",
        body: "The next subsidy disbursement window opens on June 15. Qualified grantees will receive a notification."
      }
    ];
    return (_ctx, _push, _parent, _attrs) => {
      _push(`<div${ssrRenderAttrs(_attrs)}>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        title: "Announcements",
        description: "Updates from the UniFAST Office."
      }, null, _parent));
      _push(`<ul class="space-y-2"><!--[-->`);
      ssrRenderList(announcements, (item) => {
        _push(`<li class="rounded-lg border bg-surface p-4"><div class="flex gap-3"><span class="grid h-8 w-8 shrink-0 place-items-center rounded-md bg-primary-soft text-primary">`);
        _push(ssrRenderComponent(unref(IconSpeakerphone), { size: 16 }, null, _parent));
        _push(`</span><div><p class="text-sm font-semibold">${ssrInterpolate(item.title)}</p><p class="mt-0.5 text-xs text-text-muted">${ssrInterpolate(item.date)} · ${ssrInterpolate(item.author)}</p><p class="mt-2 text-sm">${ssrInterpolate(item.body)}</p></div></div></li>`);
      });
      _push(`<!--]--></ul></div>`);
    };
  }
});
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/modules/announcements/StudentIndex.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
