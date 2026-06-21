import { cn } from "@/lib/utils";
import type { ReactNode } from "react";

interface Props {
  title: string;
  description?: string;
  actions?: ReactNode;
  className?: string;
  bodyClassName?: string;
  children: ReactNode;
}

export function ChartCard({ title, description, actions, className, bodyClassName, children }: Props) {
  return (
    <div className={cn("rounded-lg border bg-surface", className)}>
      <div className="flex items-start justify-between px-4 pt-3 pb-2 border-b">
        <div>
          <p className="text-sm font-semibold">{title}</p>
          {description && <p className="text-xs text-text-muted mt-0.5">{description}</p>}
        </div>
        {actions}
      </div>
      <div className={cn("p-4", bodyClassName)}>{children}</div>
    </div>
  );
}

/** Simple inline bar chart using divs */
export function MiniBars({ data }: { data: { label: string; value: number; tone?: "primary" | "success" | "warning" | "danger" | "info" }[] }) {
  const max = Math.max(...data.map((d) => d.value), 1);
  const toneClass: Record<string, string> = {
    primary: "bg-primary", success: "bg-success", warning: "bg-warning", danger: "bg-danger", info: "bg-info",
  };
  return (
    <div className="space-y-2.5">
      {data.map((d) => (
        <div key={d.label}>
          <div className="flex items-center justify-between text-xs mb-1">
            <span className="text-text-muted">{d.label}</span>
            <span className="tabular-nums font-medium">{d.value.toLocaleString()}</span>
          </div>
          <div className="h-1.5 rounded-full bg-surface-muted overflow-hidden">
            <div
              className={cn("h-full rounded-full", toneClass[d.tone ?? "primary"])}
              style={{ width: `${(d.value / max) * 100}%` }}
            />
          </div>
        </div>
      ))}
    </div>
  );
}

/** Pseudo line chart drawn with SVG, no external lib */
export function MiniLine({ points }: { points: number[] }) {
  const w = 220, h = 60, p = 4;
  const max = Math.max(...points, 1);
  const step = (w - p * 2) / Math.max(points.length - 1, 1);
  const d = points
    .map((v, i) => `${i === 0 ? "M" : "L"} ${p + i * step} ${h - p - (v / max) * (h - p * 2)}`)
    .join(" ");
  return (
    <svg viewBox={`0 0 ${w} ${h}`} className="w-full h-16">
      <path d={d} stroke="var(--primary)" strokeWidth="1.5" fill="none" />
      <path d={`${d} L ${p + (points.length - 1) * step} ${h - p} L ${p} ${h - p} Z`} fill="var(--primary-soft)" opacity="0.5" />
    </svg>
  );
}
