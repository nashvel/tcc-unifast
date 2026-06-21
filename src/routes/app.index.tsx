import { createFileRoute } from "@tanstack/react-router";
import { PageHeader } from "@/components/ui/page-header";
import { StatCard } from "@/components/ui/stat-card";
import { ChartCard, MiniBars, MiniLine } from "@/components/ui/chart-card";
import { ActivityTimeline } from "@/components/ui/activity-timeline";
import { StatusBadge, statusVariantFor, formatStatus } from "@/components/ui/status-badge";
import {
  IconUsersGroup, IconUserCheck, IconUserOff, IconClipboardList, IconFileCheck,
  IconCircleCheck, IconCircleX, IconShieldCheck, IconUpload, IconEdit, IconSpeakerphone, IconHistory,
} from "@tabler/icons-react";
import { mockGrantees } from "@/data/mockGrantees";
import { mockBatches } from "@/data/mockBatches";
import { mockAnnouncements } from "@/data/mockAnnouncements";
import { mockAuditLogs } from "@/data/mockAuditLogs";

export const Route = createFileRoute("/app/")({
  component: Dashboard,
});

function Dashboard() {
  const g = mockGrantees;
  const stats = {
    total: g.length,
    active: g.filter((x) => x.accountStatus === "active").length,
    inactive: g.filter((x) => x.accountStatus === "inactive" || x.accountStatus === "pending_activation").length,
    pending: g.filter((x) => x.submissionStatus === "submitted" || x.submissionStatus === "under_review").length,
    validated: g.filter((x) => x.submissionStatus === "approved").length,
    eligible: g.filter((x) => x.eligibility === "eligible").length,
    ineligible: g.filter((x) => x.eligibility === "ineligible").length,
    risk: g.filter((x) => x.risk === "high").length,
  };

  return (
    <div>
      <PageHeader
        title="Dashboard"
        description="Overview of TES grantee operations for the current academic period."
      />

      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <StatCard label="Total Grantees" value={stats.total.toLocaleString()} icon={IconUsersGroup} tone="primary" />
        <StatCard label="Active Accounts" value={stats.active} icon={IconUserCheck} tone="success" />
        <StatCard label="Inactive / Pending" value={stats.inactive} icon={IconUserOff} tone="warning" />
        <StatCard label="Pending Submissions" value={stats.pending} icon={IconClipboardList} tone="info" />
        <StatCard label="Validated Documents" value={stats.validated} icon={IconFileCheck} tone="success" />
        <StatCard label="Eligible Grantees" value={stats.eligible} icon={IconCircleCheck} tone="success" />
        <StatCard label="Ineligible Grantees" value={stats.ineligible} icon={IconCircleX} tone="danger" />
        <StatCard label="Risk-flagged" value={stats.risk} icon={IconShieldCheck} tone="danger" />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-3 mt-4">
        <ChartCard title="Submission Trend" description="Daily submissions, last 14 days" className="lg:col-span-2">
          <MiniLine points={[8, 12, 15, 11, 18, 22, 19, 25, 28, 24, 31, 36, 33, 41]} />
          <div className="grid grid-cols-3 gap-3 mt-3">
            <div><p className="text-xs text-text-muted">Today</p><p className="text-base font-semibold">41</p></div>
            <div><p className="text-xs text-text-muted">7-day avg</p><p className="text-base font-semibold">31</p></div>
            <div><p className="text-xs text-text-muted">vs last week</p><p className="text-base font-semibold text-success">+18%</p></div>
          </div>
        </ChartCard>
        <ChartCard title="Validation Summary" description="Document outcomes this period">
          <MiniBars data={[
            { label: "Approved", value: 740, tone: "success" },
            { label: "Pending", value: 180, tone: "warning" },
            { label: "Rejected", value: 62, tone: "danger" },
            { label: "Suspicious", value: 11, tone: "danger" },
          ]} />
        </ChartCard>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-3 mt-4">
        <ChartCard title="Batch Progress" description="Validation completion by batch" className="lg:col-span-2">
          <div className="space-y-3">
            {mockBatches.slice(0, 3).map((b) => {
              const pct = Math.round((b.validated / b.totalGrantees) * 100);
              return (
                <div key={b.id}>
                  <div className="flex items-center justify-between text-xs mb-1">
                    <div className="flex items-center gap-2">
                      <span className="font-medium">{b.name}</span>
                      <StatusBadge variant={statusVariantFor(b.status)}>{formatStatus(b.status)}</StatusBadge>
                    </div>
                    <span className="text-text-muted tabular-nums">{b.validated.toLocaleString()} / {b.totalGrantees.toLocaleString()} ({pct}%)</span>
                  </div>
                  <div className="h-1.5 rounded-full bg-surface-muted overflow-hidden">
                    <div className="h-full rounded-full bg-primary" style={{ width: `${pct}%` }} />
                  </div>
                </div>
              );
            })}
          </div>
        </ChartCard>
        <ChartCard title="Recent Announcements">
          <ul className="space-y-3">
            {mockAnnouncements.slice(0, 3).map((a) => (
              <li key={a.id} className="flex gap-2">
                <div className="h-7 w-7 rounded-md bg-primary-soft text-primary grid place-items-center shrink-0">
                  <IconSpeakerphone size={14} />
                </div>
                <div className="min-w-0">
                  <p className="text-sm font-medium leading-tight truncate">{a.title}</p>
                  <p className="text-xs text-text-muted line-clamp-2">{a.body}</p>
                  <div className="flex items-center gap-2 mt-1">
                    <StatusBadge variant={statusVariantFor(a.status)}>{formatStatus(a.status)}</StatusBadge>
                    <span className="text-[11px] text-text-soft">{a.publishedAt ?? a.scheduledFor ?? ""}</span>
                  </div>
                </div>
              </li>
            ))}
          </ul>
        </ChartCard>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-3 mt-4">
        <ChartCard title="Recent Activity" description="Latest staff actions across modules">
          <ActivityTimeline items={mockAuditLogs.slice(0, 5).map((l) => ({
            icon: l.action.includes("approve") ? IconCircleCheck : l.action.includes("reject") ? IconCircleX :
              l.action.includes("flag") ? IconShieldCheck : l.action.includes("import") ? IconUpload :
              l.action.includes("publish") ? IconSpeakerphone : IconEdit,
            title: <><span className="font-medium">{l.user}</span> {formatStatus(l.action)}</>,
            meta: <>{l.module} • {l.target}</>,
            time: l.timestamp.split(" ")[1],
            tone: l.action.includes("flag") || l.action.includes("reject") ? "danger" :
              l.action.includes("approve") || l.action.includes("publish") ? "success" : "primary",
          }))} />
        </ChartCard>
        <ChartCard title="System Health" description="Operational metrics">
          <div className="grid grid-cols-2 gap-3">
            <div className="rounded-md border p-3">
              <p className="text-xs text-text-muted">Avg validation time</p>
              <p className="text-lg font-semibold">2.4 hrs</p>
              <p className="text-[11px] text-success mt-0.5">↓ 12% vs last week</p>
            </div>
            <div className="rounded-md border p-3">
              <p className="text-xs text-text-muted">Auto-approval rate</p>
              <p className="text-lg font-semibold">68%</p>
              <p className="text-[11px] text-success mt-0.5">↑ 5% vs last week</p>
            </div>
            <div className="rounded-md border p-3">
              <p className="text-xs text-text-muted">Resubmission rate</p>
              <p className="text-lg font-semibold">9.2%</p>
              <p className="text-[11px] text-warning mt-0.5">↑ 1.1% vs last week</p>
            </div>
            <div className="rounded-md border p-3">
              <p className="text-xs text-text-muted">Activation rate</p>
              <p className="text-lg font-semibold">{Math.round((stats.active / stats.total) * 100)}%</p>
              <p className="text-[11px] text-text-muted mt-0.5">{stats.active} of {stats.total}</p>
            </div>
          </div>
        </ChartCard>
      </div>

      <div className="mt-3">
        <p className="text-[11px] text-text-soft text-center">
          History: <IconHistory size={11} className="inline" /> Last system sync at 09:42 — UniFAST Office
        </p>
      </div>
    </div>
  );
}
