<script setup lang="ts">
import { ref } from "vue";
import { useRoute } from "vue-router";
import { useI18n } from "vue-i18n";
import {
  ArrowUpRight,
  ChartBar,
  ChartNoAxesCombined,
  Check,
  CircleCheck,
  FileCheck2,
  FileSpreadsheet,
  LayoutDashboard,
  LayoutGrid,
  Megaphone,
} from "lucide-vue-next";
import AppTour from "@/components/tour/AppTour.vue";
import { translateKnownText } from "@/i18n/knownText";
import { withLang } from "@/i18n/routeLang";

type DashboardVariant = "operations" | "analytics" | "compact";
const route = useRoute();
const { t } = useI18n();
const saved =
  typeof localStorage !== "undefined"
    ? (localStorage.getItem("unifast.dashboard.variant") as DashboardVariant | null)
    : null;
const variant = ref<DashboardVariant>(saved ?? "operations");

const kpis = [
  {
    label: "Total grantees",
    value: "2,486",
    delta: "+4.2%",
    tone: "primary",
    spark: [52, 58, 57, 63, 66, 65, 72],
  },
  {
    label: "Auto-approval rate",
    value: "68%",
    delta: "+5.0%",
    tone: "primary",
    spark: [58, 61, 60, 63, 66, 65, 68],
  },
  {
    label: "Avg validation",
    value: "2.4h",
    delta: "-0.3h",
    tone: "gold",
    spark: [76, 69, 63, 57, 53, 49, 44],
  },
  {
    label: "Active batches",
    value: "8",
    delta: "+2",
    tone: "primary",
    spark: [28, 36, 36, 45, 56, 56, 68],
  },
];
const throughput = [82, 104, 126, 118, 158, 96, 112];
const submitted = [120, 138, 152, 141, 176, 108, 132];
const days = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];
const status = [
  { label: "Active", value: 2138, percent: 86, color: "bg-primary" },
  { label: "Pending", value: 184, percent: 7, color: "bg-gold" },
  { label: "Validated", value: 132, percent: 5, color: "bg-success" },
  { label: "At risk", value: 32, percent: 2, color: "bg-danger" },
];
const batchDistribution = [
  ["Batch 01", 842],
  ["Batch 02", 984],
  ["Batch 03", 1106],
  ["Batch 04", 1248],
  ["Batch 05", 736],
  ["Batch 06", 518],
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
  ["Active batches", "8"],
];

function choose(value: DashboardVariant) {
  variant.value = value;
  if (typeof localStorage !== "undefined") localStorage.setItem("unifast.dashboard.variant", value);
}

function points(values: number[], width = 230, height = 70) {
  const min = Math.min(...values);
  const max = Math.max(...values);
  return values
    .map(
      (value, index) =>
        `${(index / (values.length - 1)) * width},${height - ((value - min) / (max - min || 1)) * (height - 10) - 5}`,
    )
    .join(" ");
}

function chartCoordinates(values: number[]) {
  const width = 760;
  const height = 220;
  const left = 42;
  const right = 12;
  const top = 10;
  const bottom = 28;
  return values.map((value, index) => ({
    x: left + (index / (values.length - 1)) * (width - left - right),
    y: top + (height - top - bottom) * (1 - value / 180),
  }));
}

function smoothPath(values: number[]) {
  const coordinates = chartCoordinates(values);
  return coordinates.reduce((path, point, index) => {
    if (index === 0) return `M ${point.x} ${point.y}`;
    const previous = coordinates[index - 1];
    const middle = (previous.x + point.x) / 2;
    return `${path} C ${middle} ${previous.y}, ${middle} ${point.y}, ${point.x} ${point.y}`;
  }, "");
}

function areaPath(values: number[]) {
  const coordinates = chartCoordinates(values);
  return `${smoothPath(values)} L ${coordinates.at(-1)?.x} 192 L ${coordinates[0].x} 192 Z`;
}
</script>

<template>
  <div class="space-y-4">
    <header class="flex flex-wrap items-start justify-between gap-3" data-tour="page-header">
      <div>
        <p class="text-xs font-medium uppercase tracking-[0.14em] text-text-muted">
          Operations · July 11, 2026
        </p>
        <h1 class="mt-1 text-3xl font-semibold tracking-tight text-primary">
          Good day, Admin. <span class="font-normal text-text">Here's your overview.</span>
        </h1>
      </div>
      <div class="flex items-center gap-2">
        <AppTour />
        <div
          class="inline-flex rounded-md border bg-surface p-0.5"
          role="tablist"
          data-tour="dashboard-switcher"
        >
          <button
            v-for="option in [
              ['operations', 'Operations', LayoutDashboard],
              ['analytics', 'Analytics', ChartNoAxesCombined],
              ['compact', 'Compact', LayoutGrid],
            ]"
            :key="option[0] as string"
            :class="[
              'inline-flex h-8 items-center gap-1.5 rounded px-2.5 text-xs font-medium',
              variant === option[0] ? 'bg-primary text-white' : 'text-text-muted hover:text-text',
            ]"
            @click="choose(option[0] as DashboardVariant)"
          >
            <component :is="option[2]" :size="14" /><span class="hidden sm:inline">{{
              translateKnownText(t, option[1] as string)
            }}</span>
          </button>
        </div>
      </div>
    </header>

    <!-- Hick's Law: Immediate Operational Triage Banner -->
    <section class="rounded-xl border border-primary/25 bg-gradient-to-r from-primary/10 via-primary/5 to-surface p-4 sm:p-5 shadow-2xs">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <div class="flex items-center gap-2">
            <span class="relative flex h-2.5 w-2.5">
              <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
              <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
            </span>
            <h2 class="text-sm font-semibold text-text">Operational Priority Triage</h2>
            <span class="rounded-full bg-primary/15 px-2 py-0.5 text-2xs font-semibold text-primary">
              Active AY Cycle
            </span>
          </div>
          <p class="mt-1 text-xs text-text-muted">
            <strong class="text-text font-semibold">184 student submissions</strong> awaiting document review ·
            <strong class="text-text font-semibold">3 biometric face reviews</strong> uncertain ·
            <strong class="text-text font-semibold">3 active batches</strong>.
          </p>
        </div>

        <div class="flex items-center gap-2 shrink-0">
          <RouterLink
            :to="withLang('/app/documents', route.query.lang)"
            class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-primary px-3.5 text-xs font-semibold text-white shadow-xs transition hover:bg-primary/90"
          >
            <component :is="FileCheck2" :size="15" />
            Review Queue &rarr;
          </RouterLink>
          <RouterLink
            :to="withLang('/app/face-reviews', route.query.lang)"
            class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-border bg-surface px-3 text-xs font-medium text-text hover:bg-surface-muted transition"
          >
            Face Reviews (3)
          </RouterLink>
        </div>
      </div>
    </section>

    <template v-if="variant === 'operations'">
      <section
        class="dashboard-panel grid grid-cols-2 gap-4 lg:grid-cols-4"
        data-tour="dashboard-kpis"
      >
        <article v-for="kpi in kpis" :key="kpi.label" class="rounded-xl border bg-surface p-4">
          <div class="flex items-start justify-between">
            <p class="text-xs text-text-muted">{{ translateKnownText(t, kpi.label) }}</p>
            <span
              class="inline-flex items-center gap-0.5 rounded-full bg-success-soft px-1.5 py-0.5 text-2xs font-semibold text-success"
              ><ArrowUpRight :size="11" />{{ kpi.delta }}</span
            >
          </div>
          <div class="mt-1.5 flex items-end justify-between gap-2">
            <p class="text-2xl font-semibold tabular-nums">{{ kpi.value }}</p>
            <svg class="h-10 w-24" viewBox="0 0 230 70" preserveAspectRatio="none">
              <polyline
                :points="points(kpi.spark)"
                fill="none"
                :stroke="kpi.tone === 'gold' ? 'var(--accent-gold)' : 'var(--primary)'"
                stroke-width="5"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
          </div>
        </article>
      </section>

      <section class="dashboard-panel grid gap-4 lg:grid-cols-[minmax(0,2.2fr)_minmax(0,1fr)]">
        <article class="rounded-xl border bg-surface p-5 sm:p-6">
          <div class="flex items-start justify-between">
            <div>
              <h2 class="text-lg font-semibold">Validation throughput</h2>
              <p class="mt-1 text-xs text-text-muted">Submitted vs validated · last 7 days</p>
            </div>
            <div class="flex gap-3 text-xs text-text-muted">
              <span>■ Validated</span><span class="text-gold">■ Submitted</span>
            </div>
          </div>
          <svg
            class="mt-5 h-64 w-full overflow-visible"
            viewBox="0 0 760 220"
            preserveAspectRatio="none"
            role="img"
            :aria-label="t('dashboard.admin.chartAria')"
          >
            <defs>
              <linearGradient id="dashboard-primary-area" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="var(--primary)" stop-opacity="0.35" />
                <stop offset="100%" stop-color="var(--primary)" stop-opacity="0" />
              </linearGradient>
              <linearGradient id="dashboard-gold-area" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="var(--accent-gold)" stop-opacity="0.28" />
                <stop offset="100%" stop-color="var(--accent-gold)" stop-opacity="0" />
              </linearGradient>
            </defs>
            <g v-for="tick in [0, 45, 90, 135, 180]" :key="tick">
              <line
                x1="42"
                x2="748"
                :y1="192 - (tick / 180) * 182"
                :y2="192 - (tick / 180) * 182"
                stroke="var(--border)"
                stroke-dasharray="3 3"
              />
              <text
                x="32"
                :y="196 - (tick / 180) * 182"
                text-anchor="end"
                class="fill-text-muted"
                font-size="10"
              >
                {{ tick }}
              </text>
            </g>
            <path
              :d="areaPath(submitted)"
              fill="url(#dashboard-gold-area)"
              class="chart-area chart-area-delay"
            />
            <path
              :d="areaPath(throughput)"
              fill="url(#dashboard-primary-area)"
              class="chart-area"
            />
            <path
              :d="smoothPath(submitted)"
              fill="none"
              stroke="var(--accent-gold)"
              stroke-width="2"
              stroke-linecap="round"
              class="chart-line chart-line-delay"
              pathLength="1"
            />
            <path
              :d="smoothPath(throughput)"
              fill="none"
              stroke="var(--primary)"
              stroke-width="2"
              stroke-linecap="round"
              class="chart-line"
              pathLength="1"
            />
            <text
              v-for="(day, index) in days"
              :key="day"
              :x="42 + (index / 6) * 706"
              y="214"
              text-anchor="middle"
              class="fill-text-muted"
              font-size="10"
            >
              {{ day }}
            </text>
          </svg>
        </article>
        <article class="rounded-xl border bg-surface p-5">
          <div class="flex justify-between">
            <h2 class="text-lg font-semibold">Grantee status</h2>
            <RouterLink :to="withLang('/app/grantees', route.query.lang)" class="text-xs text-primary">{{ t("common.details") }}</RouterLink>
          </div>
          <div
            class="relative mx-auto mt-5 grid h-40 w-40 place-items-center rounded-full"
            style="
              background: conic-gradient(
                var(--primary) 0 68%,
                var(--accent-gold) 68% 82%,
                var(--success) 82% 94%,
                var(--danger) 94% 100%
              );
            "
          >
            <div class="grid h-24 w-24 place-items-center rounded-full bg-surface text-center">
              <div>
                <p class="text-2xl font-semibold">2,486</p>
                <p class="text-xs text-text-muted">grantees</p>
              </div>
            </div>
          </div>
          <ul class="mt-4 grid grid-cols-2 gap-2">
            <li v-for="item in status" :key="item.label" class="flex justify-between text-xs">
              <span class="flex items-center gap-2"
                ><i :class="['h-2 w-2 rounded-sm', item.color]" />{{ translateKnownText(t, item.label) }}</span
              ><span class="text-text-muted">{{ item.value.toLocaleString() }}</span>
            </li>
          </ul>
        </article>
      </section>

      <section class="dashboard-panel grid gap-4 lg:grid-cols-[minmax(0,2.4fr)_minmax(0,1fr)]">
        <article class="rounded-xl border bg-surface p-5 sm:p-6">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-lg font-semibold">Pipeline progress</h2>
              <p class="mt-1 text-xs text-text-muted">
                Validation is on track — 3 stages remaining this cycle.
              </p>
            </div>
            <span class="text-2xl font-semibold tabular-nums text-gold">62%</span>
          </div>
          <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-primary-soft">
            <div class="h-full w-[62%] bg-primary" />
          </div>
          <ol class="mt-6 grid grid-cols-5 gap-2">
            <li
              v-for="(step, index) in [
                'Intake',
                'Validation',
                'Eligibility',
                'Batching',
                'Release',
              ]"
              :key="step"
              class="flex flex-col items-center gap-2 text-center"
            >
              <span
                :class="[
                  'grid h-8 w-8 place-items-center rounded-full text-xs font-semibold',
                  index < 2 ? 'bg-primary text-white' : 'border bg-surface text-text-muted',
                ]"
                ><Check v-if="index < 2" :size="14" /><template v-else>{{
                  index + 1
                }}</template></span
              >
              <p
                :class="[
                  'mt-2 text-xs',
                  index === 1 ? 'font-semibold text-primary' : 'text-text-muted',
                ]"
              >
                {{ translateKnownText(t, step) }}
              </p>
            </li>
          </ol>
        </article>
        <article class="rounded-xl border bg-surface p-5">
          <h2 class="text-lg font-semibold">Quick actions</h2>
          <ul class="mt-4 space-y-1">
            <li
              v-for="action in [
                ['Review Submissions', '/app/documents', FileCheck2],
                ['Run Eligibility', '/app/eligibility', CircleCheck],
                ['Call for Billing', '/app/billing', FileSpreadsheet],
                ['Manage Batches', '/app/batches', ChartBar],
                ['New Announcement', '/app/announcements/new', Megaphone],
              ]"
              :key="action[0] as string"
            >
              <RouterLink
                :to="withLang(action[1] as string, route.query.lang)"
                class="flex items-center gap-3 rounded-md px-2 py-2 text-sm transition-colors hover:bg-surface-muted"
              >
                <span
                  class="grid h-8 w-8 place-items-center rounded-md bg-primary-soft text-primary"
                >
                  <component :is="action[2]" :size="16" />
                </span>
                {{ translateKnownText(t, action[0] as string) }}
              </RouterLink>
            </li>
          </ul>
        </article>
      </section>
    </template>

    <template v-else-if="variant === 'analytics'">
      <section class="dashboard-panel grid grid-cols-2 gap-4 lg:grid-cols-4">
        <article
          v-for="item in [
            ['Total grantees', '2,486', 'primary'],
            ['Validated', '1,842', 'success'],
            ['Pending', '184', 'gold'],
            ['At risk', '32', 'danger'],
          ]"
          :key="item[0]"
          :class="`rounded-xl border border-l-4 border-l-${item[2]} bg-surface p-5`"
        >
          <p class="text-xs uppercase tracking-wide text-text-muted">
            {{ translateKnownText(t, item[0] as string) }}
          </p>
          <p class="mt-2 text-3xl font-semibold">{{ item[1] }}</p>
        </article>
      </section>
      <section class="dashboard-panel grid gap-4 lg:grid-cols-2">
        <article class="rounded-xl border bg-surface p-5">
          <h2 class="text-lg font-semibold">Throughput trend</h2>
          <p class="mt-1 text-xs text-text-muted">Line comparison · last 7 days</p>
          <svg
            class="mt-5 h-72 w-full overflow-visible"
            viewBox="0 0 760 220"
            preserveAspectRatio="none"
          >
            <g v-for="tick in [0, 45, 90, 135, 180]" :key="tick">
              <line
                x1="42"
                x2="748"
                :y1="192 - (tick / 180) * 182"
                :y2="192 - (tick / 180) * 182"
                stroke="var(--border)"
                stroke-dasharray="3 3"
              />
              <text
                x="32"
                :y="196 - (tick / 180) * 182"
                text-anchor="end"
                class="fill-text-muted"
                font-size="10"
              >
                {{ tick }}
              </text>
            </g>
            <path
              :d="smoothPath(submitted)"
              fill="none"
              stroke="var(--accent-gold)"
              stroke-width="2.5"
              class="chart-line chart-line-delay"
              pathLength="1"
            />
            <path
              :d="smoothPath(throughput)"
              fill="none"
              stroke="var(--primary)"
              stroke-width="2.5"
              class="chart-line"
              pathLength="1"
            />
            <g
              v-for="(point, index) in chartCoordinates(submitted)"
              :key="`s-${index}`"
              class="chart-area"
            >
              <circle
                :cx="point.x"
                :cy="point.y"
                r="3"
                fill="var(--surface)"
                stroke="var(--accent-gold)"
                stroke-width="2"
              />
            </g>
            <g
              v-for="(point, index) in chartCoordinates(throughput)"
              :key="`v-${index}`"
              class="chart-area"
            >
              <circle
                :cx="point.x"
                :cy="point.y"
                r="3"
                fill="var(--surface)"
                stroke="var(--primary)"
                stroke-width="2"
              />
            </g>
            <text
              v-for="(day, index) in days"
              :key="day"
              :x="42 + (index / 6) * 706"
              y="214"
              text-anchor="middle"
              class="fill-text-muted"
              font-size="10"
            >
              {{ day }}
            </text>
          </svg>
        </article>
        <article class="rounded-xl border bg-surface p-5">
          <h2 class="text-lg font-semibold">Status breakdown</h2>
          <p class="mt-1 text-xs text-text-muted">Radial view · 2,486 grantees</p>
          <div class="relative mx-auto mt-5 h-64 max-w-sm">
            <svg viewBox="0 0 260 260" class="h-full w-full -rotate-90">
              <g v-for="(item, index) in status" :key="item.label">
                <circle
                  cx="130"
                  cy="130"
                  :r="100 - index * 18"
                  fill="none"
                  stroke="var(--surface-muted)"
                  stroke-width="12"
                />
                <circle
                  cx="130"
                  cy="130"
                  :r="100 - index * 18"
                  fill="none"
                  :stroke="
                    item.label === 'Active'
                      ? 'var(--primary)'
                      : item.label === 'Pending'
                        ? 'var(--accent-gold)'
                        : item.label === 'Validated'
                          ? 'var(--success)'
                          : 'var(--danger)'
                  "
                  stroke-width="12"
                  stroke-linecap="round"
                  pathLength="100"
                  :stroke-dasharray="`${item.percent} 100`"
                  class="radial-ring"
                />
              </g>
            </svg>
            <div class="absolute inset-0 grid place-items-center text-center">
              <div>
                <p class="text-3xl font-semibold">2,486</p>
                <p class="text-xs text-text-muted">total</p>
              </div>
            </div>
          </div>
          <ul class="mt-2 grid grid-cols-2 gap-1.5">
            <li
              v-for="item in status"
              :key="item.label"
              class="flex items-center justify-between text-xs"
            >
              <span class="inline-flex items-center gap-2"
                ><i :class="['h-2 w-2 rounded-sm', item.color]" />{{ translateKnownText(t, item.label) }}</span
              ><span class="tabular-nums text-text-muted">{{ item.value.toLocaleString() }}</span>
            </li>
          </ul>
        </article>
      </section>
      <section class="dashboard-panel rounded-xl border bg-surface p-5">
        <h2 class="text-lg font-semibold">Grantees by batch</h2>
        <svg
          class="mt-5 h-64 w-full overflow-visible"
          viewBox="0 0 760 220"
          preserveAspectRatio="none"
        >
          <g v-for="tick in [0, 350, 700, 1050, 1400]" :key="tick">
            <line
              x1="42"
              x2="748"
              :y1="190 - (tick / 1400) * 175"
              :y2="190 - (tick / 1400) * 175"
              stroke="var(--border)"
              stroke-dasharray="3 3"
            />
            <text
              x="32"
              :y="194 - (tick / 1400) * 175"
              text-anchor="end"
              class="fill-text-muted"
              font-size="10"
            >
              {{ tick }}
            </text>
          </g>
          <g v-for="(batch, index) in batchDistribution" :key="batch[0]">
            <rect
              :x="78 + index * 112"
              :y="190 - (Number(batch[1]) / 1400) * 175"
              width="32"
              :height="(Number(batch[1]) / 1400) * 175"
              rx="6"
              fill="var(--accent-gold)"
              class="chart-bar"
            />
            <text
              :x="94 + index * 112"
              y="211"
              text-anchor="middle"
              class="fill-text-muted"
              font-size="9"
            >
              {{ batch[0] }}
            </text>
          </g>
        </svg>
      </section>
    </template>

    <template v-else>
      <section class="dashboard-panel grid gap-4 lg:grid-cols-3">
        <article class="rounded-xl border bg-surface p-4 lg:col-span-2">
          <h2 class="text-sm font-semibold uppercase text-text-muted">Metrics</h2>
          <dl class="mt-3 divide-y">
            <div v-for="row in compactRows" :key="row[0]" class="flex justify-between py-2.5">
              <dt class="text-sm">{{ translateKnownText(t, row[0]) }}</dt>
              <dd class="text-sm font-semibold tabular-nums">{{ row[1] }}</dd>
            </div>
          </dl>
          <svg class="mt-4 h-24 w-full" viewBox="0 0 760 220" preserveAspectRatio="none">
            <defs>
              <linearGradient id="compact-area" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="var(--primary)" stop-opacity=".25" />
                <stop offset="100%" stop-color="var(--primary)" stop-opacity="0" />
              </linearGradient>
            </defs>
            <path :d="areaPath(throughput)" fill="url(#compact-area)" class="chart-area" />
            <path
              :d="smoothPath(throughput)"
              fill="none"
              stroke="var(--primary)"
              stroke-width="3"
              pathLength="1"
              class="chart-line"
            />
          </svg>
        </article>
        <article class="rounded-xl border bg-surface p-4">
          <h2 class="text-sm font-semibold uppercase text-text-muted">Status</h2>
          <ul class="mt-3 space-y-3">
            <li v-for="item in status" :key="item.label">
              <div class="flex justify-between text-xs">
                <span>{{ translateKnownText(t, item.label) }}</span
                ><span class="text-text-muted"
                  >{{ item.value.toLocaleString() }} · {{ item.percent }}%</span
                >
              </div>
              <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-surface-muted">
                <div :class="['h-full', item.color]" :style="{ width: `${item.percent}%` }" />
              </div>
            </li>
          </ul>
          <h2 class="mt-6 text-sm font-semibold uppercase text-text-muted">Latest broadcast</h2>
          <p class="mt-3 text-sm font-medium">TES application deadline</p>
          <p class="mt-1 text-xs text-text-muted">
            Applications close on May 31. Complete pending requirements before the cut-off.
          </p>
          <RouterLink to="/app/announcements" class="mt-3 inline-block text-xs text-primary"
            >Manage announcements →</RouterLink
          >
        </article>
      </section>
    </template>
  </div>
</template>
