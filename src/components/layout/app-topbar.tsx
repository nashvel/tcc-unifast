import { useState } from "react";
import { IconBell, IconSearch, IconMenu2, IconUserCircle, IconLogout, IconChevronDown, IconCommand } from "@tabler/icons-react";
import { useAuthStore } from "@/stores/authStore";
import { useNotifications, useMarkNotificationRead } from "@/hooks/queries";
import { useNavigate } from "@tanstack/react-router";
import { cn } from "@/lib/utils";
import { ThemeToggle } from "@/components/ui/theme-toggle";
import { UserAvatar } from "@/components/ui/dicebear-avatar";
import { signOut as mockSignOut } from "@/lib/mock-auth";

export function AppTopbar({ onToggleSidebar }: { onToggleSidebar: () => void }) {
  const profile = useAuthStore((s) => s.profile);
  const role = useAuthStore((s) => s.role);
  const email = useAuthStore((s) => s.email);
  const navigate = useNavigate();
  const { data: notifs = [] } = useNotifications();
  const markRead = useMarkNotificationRead();

  const [openNotif, setOpenNotif] = useState(false);
  const [openProfile, setOpenProfile] = useState(false);
  const unread = notifs.filter((n) => !n.read).length;

  const displayName = profile?.full_name || email || "User";
  const avatarSeed = email || displayName;

  function signOut() {
    mockSignOut();
    useAuthStore.getState().reset();
    navigate({ to: "/login" });
  }

  return (
    <header className="h-14 bg-surface border-b flex items-center px-3 gap-2 sticky top-0 z-30">
      <button onClick={onToggleSidebar} className="lg:hidden p-1.5 rounded-md hover:bg-surface-muted">
        <IconMenu2 size={18} />
      </button>
      <button
        onClick={() => window.dispatchEvent(new KeyboardEvent("keydown", { key: "k", metaKey: true }))}
        className="flex items-center gap-2 h-9 px-2.5 rounded-md border bg-input text-left flex-1 max-w-md text-text-muted hover:bg-surface-muted"
      >
        <IconSearch size={15} />
        <span className="text-sm flex-1 truncate">Search or jump to…</span>
        <kbd className="hidden sm:inline-flex items-center gap-0.5 text-2xs px-1 py-0.5 rounded border bg-surface text-text-soft">
          <IconCommand size={10} /> K
        </kbd>
      </button>
      <div className="flex-1" />
      <ThemeToggle />

      <div className="relative">
        <button onClick={() => { setOpenNotif((v) => !v); setOpenProfile(false); }} className="relative p-2 rounded-md hover:bg-surface-muted">
          <IconBell size={18} />
          {unread > 0 && (
            <span className="absolute top-1 right-1 h-4 min-w-4 rounded-full bg-danger text-white text-2xs font-medium px-1 grid place-items-center">
              {unread}
            </span>
          )}
        </button>
        {openNotif && (
          <div className="absolute right-0 mt-1 w-80 bg-surface border rounded-lg shadow-lg z-40">
            <div className="flex items-center justify-between px-3 h-10 border-b">
              <p className="text-sm font-semibold">Notifications</p>
              <button onClick={() => markRead.mutate("all")} className="text-xs text-primary hover:underline">Mark all read</button>
            </div>
            <ul className="max-h-80 overflow-y-auto">
              {notifs.length === 0 && <li className="px-3 py-6 text-center text-xs text-text-muted">No notifications</li>}
              {notifs.map((n) => (
                <li key={n.id} className={cn("px-3 py-2 border-b last:border-0 text-xs", !n.read && "bg-primary-soft/30")}>
                  <p className="font-medium text-sm">{n.title}</p>
                  <p className="text-text-muted mt-0.5">{n.body}</p>
                  <p className="text-text-soft mt-1">{new Date(n.created_at).toLocaleString()}</p>
                </li>
              ))}
            </ul>
          </div>
        )}
      </div>

      <div className="relative">
        <button onClick={() => { setOpenProfile((v) => !v); setOpenNotif(false); }} className="flex items-center gap-2 pl-1 pr-2 py-1 rounded-md hover:bg-surface-muted">
          <UserAvatar seed={avatarSeed} path={profile?.avatar_url} size={28} alt={displayName} />
          <div className="hidden sm:block text-left leading-tight">
            <p className="text-xs font-medium">{displayName}</p>
            <p className="text-2xs text-text-muted capitalize">{role}</p>
          </div>
          <IconChevronDown size={14} className="text-text-muted" />
        </button>
        {openProfile && (
          <div className="absolute right-0 mt-1 w-56 bg-surface border rounded-lg shadow-lg z-40 p-1">
            <div className="px-2.5 py-2 border-b">
              <p className="text-sm font-medium">{displayName}</p>
              <p className="text-xs text-text-muted">{email}</p>
            </div>
            <button className="w-full flex items-center gap-2 px-2.5 py-1.5 text-sm rounded hover:bg-surface-muted">
              <IconUserCircle size={15} /> Profile
            </button>
            <button
              onClick={signOut}
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
