import { createFileRoute, Link } from "@tanstack/react-router";
import { PageHeader } from "@/components/ui/page-header";
import { Btn } from "@/components/ui/btn";
import { StatusBadge, statusVariantFor, formatStatus } from "@/components/ui/status-badge";
import { useAnnouncements } from "@/hooks/queries";
import { IconPlus, IconMail, IconMessage, IconDeviceMobile } from "@tabler/icons-react";

export const Route = createFileRoute("/app/announcements/")({
  component: AnnouncementsPage,
});

function AnnouncementsPage() {
  const { data: announcements = [], isLoading } = useAnnouncements();
  return (
    <div>
      <PageHeader title="Announcements" description="Broadcast updates to grantees by audience and channel."
        actions={<Link to="/app/announcements/new"><Btn variant="primary" icon={IconPlus}>New announcement</Btn></Link>} />
      <div className="space-y-2">
        {isLoading && <p className="text-sm text-text-muted">Loading…</p>}
        {!isLoading && announcements.length === 0 && <p className="text-sm text-text-muted">No announcements yet.</p>}
        {announcements.map((a) => (
          <div key={a.id} className="rounded-lg border bg-surface p-4 flex flex-col sm:flex-row sm:items-center gap-3 justify-between">
            <div className="min-w-0">
              <div className="flex items-center gap-2 flex-wrap">
                <p className="font-semibold text-sm">{a.title}</p>
                <StatusBadge variant={statusVariantFor(a.status)}>{formatStatus(a.status)}</StatusBadge>
              </div>
              <p className="text-xs text-text-muted mt-1 line-clamp-2">{a.body}</p>
              <div className="flex items-center gap-2 mt-2 flex-wrap">
                <StatusBadge variant="primary">{a.audienceLabel}</StatusBadge>
                {a.channels.includes("in_app") && <StatusBadge variant="info"><IconMessage size={10} /> In-app</StatusBadge>}
                {a.channels.includes("email") && <StatusBadge variant="info"><IconMail size={10} /> Email</StatusBadge>}
                {a.channels.includes("sms") && <StatusBadge variant="info"><IconDeviceMobile size={10} /> SMS</StatusBadge>}
                <span className="text-[11px] text-text-soft">{a.publishedAt ?? a.scheduledFor ?? "—"}</span>
              </div>
            </div>
            <div className="flex gap-2 shrink-0">
              <Link to="/app/announcements/$id/edit" params={{ id: a.id }}><Btn variant="outline" size="sm">Edit</Btn></Link>
              <Link to="/app/announcements/logs"><Btn variant="outline" size="sm">Logs</Btn></Link>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
