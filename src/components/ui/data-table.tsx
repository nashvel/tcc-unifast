import { cn } from "@/lib/utils";
import type { ReactNode, TableHTMLAttributes, ThHTMLAttributes, TdHTMLAttributes, HTMLAttributes } from "react";

/**
 * Column visibility priority.
 *   "essential" — always visible (default)
 *   "high"      — hidden below sm (<640px)
 *   "medium"    — hidden below md (<768px)
 *   "low"       — hidden below lg (<1024px)
 *   "optional"  — hidden below xl (<1280px)
 */
export type ColPriority = "essential" | "high" | "medium" | "low" | "optional";

const priorityClass: Record<ColPriority, string> = {
  essential: "",
  high: "hidden sm:table-cell",
  medium: "hidden md:table-cell",
  low: "hidden lg:table-cell",
  optional: "hidden xl:table-cell",
};

export function DataTable({ className, children, ...props }: TableHTMLAttributes<HTMLTableElement>) {
  return (
    <div className="rounded-lg border bg-surface overflow-x-auto max-w-full [scrollbar-width:thin]">
      <table
        className={cn(
          "w-full min-w-[480px] text-[13px] xl:text-sm table-auto",
          className,
        )}
        {...props}
      >
        {children}
      </table>
    </div>
  );
}

export function THead({ children }: { children: ReactNode }) {
  return (
    <thead className="bg-surface-muted/60 text-text-muted text-[10px] xl:text-[11px] uppercase tracking-wide sticky top-0 z-10">
      {children}
    </thead>
  );
}

const stickyClass =
  "sticky left-0 z-20 bg-surface before:absolute before:inset-y-0 before:-right-2 before:w-2 before:pointer-events-none before:bg-gradient-to-r before:from-black/10 before:to-transparent";
const stickyHeadClass =
  "sticky left-0 z-30 bg-surface-muted/95 backdrop-blur before:absolute before:inset-y-0 before:-right-2 before:w-2 before:pointer-events-none before:bg-gradient-to-r before:from-black/10 before:to-transparent";

type ThProps = ThHTMLAttributes<HTMLTableCellElement> & {
  priority?: ColPriority;
  sticky?: boolean;
};

export function Th({ className, children, priority = "essential", sticky, ...props }: ThProps) {
  return (
    <th
      className={cn(
        "h-9 px-2 xl:px-3 text-left font-medium whitespace-nowrap border-b",
        priorityClass[priority],
        sticky && stickyHeadClass,
        className,
      )}
      {...props}
    >
      {children}
    </th>
  );
}

export function Tr({ className, children, ...props }: HTMLAttributes<HTMLTableRowElement>) {
  return (
    <tr
      className={cn(
        "group border-b last:border-0 hover:bg-surface-muted/40 [&:hover_td.is-sticky]:bg-surface-muted/40 transition-colors",
        className,
      )}
      {...props}
    >
      {children}
    </tr>
  );
}

type TdProps = TdHTMLAttributes<HTMLTableCellElement> & {
  truncate?: boolean | number; // true = default max-w, number = px
  title?: string;
  priority?: ColPriority;
  sticky?: boolean;
};

export function Td({ className, children, truncate, title, priority = "essential", sticky, ...props }: TdProps) {
  const shouldTruncate = truncate !== undefined && truncate !== false;
  const maxW =
    typeof truncate === "number" ? `${truncate}px` : shouldTruncate ? "16ch" : undefined;
  const autoTitle =
    title ?? (shouldTruncate && typeof children === "string" ? children : undefined);

  return (
    <td
      className={cn(
        "px-2 xl:px-3 py-2 align-middle",
        shouldTruncate
          ? "whitespace-nowrap overflow-hidden text-ellipsis"
          : "break-words [overflow-wrap:anywhere]",
        priorityClass[priority],
        sticky && `is-sticky ${stickyClass}`,
        className,
      )}
      style={shouldTruncate ? { maxWidth: maxW } : undefined}
      title={autoTitle}
      {...props}
    >
      {children}
    </td>
  );
}
}
