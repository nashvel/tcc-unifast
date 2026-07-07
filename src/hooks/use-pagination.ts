import { useMemo, useState, useEffect, useCallback } from "react";

interface Options {
  /** URL param name for page number. Set to null to disable URL sync. Default: "page" */
  pageParam?: string | null;
  /** URL param name for page size. Set to null to omit. Default: "size" */
  sizeParam?: string | null;
}

function readParam(name: string | null | undefined): number | null {
  if (!name || typeof window === "undefined") return null;
  const v = new URLSearchParams(window.location.search).get(name);
  const n = v ? Number(v) : NaN;
  return Number.isFinite(n) && n > 0 ? n : null;
}

function writeParams(updates: Record<string, string | null>) {
  if (typeof window === "undefined") return;
  const url = new URL(window.location.href);
  for (const [k, v] of Object.entries(updates)) {
    if (v === null || v === "") url.searchParams.delete(k);
    else url.searchParams.set(k, v);
  }
  window.history.replaceState(window.history.state, "", url.toString());
}

export function usePagination<T>(
  items: T[],
  initialPageSize = 10,
  options: Options = {},
) {
  const { pageParam = "page", sizeParam = "size" } = options;

  const [pageSize, setPageSizeState] = useState(
    () => readParam(sizeParam) ?? initialPageSize,
  );
  const [page, setPageState] = useState(() => readParam(pageParam) ?? 1);

  const total = items.length;
  const pageCount = Math.max(1, Math.ceil(total / pageSize));

  // Clamp page when data shrinks.
  useEffect(() => {
    if (page > pageCount) setPageState(pageCount);
  }, [page, pageCount]);

  // Keep URL in sync (page=1/default size are omitted for clean URLs).
  useEffect(() => {
    if (!pageParam && !sizeParam) return;
    writeParams({
      ...(pageParam ? { [pageParam]: page > 1 ? String(page) : null } : {}),
      ...(sizeParam
        ? { [sizeParam]: pageSize !== initialPageSize ? String(pageSize) : null }
        : {}),
    });
  }, [page, pageSize, pageParam, sizeParam, initialPageSize]);

  // React to back/forward navigation.
  useEffect(() => {
    if (!pageParam && !sizeParam) return;
    const onPop = () => {
      const p = readParam(pageParam);
      const s = readParam(sizeParam);
      if (p !== null) setPageState(p);
      else setPageState(1);
      if (s !== null) setPageSizeState(s);
    };
    window.addEventListener("popstate", onPop);
    return () => window.removeEventListener("popstate", onPop);
  }, [pageParam, sizeParam]);

  const setPage = useCallback((n: number) => setPageState(n), []);
  const setPageSize = useCallback((n: number) => {
    setPageSizeState(n);
    setPageState(1);
  }, []);

  const pageItems = useMemo(() => {
    const start = (page - 1) * pageSize;
    return items.slice(start, start + pageSize);
  }, [items, page, pageSize]);

  return {
    page,
    pageSize,
    pageCount,
    total,
    pageItems,
    setPage,
    setPageSize,
    from: total === 0 ? 0 : (page - 1) * pageSize + 1,
    to: Math.min(total, page * pageSize),
  };
}
