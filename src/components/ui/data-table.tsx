import { cn } from "@/lib/utils";
import type { ReactNode, TableHTMLAttributes, ThHTMLAttributes, TdHTMLAttributes, HTMLAttributes } from "react";

export function DataTable({ className, children, ...props }: TableHTMLAttributes<HTMLTableElement>) {
  return (
    <div className="rounded-lg border bg-surface overflow-x-auto">
      <table className={cn("w-full text-sm", className)} {...props}>
        {children}
      </table>
    </div>
  );
}

export function THead({ children }: { children: ReactNode }) {
  return (
    <thead className="bg-surface-muted/60 text-text-muted text-[11px] uppercase tracking-wide">
      {children}
    </thead>
  );
}

export function Th({ className, children, ...props }: ThHTMLAttributes<HTMLTableCellElement>) {
  return (
    <th
      className={cn("h-9 px-3 text-left font-medium whitespace-nowrap border-b", className)}
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
    <td className={cn("px-3 py-2 align-middle", className)} {...props}>
      {children}
    </td>
  );
}
