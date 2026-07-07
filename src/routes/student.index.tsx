import { createFileRoute, Link } from "@tanstack/react-router";
import {
  IconBell, IconMail, IconChevronDown, IconCheck, IconCircle,
  IconFilePlus, IconUpload, IconListCheck, IconBook,
  IconSpeakerphone, IconSchool, IconCalendarEvent,
} from "@tabler/icons-react";
import { requiredDocs } from "@/data/mockDocuments";
import { useDocuments } from "@/hooks/queries";
import { useAuthStore } from "@/stores/authStore";
import { cn } from "@/lib/utils";

export const Route = createFileRoute("/student/")({
  component: StudentHome,
});

const JOURNEY: { key: string; label: string; done: boolean; current?: boolean }[] = [
  { key: "explore", label: "Explore", done: true },
  { key: "requirements", label: "Requirements", done: true, current: true },
  { key: "review", label: "Review", done: false },
  { key: "interview", label: "Interview", done: false },
  { key: "decision", label: "Decision", done: false },
];

function StudentHome() {
  const profile = useAuthStore((s) => s.profile);
  const email = useAuthStore((s) => s.email);
  const { data: myDocs = [] } = useDocuments({ ownerOnly: true });

  const firstName = (profile?.full_name ?? "").split(" ")[0] || "Grantee";
  const today = new Date().toLocaleDateString(undefined, { month: "long", day: "numeric", year: "numeric" });

  const completed = myDocs.filter((d) => requiredDocs.includes(d.type) && (d.status === "approved" || d.status === "pending")).length;
  const totalReq = requiredDocs.length;
  const docProgress = Math.round((completed / totalReq) * 100);
  const journeyPct = 75;

  const applications = [
    { id: 1, title: "TCC Academic Excellence Scholarship", cycle: "AY 2024–2025", updated: "May 10, 2025", status: "Pending Review", tone: "warning" as const },
    { id: 2, title: "TCC Leadership Grant", cycle: "AY 2025–2026", updated: "May 8, 2025", status: "Draft", tone: "info" as const },
  ];

  const docChecklist = [
    { label: "PSA Birth Certificate", state: "Completed" as const },
    { label: "Certificate of Enrollment", state: "Completed" as const },
    { label: "Grades (Transcript)", state: "Completed" as const },
    { label: "Income Tax Return", state: "Uploaded" as const },
    { label: "2x2 ID Picture", state: "Missing" as const },
  ];

  const upcoming = [
    { m: "MAY", d: "15", title: "Scholarship Orientation", meta: "8:00 AM – 12:00 PM · AVR, TCC Main Campus" },
    { m: "MAY", d: "31", title: "Application Deadline", meta: "Academic Excellence Scholarship" },
    { m: "JUN", d: "15", title: "Interview Schedule", meta: "TCC Leadership Grant" },
  ];

  return (
    <div className="space-y-8">
      {/* Header row */}
      <header className="flex flex-wrap items-end justify-between gap-4 pb-5 border-b">
        <div className="min-w-0">
          <p className="text-2xs uppercase tracking-[0.14em] text-text-soft">Student Dashboard</p>
          <h1 className="mt-1 text-2xl sm:text-3xl font-semibold tracking-tight text-text">
            Good to see you, <span className="text-primary">{firstName}</span>.
          </h1>
          <p className="mt-1.5 text-sm text-text-muted">Here's your scholarship journey overview.</p>
        </div>
        <div className="flex items-center gap-2">
          <p className="text-xs text-text-muted hidden md:block pr-2 border-r">{today}</p>
          <button aria-label="Notifications" className="p-2 rounded-md hover:bg-surface-muted text-text-muted">
            <IconBell size={18} />
          </button>
          <button aria-label="Messages" className="relative p-2 rounded-md hover:bg-surface-muted text-text-muted">
            <IconMail size={18} />
            <span className="absolute top-1 right-1 h-4 min-w-4 px-1 rounded-full bg-primary text-primary-foreground text-2xs font-semibold grid place-items-center">2</span>
          </button>
          <div className="flex items-center gap-2 pl-3 ml-1 border-l">
            <div className="h-9 w-9 rounded-full bg-primary-soft text-primary grid place-items-center font-semibold">
              {firstName.slice(0, 1)}
            </div>
            <div className="text-sm leading-tight">
              <p className="font-medium">{profile?.full_name || email || "Student"}</p>
              <p className="text-xs text-text-muted">Student</p>
            </div>
            <IconChevronDown size={14} className="text-text-soft" />
          </div>
        </div>
      </header>

      {/* Journey + Quick Actions */}
      <section aria-labelledby="focus-heading" className="space-y-3">
        <div className="flex items-baseline justify-between">
          <h2 id="focus-heading" className="text-2xs uppercase tracking-[0.14em] text-text-soft font-semibold">Today's Focus</h2>
          <span className="text-xs text-text-muted">Updated just now</span>
        </div>
        <div className="grid grid-cols-1 lg:grid-cols-[minmax(0,2.4fr)_minmax(0,1fr)] gap-4">
        <section className="rounded-xl border bg-surface p-6 sm:p-7 shadow-xs">
          <div className="flex items-baseline justify-between">
            <h3 className="text-lg font-semibold tracking-tight">Your Journey Progress</h3>
            <span className="text-xs text-text-muted">Stage 2 of 5</span>
          </div>
          <p className="mt-1 text-xs text-text-muted">Keep going — you're on the right track.</p>

          <div className="mt-5 flex items-center gap-3">
            <div className="flex-1 h-2 rounded-full bg-primary-soft overflow-hidden">
              <div className="h-full bg-primary transition-all" style={{ width: `${journeyPct}%` }} />
            </div>
            <span className="text-lg font-semibold text-gold tabular-nums">{journeyPct}%</span>
          </div>

          <ol className="mt-6 grid grid-cols-5 gap-2">
            {JOURNEY.map((step, i) => {
              const done = step.done;
              return (
                <li key={step.key} className="flex flex-col items-center gap-2 text-center">
                  <div className={cn(
                    "h-8 w-8 rounded-full grid place-items-center text-xs font-semibold",
                    done ? "bg-primary text-primary-foreground" : "border border-border-strong text-text-soft bg-surface",
                  )}>
                    {done ? <IconCheck size={14} /> : i + 1}
                  </div>
                  <span className={cn(
                    "text-xs",
                    step.current ? "text-primary font-semibold" : done ? "text-text" : "text-text-muted",
                  )}>
                    {step.label}
                  </span>
                </li>
              );
            })}
          </ol>
        </section>

        <section className="rounded-xl border bg-surface p-6 shadow-xs">
          <h3 className="text-lg font-semibold tracking-tight">Quick Actions</h3>
          <p className="mt-1 text-xs text-text-muted">Shortcuts to common tasks.</p>
          <ul className="mt-4 space-y-1">
            <QuickAction icon={<IconFilePlus size={16} />} label="Start New Application" to="/student/submissions" />
            <QuickAction icon={<IconUpload size={16} />} label="Upload Document" to="/student/upload" />
            <QuickAction icon={<IconListCheck size={16} />} label="Check Requirements" to="/student/documents" />
            <QuickAction icon={<IconBook size={16} />} label="View Scholarship Programs" to="/student/submissions" />
          </ul>
        </section>
        </div>
      </section>

      {/* Applications + Documents + Upcoming */}
      <section aria-labelledby="track-heading" className="space-y-3">
        <h2 id="track-heading" className="text-2xs uppercase tracking-[0.14em] text-text-soft font-semibold">Track & Manage</h2>
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <section className="rounded-xl border bg-surface p-6 shadow-xs">
          <div className="flex items-center justify-between">
            <h3 className="text-lg font-semibold tracking-tight">My Applications</h3>
            <Link to="/student/submissions" className="text-xs text-primary hover:underline">View all</Link>
          </div>
          <ul className="mt-4 space-y-3">
            {applications.map((a) => (
              <li key={a.id} className="relative rounded-md border bg-surface pl-4 pr-3 py-3">
                <span className="absolute left-0 top-2 bottom-2 w-1 rounded-r bg-primary" />
                <div className="flex items-start justify-between gap-2">
                  <div className="min-w-0">
                    <p className="text-sm font-semibold truncate">{a.title}</p>
                    <p className="text-xs text-text-muted mt-0.5">{a.cycle}</p>
                    <p className="text-xs text-text-muted mt-1">Updated: {a.updated}</p>
                  </div>
                  <span className={cn(
                    "shrink-0 text-2xs font-semibold px-2 py-1 rounded-full",
                    a.tone === "warning" ? "bg-warning-soft text-warning" : "bg-info-soft text-info",
                  )}>{a.status}</span>
                </div>
              </li>
            ))}
          </ul>
        </section>

        <section className="rounded-xl border bg-surface p-6 shadow-xs">
          <div className="flex items-center justify-between">
            <h3 className="text-lg font-semibold tracking-tight">Required Documents</h3>
            <Link to="/student/documents" className="text-xs text-primary hover:underline">View all</Link>
          </div>
          <div className="mt-3 h-2 rounded-full bg-primary-soft overflow-hidden">
            <div className="h-full bg-primary" style={{ width: `${docProgress}%` }} />
          </div>
          <p className="mt-1.5 text-xs text-text-muted">{completed || 4} of {totalReq || 5} completed</p>
          <ul className="mt-3 divide-y">
            {docChecklist.map((it) => {
              const ok = it.state !== "Missing";
              const toneCls = it.state === "Completed" ? "text-success"
                : it.state === "Uploaded" ? "text-info"
                : "text-danger";
              return (
                <li key={it.label} className="flex items-center justify-between py-2.5 text-sm">
                  <span className="inline-flex items-center gap-2">
                    {ok
                      ? <IconCheck size={16} className="text-success" />
                      : <IconCircle size={16} className="text-text-soft" />}
                    <span>{it.label}</span>
                  </span>
                  <span className={cn("text-xs font-medium", toneCls)}>{it.state}</span>
                </li>
              );
            })}
          </ul>
          <div className="mt-3 flex items-center justify-between text-xs">
            <span className="text-text-muted">+1 more requirements</span>
            <Link to="/student/documents" className="text-primary hover:underline">View all</Link>
          </div>
        </section>

        <section className="rounded-xl border bg-surface p-6 shadow-xs">
          <div className="flex items-center justify-between">
            <h3 className="text-lg font-semibold tracking-tight">Upcoming</h3>
            <Link to="/student/notifications" className="text-xs text-primary hover:underline">View calendar</Link>
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
      </section>

      {/* Bottom row: Recommended / Academic Snapshot / Stay Updated */}
      <section aria-labelledby="explore-heading" className="space-y-3">
        <h2 id="explore-heading" className="text-2xs uppercase tracking-[0.14em] text-text-soft font-semibold">For You</h2>
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <section className="rounded-xl border bg-gold-soft p-6 overflow-hidden relative shadow-xs">
          <p className="text-2xs uppercase tracking-[0.14em] text-primary/70 font-semibold">Recommended</p>
          <h3 className="mt-2 text-lg font-semibold tracking-tight">TCC Financial Assistance Grant</h3>
          <p className="mt-1 text-xs text-text-muted">Open until June 30, 2025</p>
          <button className="mt-5 inline-flex items-center rounded-md bg-gold hover:bg-gold-hover px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors">
            View Details
          </button>
        </section>

        <section className="rounded-xl border bg-surface p-6 shadow-xs">
          <h3 className="text-lg font-semibold tracking-tight inline-flex items-center gap-2">
            <IconSchool size={18} className="text-primary" /> Academic Snapshot
          </h3>
          <div className="mt-5 grid grid-cols-2 gap-4">
            <div>
              <p className="text-2xs uppercase tracking-wide text-text-soft">GWA</p>
              <p className="mt-1 text-3xl font-semibold tabular-nums">1.35</p>
              <span className="mt-2 inline-block text-2xs font-semibold px-2 py-1 rounded-full bg-success-soft text-success">Very Good</span>
            </div>
            <div>
              <p className="text-2xs uppercase tracking-wide text-text-soft">Units Earned</p>
              <p className="mt-1 text-3xl font-semibold tabular-nums">24 <span className="text-text-muted">/ 24</span></p>
              <span className="mt-2 inline-block text-2xs font-semibold px-2 py-1 rounded-full bg-primary-soft text-primary">Full Load</span>
            </div>
          </div>
        </section>

        <section className="rounded-xl border bg-gold-soft p-6 relative overflow-hidden shadow-xs">
          <h3 className="text-lg font-semibold tracking-tight inline-flex items-center gap-2">
            <IconSpeakerphone size={18} className="text-primary" /> Stay Updated
          </h3>
          <p className="mt-3 text-sm text-text-muted max-w-[28ch]">
            Don't miss important announcements and opportunities.
          </p>
          <Link
            to="/student/announcements"
            className="mt-5 inline-flex items-center rounded-md bg-gold hover:bg-gold-hover px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors"
          >
            View Announcements
          </Link>
          <IconCalendarEvent size={80} className="absolute -right-3 -bottom-3 text-primary/10" aria-hidden />
        </section>
        </div>
      </section>
    </div>
  );
}

function QuickAction({ icon, label, to }: { icon: React.ReactNode; label: string; to: string }) {
  return (
    <li>
      <Link
        to={to}
        className="flex items-center gap-3 rounded-md px-2 py-2 text-sm hover:bg-surface-muted transition-colors"
      >
        <span className="h-7 w-7 rounded-md bg-primary-soft text-primary grid place-items-center shrink-0">{icon}</span>
        <span>{label}</span>
      </Link>
    </li>
  );
}
