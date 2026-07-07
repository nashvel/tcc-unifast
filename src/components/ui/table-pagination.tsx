import {
  IconChevronLeft,
  IconChevronRight,
  IconChevronsLeft,
  IconChevronsRight,
} from "@tabler/icons-react";
import { cn } from "@/lib/utils";

interface Props {
  page: number;
  pageCount: number;
  pageSize: number;
  total: number;
  from: number;
  to: number;
  onPageChange: (n: number) => void;
  onPageSizeChange?: (n: number) => void;
  pageSizeOptions?: number[];
  className?: string;
}

export function TablePagination({
  page,
  pageCount,
  pageSize,
  total,
  from,
  to,
  onPageChange,
  onPageSizeChange,
  pageSizeOptions = [10, 25, 50, 100],
  className,
}: Props) {
  const canPrev = page > 1;
  const canNext = page < pageCount;

  return (
    <div
      className={cn(
        "flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-3 py-1.5 border-t bg-surface text-[11px] text-text-muted",
        className,
      )}
    >
      <div className="flex items-center gap-3">
        <span className="tabular-nums">
          <span className="font-medium text-text">
            {total === 0 ? "0" : `${from.toLocaleString()}–${to.toLocaleString()}`}
          </span>{" "}
          of <span className="tabular-nums">{total.toLocaleString()}</span>
        </span>
        {onPageSizeChange && (
          <label className="flex items-center gap-1.5">
            <span>Rows per page</span>
            <select
              className="h-6 rounded border bg-surface px-1 pr-4 text-[11px] font-medium text-text hover:bg-surface-muted focus:outline-none focus:ring-1 focus:ring-primary"
              value={pageSize}
              onChange={(e) => onPageSizeChange(Number(e.target.value))}
            >
              {pageSizeOptions.map((n) => (
                <option key={n} value={n}>{n}</option>
              ))}
            </select>
          </label>
        )}
      </div>

      <div className="flex items-center gap-2">
        <span className="tabular-nums">
          Page <span className="font-medium text-text">{page}</span> of{" "}
          <span className="font-medium text-text">{pageCount}</span>
        </span>
        <div className="inline-flex rounded-md border bg-surface shadow-xs overflow-hidden divide-x">
          <PagerBtn label="First page" disabled={!canPrev} onClick={() => onPageChange(1)}>
            <IconChevronsLeft size={13} stroke={2} />
          </PagerBtn>
          <PagerBtn label="Previous page" disabled={!canPrev} onClick={() => onPageChange(page - 1)}>
            <IconChevronLeft size={13} stroke={2} />
          </PagerBtn>
          <PagerBtn label="Next page" disabled={!canNext} onClick={() => onPageChange(page + 1)}>
            <IconChevronRight size={13} stroke={2} />
          </PagerBtn>
          <PagerBtn label="Last page" disabled={!canNext} onClick={() => onPageChange(pageCount)}>
            <IconChevronsRight size={13} stroke={2} />
          </PagerBtn>
        </div>
      </div>
    </div>
  );
}

function PagerBtn({
  children,
  label,
  disabled,
  onClick,
}: {
  children: React.ReactNode;
  label: string;
  disabled?: boolean;
  onClick: () => void;
}) {
  return (
    <button
      type="button"
      aria-label={label}
      title={label}
      disabled={disabled}
      onClick={onClick}
      className={cn(
        "h-6 w-7 grid place-items-center transition-colors",
        disabled
          ? "text-text-soft/60 bg-surface-muted/40 cursor-not-allowed"
          : "text-text-muted hover:bg-surface-muted hover:text-text active:bg-surface-muted/80",
      )}
    >
      {children}
    </button>
  );
}
