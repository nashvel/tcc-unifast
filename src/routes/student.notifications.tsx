import { createFileRoute } from "@tanstack/react-router";
import { PageHeader } from "@/components/ui/page-header";
import { Btn } from "@/components/ui/btn";
import { useNotificationStore } from "@/stores/notificationStore";
import { cn } from "@/lib/utils";

export const Route = createFileRoute("/student/notifications")({
  component: () => {
    const items = useNotificationStore((s) => s.items);
    const markAll = useNotificationStore((s) => s.markAllRead);
    const markOne = useNotificationStore((s) => s.markRead);
    const toneMap: Record<string, string> = {
      info: "bg-info", success: "bg-success", warning: "bg-warning", danger: "bg-danger",
    };
    return (
      <div>
        <PageHeader title="Notifications" actions={<Btn variant="outline" onClick={markAll}>Mark all read</Btn>} />
        <ul className="space-y-2">
          {items.map((n) => (
            <li key={n.id} className={cn("rounded-lg border bg-surface p-3 flex gap-3", !n.read && "border-primary/30 bg-primary-soft/20")}>
              <div className={cn("h-2 w-2 rounded-full mt-2 shrink-0", toneMap[n.type])} />
              <div className="flex-1 min-w-0">
                <div className="flex items-center justify-between gap-2">
                  <p className="text-sm font-medium">{n.title}</p>
                  <span className="text-[11px] text-text-soft">{n.createdAt}</span>
                </div>
                <p className="text-xs text-text-muted">{n.body}</p>
              </div>
              {!n.read && <button onClick={() => markOne(n.id)} className="text-[11px] text-primary hover:underline self-start">Mark read</button>}
            </li>
          ))}
        </ul>
      </div>
    );
  },
});
