import { useState } from "react";
import { IconBell, IconSearch, IconMenu2, IconUserCircle, IconLogout, IconChevronDown } from "@tabler/icons-react";
import { useAuthStore } from "@/stores/authStore";
import { useNotificationStore } from "@/stores/notificationStore";
import { useNavigate } from "@tanstack/react-router";
import { cn } from "@/lib/utils";

export function AppTopbar({ onToggleSidebar }: { onToggleSidebar: () => void }) {
  const user = useAuthStore((s) => s.user);
  const logout = useAuthStore((s) => s.logout);
  const notifs = useNotificationStore((s) => s.items);
  const markAllRead = useNotificationStore((s) => s.markAllRead);
  const navigate = useNavigate();

  const [openNotif, setOpenNotif] = useState(false);
  const [openProfile, setOpenProfile] = useState(false);
  const unread = notifs.filter((n) => !n.read).length;

  const initials = (user?.name ?? "U").split(" ").map((p) => p[0]).slice(0, 2).join("");

  return (
    <header className="h-14 bg-surface border-b flex items-center px-3 gap-2 sticky top-0 z-30">
      <button onClick={onToggleSidebar} className="lg:hidden p-1.5 rounded-md hover:bg-surface-muted">
        <IconMenu2 size={18} />
      </button>
      <div className="relative flex-1 max-w-md">
        <IconSearch size={15} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-text-soft" />
        <input
          placeholder="Search grantees, documents, batches…"
          className="h-9 w-full rounded-md border bg-input pl-8 pr-3 text-sm focus-ring"
        />
      </div>
      <div className="flex-1" />

      <div className="relative">
        <button onClick={() => { setOpenNotif((v) => !v); setOpenProfile(false); }} className="relative p-2 rounded-md hover:bg-surface-muted">
          <IconBell size={18} />
          {unread > 0 && (
            <span className="absolute top-1 right-1 h-4 min-w-4 rounded-full bg-danger text-white text-[10px] font-medium px-1 grid place-items-center">
              {unread}
            </span>
          )}
        </button>
        {openNotif && (
          <div className="absolute right-0 mt-1 w-80 bg-surface border rounded-lg shadow-lg z-40">
            <div className="flex items-center justify-between px-3 h-10 border-b">
              <p className="text-sm font-semibold">Notifications</p>
              <button onClick={markAllRead} className="text-xs text-primary hover:underline">Mark all read</button>
            </div>
            <ul className="max-h-80 overflow-y-auto">
              {notifs.map((n) => (
                <li key={n.id} className={cn("px-3 py-2 border-b last:border-0 text-xs", !n.read && "bg-primary-soft/30")}>
                  <p className="font-medium text-sm">{n.title}</p>
                  <p className="text-text-muted mt-0.5">{n.body}</p>
                  <p className="text-text-soft mt-1">{n.createdAt}</p>
                </li>
              ))}
            </ul>
          </div>
        )}
      </div>

      <div className="relative">
        <button onClick={() => { setOpenProfile((v) => !v); setOpenNotif(false); }} className="flex items-center gap-2 pl-1 pr-2 py-1 rounded-md hover:bg-surface-muted">
          <div className="h-7 w-7 rounded-full bg-primary-soft text-primary grid place-items-center text-xs font-semibold">{initials || "U"}</div>
          <div className="hidden sm:block text-left leading-tight">
            <p className="text-[12px] font-medium">{user?.name}</p>
            <p className="text-[10px] text-text-muted capitalize">{user?.role}</p>
          </div>
          <IconChevronDown size={14} className="text-text-muted" />
        </button>
        {openProfile && (
          <div className="absolute right-0 mt-1 w-56 bg-surface border rounded-lg shadow-lg z-40 p-1">
            <div className="px-2.5 py-2 border-b">
              <p className="text-sm font-medium">{user?.name}</p>
              <p className="text-xs text-text-muted">{user?.email}</p>
            </div>
            <button className="w-full flex items-center gap-2 px-2.5 py-1.5 text-sm rounded hover:bg-surface-muted">
              <IconUserCircle size={15} /> Profile
            </button>
            <button
              onClick={() => { logout(); navigate({ to: "/login" }); }}
              className="w-full flex items-center gap-2 px-2.5 py-1.5 text-sm rounded hover:bg-surface-muted text-danger"
            >
              <IconLogout size={15} /> Sign out
            </button>
          </div>
        )}
      </div>
    </header>
  );
}
