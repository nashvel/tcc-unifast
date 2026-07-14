import { defineComponent, ref, resolveComponent, mergeProps, unref, createVNode, resolveDynamicComponent, withCtx, createTextVNode, openBlock, createBlock, toDisplayString, useSSRContext } from "vue";
import { ssrRenderAttrs, ssrRenderList, ssrRenderClass, ssrRenderVNode, ssrInterpolate, ssrRenderComponent, ssrRenderAttr, ssrRenderStyle } from "vue/server-renderer";
import { LayoutDashboard, ChartNoAxesCombined, LayoutGrid, ArrowUpRight, Check, FileCheck2, CircleCheck, ChartBar, Megaphone } from "lucide-vue-next";
const _sfc_main = /* @__PURE__ */ defineComponent({
  __name: "AdminDashboard",
  __ssrInlineRender: true,
  setup(__props) {
    const saved = typeof localStorage !== "undefined" ? localStorage.getItem("unifast.dashboard.variant") : null;
    const variant = ref(saved ?? "operations");
    const kpis = [
      {
        label: "Total grantees",
        value: "2,486",
        delta: "+4.2%",
        tone: "primary",
        spark: [52, 58, 57, 63, 66, 65, 72]
      },
      {
        label: "Auto-approval rate",
        value: "68%",
        delta: "+5.0%",
        tone: "primary",
        spark: [58, 61, 60, 63, 66, 65, 68]
      },
      {
        label: "Avg validation",
        value: "2.4h",
        delta: "-0.3h",
        tone: "gold",
        spark: [76, 69, 63, 57, 53, 49, 44]
      },
      {
        label: "Active batches",
        value: "8",
        delta: "+2",
        tone: "primary",
        spark: [28, 36, 36, 45, 56, 56, 68]
      }
    ];
    const throughput = [82, 104, 126, 118, 158, 96, 112];
    const submitted = [120, 138, 152, 141, 176, 108, 132];
    const days = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];
    const status = [
      { label: "Active", value: 2138, percent: 86, color: "bg-primary" },
      { label: "Pending", value: 184, percent: 7, color: "bg-gold" },
      { label: "Validated", value: 132, percent: 5, color: "bg-success" },
      { label: "At risk", value: 32, percent: 2, color: "bg-danger" }
    ];
    const batchDistribution = [
      ["Batch 01", 842],
      ["Batch 02", 984],
      ["Batch 03", 1106],
      ["Batch 04", 1248],
      ["Batch 05", 736],
      ["Batch 06", 518]
    ];
    const compactRows = [
      ["Total grantees", "2,486"],
      ["Active", "2,138"],
      ["Pending", "184"],
      ["Validated", "1,842"],
      ["Eligible", "1,706"],
      ["At risk", "32"],
      ["Auto-approval rate", "68%"],
      ["Avg validation", "2.4h"],
      ["Active batches", "8"]
    ];
    function points(values, width = 230, height = 70) {
      const min = Math.min(...values);
      const max = Math.max(...values);
      return values.map(
        (value, index) => `${index / (values.length - 1) * width},${height - (value - min) / (max - min || 1) * (height - 10) - 5}`
      ).join(" ");
    }
    function chartCoordinates(values) {
      const width = 760;
      const height = 220;
      const left = 42;
      const right = 12;
      const top = 10;
      const bottom = 28;
      return values.map((value, index) => ({
        x: left + index / (values.length - 1) * (width - left - right),
        y: top + (height - top - bottom) * (1 - value / 180)
      }));
    }
    function smoothPath(values) {
      const coordinates = chartCoordinates(values);
      return coordinates.reduce((path, point, index) => {
        if (index === 0) return `M ${point.x} ${point.y}`;
        const previous = coordinates[index - 1];
        const middle = (previous.x + point.x) / 2;
        return `${path} C ${middle} ${previous.y}, ${middle} ${point.y}, ${point.x} ${point.y}`;
      }, "");
    }
    function areaPath(values) {
      const coordinates = chartCoordinates(values);
      return `${smoothPath(values)} L ${coordinates.at(-1)?.x} 192 L ${coordinates[0].x} 192 Z`;
    }
    return (_ctx, _push, _parent, _attrs) => {
      const _component_RouterLink = resolveComponent("RouterLink");
      _push(`<div${ssrRenderAttrs(mergeProps({ class: "space-y-4" }, _attrs))}><header class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-xs font-medium uppercase tracking-[0.14em] text-text-muted"> Operations · July 11, 2026 </p><h1 class="mt-1 text-3xl font-semibold tracking-tight text-primary"> Good day, Admin. <span class="font-normal text-text">Here&#39;s your overview.</span></h1></div><div class="flex items-center gap-2"><div class="inline-flex rounded-md border bg-surface p-0.5" role="tablist"><!--[-->`);
      ssrRenderList([
        ["operations", "Operations", unref(LayoutDashboard)],
        ["analytics", "Analytics", unref(ChartNoAxesCombined)],
        ["compact", "Compact", unref(LayoutGrid)]
      ], (option) => {
        _push(`<button class="${ssrRenderClass([
          "inline-flex h-8 items-center gap-1.5 rounded px-2.5 text-xs font-medium",
          variant.value === option[0] ? "bg-primary text-white" : "text-text-muted hover:text-text"
        ])}">`);
        ssrRenderVNode(_push, createVNode(resolveDynamicComponent(option[2]), { size: 14 }, null), _parent);
        _push(`<span class="hidden sm:inline">${ssrInterpolate(option[1])}</span></button>`);
      });
      _push(`<!--]--></div></div></header>`);
      if (variant.value === "operations") {
        _push(`<!--[--><section class="dashboard-panel grid grid-cols-2 gap-4 lg:grid-cols-4"><!--[-->`);
        ssrRenderList(kpis, (kpi) => {
          _push(`<article class="rounded-xl border bg-surface p-4"><div class="flex items-start justify-between"><p class="text-xs text-text-muted">${ssrInterpolate(kpi.label)}</p><span class="inline-flex items-center gap-0.5 rounded-full bg-success-soft px-1.5 py-0.5 text-2xs font-semibold text-success">`);
          _push(ssrRenderComponent(unref(ArrowUpRight), { size: 11 }, null, _parent));
          _push(`${ssrInterpolate(kpi.delta)}</span></div><div class="mt-1.5 flex items-end justify-between gap-2"><p class="text-2xl font-semibold tabular-nums">${ssrInterpolate(kpi.value)}</p><svg class="h-10 w-24" viewBox="0 0 230 70" preserveAspectRatio="none"><polyline${ssrRenderAttr("points", points(kpi.spark))} fill="none"${ssrRenderAttr("stroke", kpi.tone === "gold" ? "var(--accent-gold)" : "var(--primary)")} stroke-width="5" stroke-linecap="round" stroke-linejoin="round"></polyline></svg></div></article>`);
        });
        _push(`<!--]--></section><section class="dashboard-panel grid gap-4 lg:grid-cols-[minmax(0,2.2fr)_minmax(0,1fr)]"><article class="rounded-xl border bg-surface p-5 sm:p-6"><div class="flex items-start justify-between"><div><h2 class="text-lg font-semibold">Validation throughput</h2><p class="mt-1 text-xs text-text-muted">Submitted vs validated · last 7 days</p></div><div class="flex gap-3 text-xs text-text-muted"><span>■ Validated</span><span class="text-gold">■ Submitted</span></div></div><svg class="mt-5 h-64 w-full overflow-visible" viewBox="0 0 760 220" preserveAspectRatio="none" role="img" aria-label="Submitted and validated records over the last seven days"><defs><linearGradient id="dashboard-primary-area" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="var(--primary)" stop-opacity="0.35"></stop><stop offset="100%" stop-color="var(--primary)" stop-opacity="0"></stop></linearGradient><linearGradient id="dashboard-gold-area" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="var(--accent-gold)" stop-opacity="0.28"></stop><stop offset="100%" stop-color="var(--accent-gold)" stop-opacity="0"></stop></linearGradient></defs><!--[-->`);
        ssrRenderList([0, 45, 90, 135, 180], (tick) => {
          _push(`<g><line x1="42" x2="748"${ssrRenderAttr("y1", 192 - tick / 180 * 182)}${ssrRenderAttr("y2", 192 - tick / 180 * 182)} stroke="var(--border)" stroke-dasharray="3 3"></line><text x="32"${ssrRenderAttr("y", 196 - tick / 180 * 182)} text-anchor="end" class="fill-text-muted" font-size="10">${ssrInterpolate(tick)}</text></g>`);
        });
        _push(`<!--]--><path${ssrRenderAttr("d", areaPath(submitted))} fill="url(#dashboard-gold-area)" class="chart-area chart-area-delay"></path><path${ssrRenderAttr("d", areaPath(throughput))} fill="url(#dashboard-primary-area)" class="chart-area"></path><path${ssrRenderAttr("d", smoothPath(submitted))} fill="none" stroke="var(--accent-gold)" stroke-width="2" stroke-linecap="round" class="chart-line chart-line-delay" pathLength="1"></path><path${ssrRenderAttr("d", smoothPath(throughput))} fill="none" stroke="var(--primary)" stroke-width="2" stroke-linecap="round" class="chart-line" pathLength="1"></path><!--[-->`);
        ssrRenderList(days, (day, index) => {
          _push(`<text${ssrRenderAttr("x", 42 + index / 6 * 706)} y="214" text-anchor="middle" class="fill-text-muted" font-size="10">${ssrInterpolate(day)}</text>`);
        });
        _push(`<!--]--></svg></article><article class="rounded-xl border bg-surface p-5"><div class="flex justify-between"><h2 class="text-lg font-semibold">Grantee status</h2>`);
        _push(ssrRenderComponent(_component_RouterLink, {
          to: "/app/grantees",
          class: "text-xs text-primary"
        }, {
          default: withCtx((_, _push2, _parent2, _scopeId) => {
            if (_push2) {
              _push2(`Details`);
            } else {
              return [
                createTextVNode("Details")
              ];
            }
          }),
          _: 1
        }, _parent));
        _push(`</div><div class="relative mx-auto mt-5 grid h-40 w-40 place-items-center rounded-full" style="${ssrRenderStyle({ "background": "conic-gradient(\n                var(--primary) 0 68%,\n                var(--accent-gold) 68% 82%,\n                var(--success) 82% 94%,\n                var(--danger) 94% 100%\n              )" })}"><div class="grid h-24 w-24 place-items-center rounded-full bg-surface text-center"><div><p class="text-2xl font-semibold">2,486</p><p class="text-xs text-text-muted">grantees</p></div></div></div><ul class="mt-4 grid grid-cols-2 gap-2"><!--[-->`);
        ssrRenderList(status, (item) => {
          _push(`<li class="flex justify-between text-xs"><span class="flex items-center gap-2"><i class="${ssrRenderClass(["h-2 w-2 rounded-sm", item.color])}"></i>${ssrInterpolate(item.label)}</span><span class="text-text-muted">${ssrInterpolate(item.value.toLocaleString())}</span></li>`);
        });
        _push(`<!--]--></ul></article></section><section class="dashboard-panel grid gap-4 lg:grid-cols-[minmax(0,2.4fr)_minmax(0,1fr)]"><article class="rounded-xl border bg-surface p-5 sm:p-6"><div class="flex items-center justify-between"><div><h2 class="text-lg font-semibold">Pipeline progress</h2><p class="mt-1 text-xs text-text-muted"> Validation is on track — 3 stages remaining this cycle. </p></div><span class="text-2xl font-semibold tabular-nums text-gold">62%</span></div><div class="mt-4 h-1.5 overflow-hidden rounded-full bg-primary-soft"><div class="h-full w-[62%] bg-primary"></div></div><ol class="mt-6 grid grid-cols-5 gap-2"><!--[-->`);
        ssrRenderList([
          "Intake",
          "Validation",
          "Eligibility",
          "Batching",
          "Release"
        ], (step, index) => {
          _push(`<li class="flex flex-col items-center gap-2 text-center"><span class="${ssrRenderClass([
            "grid h-8 w-8 place-items-center rounded-full text-xs font-semibold",
            index < 2 ? "bg-primary text-white" : "border bg-surface text-text-muted"
          ])}">`);
          if (index < 2) {
            _push(ssrRenderComponent(unref(Check), { size: 14 }, null, _parent));
          } else {
            _push(`<!--[-->${ssrInterpolate(index + 1)}<!--]-->`);
          }
          _push(`</span><p class="${ssrRenderClass([
            "mt-2 text-xs",
            index === 1 ? "font-semibold text-primary" : "text-text-muted"
          ])}">${ssrInterpolate(step)}</p></li>`);
        });
        _push(`<!--]--></ol></article><article class="rounded-xl border bg-surface p-5"><h2 class="text-lg font-semibold">Quick actions</h2><ul class="mt-4 space-y-1"><!--[-->`);
        ssrRenderList([
          ["Review Submissions", "/app/documents", unref(FileCheck2)],
          ["Run Eligibility", "/app/eligibility", unref(CircleCheck)],
          ["Manage Batches", "/app/batches", unref(ChartBar)],
          ["New Announcement", "/app/announcements/new", unref(Megaphone)]
        ], (action) => {
          _push(`<li>`);
          _push(ssrRenderComponent(_component_RouterLink, {
            to: action[1],
            class: "flex items-center gap-3 rounded-md px-2 py-2 text-sm transition-colors hover:bg-surface-muted"
          }, {
            default: withCtx((_, _push2, _parent2, _scopeId) => {
              if (_push2) {
                _push2(`<span class="grid h-8 w-8 place-items-center rounded-md bg-primary-soft text-primary"${_scopeId}>`);
                ssrRenderVNode(_push2, createVNode(resolveDynamicComponent(action[2]), { size: 16 }, null), _parent2, _scopeId);
                _push2(`</span> ${ssrInterpolate(action[0])}`);
              } else {
                return [
                  createVNode("span", { class: "grid h-8 w-8 place-items-center rounded-md bg-primary-soft text-primary" }, [
                    (openBlock(), createBlock(resolveDynamicComponent(action[2]), { size: 16 }))
                  ]),
                  createTextVNode(" " + toDisplayString(action[0]), 1)
                ];
              }
            }),
            _: 2
          }, _parent));
          _push(`</li>`);
        });
        _push(`<!--]--></ul></article></section><!--]-->`);
      } else if (variant.value === "analytics") {
        _push(`<!--[--><section class="dashboard-panel grid grid-cols-2 gap-4 lg:grid-cols-4"><!--[-->`);
        ssrRenderList([
          ["Total grantees", "2,486", "primary"],
          ["Validated", "1,842", "success"],
          ["Pending", "184", "gold"],
          ["At risk", "32", "danger"]
        ], (item) => {
          _push(`<article class="${ssrRenderClass(`rounded-xl border border-l-4 border-l-${item[2]} bg-surface p-5`)}"><p class="text-xs uppercase tracking-wide text-text-muted">${ssrInterpolate(item[0])}</p><p class="mt-2 text-3xl font-semibold">${ssrInterpolate(item[1])}</p></article>`);
        });
        _push(`<!--]--></section><section class="dashboard-panel grid gap-4 lg:grid-cols-2"><article class="rounded-xl border bg-surface p-5"><h2 class="text-lg font-semibold">Throughput trend</h2><p class="mt-1 text-xs text-text-muted">Line comparison · last 7 days</p><svg class="mt-5 h-72 w-full overflow-visible" viewBox="0 0 760 220" preserveAspectRatio="none"><!--[-->`);
        ssrRenderList([0, 45, 90, 135, 180], (tick) => {
          _push(`<g><line x1="42" x2="748"${ssrRenderAttr("y1", 192 - tick / 180 * 182)}${ssrRenderAttr("y2", 192 - tick / 180 * 182)} stroke="var(--border)" stroke-dasharray="3 3"></line><text x="32"${ssrRenderAttr("y", 196 - tick / 180 * 182)} text-anchor="end" class="fill-text-muted" font-size="10">${ssrInterpolate(tick)}</text></g>`);
        });
        _push(`<!--]--><path${ssrRenderAttr("d", smoothPath(submitted))} fill="none" stroke="var(--accent-gold)" stroke-width="2.5" class="chart-line chart-line-delay" pathLength="1"></path><path${ssrRenderAttr("d", smoothPath(throughput))} fill="none" stroke="var(--primary)" stroke-width="2.5" class="chart-line" pathLength="1"></path><!--[-->`);
        ssrRenderList(chartCoordinates(submitted), (point, index) => {
          _push(`<g class="chart-area"><circle${ssrRenderAttr("cx", point.x)}${ssrRenderAttr("cy", point.y)} r="3" fill="var(--surface)" stroke="var(--accent-gold)" stroke-width="2"></circle></g>`);
        });
        _push(`<!--]--><!--[-->`);
        ssrRenderList(chartCoordinates(throughput), (point, index) => {
          _push(`<g class="chart-area"><circle${ssrRenderAttr("cx", point.x)}${ssrRenderAttr("cy", point.y)} r="3" fill="var(--surface)" stroke="var(--primary)" stroke-width="2"></circle></g>`);
        });
        _push(`<!--]--><!--[-->`);
        ssrRenderList(days, (day, index) => {
          _push(`<text${ssrRenderAttr("x", 42 + index / 6 * 706)} y="214" text-anchor="middle" class="fill-text-muted" font-size="10">${ssrInterpolate(day)}</text>`);
        });
        _push(`<!--]--></svg></article><article class="rounded-xl border bg-surface p-5"><h2 class="text-lg font-semibold">Status breakdown</h2><p class="mt-1 text-xs text-text-muted">Radial view · 2,486 grantees</p><div class="relative mx-auto mt-5 h-64 max-w-sm"><svg viewBox="0 0 260 260" class="h-full w-full -rotate-90"><!--[-->`);
        ssrRenderList(status, (item, index) => {
          _push(`<g><circle cx="130" cy="130"${ssrRenderAttr("r", 100 - index * 18)} fill="none" stroke="var(--surface-muted)" stroke-width="12"></circle><circle cx="130" cy="130"${ssrRenderAttr("r", 100 - index * 18)} fill="none"${ssrRenderAttr(
            "stroke",
            item.label === "Active" ? "var(--primary)" : item.label === "Pending" ? "var(--accent-gold)" : item.label === "Validated" ? "var(--success)" : "var(--danger)"
          )} stroke-width="12" stroke-linecap="round" pathLength="100"${ssrRenderAttr("stroke-dasharray", `${item.percent} 100`)} class="radial-ring"></circle></g>`);
        });
        _push(`<!--]--></svg><div class="absolute inset-0 grid place-items-center text-center"><div><p class="text-3xl font-semibold">2,486</p><p class="text-xs text-text-muted">total</p></div></div></div><ul class="mt-2 grid grid-cols-2 gap-1.5"><!--[-->`);
        ssrRenderList(status, (item) => {
          _push(`<li class="flex items-center justify-between text-xs"><span class="inline-flex items-center gap-2"><i class="${ssrRenderClass(["h-2 w-2 rounded-sm", item.color])}"></i>${ssrInterpolate(item.label)}</span><span class="tabular-nums text-text-muted">${ssrInterpolate(item.value.toLocaleString())}</span></li>`);
        });
        _push(`<!--]--></ul></article></section><section class="dashboard-panel rounded-xl border bg-surface p-5"><h2 class="text-lg font-semibold">Grantees by batch</h2><svg class="mt-5 h-64 w-full overflow-visible" viewBox="0 0 760 220" preserveAspectRatio="none"><!--[-->`);
        ssrRenderList([0, 350, 700, 1050, 1400], (tick) => {
          _push(`<g><line x1="42" x2="748"${ssrRenderAttr("y1", 190 - tick / 1400 * 175)}${ssrRenderAttr("y2", 190 - tick / 1400 * 175)} stroke="var(--border)" stroke-dasharray="3 3"></line><text x="32"${ssrRenderAttr("y", 194 - tick / 1400 * 175)} text-anchor="end" class="fill-text-muted" font-size="10">${ssrInterpolate(tick)}</text></g>`);
        });
        _push(`<!--]--><!--[-->`);
        ssrRenderList(batchDistribution, (batch, index) => {
          _push(`<g><rect${ssrRenderAttr("x", 78 + index * 112)}${ssrRenderAttr("y", 190 - Number(batch[1]) / 1400 * 175)} width="32"${ssrRenderAttr("height", Number(batch[1]) / 1400 * 175)} rx="6" fill="var(--accent-gold)" class="chart-bar"></rect><text${ssrRenderAttr("x", 94 + index * 112)} y="211" text-anchor="middle" class="fill-text-muted" font-size="9">${ssrInterpolate(batch[0])}</text></g>`);
        });
        _push(`<!--]--></svg></section><!--]-->`);
      } else {
        _push(`<section class="dashboard-panel grid gap-4 lg:grid-cols-3"><article class="rounded-xl border bg-surface p-4 lg:col-span-2"><h2 class="text-sm font-semibold uppercase text-text-muted">Metrics</h2><dl class="mt-3 divide-y"><!--[-->`);
        ssrRenderList(compactRows, (row) => {
          _push(`<div class="flex justify-between py-2.5"><dt class="text-sm">${ssrInterpolate(row[0])}</dt><dd class="text-sm font-semibold tabular-nums">${ssrInterpolate(row[1])}</dd></div>`);
        });
        _push(`<!--]--></dl><svg class="mt-4 h-24 w-full" viewBox="0 0 760 220" preserveAspectRatio="none"><defs><linearGradient id="compact-area" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="var(--primary)" stop-opacity=".25"></stop><stop offset="100%" stop-color="var(--primary)" stop-opacity="0"></stop></linearGradient></defs><path${ssrRenderAttr("d", areaPath(throughput))} fill="url(#compact-area)" class="chart-area"></path><path${ssrRenderAttr("d", smoothPath(throughput))} fill="none" stroke="var(--primary)" stroke-width="3" pathLength="1" class="chart-line"></path></svg></article><article class="rounded-xl border bg-surface p-4"><h2 class="text-sm font-semibold uppercase text-text-muted">Status</h2><ul class="mt-3 space-y-3"><!--[-->`);
        ssrRenderList(status, (item) => {
          _push(`<li><div class="flex justify-between text-xs"><span>${ssrInterpolate(item.label)}</span><span class="text-text-muted">${ssrInterpolate(item.value.toLocaleString())} · ${ssrInterpolate(item.percent)}%</span></div><div class="mt-1 h-1.5 overflow-hidden rounded-full bg-surface-muted"><div class="${ssrRenderClass(["h-full", item.color])}" style="${ssrRenderStyle({ width: `${item.percent}%` })}"></div></div></li>`);
        });
        _push(`<!--]--></ul><h2 class="mt-6 text-sm font-semibold uppercase text-text-muted">Latest broadcast</h2><p class="mt-3 text-sm font-medium">TES application deadline</p><p class="mt-1 text-xs text-text-muted"> Applications close on May 31. Complete pending requirements before the cut-off. </p>`);
        _push(ssrRenderComponent(_component_RouterLink, {
          to: "/app/announcements",
          class: "mt-3 inline-block text-xs text-primary"
        }, {
          default: withCtx((_, _push2, _parent2, _scopeId) => {
            if (_push2) {
              _push2(`Manage announcements →`);
            } else {
              return [
                createTextVNode("Manage announcements →")
              ];
            }
          }),
          _: 1
        }, _parent));
        _push(`</article></section>`);
      }
      _push(`</div>`);
    };
  }
});
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/modules/dashboard/AdminDashboard.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
