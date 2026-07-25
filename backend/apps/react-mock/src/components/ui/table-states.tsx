import { Tr, Td } from "./data-table";
import { IconAlertCircle, IconInbox, IconRefresh } from "@tabler/icons-react";
import { cn } from "@/lib/utils";

interface Props {
  colSpan: number;
  isLoading?: boolean;
  isFetching?: boolean;
  isError?: boolean;
  error?: unknown;
  isEmpty?: boolean;
  onRetry?: () => void;
  /** Number of skeleton rows to show while loading. Default: 5 */
  skeletonRows?: number;
  emptyTitle?: string;
  emptyHint?: string;
}

/**
 * Render inside <tbody>. Returns skeleton / error / empty rows.
 * Returns null when there is data to render — caller renders rows normally.
 */
export function TableStates({
  colSpan,
  isLoading,
  isFetching,
  isError,
  error,
  isEmpty,
  onRetry,
  skeletonRows = 5,
  emptyTitle = "Nothing to show",
  emptyHint = "Try adjusting filters or come back later.",
}: Props) {
  if (isLoading) {
    return (
      <>
        {Array.from({ length: skeletonRows }).map((_, i) => (
          <Tr key={`sk-${i}`} className="pointer-events-none">
            {Array.from({ length: colSpan }).map((_, j) => (
              <Td key={j}>
                <div
                  className="h-3 rounded bg-surface-muted animate-pulse"
                  style={{ width: `${40 + ((i * 13 + j * 17) % 55)}%` }}
                />
              </Td>
            ))}
          </Tr>
        ))}
      </>
    );
  }

  if (isError) {
    const msg = error instanceof Error ? error.message : "Something went wrong loading this data.";
    return (
      <Tr>
        <Td colSpan={colSpan} className="py-10">
          <div className="flex flex-col items-center gap-2 text-center">
            <IconAlertCircle size={22} className="text-danger" />
            <p className="text-sm font-medium">Couldn't load data</p>
            <p className="text-xs text-text-muted max-w-sm">{msg}</p>
            {onRetry && (
              <button
                onClick={onRetry}
                className="mt-1 inline-flex items-center gap-1 text-xs text-primary hover:underline"
              >
                <IconRefresh size={12} /> Retry
              </button>
            )}
          </div>
        </Td>
      </Tr>
    );
  }

  if (isEmpty) {
    return (
      <Tr>
        <Td colSpan={colSpan} className="py-10">
          <div className={cn(
            "flex flex-col items-center gap-2 text-center",
            isFetching && "opacity-60",
          )}>
            <IconInbox size={22} className="text-text-muted" />
            <p className="text-sm font-medium">{emptyTitle}</p>
            <p className="text-xs text-text-muted max-w-sm">{emptyHint}</p>
          </div>
        </Td>
      </Tr>
    );
  }

  return null;
}
