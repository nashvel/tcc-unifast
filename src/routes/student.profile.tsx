import { createFileRoute, Link } from "@tanstack/react-router";
import { useEffect, useState } from "react";
import { IconId, IconUser, IconCheck, IconClock, IconPencil } from "@tabler/icons-react";
import { PageHeader } from "@/components/ui/page-header";
import { FormField, TextInput } from "@/components/ui/form-field";
import { Btn } from "@/components/ui/btn";
import { UserAvatar } from "@/components/ui/dicebear-avatar";
import { supabase } from "@/integrations/supabase/client";
import { useAuthStore } from "@/stores/authStore";

export const Route = createFileRoute("/student/profile")({
  component: StudentProfile,
});

function StudentProfile() {
  const profile = useAuthStore((s) => s.profile);
  const email = useAuthStore((s) => s.email);
  const displayName = profile?.full_name || "Maria Clara Dela Cruz";

  return (
    <div>
      <PageHeader
        title="My Profile"
        description="Personal and academic information on file. View only — manage edits from Settings."
        actions={
          <Link to="/student/settings">
            <Btn variant="outline"><IconPencil size={14} /> Edit in Settings</Btn>
          </Link>
        }
      />

      <div className="mb-4 rounded-lg border bg-surface p-4 flex items-center gap-3">
        <UserAvatar seed={email || displayName} path={profile?.avatar_url ?? null} size={56} alt={displayName} />
        <div className="min-w-0">
          <p className="text-sm font-semibold truncate">{displayName}</p>
          <p className="text-xs text-text-muted truncate">{email}</p>
        </div>
      </div>

      <OnboardingStatus />

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div className="rounded-lg border bg-surface p-4 space-y-3">
          <p className="text-sm font-semibold">Personal</p>
          <FormField label="Full name"><TextInput value={displayName} disabled readOnly /></FormField>
          <FormField label="Birthdate"><TextInput type="date" value="2003-05-14" disabled readOnly /></FormField>
          <FormField label="Email"><TextInput value={email ?? "mc.delacruz@plm.edu.ph"} disabled readOnly /></FormField>
          <FormField label="Contact"><TextInput value="+639171234567" disabled readOnly /></FormField>
        </div>
        <div className="rounded-lg border bg-surface p-4 space-y-3">
          <p className="text-sm font-semibold">Academic</p>
          <FormField label="University"><TextInput value="Pamantasan ng Lungsod ng Maynila" disabled readOnly /></FormField>
          <FormField label="Program"><TextInput value="BS Computer Science" disabled readOnly /></FormField>
          <FormField label="Year level"><TextInput value="2" disabled readOnly /></FormField>
          <FormField label="Student #"><TextInput value="2024-00123" disabled readOnly /></FormField>
        </div>
      </div>
    </div>
  );
}

function OnboardingStatus() {
  const userId = useAuthStore((s) => s.userId);
  const [completedAt, setCompletedAt] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (!userId) return;
    let active = true;
    (async () => {
      const { data } = await supabase
        .from("profiles")
        .select("onboarding_completed_at")
        .eq("id", userId)
        .maybeSingle();
      if (!active) return;
      setCompletedAt((data?.onboarding_completed_at as string | null) ?? null);
      setLoading(false);
    })();
    return () => { active = false; };
  }, [userId]);

  const done = !!completedAt;
  const completedLabel = completedAt
    ? new Date(completedAt).toLocaleDateString(undefined, { year: "numeric", month: "short", day: "numeric" })
    : null;

  return (
    <div className="rounded-lg border bg-surface p-4 mb-4">
      <div className="flex items-center justify-between mb-3">
        <div>
          <p className="text-sm font-semibold">Onboarding verification</p>
          <p className="text-xs text-text-muted">
            {loading ? "Loading…" : done ? `Completed on ${completedLabel}` : "Complete your scans on next sign-in."}
          </p>
        </div>
      </div>
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <StatusRow icon={IconId} label="ID scan" done={done} />
        <StatusRow icon={IconUser} label="Face scan" done={done} />
      </div>
    </div>
  );
}

function StatusRow({ icon: Icon, label, done }: { icon: typeof IconId; label: string; done: boolean }) {
  return (
    <div className="flex items-center gap-3 rounded-md border p-3">
      <span className="h-9 w-9 rounded-lg bg-primary-soft text-primary grid place-items-center">
        <Icon size={18} />
      </span>
      <div className="flex-1 min-w-0">
        <p className="text-sm font-medium truncate">{label}</p>
        <p className="text-[11px] text-text-muted">{done ? "Verified" : "Pending"}</p>
      </div>
      <span
        className={`inline-flex items-center gap-1 text-[11px] font-medium px-2 py-1 rounded-full ${
          done ? "bg-success-soft text-success" : "bg-warning-soft text-warning"
        }`}
      >
        {done ? <IconCheck size={12} /> : <IconClock size={12} />}
        {done ? "Completed" : "Pending"}
      </span>
    </div>
  );
}
