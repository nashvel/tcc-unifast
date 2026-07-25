import { cn } from "@/lib/utils";
import type { ComponentType, ReactNode } from "react";

interface Props {
  icon?: ComponentType<{ size?: number; className?: string }>;
  title: string;
  description?: string;
  action?: ReactNode;
  className?: string;
}

export function EmptyState({ icon: Icon, title, description, action, className }: Props) {
  return (
    <div className={cn("flex flex-col items-center justify-center text-center py-12 px-6 border border-dashed rounded-lg bg-surface", className)}>
      {Icon && (
        <div className="rounded-full bg-surface-muted p-3 mb-3">
          <Icon size={20} className="text-text-muted" />
        </div>
      )}
      <p className="text-sm font-medium">{title}</p>
      {description && <p className="text-xs text-text-muted mt-1 max-w-sm">{description}</p>}
      {action && <div className="mt-4">{action}</div>}
    </div>
  );
}

export function LoadingState({ label = "Loading…" }: { label?: string }) {
  return (
    <div className="flex items-center justify-center py-12 text-sm text-text-muted">
      <div className="h-4 w-4 mr-2 border-2 border-border-strong border-t-primary rounded-full animate-spin" />
      {label}
    </div>
  );
}
