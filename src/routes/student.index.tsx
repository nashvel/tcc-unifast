import { createFileRoute, Link } from "@tanstack/react-router";
import { PageHeader } from "@/components/ui/page-header";
import { StatCard } from "@/components/ui/stat-card";
import { StatusBadge } from "@/components/ui/status-badge";
import { ChartCard } from "@/components/ui/chart-card";
import { requiredDocs } from "@/data/mockDocuments";
import { useDocuments, useAnnouncements } from "@/hooks/queries";
import { useAuthStore } from "@/stores/authStore";
import { IconUserCheck, IconFileCheck, IconCircleCheck, IconClipboardList, IconSpeakerphone, IconArrowRight } from "@tabler/icons-react";

export const Route = createFileRoute("/student/")({
  component: StudentHome,
});

function StudentHome() {
  const profile = useAuthStore((s) => s.profile);
  const { data: myDocs = [] } = useDocuments({ ownerOnly: true });
  const { data: announcements = [] } = useAnnouncements();
  const submitted = myDocs.length;
  const approved = myDocs.filter((d) => d.status === "approved").length;
  const completion = Math.min(100, Math.round((submitted / requiredDocs.length) * 100));
  const firstName = (profile?.full_name ?? "").split(" ")[0] || "Grantee";

  return (
    <div>
      <PageHeader title={`Welcome, ${firstName}`} description="Here's your TES application overview." />
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
        <StatCard label="Account status" value="Active" icon={IconUserCheck} tone="success" />
        <StatCard label="Documents submitted" value={`${submitted} / ${requiredDocs.length}`} icon={IconFileCheck} tone="info" hint={
          <div className="h-1 mt-1 rounded-full bg-surface-muted overflow-hidden"><div className="h-full bg-primary" style={{ width: `${completion}%` }} /></div>
        } />
        <StatCard label="Approved" value={approved} icon={IconCircleCheck} tone="primary" />
        <StatCard label="Eligibility" value="Pending" icon={IconClipboardList} tone="warning" />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-3">
        <ChartCard title="Required Documents" className="lg:col-span-2">
          <ul className="divide-y">
            {requiredDocs.map((req) => {
              const d = myDocs.find((x) => x.type === req);
              return (
                <li key={req} className="flex items-center justify-between py-2 text-sm">
                  <span>{req}</span>
                  <div className="flex items-center gap-2">
                    {d ? <StatusBadge variant={d.status === "approved" ? "success" : "warning"}>{d.status}</StatusBadge> : <StatusBadge variant="neutral">Not Submitted</StatusBadge>}
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
        </ChartCard>
      </div>
    </div>
  );
}
