import { useState } from "react";
import { useNavigate, useRouterState } from "@tanstack/react-router";
import { IconBell, IconChevronLeft } from "@tabler/icons-react";
import { useAuthStore } from "@/stores/authStore";
import { useNotifications } from "@/hooks/queries";
import { UserAvatar } from "@/components/ui/dicebear-avatar";
import { supabase } from "@/integrations/supabase/client";


const TITLES: Record<string, string> = {
  "/student": "Home",
  "/student/profile": "Profile",
  "/student/documents": "Required Documents",
  "/student/upload": "Upload",
  "/student/submissions": "My Submissions",
  "/student/announcements": "Announcements",
  "/student/notifications": "Notifications",
};

const ROOT = "/student";

/** Mobile-app style topbar for the student portal: contextual title, back chevron on subpages, bell + avatar. */
export function StudentMobileTopbar() {
  const pathname = useRouterState({ select: (s) => s.location.pathname });
  const navigate = useNavigate();
  const profile = useAuthStore((s) => s.profile);
  const email = useAuthStore((s) => s.email);
  const { data: notifs = [] } = useNotifications();
  const [openProfile, setOpenProfile] = useState(false);


  const unread = notifs.filter((n) => !n.read).length;
  const displayName = profile?.full_name || email || "Student";
  const title = TITLES[pathname] ?? "Student Portal";
  const isRoot = pathname === ROOT;

  async function signOut() {
    await supabase.auth.signOut();
    useAuthStore.getState().reset();
    navigate({ to: "/login" });
  }

  return (
    <header
      className="h-14 bg-surface/95 backdrop-blur supports-[backdrop-filter]:bg-surface/80 border-b flex items-center px-3 gap-2 sticky top-0 z-30"
      style={{ paddingTop: "env(safe-area-inset-top)" }}
    >
      {!isRoot ? (
        <button
          onClick={() => navigate({ to: ROOT })}
          className="-ml-1 p-1.5 rounded-full hover:bg-surface-muted active:bg-surface-2"
          aria-label="Back"
        >
          <IconChevronLeft size={20} />
        </button>
      ) : (
        <div className="w-2" />
      )}

      <h1 className="flex-1 text-[15px] font-semibold truncate">{title}</h1>

      <button
        onClick={() => { setOpenProfile(false); navigate({ to: "/student/notifications" }); }}
        className="relative p-2 rounded-full hover:bg-surface-muted active:bg-surface-2"
        aria-label="Notifications"
      >
        <IconBell size={20} />
        {unread > 0 && (
          <span className="absolute top-1 right-1 h-4 min-w-4 rounded-full bg-danger text-white text-[10px] font-medium px-1 grid place-items-center">
            {unread}
          </span>
        )}
      </button>



      <div className="relative">
        <button
          onClick={() => { setOpenProfile((v) => !v); setOpenNotif(false); }}
          className="p-0.5 rounded-full hover:bg-surface-muted"
          aria-label="Profile"
        >
          <UserAvatar seed={email || displayName} path={profile?.avatar_url} size={30} alt={displayName} />
        </button>
        {openProfile && (
          <>
            <div className="fixed inset-0 z-30" onClick={() => setOpenProfile(false)} />
            <div className="absolute right-0 mt-1 w-56 bg-surface border rounded-xl shadow-xl z-40 p-1 overflow-hidden">
              <div className="px-2.5 py-2 border-b">
                <p className="text-sm font-medium truncate">{displayName}</p>
                <p className="text-xs text-text-muted truncate">{email}</p>
              </div>
              <button
                onClick={() => { setOpenProfile(false); navigate({ to: "/student/profile" }); }}
                className="w-full text-left px-2.5 py-2 text-sm rounded hover:bg-surface-muted"
              >
                Edit profile
              </button>
              <button
                onClick={signOut}
                className="w-full text-left px-2.5 py-2 text-sm rounded hover:bg-surface-muted text-danger"
              >
                Sign out
              </button>
            </div>
          </>
        )}
      </div>
    </header>
  );
}

type Notif = {
  id: string;
  title: string;
  body: string | null;
  read: boolean;
  created_at: string;
};

function NotificationSheet({
  notifs,
  onClose,
  onMarkAll,
  onItem,
}: {
  notifs: Notif[];
  onClose: () => void;
  onMarkAll: () => void;
  onItem: (id: string) => void;
}) {
  // Lock body scroll while open
  useEffect(() => {
    const prev = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    return () => {
      document.body.style.overflow = prev;
    };
  }, []);

  const unread = notifs.filter((n) => !n.read).length;

  return (
    <>
      <motion.div
        className="fixed inset-0 z-40 bg-black/40"
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        exit={{ opacity: 0 }}
        transition={{ duration: 0.2 }}
        onClick={onClose}
      />
      <motion.div
        role="dialog"
        aria-modal="true"
        aria-label="Notifications"
        className="fixed inset-x-0 bottom-0 z-50 bg-surface rounded-t-2xl shadow-2xl flex flex-col max-h-[85vh] overflow-hidden"
        style={{ paddingBottom: "env(safe-area-inset-bottom)" }}
        initial={{ y: "100%" }}
        animate={{ y: 0 }}
        exit={{ y: "100%" }}
        transition={{ type: "spring", damping: 32, stiffness: 320 }}
        drag="y"
        dragConstraints={{ top: 0, bottom: 0 }}
        dragElastic={{ top: 0, bottom: 0.5 }}
        onDragEnd={(_, info) => {
          if (info.offset.y > 120 || info.velocity.y > 500) onClose();
        }}
      >
        {/* Grabber */}
        <div className="pt-2 pb-1 grid place-items-center cursor-grab active:cursor-grabbing touch-none">
          <span className="h-1.5 w-10 rounded-full bg-surface-2" />
        </div>
        <div className="flex items-center justify-between px-4 pb-3 border-b">
          <div>
            <p className="text-base font-semibold leading-tight">Notifications</p>
            <p className="text-[11px] text-text-muted mt-0.5">
              {unread > 0 ? `${unread} unread` : "All caught up"}
            </p>
          </div>
          {unread > 0 && (
            <button
              onClick={onMarkAll}
              className="inline-flex items-center gap-1 text-xs font-medium text-primary px-2 py-1 rounded-md hover:bg-primary-soft active:bg-primary-soft/70"
            >
              <IconCheck size={14} /> Mark all
            </button>
          )}
        </div>
        <ul className="flex-1 overflow-y-auto overscroll-contain">
          {notifs.length === 0 && (
            <li className="px-4 py-16 text-center text-sm text-text-muted">
              You're all caught up 🎉
            </li>
          )}
          {notifs.map((n) => (
            <li key={n.id}>
              <button
                onClick={() => onItem(n.id)}
                className={cn(
                  "w-full text-left px-4 py-3 border-b last:border-0 flex gap-3 items-start active:bg-surface-muted transition-colors",
                  !n.read && "bg-primary-soft/30",
                )}
              >
                <span
                  className={cn(
                    "mt-1.5 h-2 w-2 rounded-full shrink-0",
                    n.read ? "bg-transparent" : "bg-primary",
                  )}
                />
                <span className="flex-1 min-w-0">
                  <span className="block font-medium text-sm truncate">{n.title}</span>
                  {n.body && (
                    <span className="block text-xs text-text-muted mt-0.5 line-clamp-2">
                      {n.body}
                    </span>
                  )}
                  <span className="block text-[11px] text-text-soft mt-1">
                    {new Date(n.created_at).toLocaleString()}
                  </span>
                </span>
              </button>
            </li>
          ))}
        </ul>
      </motion.div>
    </>
  );
}

