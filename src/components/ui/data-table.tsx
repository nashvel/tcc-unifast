import { cn } from "@/lib/utils";
import type { ReactNode, TableHTMLAttributes, ThHTMLAttributes, TdHTMLAttributes, HTMLAttributes } from "react";

export function DataTable({ className, children, ...props }: TableHTMLAttributes<HTMLTableElement>) {
  return (
    <div className="rounded-lg border bg-surface overflow-x-auto max-w-full [scrollbar-width:thin]">
      <table
        className={cn(
          "w-full min-w-[640px] text-[13px] xl:text-sm table-auto",
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

export function Th({ className, children, ...props }: ThHTMLAttributes<HTMLTableCellElement>) {
  return (
    <th
      className={cn("h-9 px-2 xl:px-3 text-left font-medium whitespace-nowrap border-b", className)}
      {...props}
    >
      {children}
    </th>
  );
}

export function Tr({ className, children, ...props }: HTMLAttributes<HTMLTableRowElement>) {
  return (
    <tr className={cn("border-b last:border-0 hover:bg-surface-muted/40 transition-colors", className)} {...props}>
      {children}
    </tr>
  );
}

export function Td({ className, children, ...props }: TdHTMLAttributes<HTMLTableCellElement>) {
  return (
    <td
      className={cn(
        "px-2 xl:px-3 py-2 align-middle break-words [overflow-wrap:anywhere]",
        className,
      )}
      {...props}
    >
      {children}
    </td>
  );
}
