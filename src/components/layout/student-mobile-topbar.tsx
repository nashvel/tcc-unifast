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
          onClick={() => { setOpenProfile((v) => !v); }}
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

