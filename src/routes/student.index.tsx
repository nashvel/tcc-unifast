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
  const submitted = myDocs.length;
  const approved = myDocs.filter((d) => d.status === "approved").length;
  const completion = Math.min(100, Math.round((submitted / requiredDocs.length) * 100));
  const firstName = (profile?.full_name ?? "").split(" ")[0] || "Grantee";

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
