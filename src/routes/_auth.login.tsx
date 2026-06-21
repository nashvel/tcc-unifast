import { createFileRoute, Link, useNavigate } from "@tanstack/react-router";
import { useState } from "react";
import { IconArrowRight, IconLock, IconMail, IconUser } from "@tabler/icons-react";
import { FormField, TextInput } from "@/components/ui/form-field";
import { Btn } from "@/components/ui/btn";
import { useAuthStore, type AppRole } from "@/stores/authStore";

export const Route = createFileRoute("/_auth/login")({
  component: LoginPage,
});

const demoUsers: { role: AppRole; name: string; email: string; label: string }[] = [
  { role: "admin", name: "System Administrator", email: "admin@unifast.gov.ph", label: "Admin" },
  { role: "head", name: "Ricardo Santos", email: "r.santos@unifast.gov.ph", label: "Office Head" },
  { role: "staff", name: "Jessica Cruz", email: "j.cruz@unifast.gov.ph", label: "UniFAST Staff" },
  { role: "student", name: "Maria Clara Dela Cruz", email: "mc.delacruz@plm.edu.ph", label: "Student Grantee" },
];

function LoginPage() {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const login = useAuthStore((s) => s.login);
  const navigate = useNavigate();

  function submit(e: React.FormEvent) {
    e.preventDefault();
    const u = demoUsers.find((d) => d.email === email);
    if (!u || password.length < 4) {
      setError("Invalid email or password. Try a demo login below.");
      return;
    }
    signIn(u);
  }

  function signIn(u: typeof demoUsers[number]) {
    login({ id: u.email, name: u.name, email: u.email, role: u.role, studentNumber: u.role === "student" ? "2024-10000" : undefined });
    navigate({ to: u.role === "student" ? "/student" : "/app" });
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
        <Btn variant="primary" type="submit" className="w-full">Sign in <IconArrowRight size={14} /></Btn>
      </form>

      <div className="flex items-center justify-between mt-4 text-xs">
        <Link to="/forgot-password" className="text-primary hover:underline">Forgot password?</Link>
        <Link to="/activate" className="text-primary hover:underline">Activate your account</Link>
      </div>

      <div className="mt-7 border-t pt-4">
        <p className="text-[11px] uppercase tracking-wider font-semibold text-text-soft mb-2">Demo logins</p>
        <div className="grid grid-cols-2 gap-2">
          {demoUsers.map((u) => (
            <button
              key={u.email}
              onClick={() => signIn(u)}
              className="flex items-center gap-2 rounded-md border bg-surface px-2.5 py-2 text-left hover:bg-surface-muted"
            >
              <IconUser size={14} className="text-text-muted" />
              <div className="leading-tight">
                <p className="text-[12px] font-medium">{u.label}</p>
                <p className="text-[10px] text-text-soft truncate max-w-[120px]">{u.email}</p>
              </div>
            </button>
          ))}
        </div>
      </div>
    </div>
  );
}
