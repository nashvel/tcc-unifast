import { cn } from "@/lib/utils";
import type { ComponentType, ReactNode } from "react";

interface Item {
  icon?: ComponentType<{ size?: number; className?: string }>;
  title: ReactNode;
  meta?: ReactNode;
  time?: string;
  tone?: "primary" | "success" | "warning" | "danger" | "info" | "neutral";
}

const toneRing: Record<string, string> = {
  primary: "bg-primary-soft text-primary",
  success: "bg-success-soft text-success",
  warning: "bg-warning-soft text-warning",
  danger: "bg-danger-soft text-danger",
  info: "bg-info-soft text-info",
  neutral: "bg-surface-muted text-text-muted",
};

export function ActivityTimeline({ items, className }: { items: Item[]; className?: string }) {
  return (
    <ul className={cn("space-y-3", className)}>
      {items.map((it, i) => {
        const Icon = it.icon;
        return (
          <li key={i} className="flex gap-3">
            <div className={cn("h-7 w-7 rounded-md grid place-items-center shrink-0", toneRing[it.tone ?? "neutral"])}>
              {Icon && <Icon size={14} />}
            </div>
            <div className="min-w-0 flex-1">
              <p className="text-sm leading-tight">{it.title}</p>
              {it.meta && <p className="text-xs text-text-muted mt-0.5">{it.meta}</p>}
            </div>
            {it.time && <span className="text-[11px] text-text-soft whitespace-nowrap">{it.time}</span>}
          </li>
        );
      })}
    </ul>
  );
}
