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
  /** True while the underlying data is loading — disables nav; size select stays enabled */
  isLoading?: boolean;
  /** Hard-disable all controls (e.g. error state) */
  disabled?: boolean;
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
  isLoading = false,
  disabled = false,
  className,
}: Props) {
  const isEmpty = total === 0;
  // Nav is unusable when disabled, loading, empty, or only one page exists.
  const navLocked = disabled || isLoading || isEmpty || pageCount <= 1;
  const canPrev = !navLocked && page > 1;
  const canNext = !navLocked && page < pageCount;
  const sizeLocked = disabled; // stay usable during loading so users can change size

  const clamp = (n: number) => Math.min(pageCount, Math.max(1, n));
  const onKeyDown = (e: React.KeyboardEvent) => {
    if (navLocked) return;
    const t = e.target as HTMLElement;
    if (t.tagName === "SELECT" || t.tagName === "INPUT" || t.tagName === "TEXTAREA") return;
    switch (e.key) {
      case "ArrowLeft": e.preventDefault(); if (canPrev) onPageChange(clamp(page - 1)); break;
      case "ArrowRight": e.preventDefault(); if (canNext) onPageChange(clamp(page + 1)); break;
      case "Home": e.preventDefault(); if (canPrev) onPageChange(1); break;
      case "End": e.preventDefault(); if (canNext) onPageChange(pageCount); break;
      case "PageUp": e.preventDefault(); if (canPrev) onPageChange(clamp(page - 5)); break;
      case "PageDown": e.preventDefault(); if (canNext) onPageChange(clamp(page + 5)); break;
    }
  };

  return (
    <nav
      aria-label="Table pagination"
      aria-busy={isLoading || undefined}
      onKeyDown={onKeyDown}
      className={cn(
        "flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-3 py-1.5 border-t bg-surface text-[11px] text-text-muted",
        (disabled || isLoading) && "opacity-80",
        className,
      )}
    >
      <div className="flex items-center gap-3">
        <span className="tabular-nums" aria-live="polite" aria-atomic="true">
          {isLoading ? (
            <span className="text-text-muted">Loading…</span>
          ) : isEmpty ? (
            <span className="text-text-muted">No results</span>
          ) : (
            <>
              <span className="font-medium text-text">
                {from.toLocaleString()}–{to.toLocaleString()}
              </span>{" "}
              of <span className="tabular-nums">{total.toLocaleString()}</span>
              <span className="sr-only"> results</span>
            </>
          )}
        </span>
        {onPageSizeChange && (
          <label className={cn("flex items-center gap-1.5", sizeLocked && "opacity-50")}>
            <span>Rows per page</span>
            <select
              aria-label="Rows per page"
              disabled={sizeLocked}
              className="h-6 rounded border bg-surface px-1 pr-4 text-[11px] font-medium text-text hover:bg-surface-muted disabled:cursor-not-allowed disabled:hover:bg-surface focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-1 focus-visible:ring-offset-surface"
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
        <span className="tabular-nums" aria-live="polite" aria-atomic="true">
          Page <span className="font-medium text-text">{isEmpty ? 0 : page}</span> of{" "}
          <span className="font-medium text-text">{isEmpty ? 0 : pageCount}</span>
        </span>
        <div
          role="group"
          aria-label="Pagination navigation"
          className="inline-flex rounded-md border bg-surface shadow-xs overflow-hidden divide-x"
        >
          <PagerBtn label="First page" disabled={!canPrev} onClick={() => onPageChange(1)}>
            <IconChevronsLeft size={13} stroke={2} aria-hidden />
          </PagerBtn>
          <PagerBtn label="Previous page" disabled={!canPrev} onClick={() => onPageChange(page - 1)}>
            <IconChevronLeft size={13} stroke={2} aria-hidden />
          </PagerBtn>
          <PagerBtn label="Next page" disabled={!canNext} onClick={() => onPageChange(page + 1)}>
            <IconChevronRight size={13} stroke={2} aria-hidden />
          </PagerBtn>
          <PagerBtn label="Last page" disabled={!canNext} onClick={() => onPageChange(pageCount)}>
            <IconChevronsRight size={13} stroke={2} aria-hidden />
          </PagerBtn>
        </div>
      </div>
    </nav>
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
      aria-disabled={disabled || undefined}
      title={label}
      disabled={disabled}
      onClick={onClick}
      className={cn(
        "h-6 w-7 grid place-items-center transition-colors outline-none",
        "focus-visible:relative focus-visible:z-10 focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-inset",
        disabled
          ? "text-text-soft/60 bg-surface-muted/40 cursor-not-allowed"
          : "text-text-muted hover:bg-surface-muted hover:text-text active:bg-surface-muted/80",
      )}
    >
      {children}
    </button>
  );
}
