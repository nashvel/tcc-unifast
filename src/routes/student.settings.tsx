import { createFileRoute, useNavigate } from "@tanstack/react-router";
import { useMemo, useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import {
  IconDeviceLaptop, IconCheck, IconDownload, IconLogout, IconFilter,
  IconAlertCircle, IconShieldCheck, IconUser, IconKey, IconHistory,
} from "@tabler/icons-react";
import { PageHeader } from "@/components/ui/page-header";
import { ChartCard } from "@/components/ui/chart-card";
import { FormField, TextInput } from "@/components/ui/form-field";
import { Selectish } from "@/components/ui/form-field";
import { Btn } from "@/components/ui/btn";
import { AvatarEditor } from "@/components/ui/avatar-editor";
import { supabase } from "@/integrations/supabase/client";
import { useAuthStore } from "@/stores/authStore";

export const Route = createFileRoute("/student/settings")({
  component: StudentSettings,
});

type LoginEvent = {
  id: string;
  signed_in_at: string;
  ip_address: string | null;
  user_agent: string | null;
};

const DEVICE_KINDS = ["All", "iOS", "Android", "Mac", "Windows", "Linux", "Other"] as const;
type DeviceKind = (typeof DEVICE_KINDS)[number];

function deviceLabel(ua: string | null) {
  if (!ua) return "Unknown device";
  if (/iPhone|iPad/.test(ua)) return "iOS device";
  if (/Android/.test(ua)) return "Android device";
  if (/Mac OS X/.test(ua)) return "Mac";
  if (/Windows/.test(ua)) return "Windows PC";
  if (/Linux/.test(ua)) return "Linux";
  return "Browser";
}

function deviceKind(ua: string | null): DeviceKind {
  if (!ua) return "Other";
  if (/iPhone|iPad/.test(ua)) return "iOS";
  if (/Android/.test(ua)) return "Android";
  if (/Mac OS X/.test(ua)) return "Mac";
  if (/Windows/.test(ua)) return "Windows";
  if (/Linux/.test(ua)) return "Linux";
  return "Other";
}

/* ---------- Password strength ---------- */
function evaluatePassword(pw: string): { score: number; issues: string[] } {
  const issues: string[] = [];
  if (pw.length < 10) issues.push("at least 10 characters");
  if (!/[a-z]/.test(pw)) issues.push("a lowercase letter");
  if (!/[A-Z]/.test(pw)) issues.push("an uppercase letter");
  if (!/[0-9]/.test(pw)) issues.push("a number");
  if (!/[^A-Za-z0-9]/.test(pw)) issues.push("a symbol");
  const COMMON = ["password", "12345678", "qwerty", "letmein", "iloveyou", "admin1234"];
  if (COMMON.some((c) => pw.toLowerCase().includes(c))) issues.push("no common words");
  const score = Math.max(0, 5 - issues.length);
  return { score, issues };
}

function csvEscape(v: unknown) {
  const s = v == null ? "" : String(v);
  return /[",\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
}

function StudentSettings() {
  const userId = useAuthStore((s) => s.userId);
  const email = useAuthStore((s) => s.email);
  const profile = useAuthStore((s) => s.profile);
  const qc = useQueryClient();
  const navigate = useNavigate();

  // password form
  const [current, setCurrent] = useState("");
  const [next, setNext] = useState("");
  const [confirm, setConfirm] = useState("");
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<string | null>(null);
  const [fieldErrs, setFieldErrs] = useState<{ current?: string; next?: string; confirm?: string }>({});
  const [ok, setOk] = useState<string | null>(null);

  // edit profile form
  const setProfileStore = useAuthStore((s) => s.setProfile);
  const [fullName, setFullName] = useState(profile?.full_name ?? "");
  const [profileBusy, setProfileBusy] = useState(false);
  const [profileMsg, setProfileMsg] = useState<{ tone: "ok" | "err"; text: string } | null>(null);

  async function saveProfile(e: React.FormEvent) {
    e.preventDefault();
    if (!userId) return;
    const name = fullName.trim();
    if (!name) {
      setProfileMsg({ tone: "err", text: "Full name is required." });
      return;
    }
    setProfileBusy(true);
    setProfileMsg(null);
    const { error } = await supabase
      .from("profiles")
      .update({ full_name: name })
      .eq("id", userId);
    setProfileBusy(false);
    if (error) {
      setProfileMsg({ tone: "err", text: error.message });
      return;
    }
    if (profile) setProfileStore({ ...profile, full_name: name });
    setProfileMsg({ tone: "ok", text: "Profile updated." });
  }

  // filters
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  const [device, setDevice] = useState<DeviceKind>("All");

  const events = useQuery({
    queryKey: ["login_events", userId],
    enabled: !!userId,
    queryFn: async () => {
      const { data, error } = await supabase
        .from("login_events")
        .select("id, signed_in_at, ip_address, user_agent")
        .order("signed_in_at", { ascending: false })
        .limit(200);
      if (error) throw error;
      return (data ?? []) as LoginEvent[];
    },
  });

  const filtered = useMemo(() => {
    const list = events.data ?? [];
    const fromTs = from ? new Date(from).getTime() : -Infinity;
    const toTs = to ? new Date(to + "T23:59:59").getTime() : Infinity;
    return list.filter((ev) => {
      const t = new Date(ev.signed_in_at).getTime();
      if (t < fromTs || t > toTs) return false;
      if (device !== "All" && deviceKind(ev.user_agent) !== device) return false;
      return true;
    });
  }, [events.data, from, to, device]);

  const pwEval = evaluatePassword(next);
  const strengthLabel = ["Too weak", "Weak", "Fair", "Good", "Strong", "Excellent"][pwEval.score];
  const strengthTone =
    pwEval.score <= 1 ? "bg-danger" : pwEval.score <= 2 ? "bg-warning" : pwEval.score <= 3 ? "bg-info" : "bg-success";

  function validate(): boolean {
    const fe: typeof fieldErrs = {};
    if (!current) fe.current = "Enter your current password.";
    if (!next) fe.next = "Enter a new password.";
    else if (pwEval.issues.length > 0) fe.next = `Password must include ${pwEval.issues.join(", ")}.`;
    else if (current && next === current) fe.next = "New password must be different from the current password.";
    if (!confirm) fe.confirm = "Confirm your new password.";
    else if (next && confirm !== next) fe.confirm = "Passwords do not match.";
    setFieldErrs(fe);
    return Object.keys(fe).length === 0;
  }

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setErr(null); setOk(null);
    if (!validate()) return;
    setBusy(true);
    const { data: u } = await supabase.auth.getUser();
    const userEmail = u.user?.email;
    if (!userEmail) { setBusy(false); setErr("No active session. Please sign in again."); return; }
    const { error: reauthErr } = await supabase.auth.signInWithPassword({ email: userEmail, password: current });
    if (reauthErr) {
      setBusy(false);
      setFieldErrs({ current: "Current password is incorrect." });
      return;
    }
    const { error } = await supabase.auth.updateUser({ password: next });
    setBusy(false);
    if (error) {
      // Surface specific Supabase auth errors clearly
      const msg = /should be different|same_password/i.test(error.message)
        ? "New password must be different from the current password."
        : /weak|pwned|leaked/i.test(error.message)
        ? "This password has appeared in data breaches. Choose a different one."
        : error.message;
      setErr(msg);
      return;
    }
    setOk("Password updated successfully.");
    setCurrent(""); setNext(""); setConfirm(""); setFieldErrs({});
  }

  async function handleSignOut() {
    await qc.cancelQueries();
    qc.clear();
    await supabase.auth.signOut();
    useAuthStore.getState().reset();
    navigate({ to: "/login", replace: true });
  }

  function exportCSV() {
    const headers = ["Signed in at", "Device", "User agent", "IP address"];
    const rows = filtered.map((ev) => [
      new Date(ev.signed_in_at).toISOString(),
      deviceLabel(ev.user_agent),
      ev.user_agent ?? "",
      ev.ip_address ?? "",
    ]);
    const csv = [headers, ...rows].map((r) => r.map(csvEscape).join(",")).join("\n");
    const blob = new Blob([csv], { type: "text/csv;charset=utf-8" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `login-activity-${new Date().toISOString().slice(0, 10)}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  }

  const currentUA = typeof navigator !== "undefined" ? navigator.userAgent : "";

  type Section = "general" | "security" | "sessions";
  const [section, setSection] = useState<Section>("general");

  const nav: { key: Section; label: string; icon: typeof IconUser; hint: string }[] = [
    { key: "general", label: "General", icon: IconUser, hint: "Profile & avatar" },
    { key: "security", label: "Security", icon: IconKey, hint: "Password" },
    { key: "sessions", label: "Sessions", icon: IconHistory, hint: "Current device & history" },
  ];

  return (
    <div>
      <PageHeader
        title="Settings"
        description="Manage your profile, security, and sign-in activity."
        actions={
          <Btn variant="ghost" onClick={handleSignOut}>
            <IconLogout size={14} /> Sign out
          </Btn>
        }
      />

      <div className="grid grid-cols-1 lg:grid-cols-[220px_minmax(0,1fr)] gap-4">
        {/* Side navigation */}
        <nav aria-label="Settings sections" className="rounded-lg border bg-surface p-2 h-fit lg:sticky lg:top-4">
          <ul className="space-y-0.5">
            {nav.map((n) => {
              const Icon = n.icon;
              const active = section === n.key;
              return (
                <li key={n.key}>
                  <button
                    type="button"
                    onClick={() => setSection(n.key)}
                    aria-current={active ? "page" : undefined}
                    className={`w-full flex items-start gap-2 rounded-md px-2.5 py-2 text-left transition-colors ${
                      active ? "bg-sidebar-active text-sidebar-active-text" : "text-text hover:bg-surface-muted"
                    }`}
                  >
                    <Icon size={16} className={active ? "text-sidebar-active-text mt-0.5" : "text-text-muted mt-0.5"} />
                    <span className="min-w-0">
                      <span className="block text-[13px] font-medium leading-tight">{n.label}</span>
                      <span className={`block text-[11px] ${active ? "text-sidebar-active-text/80" : "text-text-muted"}`}>
                        {n.hint}
                      </span>
                    </span>
                  </button>
                </li>
              );
            })}
          </ul>
        </nav>

        {/* Section content */}
        <div className="min-w-0 space-y-3">
        {section === "general" && (
        <ChartCard title="Edit Profile">
          <div className="grid grid-cols-1 md:grid-cols-[auto_minmax(0,1fr)] gap-4 items-start">
            <AvatarEditor />
            <form onSubmit={saveProfile} className="space-y-3 min-w-0" noValidate>
              <FormField label="Full name" required>
                <TextInput
                  value={fullName}
                  onChange={(e) => { setFullName(e.target.value); setProfileMsg(null); }}
                  placeholder="Your full name"
                  autoComplete="name"
                />
              </FormField>
              <FormField label="Email" helper="Contact support to change your email.">
                <TextInput value={email ?? ""} disabled readOnly />
              </FormField>
              {profileMsg && (
                <p className={`text-xs inline-flex items-center gap-1 ${profileMsg.tone === "ok" ? "text-success" : "text-danger"}`}>
                  {profileMsg.tone === "ok" ? <IconCheck size={12} /> : <IconAlertCircle size={12} />}
                  {profileMsg.text}
                </p>
              )}
              <Btn variant="primary" type="submit" disabled={profileBusy || fullName.trim() === (profile?.full_name ?? "")}>
                {profileBusy ? "Saving…" : "Save profile"}
              </Btn>
            </form>
          </div>
        </ChartCard>
        )}

        {section === "security" && (
        <ChartCard title="Change Password">
          <form onSubmit={submit} className="space-y-3" noValidate>
            <FormField label="Current password" required error={fieldErrs.current}>
              <TextInput
                type="password"
                value={current}
                onChange={(e) => { setCurrent(e.target.value); setFieldErrs((p) => ({ ...p, current: undefined })); }}
                placeholder="••••••••"
                autoComplete="current-password"
              />
            </FormField>
            <FormField
              label="New password"
              required
              error={fieldErrs.next}
              helper="Use at least 10 characters with a mix of upper/lowercase letters, a number, and a symbol."
            >
              <TextInput
                type="password"
                value={next}
                onChange={(e) => { setNext(e.target.value); setFieldErrs((p) => ({ ...p, next: undefined })); }}
                placeholder="••••••••"
                autoComplete="new-password"
              />
            </FormField>
            {next && (
              <div className="space-y-1">
                <div className="flex items-center justify-between text-[11px]">
                  <span className="text-text-muted">Strength</span>
                  <span className="font-medium">{strengthLabel}</span>
                </div>
                <div className="h-1.5 rounded-full bg-surface-muted overflow-hidden">
                  <div className={`h-full ${strengthTone} transition-all`} style={{ width: `${(pwEval.score / 5) * 100}%` }} />
                </div>
                {pwEval.issues.length > 0 && (
                  <p className="text-[11px] text-text-soft">Add: {pwEval.issues.join(", ")}.</p>
                )}
              </div>
            )}
            <FormField label="Confirm new password" required error={fieldErrs.confirm}>
              <TextInput
                type="password"
                value={confirm}
                onChange={(e) => { setConfirm(e.target.value); setFieldErrs((p) => ({ ...p, confirm: undefined })); }}
                placeholder="••••••••"
                autoComplete="new-password"
              />
            </FormField>
            {err && (
              <p className="text-xs text-danger inline-flex items-center gap-1">
                <IconAlertCircle size={12} /> {err}
              </p>
            )}
            {ok && (
              <p className="text-xs text-success inline-flex items-center gap-1">
                <IconCheck size={12} /> {ok}
              </p>
            )}
            <Btn variant="primary" type="submit" disabled={busy}>
              {busy ? "Updating…" : "Update password"}
            </Btn>
          </form>
        </ChartCard>
        )}

        {section === "sessions" && (
        <>
        <ChartCard
          title="Current Session"
          actions={
            <Btn variant="ghost" onClick={handleSignOut}>
              <IconLogout size={13} /> Sign out
            </Btn>
          }
        >
          <div className="flex items-start gap-3">
            <div className="h-10 w-10 rounded-md bg-primary-soft text-primary grid place-items-center shrink-0">
              <IconShieldCheck size={18} />
            </div>
            <div className="min-w-0 text-sm">
              <p className="font-medium truncate">{profile?.full_name || email || "Signed-in user"}</p>
              <p className="text-[11px] text-text-muted truncate">{email}</p>
              <p className="text-[11px] text-text-muted mt-1">
                This device: <span className="font-medium text-text">{deviceLabel(currentUA)}</span>
              </p>
            </div>
          </div>
          <p className="mt-3 text-[10px] text-text-soft">
            Signing out clears your session on this device only. To end other sessions, change your password.
          </p>
        </ChartCard>

        {/* Login Activity */}
        <ChartCard
          title="Login Activity"
          actions={
            <Btn variant="ghost" onClick={exportCSV} disabled={filtered.length === 0}>
              <IconDownload size={13} /> Export CSV
            </Btn>
          }
        >
          {/* Filters */}
          <div className="grid grid-cols-1 sm:grid-cols-4 gap-2 mb-3">
            <FormField label="From">
              <TextInput type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
            </FormField>
            <FormField label="To">
              <TextInput type="date" value={to} onChange={(e) => setTo(e.target.value)} />
            </FormField>
            <FormField label="Device">
              <Selectish value={device} onChange={(e) => setDevice(e.target.value as DeviceKind)}>
                {DEVICE_KINDS.map((d) => <option key={d} value={d}>{d}</option>)}
              </Selectish>
            </FormField>
            <div className="flex items-end">
              <Btn
                variant="ghost"
                onClick={() => { setFrom(""); setTo(""); setDevice("All"); }}
                disabled={!from && !to && device === "All"}
              >
                <IconFilter size={13} /> Reset
              </Btn>
            </div>
          </div>

          {events.isLoading ? (
            <p className="text-xs text-text-muted">Loading…</p>
          ) : filtered.length === 0 ? (
            <p className="text-xs text-text-muted">No sign-ins match the current filters.</p>
          ) : (
            <ul className="divide-y max-h-[420px] overflow-y-auto">
              {filtered.map((ev, idx) => (
                <li key={ev.id} className="flex items-start gap-3 py-2.5 text-sm">
                  <div className="h-8 w-8 rounded-md bg-surface-muted grid place-items-center text-text-muted shrink-0">
                    <IconDeviceLaptop size={14} />
                  </div>
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2">
                      <p className="font-medium truncate">{deviceLabel(ev.user_agent)}</p>
                      {idx === 0 && !from && !to && device === "All" && (
                        <span className="text-[10px] px-1.5 py-0.5 rounded bg-success-soft text-success font-medium">
                          Most recent
                        </span>
                      )}
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
          <p className="mt-3 text-[10px] text-text-soft">
            Showing {filtered.length} of {events.data?.length ?? 0} recent sign-ins. Contact your office if you see activity you don't recognize.
          </p>
        </ChartCard>
      </div>
    </div>
  );
}
