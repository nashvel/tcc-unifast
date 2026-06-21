import { createFileRoute, Link, useNavigate } from "@tanstack/react-router";
import { useState } from "react";
import { IconArrowRight, IconLock, IconMail, IconUser } from "@tabler/icons-react";
import { FormField, TextInput } from "@/components/ui/form-field";
import { Btn } from "@/components/ui/btn";
import { supabase } from "@/integrations/supabase/client";
import { useServerFn } from "@tanstack/react-start";
import { DEMO_USERS, getDemoCredentials } from "@/lib/demo-seed.functions";

export const Route = createFileRoute("/_auth/login")({
  component: LoginPage,
});

function LoginPage() {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const navigate = useNavigate();
  const getDemoCredsFn = useServerFn(getDemoCredentials);

  async function quickDemoLogin(role: "admin" | "head" | "staff" | "student") {
    setError(null);
    setBusy(true);
    try {
      const creds = await getDemoCredsFn({ data: { role } });
      setEmail(creds.email);
      setPassword(creds.password);
      await signInWith(creds.email, creds.password);
    } catch (e) {
      setBusy(false);
      setError(e instanceof Error ? e.message : "Demo login failed.");
    }
  }

  async function signInWith(emailValue: string, passwordValue: string) {
    setError(null);
    setBusy(true);
    const { data, error } = await supabase.auth.signInWithPassword({ email: emailValue, password: passwordValue });
    setBusy(false);
    if (error || !data.user) {
      setError(error?.message ?? "Sign in failed.");
      return;
    }
    // Look up role to decide which area to land on
    const { data: roleRow } = await supabase.from("user_roles").select("role").eq("user_id", data.user.id).maybeSingle();
    const role = (roleRow?.role as string | undefined) ?? "student";
    navigate({ to: role === "student" ? "/student" : "/app" });
  }

  function submit(e: React.FormEvent) {
    e.preventDefault();
    signInWith(email, password);
  }

  async function runSeed() {
    setSeeding(true);
    setError(null);
    setInfo(null);
    try {
      await seedFn();
      setInfo("Demo accounts ready. Click any role below to sign in.");
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to seed demo accounts.");
    } finally {
      setSeeding(false);
    }
  }

  return (
    <div>
      <h1 className="text-xl font-semibold tracking-tight">Sign in to your account</h1>
      <p className="text-sm text-text-muted mt-1">Use your registered UniFAST credentials.</p>

      <form onSubmit={submit} className="mt-6 space-y-4">
        <FormField label="Email" required>
          <div className="relative">
            <IconMail size={14} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-text-soft" />
            <TextInput value={email} onChange={(e) => setEmail(e.target.value)} className="pl-8" placeholder="you@unifast.gov.ph" />
          </div>
        </FormField>
        <FormField label="Password" required>
          <div className="relative">
            <IconLock size={14} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-text-soft" />
            <TextInput type="password" value={password} onChange={(e) => setPassword(e.target.value)} className="pl-8" placeholder="••••••••" />
          </div>
        </FormField>
        {error && <p className="text-xs text-danger">{error}</p>}
        {info && <p className="text-xs text-success">{info}</p>}
        <Btn variant="primary" type="submit" className="w-full" disabled={busy}>
          {busy ? "Signing in…" : <>Sign in <IconArrowRight size={14} /></>}
        </Btn>
      </form>

      <div className="flex items-center justify-between mt-4 text-xs">
        <Link to="/forgot-password" className="text-primary hover:underline">Forgot password?</Link>
        <Link to="/activate" className="text-primary hover:underline">Activate your account</Link>
      </div>

      <div className="mt-7 border-t pt-4">
        <div className="flex items-center justify-between mb-2">
          <p className="text-[11px] uppercase tracking-wider font-semibold text-text-soft">Demo logins</p>
          <button onClick={runSeed} disabled={seeding} className="inline-flex items-center gap-1 text-[11px] text-primary hover:underline disabled:opacity-50">
            <IconSparkles size={11} /> {seeding ? "Seeding…" : "Seed demo data"}
          </button>
        </div>
        <div className="grid grid-cols-2 gap-2">
          {DEMO_USERS.map((u) => (
            <button
              key={u.email}
              type="button"
              onClick={() => quickDemoLogin(u.role)}
              disabled={busy}
              className="flex items-center gap-2 rounded-md border bg-surface px-2.5 py-2 text-left hover:bg-surface-muted disabled:opacity-50"
            >
              <IconUser size={14} className="text-text-muted" />
              <div className="leading-tight">
                <p className="text-[12px] font-medium capitalize">{u.role === "head" ? "Office Head" : u.role === "staff" ? "UniFAST Staff" : u.role === "admin" ? "Admin" : "Student"}</p>
                <p className="text-[10px] text-text-soft truncate max-w-[140px]">{u.email}</p>
              </div>
            </button>
          ))}
        </div>
        <p className="mt-2 text-[10px] text-text-soft">Click a role to sign in instantly with that demo account.</p>
      </div>
    </div>
  );
}
