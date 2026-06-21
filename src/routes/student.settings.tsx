import { createFileRoute } from "@tanstack/react-router";
import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { IconLock, IconHistory, IconCheck, IconDeviceLaptop } from "@tabler/icons-react";
import { PageHeader } from "@/components/ui/page-header";
import { ChartCard } from "@/components/ui/chart-card";
import { FormField, TextInput } from "@/components/ui/form-field";
import { Btn } from "@/components/ui/btn";
import { supabase } from "@/integrations/supabase/client";
import { useAuthStore } from "@/stores/authStore";

export const Route = createFileRoute("/student/settings")({
  component: StudentSettings,
});

function StudentSettings() {
  const userId = useAuthStore((s) => s.userId);
  const [current, setCurrent] = useState("");
  const [next, setNext] = useState("");
  const [confirm, setConfirm] = useState("");
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<string | null>(null);
  const [ok, setOk] = useState<string | null>(null);

  const events = useQuery({
    queryKey: ["login_events", userId],
    enabled: !!userId,
    queryFn: async () => {
      const { data, error } = await supabase
        .from("login_events")
        .select("id, signed_in_at, ip_address, user_agent")
        .order("signed_in_at", { ascending: false })
        .limit(25);
      if (error) throw error;
      return data ?? [];
    },
  });

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setErr(null); setOk(null);
    if (next.length < 8) { setErr("New password must be at least 8 characters."); return; }
    if (next !== confirm) { setErr("Passwords do not match."); return; }
    setBusy(true);
    // Reauthenticate by attempting a sign-in with current password
    const { data: u } = await supabase.auth.getUser();
    const email = u.user?.email;
    if (!email) { setBusy(false); setErr("No user session."); return; }
    const { error: reauthErr } = await supabase.auth.signInWithPassword({ email, password: current });
    if (reauthErr) { setBusy(false); setErr("Current password is incorrect."); return; }
    const { error } = await supabase.auth.updateUser({ password: next });
    setBusy(false);
    if (error) { setErr(error.message); return; }
    setOk("Password updated successfully.");
    setCurrent(""); setNext(""); setConfirm("");
  }

  function deviceLabel(ua: string | null) {
    if (!ua) return "Unknown device";
    if (/iPhone|iPad/.test(ua)) return "iOS device";
    if (/Android/.test(ua)) return "Android device";
    if (/Mac OS X/.test(ua)) return "Mac";
    if (/Windows/.test(ua)) return "Windows PC";
    if (/Linux/.test(ua)) return "Linux";
    return "Browser";
  }

  return (
    <div>
      <PageHeader title="Settings" description="Manage your password and review recent sign-in activity." />

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-3">
        <ChartCard title="Change Password" icon={<IconLock size={14} />}>
          <form onSubmit={submit} className="space-y-3">
            <FormField label="Current password" required>
              <TextInput type="password" value={current} onChange={(e) => setCurrent(e.target.value)} placeholder="••••••••" />
            </FormField>
            <FormField label="New password" required help="At least 8 characters.">
              <TextInput type="password" value={next} onChange={(e) => setNext(e.target.value)} placeholder="••••••••" />
            </FormField>
            <FormField label="Confirm new password" required>
              <TextInput type="password" value={confirm} onChange={(e) => setConfirm(e.target.value)} placeholder="••••••••" />
            </FormField>
            {err && <p className="text-xs text-danger">{err}</p>}
            {ok && <p className="text-xs text-success inline-flex items-center gap-1"><IconCheck size={12} /> {ok}</p>}
            <Btn variant="primary" type="submit" disabled={busy}>
              {busy ? "Updating…" : "Update password"}
            </Btn>
          </form>
        </ChartCard>

        <ChartCard title="Login Activity" icon={<IconHistory size={14} />}>
          {events.isLoading ? (
            <p className="text-xs text-text-muted">Loading…</p>
          ) : !events.data || events.data.length === 0 ? (
            <p className="text-xs text-text-muted">No login activity recorded yet.</p>
          ) : (
            <ul className="divide-y max-h-[420px] overflow-y-auto">
              {events.data.map((ev, idx) => (
                <li key={ev.id} className="flex items-start gap-3 py-2.5 text-sm">
                  <div className="h-8 w-8 rounded-md bg-surface-muted grid place-items-center text-text-muted shrink-0">
                    <IconDeviceLaptop size={14} />
                  </div>
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2">
                      <p className="font-medium truncate">{deviceLabel(ev.user_agent)}</p>
                      {idx === 0 && <span className="text-[10px] px-1.5 py-0.5 rounded bg-success-soft text-success font-medium">Current</span>}
                    </div>
                    <p className="text-[11px] text-text-muted">
                      {new Date(ev.signed_in_at).toLocaleString()}
                      {ev.ip_address ? ` · ${ev.ip_address}` : ""}
                    </p>
                  </div>
                </li>
              ))}
            </ul>
          )}
          <p className="mt-3 text-[10px] text-text-soft">Showing the last 25 sign-ins. Contact your office if you see activity you don't recognize.</p>
        </ChartCard>
      </div>
    </div>
  );
}
