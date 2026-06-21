import { createFileRoute, Outlet } from "@tanstack/react-router";
import { IconShieldCheck } from "@tabler/icons-react";

export const Route = createFileRoute("/_auth")({
  component: AuthLayout,
});

function AuthLayout() {
  return (
    <div className="min-h-screen bg-bg flex">
      <div className="hidden lg:flex flex-1 flex-col justify-between p-10 bg-surface border-r">
        <div className="flex items-center gap-2">
          <div className="h-8 w-8 rounded-md bg-primary grid place-items-center text-white">
            <IconShieldCheck size={18} />
          </div>
          <div className="leading-tight">
            <p className="text-sm font-semibold">UniFAST TES</p>
            <p className="text-xs text-text-muted">Grantee Management</p>
          </div>
        </div>
        <div>
          <h2 className="text-2xl font-semibold leading-tight tracking-tight max-w-md">
            Tertiary Education Subsidy — Grantee Management & Document Validation
          </h2>
          <p className="text-sm text-text-muted mt-3 max-w-md">
            Validate submissions, track academic records, evaluate eligibility, and communicate with TES grantees from a single workspace.
          </p>
          <ul className="mt-6 space-y-2 text-xs text-text-muted">
            <li>• Centralized masterlist & batch management</li>
            <li>• OCR-assisted document validation</li>
            <li>• Rules-based eligibility evaluation</li>
            <li>• Full audit trail and role-based access</li>
          </ul>
        </div>
        <p className="text-[11px] text-text-soft">© 2026 Commission on Higher Education — UniFAST</p>
      </div>
      <div className="flex-1 flex items-center justify-center p-6">
        <div className="w-full max-w-sm">
          <Outlet />
        </div>
      </div>
    </div>
  );
}
