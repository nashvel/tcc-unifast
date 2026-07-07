import { useEffect } from "react";
import { useAuthStore } from "@/stores/authStore";
import { loadSession } from "@/lib/mock-auth";

/** Mount once near the app root. Hydrates auth store from the local mock session. */
export function useSessionListener() {
  useEffect(() => {
    const s = loadSession();
    const store = useAuthStore.getState();
    if (s) {
      store.setSession({ userId: s.userId, email: s.email });
      store.setProfile(s.profile);
      store.setRole(s.role);
    }
    store.setReady(true);

    function onStorage(e: StorageEvent) {
      if (e.key !== "unifast.mock.session") return;
      const next = loadSession();
      const st = useAuthStore.getState();
      if (next) {
        st.setSession({ userId: next.userId, email: next.email });
        st.setProfile(next.profile);
        st.setRole(next.role);
      } else {
        st.reset();
      }
    }
    window.addEventListener("storage", onStorage);
    return () => window.removeEventListener("storage", onStorage);
  }, []);
}
