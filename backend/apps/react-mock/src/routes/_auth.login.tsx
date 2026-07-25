import { createFileRoute, Link, useNavigate } from "@tanstack/react-router";
import { useState } from "react";
import { IconArrowRight, IconLock, IconMail, IconUser } from "@tabler/icons-react";
import { FormField, TextInput } from "@/components/ui/form-field";
import { Btn } from "@/components/ui/btn";
import { useAuthStore } from "@/stores/authStore";
import { DEMO_USERS, signInAs, signInWithEmail, type DemoRole } from "@/lib/mock-auth";

export const Route = createFileRoute("/_auth/login")({
  component: LoginPage,
});

function LoginPage() {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const navigate = useNavigate();

  function applySession(role: DemoRole) {
    const s = role === "student" ? signInAs("student") : signInAs(role);
    const store = useAuthStore.getState();
    store.setSession({ userId: s.userId, email: s.email });
    store.setProfile(s.profile);
    store.setRole(s.role);
    store.setReady(true);
    navigate({ to: role === "student" ? "/student" : "/app" });
  }

  function quickDemoLogin(role: DemoRole) {
    setError(null);
    setBusy(true);
    try {
      applySession(role);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Login failed");
    } finally {
      setBusy(false);
    }
  }

  function submit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    if (!email || !password) {
      setError("Enter email and password.");
      return;
    }
    setBusy(true);
    try {
      const s = signInWithEmail(email, password);
      const store = useAuthStore.getState();
      store.setSession({ userId: s.userId, email: s.email });
      store.setProfile(s.profile);
      store.setRole(s.role);
      store.setReady(true);
      navigate({ to: s.role === "student" ? "/student" : "/app" });
    } catch (err) {
      setError(err instanceof Error ? err.message : "Login failed");
    } finally {
      setBusy(false);
    }
  }

  return (
    <div>
      <h1 className="text-xl font-semibold tracking-tight">Sign in to your account</h1>
      <p className="text-sm text-text-muted mt-1">Demo mode — any email/password works.</p>

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

        <Btn variant="primary" type="submit" className="w-full" disabled={busy}>
          {busy ? "Signing in…" : <>Sign in <IconArrowRight size={14} /></>}
        </Btn>
      </form>

      <div className="flex items-center justify-between mt-4 text-xs">
        <Link to="/forgot-password" className="text-primary hover:underline">Forgot password?</Link>
        <Link to="/activate" className="text-primary hover:underline">Activate your account</Link>
      </div>

      <div className="mt-7 border-t pt-4">
        <p className="text-micro uppercase tracking-wider font-semibold text-text-soft mb-2">Demo logins</p>
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
                <p className="text-xs font-medium capitalize">{u.role === "head" ? "Office Head" : u.role === "staff" ? "UniFAST Staff" : u.role === "admin" ? "Admin" : "Student"}</p>
                <p className="text-2xs text-text-soft truncate max-w-[140px]">{u.email}</p>
              </div>
            </button>
          ))}
        </div>
        <p className="mt-2 text-2xs text-text-soft">Click a role to sign in instantly with that demo account.</p>
      </div>
    </div>
  );
}
