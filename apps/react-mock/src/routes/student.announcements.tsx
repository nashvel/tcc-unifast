import { createFileRoute } from "@tanstack/react-router";
import { PageHeader } from "@/components/ui/page-header";
import { useAnnouncements } from "@/hooks/queries";
import { IconSpeakerphone } from "@tabler/icons-react";

export const Route = createFileRoute("/student/announcements")({
  component: StudentAnnouncements,
});

function StudentAnnouncements() {
  const { data: announcements = [], isLoading } = useAnnouncements();
  const published = announcements.filter((a) => a.status === "published");
  return (
    <div>
      <PageHeader title="Announcements" description="Updates from the UniFAST Office." />
      {isLoading && <p className="text-sm text-text-muted">Loading…</p>}
      {!isLoading && published.length === 0 && <p className="text-sm text-text-muted">No announcements yet.</p>}
      <ul className="space-y-2">
        {published.map((a) => (
          <li key={a.id} className="rounded-lg border bg-surface p-4">
            <div className="flex gap-3">
              <div className="h-8 w-8 rounded-md bg-primary-soft text-primary grid place-items-center shrink-0"><IconSpeakerphone size={16} /></div>
              <div>
                <p className="text-sm font-semibold">{a.title}</p>
                <p className="text-xs text-text-muted mt-0.5">{a.publishedAt} • {a.author}</p>
                <p className="text-sm mt-2">{a.body}</p>
              </div>
            </div>
          </li>
        ))}
      </ul>
    </div>
  );
}
