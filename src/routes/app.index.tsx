import { createFileRoute, Link } from "@tanstack/react-router";
import {
  IconBell, IconMail, IconChevronDown, IconCheck, IconCircle,
  IconClipboardList, IconFileCheck, IconShieldCheck, IconSpeakerphone,
  IconCircleCheck, IconCircleX, IconCalendarEvent, IconChartBar,
} from "@tabler/icons-react";
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

function Dashboard() {
  const profile = useAuthStore((s) => s.profile);
  const email = useAuthStore((s) => s.email);
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
          <h1 className="text-3xl font-semibold tracking-tight text-primary">
            Hi, {firstName}! <span aria-hidden>👋</span>{" "}
            <span className="text-text font-normal">Here's your operations overview.</span>
          </h1>
          <p className="mt-1 text-sm text-text-muted">TES grantee pipeline for the current academic period.</p>
        </div>
        <div className="flex items-center gap-4">
          <p className="text-sm text-text-muted hidden sm:block">Today is {today}</p>
          <button aria-label="Notifications" className="p-2 rounded-md hover:bg-surface-muted text-text-muted">
            <IconBell size={18} />
          </button>
          <button aria-label="Messages" className="relative p-2 rounded-md hover:bg-surface-muted text-text-muted">
            <IconMail size={18} />
            <span className="absolute -top-0.5 -right-0.5 h-4 min-w-4 px-1 rounded-full bg-primary text-primary-foreground text-2xs font-semibold grid place-items-center">3</span>
          </button>
          <div className="flex items-center gap-2 pl-3 border-l">
            <div className="h-9 w-9 rounded-full bg-primary-soft text-primary grid place-items-center font-semibold">
              {firstName.slice(0, 1)}
            </div>
            <div className="text-sm leading-tight">
              <p className="font-medium">{profile?.full_name || email || "Admin"}</p>
              <p className="text-xs text-text-muted">Administrator</p>
            </div>
            <IconChevronDown size={14} className="text-text-soft" />
          </div>
        </div>
      </div>

      {/* Pipeline + Quick Actions */}
      <div className="grid grid-cols-1 lg:grid-cols-[minmax(0,2.4fr)_minmax(0,1fr)] gap-4">
        <section className="rounded-xl border bg-surface p-5 sm:p-6">
          <h2 className="text-lg font-semibold tracking-tight">Pipeline Progress</h2>
          <p className="mt-1 text-xs text-text-muted">Validation is on track — 3 stages remaining this cycle.</p>
          <div className="mt-4 flex items-center gap-3">
            <div className="flex-1 h-2.5 rounded-full bg-primary-soft overflow-hidden">
              <div className="h-full bg-primary transition-all" style={{ width: `${pipelinePct}%` }} />
            </div>
            <span className="text-lg font-semibold text-gold tabular-nums">{pipelinePct}%</span>
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
          <h2 className="text-lg font-semibold tracking-tight">Quick Actions</h2>
          <ul className="mt-4 space-y-1">
            <QuickAction icon={<IconFileCheck size={16} />} label="Review Submissions" to="/app/documents" />
            <QuickAction icon={<IconCircleCheck size={16} />} label="Run Eligibility" to="/app/eligibility" />
            <QuickAction icon={<IconChartBar size={16} />} label="Manage Batches" to="/app/batches" />
            <QuickAction icon={<IconSpeakerphone size={16} />} label="New Announcement" to="/app/announcements/new" />
          </ul>
        </section>
      </div>

      {/* Grantees / Validation / Upcoming */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <section className="rounded-xl border bg-surface p-5">
          <div className="flex items-center justify-between">
            <h2 className="text-lg font-semibold tracking-tight">Grantees Snapshot</h2>
            <Link to="/app/grantees" className="text-xs text-primary hover:underline">View all</Link>
          </div>
          <div className="mt-4 grid grid-cols-2 gap-3">
            <SnapshotStat label="Total" value={stats.total} icon={<IconClipboardList size={14} />} />
            <SnapshotStat label="Active" value={stats.active} tone="success" icon={<IconCircleCheck size={14} />} />
            <SnapshotStat label="Pending" value={stats.pending} tone="warning" icon={<IconClipboardList size={14} />} />
            <SnapshotStat label="Risk-flagged" value={stats.risk} tone="danger" icon={<IconShieldCheck size={14} />} />
          </div>
          <div className="mt-4 space-y-3">
            {activeBatches.map((b) => {
              const pct = Math.round((b.validated / b.totalGrantees) * 100);
              return (
                <div key={b.id} className="relative rounded-md border pl-4 pr-3 py-3">
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
                  <div className="mt-2 h-1.5 rounded-full bg-primary-soft overflow-hidden">
                    <div className="h-full bg-primary" style={{ width: `${pct}%` }} />
                  </div>
                </div>
              );
            })}
          </div>
        </section>

        <section className="rounded-xl border bg-surface p-5">
          <div className="flex items-center justify-between">
            <h2 className="text-lg font-semibold tracking-tight">Validation Queue</h2>
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

      {/* Bottom: Priority batch / Operations snapshot / Broadcast */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <section className="rounded-xl border bg-gold-soft p-5 relative overflow-hidden">
          <h2 className="text-lg font-semibold tracking-tight">Priority Batch</h2>
          <p className="mt-3 text-sm font-semibold">{activeBatches[0]?.name ?? "TES Batch — Semester 2"}</p>
          <p className="text-xs text-text-muted">Cut-off in 5 days · needs eligibility sign-off</p>
          <Link to="/app/batches" className="mt-5 inline-flex items-center rounded-md bg-gold hover:bg-gold-hover px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors">
            Open Batch
          </Link>
        </section>

        <section className="rounded-xl border bg-surface p-5">
          <h2 className="text-lg font-semibold tracking-tight inline-flex items-center gap-2">
            <IconChartBar size={18} className="text-primary" /> Operations Snapshot
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
            Manage Announcements
          </Link>
          <IconCalendarEvent size={80} className="absolute -right-3 -bottom-3 text-primary/10" aria-hidden />
        </section>
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
