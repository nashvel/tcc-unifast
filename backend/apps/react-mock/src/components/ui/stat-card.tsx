import { cn } from "@/lib/utils";
import type { ComponentType, ReactNode } from "react";

interface Props {
  label: string;
  value: string | number;
  icon?: ComponentType<{ size?: number; className?: string }>;
  tone?: "neutral" | "primary" | "success" | "warning" | "danger" | "info";
  hint?: ReactNode;
  className?: string;
}

const toneMap = {
  neutral: "text-text-muted bg-surface-muted",
  primary: "text-primary bg-primary-soft",
  success: "text-success bg-success-soft",
  warning: "text-warning bg-warning-soft",
  danger: "text-danger bg-danger-soft",
  info: "text-info bg-info-soft",
};

export function StatCard({ label, value, icon: Icon, tone = "neutral", hint, className }: Props) {
  return (
    <div className={cn("rounded-lg border bg-surface p-4 flex items-start gap-3", className)}>
      {Icon && (
        <div className={cn("rounded-md p-2", toneMap[tone])}>
          <Icon size={18} />
        </div>
      )}
      <div className="min-w-0 flex-1">
        <p className="text-xs font-medium text-text-muted truncate">{label}</p>
        <p className="text-xl font-semibold tabular-nums mt-0.5">{value}</p>
        {hint && <div className="text-micro text-text-soft mt-0.5">{hint}</div>}
      </div>
    </div>
  );
}
