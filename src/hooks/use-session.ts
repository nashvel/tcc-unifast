import { useEffect } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { supabase } from "@/integrations/supabase/client";
import { useAuthStore, type AppRole } from "@/stores/authStore";

async function hydrate(userId: string, email: string | null) {
  const store = useAuthStore.getState();
  store.setSession({ userId, email });
  const [{ data: profile }, { data: roleRow }] = await Promise.all([
    supabase.from("profiles").select("*").eq("id", userId).maybeSingle(),
    supabase.from("user_roles").select("role").eq("user_id", userId).maybeSingle(),
  ]);
  store.setProfile(profile ?? null);
  store.setRole(((roleRow?.role as AppRole | undefined) ?? "student"));
  store.setReady(true);
}

/** Mount once near the app root. Hydrates auth store from Supabase and keeps it in sync. */
export function useSessionListener() {
  const qc = useQueryClient();
  useEffect(() => {
    let mounted = true;

    supabase.auth.getSession().then(({ data }) => {
      if (!mounted) return;
      const user = data.session?.user;
      if (user) hydrate(user.id, user.email ?? null);
      else useAuthStore.getState().setReady(true);
    });

    const { data: sub } = supabase.auth.onAuthStateChange((event, session) => {
      if (event !== "SIGNED_IN" && event !== "SIGNED_OUT" && event !== "USER_UPDATED") return;
      const user = session?.user;
      if (user) {
        hydrate(user.id, user.email ?? null);
        if (event !== "SIGNED_OUT") qc.invalidateQueries();
      } else {
        useAuthStore.getState().reset();
        useAuthStore.getState().setReady(true);
      }
    });

    return () => {
      mounted = false;
      sub.subscription.unsubscribe();
    };
  }, [qc]);
}
