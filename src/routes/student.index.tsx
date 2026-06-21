import { createFileRoute, Link } from "@tanstack/react-router";
import { PageHeader } from "@/components/ui/page-header";
import { StatusBadge } from "@/components/ui/status-badge";
import { ChartCard } from "@/components/ui/chart-card";
import { StatGridSkeleton, CardSkeleton } from "@/components/ui/skeletons";
import { requiredDocs } from "@/data/mockDocuments";
import { useDocuments, useAnnouncements } from "@/hooks/queries";
import { useAuthStore } from "@/stores/authStore";

import { IconSpeakerphone, IconArrowRight, IconArrowUpRight } from "@tabler/icons-react";

import { cn } from "@/lib/utils";


export const Route = createFileRoute("/student/")({
  component: StudentHome,
});

function StudentHome() {
  const profile = useAuthStore((s) => s.profile);
  const { data: myDocs = [], isLoading: docsLoading } = useDocuments({ ownerOnly: true });
  const { data: announcements = [], isLoading: annLoading } = useAnnouncements();
  const submitted = Math.min(
    requiredDocs.length,
    myDocs.filter((d) => requiredDocs.includes(d.type)).length,
  );
  const approved = myDocs.filter((d) => requiredDocs.includes(d.type) && d.status === "approved").length;
  const completion = Math.min(100, Math.round((submitted / requiredDocs.length) * 100));
  const firstName = (profile?.full_name ?? "").split(" ")[0] || "Grantee";
  const onboardingSkipped =
    typeof window !== "undefined" && sessionStorage.getItem("unifast.onboarding.skipped") === "1";

  return (
    <div>
      <PageHeader title={`Welcome, ${firstName}`} description="Here's your TES application overview." />





      {docsLoading ? (
        <StatGridSkeleton />
      ) : (
        <StatsBoard
          submitted={submitted}
          total={requiredDocs.length}
          approved={approved}
          completion={completion}
        />
      )}


      <div className="grid grid-cols-1 lg:grid-cols-3 gap-3">
        <ChartCard title="Required Documents" className="lg:col-span-2">
          <ul className="divide-y">
            {requiredDocs.map((req) => {
              const d = myDocs.find((x) => x.type === req);
              return (
                <li key={req} className="flex items-center justify-between py-2 text-sm">
                  <span>{req}</span>
                  <div className="flex items-center gap-2">
                    {d ? (
                      <StatusBadge variant={d.status === "approved" ? "success" : "warning"}>{d.status}</StatusBadge>
                    ) : onboardingSkipped ? (
                      <StatusBadge variant="warning">Skipped</StatusBadge>
                    ) : (
                      <StatusBadge variant="neutral">Not Submitted</StatusBadge>
                    )}
                    <Link to="/student/upload" className="text-xs text-primary hover:underline">Upload</Link>
                  </div>
                </li>
              );
            })}
          </ul>
          <div className="mt-3 text-right">
            <Link to="/student/upload" className="inline-flex items-center gap-1 text-sm text-primary hover:underline">
              Manage uploads <IconArrowRight size={14} />
            </Link>
          </div>
        </ChartCard>

        <ChartCard title="Latest Announcements">
          {annLoading ? (
            <CardSkeleton lines={4} className="border-none p-0 shadow-none" />
          ) : (
            <ul className="space-y-3">
              {announcements.filter((a) => a.status === "published").slice(0, 3).map((a) => (
                <li key={a.id} className="flex gap-2">
                  <div className="h-7 w-7 rounded-md bg-primary-soft text-primary grid place-items-center shrink-0"><IconSpeakerphone size={14} /></div>
                  <div className="min-w-0">
                    <p className="text-sm font-medium leading-tight truncate">{a.title}</p>
                    <p className="text-xs text-text-muted line-clamp-2">{a.body}</p>
                  </div>
                </li>
              ))}
            </ul>
          )}
        </ChartCard>
      </div>
    </div>
  );
}

function StatsBoard({
  submitted,
  total,
  approved,
  completion,
}: {
  submitted: number;
  total: number;
  approved: number;
  completion: number;
}) {
  return (
    <section
      aria-label="Application overview"
      className="mb-4 overflow-hidden rounded-2xl border bg-surface"
    >
      {/* Hero row: progress ring + headline */}
      <Link
        to="/student/documents"
        className="group grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-4 p-4 sm:p-5 hover:bg-surface-muted/40 transition"
      >
        <ProgressRing value={completion} />
        <div className="min-w-0">
          <p className="text-[10px] uppercase tracking-[0.14em] text-text-soft">
            Application
          </p>
          <p className="mt-0.5 text-2xl sm:text-[28px] font-semibold leading-none tabular-nums">
            {submitted}
            <span className="text-text-muted font-normal">/{total}</span>
            <span className="ml-2 text-sm font-normal text-text-muted align-middle">
              documents
            </span>
          </p>
          <p className="mt-1.5 text-xs text-text-muted">
            {completion === 100
              ? "All requirements submitted — sit tight."
              : `${total - submitted} more to complete your submission.`}
          </p>
        </div>
        <IconArrowUpRight
          size={18}
          className="text-text-soft transition-transform group-hover:-translate-y-0.5 group-hover:translate-x-0.5 group-hover:text-text"
        />
      </Link>

      {/* Stat rail */}
      <dl className="grid grid-cols-3 border-t divide-x bg-surface-muted/30">
        <Metric label="Status" value="Active" dot="bg-success" />
        <Metric label="Approved" value={String(approved)} dot="bg-primary" />
        <Metric label="Eligibility" value="Pending" dot="bg-warning" />
      </dl>
    </section>
  );
}

function Metric({ label, value, dot }: { label: string; value: string; dot: string }) {
  return (
    <div className="px-3 py-3 sm:py-3.5 min-w-0">
      <dt className="flex items-center gap-1.5 text-[10px] uppercase tracking-[0.14em] text-text-soft">
        <span className={cn("h-1.5 w-1.5 rounded-full", dot)} />
        <span className="truncate">{label}</span>
      </dt>
      <dd className="mt-1 text-base sm:text-lg font-semibold tabular-nums truncate">
        {value}
      </dd>
    </div>
  );
}

function ProgressRing({ value }: { value: number }) {
  const size = 64;
  const stroke = 6;
  const r = (size - stroke) / 2;
  const c = 2 * Math.PI * r;
  const offset = c - (value / 100) * c;
  return (
    <div className="relative shrink-0" style={{ width: size, height: size }}>
      <svg width={size} height={size} className="-rotate-90">
        <circle
          cx={size / 2}
          cy={size / 2}
          r={r}
          stroke="currentColor"
          strokeWidth={stroke}
          fill="none"
          className="text-surface-muted"
        />
        <circle
          cx={size / 2}
          cy={size / 2}
          r={r}
          stroke="currentColor"
          strokeWidth={stroke}
          strokeLinecap="round"
          fill="none"
          strokeDasharray={c}
          strokeDashoffset={offset}
          className="text-primary transition-[stroke-dashoffset] duration-700"
        />
      </svg>
      <div className="absolute inset-0 grid place-items-center">
        <span className="text-[13px] font-semibold tabular-nums">{value}%</span>
      </div>
    </div>
  );
}

