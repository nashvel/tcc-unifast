import { createFileRoute, Link } from "@tanstack/react-router";
import { useState } from "react";
import { FormField, TextInput } from "@/components/ui/form-field";
import { Btn } from "@/components/ui/btn";
import { IconCheck } from "@tabler/icons-react";

export const Route = createFileRoute("/_auth/forgot-password")({
  component: ForgotPage,
});

function ForgotPage() {
  const [sent, setSent] = useState(false);
  const [email, setEmail] = useState("");

  if (sent) {
    return (
      <div>
        <div className="h-9 w-9 rounded-full bg-success-soft text-success grid place-items-center mb-3">
          <IconCheck size={18} />
        </div>
        <h1 className="text-xl font-semibold">Check your email</h1>
        <p className="text-sm text-text-muted mt-1">
          If <span className="font-medium">{email}</span> is registered, we&apos;ve sent password reset instructions.
        </p>
        <Link to="/login" className="inline-block mt-5 text-sm text-primary hover:underline">← Back to sign in</Link>
      </div>
    );
  }

  return (
    <div>
      <h1 className="text-xl font-semibold tracking-tight">Forgot password</h1>
      <p className="text-sm text-text-muted mt-1">Enter your email and we&apos;ll send you a reset link.</p>
      <form onSubmit={(e) => { e.preventDefault(); setSent(true); }} className="mt-5 space-y-4">
        <FormField label="Email" required>
          <TextInput value={email} onChange={(e) => setEmail(e.target.value)} placeholder="you@unifast.gov.ph" />
        </FormField>
        <Btn variant="primary" type="submit" className="w-full">Send reset link</Btn>
      </form>
      <Link to="/login" className="inline-block mt-4 text-sm text-primary hover:underline">← Back to sign in</Link>
    </div>
  );
}
