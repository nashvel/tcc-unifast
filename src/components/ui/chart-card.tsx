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

/** Polished area/line chart drawn with SVG, no external lib */
export function MiniLine({ points, labels }: { points: number[]; labels?: string[] }) {
  const w = 600, h = 180;
  const padL = 32, padR = 12, padT = 12, padB = 22;
  const iw = w - padL - padR;
  const ih = h - padT - padB;
  const max = Math.max(...points, 1);
  const min = Math.min(...points, 0);
  const range = max - min || 1;
  const step = iw / Math.max(points.length - 1, 1);

  const xy = points.map((v, i) => ({
    x: padL + i * step,
    y: padT + ih - ((v - min) / range) * ih,
    v,
  }));

  // Smooth path (Catmull-Rom -> Bezier)
  const line = xy.reduce((acc, pt, i, arr) => {
    if (i === 0) return `M ${pt.x} ${pt.y}`;
    const prev = arr[i - 1];
    const cp1x = prev.x + (pt.x - prev.x) / 2;
    const cp2x = cp1x;
    return `${acc} C ${cp1x} ${prev.y}, ${cp2x} ${pt.y}, ${pt.x} ${pt.y}`;
  }, "");
  const area = `${line} L ${xy[xy.length - 1].x} ${padT + ih} L ${xy[0].x} ${padT + ih} Z`;

  const gridLines = 4;
  const ticks = Array.from({ length: gridLines + 1 }, (_, i) => {
    const val = min + (range * (gridLines - i)) / gridLines;
    const y = padT + (ih * i) / gridLines;
    return { y, val: Math.round(val) };
  });

  const gradId = `area-grad-${points.length}-${points[0]}`;

  return (
    <svg viewBox={`0 0 ${w} ${h}`} className="w-full h-48" preserveAspectRatio="none">
      <defs>
        <linearGradient id={gradId} x1="0" x2="0" y1="0" y2="1">
          <stop offset="0%" stopColor="var(--primary)" stopOpacity="0.35" />
          <stop offset="100%" stopColor="var(--primary)" stopOpacity="0" />
        </linearGradient>
      </defs>

      {/* grid */}
      {ticks.map((t, i) => (
        <g key={i}>
          <line x1={padL} x2={w - padR} y1={t.y} y2={t.y} stroke="currentColor" className="text-border" strokeWidth="1" strokeDasharray="3 3" opacity="0.5" />
          <text x={padL - 6} y={t.y + 3} textAnchor="end" className="fill-text-muted" fontSize="9">{t.val}</text>
        </g>
      ))}

      {/* area + line */}
      <path d={area} fill={`url(#${gradId})`} />
      <path d={line} stroke="var(--primary)" strokeWidth="2" fill="none" strokeLinecap="round" strokeLinejoin="round" />

      {/* dots */}
      {xy.map((pt, i) => (
        <g key={i}>
          <circle cx={pt.x} cy={pt.y} r="3" fill="var(--surface, #fff)" stroke="var(--primary)" strokeWidth="1.5" />
          <title>{labels?.[i] ? `${labels[i]}: ${pt.v}` : `${pt.v}`}</title>
        </g>
      ))}

      {/* x labels */}
      {xy.map((pt, i) => {
        const label = labels?.[i] ?? `${i + 1}`;
        const show = i === 0 || i === xy.length - 1 || i % Math.ceil(xy.length / 7) === 0;
        return show ? (
          <text key={i} x={pt.x} y={h - 6} textAnchor="middle" className="fill-text-muted" fontSize="9">{label}</text>
        ) : null;
      })}
    </svg>
  );
}
