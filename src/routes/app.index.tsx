import { createFileRoute, Link } from "@tanstack/react-router";
import {
  IconBell, IconMail, IconCheck, IconCircle,
  IconClipboardList, IconFileCheck, IconShieldCheck, IconSpeakerphone,
  IconCircleCheck, IconCircleX, IconCalendarEvent, IconChartBar,
  IconArrowUpRight, IconArrowDownRight,
} from "@tabler/icons-react";
import {
  ResponsiveContainer, AreaChart, Area, XAxis, YAxis, Tooltip, CartesianGrid,
  PieChart, Pie, Cell, BarChart, Bar, LineChart, Line,
} from "recharts";
import { StatusBadge, statusVariantFor, formatStatus } from "@/components/ui/status-badge";
import { useGrantees, useBatches, useAnnouncements } from "@/hooks/queries";
import { useAuthStore } from "@/stores/authStore";
import { cn } from "@/lib/utils";

export const Route = createFileRoute("/app/")({
  component: Dashboard,
});

const PIPELINE: { key: string; label: string; done: boolean; current?: boolean }[] = [
  { key: "intake", label: "Intake", done: true },
  { key: "validation", label: "Validation", done: true, current: true },
  { key: "eligibility", label: "Eligibility", done: false },
  { key: "batching", label: "Batching", done: false },
  { key: "release", label: "Release", done: false },
];

const THROUGHPUT = [
  { d: "Mon", validated: 82, submitted: 120 },
  { d: "Tue", validated: 104, submitted: 138 },
  { d: "Wed", validated: 126, submitted: 152 },
  { d: "Thu", validated: 118, submitted: 141 },
  { d: "Fri", validated: 158, submitted: 176 },
  { d: "Sat", validated: 96, submitted: 108 },
  { d: "Sun", validated: 112, submitted: 132 },
];

const REGIONS = [
  { name: "NCR", value: 428 },
  { name: "Region IV-A", value: 362 },
  { name: "Region III", value: 298 },
  { name: "Region VII", value: 241 },
  { name: "Region VI", value: 187 },
  { name: "Region XI", value: 154 },
];

const SPARK_APPROVAL = [58, 61, 60, 63, 66, 65, 68].map((v, i) => ({ i, v }));
const SPARK_TIME = [3.1, 2.9, 2.8, 2.7, 2.6, 2.5, 2.4].map((v, i) => ({ i, v }));
const SPARK_BATCHES = [4, 5, 5, 6, 7, 7, 8].map((v, i) => ({ i, v }));

function Dashboard() {
  const profile = useAuthStore((s) => s.profile);
  useAuthStore((s) => s.email);
  const { data: g = [] } = useGrantees();
  const { data: batches = [] } = useBatches();
  const { data: announcements = [] } = useAnnouncements();

  const firstName = (profile?.full_name ?? "").split(" ")[0] || "Admin";
  const today = new Date().toLocaleDateString(undefined, { month: "long", day: "numeric", year: "numeric" });

  const stats = {
    total: g.length,
    active: g.filter((x) => x.accountStatus === "active").length,
    pending: g.filter((x) => x.submissionStatus === "submitted" || x.submissionStatus === "under_review").length,
    validated: g.filter((x) => x.submissionStatus === "approved").length,
    eligible: g.filter((x) => x.eligibility === "eligible").length,
    risk: g.filter((x) => x.risk === "high").length,
  };
  const pipelinePct = 62;

  const activeBatches = batches.slice(0, 2);
  const publishedAnns = announcements.filter((a) => a.status === "published").slice(0, 3);

  const donutData = [
    { name: "Active", value: stats.active || 1, color: "var(--color-primary)" },
    { name: "Pending", value: stats.pending || 1, color: "var(--color-gold)" },
    { name: "Validated", value: stats.validated || 1, color: "var(--color-success)" },
    { name: "At risk", value: stats.risk || 1, color: "var(--color-danger)" },
  ];
  const donutTotal = donutData.reduce((s, x) => s + x.value, 0);

  const validation = [
    { label: "PSA Birth Certificate", state: "Approved" as const },
    { label: "Certificate of Enrollment", state: "Approved" as const },
    { label: "Grades (Transcript)", state: "Pending" as const },
    { label: "Income Tax Return", state: "Pending" as const },
    { label: "2x2 ID Picture", state: "Flagged" as const },
  ];
  const valDone = validation.filter((v) => v.state === "Approved").length;
  const valTotal = validation.length;
  const valPct = Math.round((valDone / valTotal) * 100);

  const upcoming = [
    { m: "MAY", d: "15", title: "Batch Cut-off", meta: "AY 2024–2025 · Semester 2" },
    { m: "MAY", d: "31", title: "Eligibility Review", meta: "Committee session · 10:00 AM" },
    { m: "JUN", d: "15", title: "Release Schedule", meta: "Subsidy disbursement window opens" },
  ];

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p className="text-xs font-medium uppercase tracking-[0.14em] text-text-muted">Operations · {today}</p>
          <h1 className="mt-1 text-3xl font-semibold tracking-tight text-primary">
            Good day, {firstName}.{" "}
            <span className="text-text font-normal">Here's your operations overview.</span>
          </h1>
        </div>
        <div className="flex items-center gap-2">
          <button aria-label="Notifications" className="p-2 rounded-md hover:bg-surface-muted text-text-muted">
            <IconBell size={18} />
          </button>
          <button aria-label="Messages" className="relative p-2 rounded-md hover:bg-surface-muted text-text-muted">
            <IconMail size={18} />
            <span className="absolute -top-0.5 -right-0.5 h-4 min-w-4 px-1 rounded-full bg-primary text-primary-foreground text-2xs font-semibold grid place-items-center">3</span>
          </button>
        </div>

      </div>

      {/* KPI strip with sparklines */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <KpiCard label="Total grantees" value={stats.total.toLocaleString()} delta="+4.2%" up spark={SPARK_APPROVAL} />
        <KpiCard label="Auto-approval rate" value="68%" delta="+5.0%" up spark={SPARK_APPROVAL} />
        <KpiCard label="Avg validation" value="2.4h" delta="-0.3h" up spark={SPARK_TIME} tone="gold" />
        <KpiCard label="Active batches" value={String(activeBatches.length || 8)} delta="+2" up spark={SPARK_BATCHES} />
      </div>

      {/* Hero chart + Donut */}
      <div className="grid grid-cols-1 lg:grid-cols-[minmax(0,2.2fr)_minmax(0,1fr)] gap-4">
        <section className="rounded-xl border bg-surface p-5 sm:p-6">
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h2 className="text-lg font-semibold tracking-tight">Validation throughput</h2>
              <p className="mt-1 text-xs text-text-muted">Submitted vs validated · last 7 days</p>
            </div>
            <div className="flex items-center gap-3 text-xs">
              <Legend swatch="var(--color-primary)" label="Validated" />
              <Legend swatch="var(--color-gold)" label="Submitted" />
            </div>
          </div>
          <div className="mt-5 h-64">
            <ResponsiveContainer width="100%" height="100%">
              <AreaChart data={THROUGHPUT} margin={{ top: 8, right: 8, left: -12, bottom: 0 }}>
                <defs>
                  <linearGradient id="gPri" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stopColor="var(--color-primary)" stopOpacity={0.35} />
                    <stop offset="100%" stopColor="var(--color-primary)" stopOpacity={0} />
                  </linearGradient>
                  <linearGradient id="gGold" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stopColor="var(--color-gold)" stopOpacity={0.28} />
                    <stop offset="100%" stopColor="var(--color-gold)" stopOpacity={0} />
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" stroke="var(--color-border)" vertical={false} />
                <XAxis dataKey="d" stroke="var(--color-text-muted)" fontSize={11} tickLine={false} axisLine={false} />
                <YAxis stroke="var(--color-text-muted)" fontSize={11} tickLine={false} axisLine={false} />
                <Tooltip
                  contentStyle={{
                    background: "var(--color-surface)",
                    border: "1px solid var(--color-border)",
                    borderRadius: 8,
                    fontSize: 12,
                  }}
                />
                <Area type="monotone" dataKey="submitted" stroke="var(--color-gold)" strokeWidth={2} fill="url(#gGold)" />
                <Area type="monotone" dataKey="validated" stroke="var(--color-primary)" strokeWidth={2} fill="url(#gPri)" />
              </AreaChart>
            </ResponsiveContainer>
          </div>
        </section>

        <section className="rounded-xl border bg-surface p-5">
          <div className="flex items-center justify-between">
            <h2 className="text-lg font-semibold tracking-tight">Grantee status</h2>
            <Link to="/app/grantees" className="text-xs text-primary hover:underline">Details</Link>
          </div>
          <div className="mt-2 relative h-48">
            <ResponsiveContainer width="100%" height="100%">
              <PieChart>
                <Pie data={donutData} dataKey="value" innerRadius={56} outerRadius={80} paddingAngle={2} stroke="none">
                  {donutData.map((d) => <Cell key={d.name} fill={d.color} />)}
                </Pie>
                <Tooltip
                  contentStyle={{
                    background: "var(--color-surface)",
                    border: "1px solid var(--color-border)",
                    borderRadius: 8,
                    fontSize: 12,
                  }}
                />
              </PieChart>
            </ResponsiveContainer>
            <div className="absolute inset-0 grid place-items-center pointer-events-none">
              <div className="text-center">
                <p className="text-2xl font-semibold tabular-nums">{donutTotal.toLocaleString()}</p>
                <p className="text-xs text-text-muted">grantees</p>
              </div>
            </div>
          </div>
          <ul className="mt-3 space-y-1.5">
            {donutData.map((d) => (
              <li key={d.name} className="flex items-center justify-between text-xs">
                <span className="inline-flex items-center gap-2">
                  <span className="h-2 w-2 rounded-sm" style={{ background: d.color }} />
                  <span className="text-text">{d.name}</span>
                </span>
                <span className="tabular-nums text-text-muted">{d.value.toLocaleString()}</span>
              </li>
            ))}
          </ul>
        </section>
      </div>

      {/* Pipeline + Quick Actions */}
      <div className="grid grid-cols-1 lg:grid-cols-[minmax(0,2.4fr)_minmax(0,1fr)] gap-4">
        <section className="rounded-xl border bg-surface p-5 sm:p-6">
          <div className="flex items-center justify-between">
            <div>
              <h2 className="text-lg font-semibold tracking-tight">Pipeline progress</h2>
              <p className="mt-1 text-xs text-text-muted">Validation is on track — 3 stages remaining this cycle.</p>
            </div>
            <span className="text-2xl font-semibold text-gold tabular-nums">{pipelinePct}%</span>
          </div>
          <div className="mt-4 h-1.5 rounded-full bg-primary-soft overflow-hidden">
            <div className="h-full bg-primary transition-all" style={{ width: `${pipelinePct}%` }} />
          </div>
          <ol className="mt-6 grid grid-cols-5 gap-2">
            {PIPELINE.map((step, i) => (
              <li key={step.key} className="flex flex-col items-center gap-2 text-center">
                <div className={cn(
                  "h-8 w-8 rounded-full grid place-items-center text-xs font-semibold",
                  step.done ? "bg-primary text-primary-foreground" : "border border-border-strong text-text-soft bg-surface",
                )}>
                  {step.done ? <IconCheck size={14} /> : i + 1}
                </div>
                <span className={cn(
                  "text-xs",
                  step.current ? "text-primary font-semibold" : step.done ? "text-text" : "text-text-muted",
                )}>{step.label}</span>
              </li>
            ))}
          </ol>
        </section>

        <section className="rounded-xl border bg-surface p-5">
          <h2 className="text-lg font-semibold tracking-tight">Quick actions</h2>
          <ul className="mt-4 space-y-1">
            <QuickAction icon={<IconFileCheck size={16} />} label="Review Submissions" to="/app/documents" />
            <QuickAction icon={<IconCircleCheck size={16} />} label="Run Eligibility" to="/app/eligibility" />
            <QuickAction icon={<IconChartBar size={16} />} label="Manage Batches" to="/app/batches" />
            <QuickAction icon={<IconSpeakerphone size={16} />} label="New Announcement" to="/app/announcements/new" />
          </ul>
        </section>
      </div>

      {/* Regions bar chart + Validation queue + Upcoming */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <section className="rounded-xl border bg-surface p-5">
          <div className="flex items-center justify-between">
            <h2 className="text-lg font-semibold tracking-tight">Grantees by region</h2>
            <Link to="/app/grantees" className="text-xs text-primary hover:underline">View all</Link>
          </div>
          <div className="mt-4 h-56">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={REGIONS} layout="vertical" margin={{ top: 4, right: 8, left: 0, bottom: 0 }}>
                <CartesianGrid strokeDasharray="3 3" stroke="var(--color-border)" horizontal={false} />
                <XAxis type="number" stroke="var(--color-text-muted)" fontSize={11} tickLine={false} axisLine={false} />
                <YAxis type="category" dataKey="name" stroke="var(--color-text-muted)" fontSize={11} tickLine={false} axisLine={false} width={90} />
                <Tooltip
                  cursor={{ fill: "var(--color-primary-soft)" }}
                  contentStyle={{
                    background: "var(--color-surface)",
                    border: "1px solid var(--color-border)",
                    borderRadius: 8,
                    fontSize: 12,
                  }}
                />
                <Bar dataKey="value" fill="var(--color-primary)" radius={[0, 4, 4, 0]} barSize={14} />
              </BarChart>
            </ResponsiveContainer>
          </div>
          <div className="mt-3 grid grid-cols-2 gap-2">
            <SnapshotStat label="Active" value={stats.active} tone="success" icon={<IconCircleCheck size={14} />} />
            <SnapshotStat label="Risk-flagged" value={stats.risk} tone="danger" icon={<IconShieldCheck size={14} />} />
          </div>
        </section>

        <section className="rounded-xl border bg-surface p-5">
          <div className="flex items-center justify-between">
            <h2 className="text-lg font-semibold tracking-tight">Validation queue</h2>
            <Link to="/app/documents" className="text-xs text-primary hover:underline">View all</Link>
          </div>
          <div className="mt-3 h-2 rounded-full bg-primary-soft overflow-hidden">
            <div className="h-full bg-primary" style={{ width: `${valPct}%` }} />
          </div>
          <p className="mt-1.5 text-xs text-text-muted">{valDone} of {valTotal} document types cleared</p>
          <ul className="mt-3 divide-y">
            {validation.map((v) => {
              const ok = v.state === "Approved";
              const toneCls = v.state === "Approved" ? "text-success"
                : v.state === "Pending" ? "text-warning"
                : "text-danger";
              return (
                <li key={v.label} className="flex items-center justify-between py-2.5 text-sm">
                  <span className="inline-flex items-center gap-2">
                    {ok
                      ? <IconCheck size={16} className="text-success" />
                      : v.state === "Flagged"
                        ? <IconCircleX size={16} className="text-danger" />
                        : <IconCircle size={16} className="text-text-soft" />}
                    <span>{v.label}</span>
                  </span>
                  <span className={cn("text-xs font-medium", toneCls)}>{v.state}</span>
                </li>
              );
            })}
          </ul>
          <div className="mt-4 space-y-2">
            {activeBatches.map((b) => {
              const pct = Math.round((b.validated / b.totalGrantees) * 100);
              return (
                <div key={b.id} className="relative rounded-md border pl-4 pr-3 py-2.5">
                  <span className="absolute left-0 top-2 bottom-2 w-1 rounded-r bg-primary" />
                  <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0">
                      <p className="text-sm font-semibold truncate">{b.name}</p>
                      <p className="text-xs text-text-muted mt-0.5 tabular-nums">
                        {b.validated.toLocaleString()} / {b.totalGrantees.toLocaleString()} validated
                      </p>
                    </div>
                    <StatusBadge variant={statusVariantFor(b.status)}>{formatStatus(b.status)}</StatusBadge>
                  </div>
                  <div className="mt-2 h-1 rounded-full bg-primary-soft overflow-hidden">
                    <div className="h-full bg-primary" style={{ width: `${pct}%` }} />
                  </div>
                </div>
              );
            })}
          </div>
        </section>

        <section className="rounded-xl border bg-surface p-5">
          <div className="flex items-center justify-between">
            <h2 className="text-lg font-semibold tracking-tight">Upcoming</h2>
            <Link to="/app/audit" className="text-xs text-primary hover:underline">View calendar</Link>
          </div>
          <ul className="mt-4 space-y-4">
            {upcoming.map((e) => (
              <li key={e.title} className="flex gap-3">
                <div className="shrink-0 w-11 text-center">
                  <p className="text-2xs font-semibold uppercase tracking-wide text-primary">{e.m}</p>
                  <p className="text-xl font-semibold leading-none tabular-nums">{e.d}</p>
                </div>
                <div className="min-w-0">
                  <p className="text-sm font-semibold truncate">{e.title}</p>
                  <p className="text-xs text-text-muted mt-0.5">{e.meta}</p>
                </div>
              </li>
            ))}
          </ul>
        </section>
      </div>

      {/* Priority batch / Ops snapshot / Broadcasts */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <section className="rounded-xl border bg-gold-soft p-5 relative overflow-hidden">
          <h2 className="text-lg font-semibold tracking-tight">Priority batch</h2>
          <p className="mt-3 text-sm font-semibold">{activeBatches[0]?.name ?? "TES Batch — Semester 2"}</p>
          <p className="text-xs text-text-muted">Cut-off in 5 days · needs eligibility sign-off</p>
          <Link to="/app/batches" className="mt-5 inline-flex items-center rounded-md bg-gold hover:bg-gold-hover px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors">
            Open batch
          </Link>
        </section>

        <section className="rounded-xl border bg-surface p-5">
          <h2 className="text-lg font-semibold tracking-tight inline-flex items-center gap-2">
            <IconChartBar size={18} className="text-primary" /> Operations snapshot
          </h2>
          <div className="mt-5 grid grid-cols-2 gap-4">
            <div>
              <p className="text-xs text-text-muted">Auto-approval rate</p>
              <p className="mt-1 text-3xl font-semibold tabular-nums">68%</p>
              <span className="mt-2 inline-block text-2xs font-semibold px-2 py-1 rounded-full bg-success-soft text-success">↑ 5% wow</span>
            </div>
            <div>
              <p className="text-xs text-text-muted">Avg validation</p>
              <p className="mt-1 text-3xl font-semibold tabular-nums">2.4<span className="text-text-muted text-base"> hrs</span></p>
              <span className="mt-2 inline-block text-2xs font-semibold px-2 py-1 rounded-full bg-primary-soft text-primary">On target</span>
            </div>
          </div>
        </section>

        <section className="rounded-xl border bg-gold-soft p-5 relative overflow-hidden">
          <h2 className="text-lg font-semibold tracking-tight inline-flex items-center gap-2">
            <IconSpeakerphone size={18} className="text-primary" /> Broadcasts
          </h2>
          {publishedAnns[0] ? (
            <>
              <p className="mt-3 text-sm font-semibold line-clamp-1">{publishedAnns[0].title}</p>
              <p className="mt-1 text-xs text-text-muted line-clamp-2 max-w-[32ch]">{publishedAnns[0].body}</p>
            </>
          ) : (
            <p className="mt-3 text-sm text-text-muted max-w-[28ch]">No published announcements yet.</p>
          )}
          <Link
            to="/app/announcements"
            className="mt-5 inline-flex items-center rounded-md bg-gold hover:bg-gold-hover px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors"
          >
            Manage announcements
          </Link>
          <IconCalendarEvent size={80} className="absolute -right-3 -bottom-3 text-primary/10" aria-hidden />
        </section>
      </div>
    </div>
  );
}

function Legend({ swatch, label }: { swatch: string; label: string }) {
  return (
    <span className="inline-flex items-center gap-1.5 text-text-muted">
      <span className="h-2 w-2 rounded-sm" style={{ background: swatch }} />
      {label}
    </span>
  );
}

function KpiCard({
  label, value, delta, up, spark, tone = "primary",
}: {
  label: string;
  value: string;
  delta: string;
  up: boolean;
  spark: { i: number; v: number }[];
  tone?: "primary" | "gold";
}) {
  const stroke = tone === "gold" ? "var(--color-gold)" : "var(--color-primary)";
  return (
    <div className="rounded-xl border bg-surface p-4">
      <div className="flex items-start justify-between gap-2">
        <p className="text-xs text-text-muted">{label}</p>
        <span className={cn(
          "inline-flex items-center gap-0.5 text-2xs font-semibold px-1.5 py-0.5 rounded-full",
          up ? "bg-success-soft text-success" : "bg-danger-soft text-danger",
        )}>
          {up ? <IconArrowUpRight size={11} /> : <IconArrowDownRight size={11} />}
          {delta}
        </span>
      </div>
      <div className="mt-1.5 flex items-end justify-between gap-2">
        <p className="text-2xl font-semibold tabular-nums">{value}</p>
        <div className="h-10 w-24">
          <ResponsiveContainer width="100%" height="100%">
            <LineChart data={spark}>
              <Line type="monotone" dataKey="v" stroke={stroke} strokeWidth={2} dot={false} />
            </LineChart>
          </ResponsiveContainer>
        </div>
      </div>
    </div>
  );
}

function QuickAction({ icon, label, to }: { icon: React.ReactNode; label: string; to: string }) {
  return (
    <li>
      <Link to={to} className="flex items-center gap-3 rounded-md px-2 py-2 text-sm hover:bg-surface-muted transition-colors">
        <span className="h-7 w-7 rounded-md bg-primary-soft text-primary grid place-items-center shrink-0">{icon}</span>
        <span>{label}</span>
      </Link>
    </li>
  );
}

function SnapshotStat({
  label, value, icon, tone = "primary",
}: {
  label: string;
  value: number;
  icon: React.ReactNode;
  tone?: "primary" | "success" | "warning" | "danger";
}) {
  const toneMap = {
    primary: "bg-primary-soft text-primary",
    success: "bg-success-soft text-success",
    warning: "bg-warning-soft text-warning",
    danger: "bg-danger-soft text-danger",
  } as const;
  return (
    <div className="rounded-md border p-3">
      <div className="flex items-center gap-2">
        <span className={cn("h-6 w-6 rounded grid place-items-center shrink-0", toneMap[tone])}>{icon}</span>
        <p className="text-xs text-text-muted">{label}</p>
      </div>
      <p className="mt-2 text-xl font-semibold tabular-nums">{value.toLocaleString()}</p>
    </div>
  );
}
