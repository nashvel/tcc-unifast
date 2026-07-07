import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight } from "lucide-react";
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

  const btn =
    "h-7 w-7 grid place-items-center rounded border bg-surface text-text-muted hover:bg-surface-muted disabled:opacity-40 disabled:cursor-not-allowed transition-colors";

  return (
    <div
      className={cn(
        "flex flex-wrap items-center justify-between gap-2 px-3 py-2 border-t bg-surface text-xs",
        className,
      )}
    >
      <div className="flex items-center gap-3 text-text-muted">
        <span>
          {total === 0 ? "0" : `${from}–${to}`} of {total.toLocaleString()}
        </span>
        {onPageSizeChange && (
          <label className="flex items-center gap-1.5">
            Rows:
            <select
              className="h-7 rounded border bg-surface px-1.5 text-xs"
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

      <div className="flex items-center gap-1">
        <button className={btn} disabled={!canPrev} onClick={() => onPageChange(1)} aria-label="First page">
          <ChevronsLeft size={14} />
        </button>
        <button className={btn} disabled={!canPrev} onClick={() => onPageChange(page - 1)} aria-label="Previous page">
          <ChevronLeft size={14} />
        </button>
        <span className="px-2 tabular-nums">
          Page <span className="font-medium text-text">{page}</span> of {pageCount}
        </span>
        <button className={btn} disabled={!canNext} onClick={() => onPageChange(page + 1)} aria-label="Next page">
          <ChevronRight size={14} />
        </button>
        <button className={btn} disabled={!canNext} onClick={() => onPageChange(pageCount)} aria-label="Last page">
          <ChevronsRight size={14} />
        </button>
      </div>
    </div>
  );
}
