import { createFileRoute, Link, useNavigate } from "@tanstack/react-router";
import { useState } from "react";
import { FormField, TextInput } from "@/components/ui/form-field";
import { Btn } from "@/components/ui/btn";
import { useMasterlist } from "@/hooks/queries";
import { IconAlertTriangle, IconShieldCheck } from "@tabler/icons-react";

export const Route = createFileRoute("/_auth/activate")({
  component: ActivatePage,
});

type Step = "verify" | "setPassword";

function ActivatePage() {
  const navigate = useNavigate();
  const { data: rows = [] } = useMasterlist();
  const [step, setStep] = useState<Step>("verify");
  const [studentNumber, setStudentNumber] = useState("");
  const [birthdate, setBirthdate] = useState("");
  const [contact, setContact] = useState("");
  const [password, setPassword] = useState("");
  const [confirm, setConfirm] = useState("");
  const [error, setError] = useState<string | null>(null);

  function verify(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    const match = rows.find(
      (r) =>
        r.student_number === studentNumber.trim() &&
        r.birthdate === birthdate &&
        (r.email === contact.trim() || r.contact === contact.trim()),
    );
    if (!match) {
      setError("We couldn't find a matching record. Student accounts cannot self-register — please confirm your details with your school's TES coordinator.");
      return;
    }
    if (match.account_status === "active") {
      setError("This account is already active. Please sign in instead.");
      return;
    }
    if (match.account_status === "duplicate" || match.account_status === "invalid") {
      setError("This record is flagged in the masterlist. Please contact your school's TES coordinator.");
      return;
    }
    setStep("setPassword");
  }

  function finalize(e: React.FormEvent) {
    e.preventDefault();
    if (password.length < 8) return setError("Password must be at least 8 characters.");
    if (password !== confirm) return setError("Passwords do not match.");
    // Account creation flow is mocked here — in production this would call
    // a server fn to invite the student via the Auth Admin API.
    navigate({ to: "/activate-success" });
  }

  return (
    <div>
      <div className="flex items-center gap-2 mb-3">
        <div className="h-8 w-8 rounded-md bg-primary-soft text-primary grid place-items-center">
          <IconShieldCheck size={16} />
        </div>
        <p className="text-[11px] uppercase tracking-wider font-semibold text-text-soft">Account Activation</p>
      </div>
      <h1 className="text-xl font-semibold tracking-tight">
        {step === "verify" ? "Activate your existing account" : "Set your password"}
      </h1>
      <p className="text-sm text-text-muted mt-1">
        {step === "verify"
          ? "Student accounts are pre-created from the TES masterlist. Verify your identity to activate."
          : "Choose a strong password. You'll use this to sign in to the student portal."}
      </p>

      {step === "verify" ? (
        <form onSubmit={verify} className="mt-5 space-y-4">
          <FormField label="Student Number" required helper="As listed on your school record.">
            <TextInput value={studentNumber} onChange={(e) => setStudentNumber(e.target.value)} placeholder="2024-10000" />
          </FormField>
          <FormField label="Birthdate" required>
            <TextInput type="date" value={birthdate} onChange={(e) => setBirthdate(e.target.value)} />
          </FormField>
          <FormField label="Registered Email or Contact Number" required helper="Must match what was submitted to UniFAST.">
            <TextInput value={contact} onChange={(e) => setContact(e.target.value)} placeholder="email or +63..." />
          </FormField>
          {error && (
            <div className="flex gap-2 rounded-md border border-danger/30 bg-danger-soft p-2.5 text-xs text-danger">
              <IconAlertTriangle size={14} className="shrink-0 mt-0.5" /> <span>{error}</span>
            </div>
          )}
          <Btn variant="primary" type="submit" className="w-full">Verify identity</Btn>
        </form>
      ) : (
        <form onSubmit={finalize} className="mt-5 space-y-4">
          <FormField label="New Password" required helper="Minimum 8 characters, include letters and numbers.">
            <TextInput type="password" value={password} onChange={(e) => setPassword(e.target.value)} />
          </FormField>
          <FormField label="Confirm Password" required>
            <TextInput type="password" value={confirm} onChange={(e) => setConfirm(e.target.value)} />
          </FormField>
          {error && <p className="text-xs text-danger">{error}</p>}
          <Btn variant="primary" type="submit" className="w-full">Activate account</Btn>
        </form>
      )}
      <Link to="/login" className="inline-block mt-4 text-sm text-primary hover:underline">← Back to sign in</Link>
    </div>
  );
}
