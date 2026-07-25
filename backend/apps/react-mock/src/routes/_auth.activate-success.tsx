import { createFileRoute, Link } from "@tanstack/react-router";
import { IconCheck } from "@tabler/icons-react";

export const Route = createFileRoute("/_auth/activate-success")({
  component: () => (
    <div className="text-center">
      <div className="mx-auto h-12 w-12 rounded-full bg-success-soft text-success grid place-items-center mb-3">
        <IconCheck size={22} />
      </div>
      <h1 className="text-xl font-semibold">Account activated</h1>
      <p className="text-sm text-text-muted mt-1">
        Your student account is now active. You can sign in and start uploading your TES requirements.
      </p>
      <Link to="/login" className="inline-block mt-5 text-sm font-medium text-primary hover:underline">Go to sign in →</Link>
    </div>
  ),
});
