import { cn } from "@/lib/utils";
import type { ReactNode } from "react";

interface Props {
  variant?: "neutral" | "success" | "warning" | "danger" | "info" | "primary";
  children: ReactNode;
  className?: string;
}

const styles: Record<NonNullable<Props["variant"]>, string> = {
  neutral: "bg-surface-muted text-text border-border",
  success: "bg-success-soft text-success border-success/20",
  warning: "bg-warning-soft text-warning border-warning/20",
  danger: "bg-danger-soft text-danger border-danger/20",
  info: "bg-info-soft text-info border-info/20",
  primary: "bg-primary-soft text-primary border-primary/20",
};

export function StatusBadge({ variant = "neutral", children, className }: Props) {
  return (
    <span
      className={cn(
        "inline-flex items-center gap-1 rounded-md border px-1.5 py-0.5 text-[11px] font-medium whitespace-nowrap",
        styles[variant],
        className,
      )}
    >
      {children}
    </span>
  );
}

export function statusVariantFor(status: string): Props["variant"] {
  const s = status.toLowerCase();
  if (["active", "approved", "eligible", "published", "open", "low"].includes(s)) return "success";
  if (["pending", "pending_activation", "under_review", "submitted", "scheduled", "for_evaluation", "medium"].includes(s)) return "warning";
  if (["rejected", "ineligible", "invalid", "locked", "suspicious", "high", "closed"].includes(s)) return "danger";
  if (["resubmission", "resubmission_required", "duplicate"].includes(s)) return "info";
  if (["draft", "inactive", "archived", "not_submitted"].includes(s)) return "neutral";
  return "neutral";
}

export function formatStatus(s: string) {
  return s.replace(/_/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());
}
