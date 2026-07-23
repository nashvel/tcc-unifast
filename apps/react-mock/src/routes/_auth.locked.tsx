import { createFileRoute, Link } from "@tanstack/react-router";
import { IconLock } from "@tabler/icons-react";

export const Route = createFileRoute("/_auth/locked")({
  component: () => (
    <div className="text-center">
      <div className="mx-auto h-12 w-12 rounded-full bg-danger-soft text-danger grid place-items-center mb-3">
        <IconLock size={22} />
      </div>
      <h1 className="text-xl font-semibold">Account locked or inactive</h1>
      <p className="text-sm text-text-muted mt-1 max-w-xs mx-auto">
        Your account has been temporarily disabled. Please contact the UniFAST Office or your school's TES coordinator for assistance.
      </p>
      <Link to="/login" className="inline-block mt-5 text-sm font-medium text-primary hover:underline">← Back to sign in</Link>
    </div>
  ),
});
