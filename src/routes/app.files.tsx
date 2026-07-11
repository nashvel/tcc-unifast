import { createFileRoute, Link } from "@tanstack/react-router";
import { useMemo, useState } from "react";
import {
  IconPhoto, IconVideo, IconFileText, IconArchive, IconFile,
  IconFolder, IconStar, IconStarFilled, IconDotsVertical, IconDownload,
} from "@tabler/icons-react";
import { PageHeader } from "@/components/ui/page-header";
import { SearchInput } from "@/components/ui/search-input";
import { Selectish } from "@/components/ui/form-field";
import { DataTable, THead, Tr, Th, Td } from "@/components/ui/data-table";
import { TableStates } from "@/components/ui/table-states";
import { TablePagination } from "@/components/ui/table-pagination";
import { usePagination } from "@/hooks/use-pagination";
import { StatusBadge, statusVariantFor, formatStatus } from "@/components/ui/status-badge";
import { useDocuments } from "@/hooks/queries";
import { cn } from "@/lib/utils";

export const Route = createFileRoute("/app/files")({
  component: FileManager,
});

type Category = "image" | "video" | "document" | "archive" | "other";

const CATEGORY_META: Record<Category, { label: string; icon: typeof IconPhoto; tint: string; iconColor: string }> = {
  image:    { label: "Images",    icon: IconPhoto,    tint: "bg-violet-50 dark:bg-violet-500/10",  iconColor: "text-violet-500" },
  video:    { label: "Videos",    icon: IconVideo,    tint: "bg-rose-50 dark:bg-rose-500/10",      iconColor: "text-rose-500" },
  document: { label: "Documents", icon: IconFileText, tint: "bg-amber-50 dark:bg-amber-500/10",    iconColor: "text-amber-500" },
  archive:  { label: "Archives",  icon: IconArchive,  tint: "bg-emerald-50 dark:bg-emerald-500/10",iconColor: "text-emerald-500" },
  other:    { label: "Other",     icon: IconFile,     tint: "bg-slate-100 dark:bg-slate-500/10",   iconColor: "text-slate-500" },
};

const CATEGORY_ORDER: Category[] = ["image", "video", "document", "archive", "other"];

function categorize(filename: string): Category {
  const ext = filename.split(".").pop()?.toLowerCase() ?? "";
  if (["jpg", "jpeg", "png", "gif", "webp", "svg", "heic"].includes(ext)) return "image";
  if (["mp4", "mov", "avi", "webm", "mkv"].includes(ext)) return "video";
  if (["pdf", "doc", "docx", "txt", "xlsx", "xls", "pptx", "csv"].includes(ext)) return "document";
  if (["zip", "rar", "tar", "gz", "7z"].includes(ext)) return "archive";
  return "other";
}

// deterministic pseudo-size derived from id so mock data stays stable
function pseudoBytes(seed: string): number {
  let h = 0;
  for (let i = 0; i < seed.length; i++) h = (h * 31 + seed.charCodeAt(i)) >>> 0;
  return 50_000 + (h % 200_000_000);
}
function formatBytes(n: number): string {
  if (n >= 1_000_000_000) return `${(n / 1_000_000_000).toFixed(1)} GB`;
  if (n >= 1_000_000) return `${(n / 1_000_000).toFixed(1)} MB`;
  if (n >= 1_000) return `${(n / 1_000).toFixed(0)} KB`;
  return `${n} B`;
}
function timeAgo(iso: string): string {
  const d = new Date(iso).getTime();
  const diff = Date.now() - d;
  const mins = Math.floor(diff / 60_000);
  if (mins < 60) return `${Math.max(1, mins)} min ago`;
  const hrs = Math.floor(mins / 60);
  if (hrs < 24) return `${hrs} hour${hrs === 1 ? "" : "s"} ago`;
  const days = Math.floor(hrs / 24);
  if (days < 7) return `${days} day${days === 1 ? "" : "s"} ago`;
  return new Date(iso).toLocaleDateString();
}

const STORAGE_QUOTA_BYTES = 15 * 1_000_000_000;

function FileManager() {
  const { data: docs = [], isLoading, isFetching, isError, error, refetch } = useDocuments();
  const [q, setQ] = useState("");
  const [category, setCategory] = useState<Category | "all">("all");
  const [status, setStatus] = useState("all");
  const [starred, setStarred] = useState<Record<string, boolean>>({});

  const files = useMemo(() => docs.map((d) => {
    const cat = categorize(d.filename);
    return {
      id: d.id,
      name: d.filename,
      grantee: d.grantee_name,
      studentNumber: d.student_number,
      type: d.type,
      status: d.status,
      uploadedAt: d.uploaded_at,
      size: pseudoBytes(d.id + d.filename),
      category: cat,
    };
  }), [docs]);

  const totals = useMemo(() => {
    const t: Record<Category, { bytes: number; count: number }> = {
      image: { bytes: 0, count: 0 }, video: { bytes: 0, count: 0 },
      document: { bytes: 0, count: 0 }, archive: { bytes: 0, count: 0 },
      other: { bytes: 0, count: 0 },
    };
    for (const f of files) { t[f.category].bytes += f.size; t[f.category].count += 1; }
    return t;
  }, [files]);

  const totalBytes = useMemo(() => files.reduce((s, f) => s + f.size, 0), [files]);
  const usedPct = Math.min(100, (totalBytes / STORAGE_QUOTA_BYTES) * 100);

  const folders = useMemo(() => {
    const map = new Map<string, { name: string; count: number; bytes: number }>();
    for (const f of files) {
      const cur = map.get(f.type) ?? { name: f.type, count: 0, bytes: 0 };
      cur.count += 1; cur.bytes += f.size;
      map.set(f.type, cur);
    }
    return Array.from(map.values()).sort((a, b) => b.bytes - a.bytes).slice(0, 5);
  }, [files]);

  const filtered = useMemo(() => files.filter((f) => {
    if (category !== "all" && f.category !== category) return false;
    if (status !== "all" && f.status !== status) return false;
    if (q) {
      const hay = `${f.name} ${f.grantee} ${f.studentNumber} ${f.type}`.toLowerCase();
      if (!hay.includes(q.toLowerCase())) return false;
    }
    return true;
  }), [files, category, status, q]);

  const pg = usePagination(filtered, 12);

  return (
    <div>
      <PageHeader
        title="File Manager"
        description="Browse every submitted file across grantees. Connected to the document submission pipeline."
      />

      <div className="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-4">
        <div className="min-w-0">
          {/* Category summary cards */}
          <div className="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3 mb-4">
            {CATEGORY_ORDER.map((c) => {
              const meta = CATEGORY_META[c];
              const Icon = meta.icon;
              const bytes = totals[c].bytes;
              const pct = totalBytes > 0 ? Math.round((bytes / totalBytes) * 100) : 0;
              const active = category === c;
              return (
                <button
                  key={c}
                  onClick={() => setCategory(active ? "all" : c)}
                  className={cn(
                    "text-left rounded-lg border bg-surface p-3 transition-colors hover:border-border-strong",
                    active && "border-primary ring-1 ring-primary/40",
                  )}
                >
                  <div className={cn("h-9 w-9 rounded-md flex items-center justify-center mb-3", meta.tint)}>
                    <Icon size={18} className={meta.iconColor} />
                  </div>
                  <div className="text-sm font-medium">{meta.label}</div>
                  <div className="flex items-center justify-between mt-1 text-2xs text-text-muted">
                    <span>{formatBytes(bytes)}</span>
                    <span>{pct}%</span>
                  </div>
                </button>
              );
            })}
          </div>

          {/* Folders */}
          {folders.length > 0 && (
            <>
              <div className="text-sm font-medium text-text-muted mb-2">Folders</div>
              <div className="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3 mb-4">
                {folders.map((f) => (
                  <div key={f.name} className="rounded-lg border bg-surface p-3">
                    <div className="flex items-center justify-between mb-3">
                      <div className="h-9 w-9 rounded-md flex items-center justify-center bg-primary/10">
                        <IconFolder size={18} className="text-primary" />
                      </div>
                      <button className="text-text-soft hover:text-text-strong" aria-label="Folder actions">
                        <IconDotsVertical size={16} />
                      </button>
                    </div>
                    <div className="text-sm font-medium truncate" title={f.name}>{f.name}</div>
                    <div className="text-2xs text-text-muted mt-1">{f.count} files · {formatBytes(f.bytes)}</div>
                  </div>
                ))}
              </div>
            </>
          )}

          {/* All files table */}
          <div className="flex flex-col sm:flex-row sm:items-center gap-2 mb-3">
            <div className="text-sm font-medium text-text-muted mr-auto">All Files</div>
            <SearchInput placeholder="Search files, grantee, or type" value={q} onChange={(e) => setQ(e.target.value)} className="sm:w-72" />
            <Selectish value={status} onChange={(e) => setStatus(e.target.value)}>
              <option value="all">All statuses</option>
              <option>pending</option><option>approved</option><option>rejected</option><option>resubmission</option><option>suspicious</option>
            </Selectish>
          </div>

          <DataTable>
            <THead>
              <Tr>
                <Th>Name</Th>
                <Th>Grantee</Th>
                <Th>Size</Th>
                <Th>Modified</Th>
                <Th>Status</Th>
                <Th></Th>
              </Tr>
            </THead>
            <tbody>
              <TableStates
                colSpan={6}
                isLoading={isLoading}
                isFetching={isFetching}
                isError={isError}
                error={error}
                isEmpty={!isLoading && !isError && filtered.length === 0}
                onRetry={() => refetch()}
                emptyTitle="No files match your filters"
                emptyHint="Adjust the search, category, or status filters."
              />
              {pg.pageItems.map((f) => {
                const meta = CATEGORY_META[f.category];
                const Icon = meta.icon;
                const isStar = !!starred[f.id];
                return (
                  <Tr key={f.id}>
                    <Td>
                      <div className="flex items-center gap-3 min-w-0">
                        <div className={cn("h-8 w-8 rounded-md flex items-center justify-center shrink-0", meta.tint)}>
                          <Icon size={16} className={meta.iconColor} />
                        </div>
                        <div className="min-w-0">
                          <Link to="/app/documents/$id" params={{ id: f.id }} className="text-sm font-medium hover:underline truncate block">
                            {f.name}
                          </Link>
                          <div className="text-2xs text-text-muted truncate">{f.type}</div>
                        </div>
                      </div>
                    </Td>
                    <Td>
                      <div className="text-sm">{f.grantee}</div>
                      <div className="text-2xs text-text-muted font-mono">{f.studentNumber}</div>
                    </Td>
                    <Td className="text-text-muted">{formatBytes(f.size)}</Td>
                    <Td className="text-text-muted">{timeAgo(f.uploadedAt)}</Td>
                    <Td><StatusBadge variant={statusVariantFor(f.status)}>{formatStatus(f.status)}</StatusBadge></Td>
                    <Td>
                      <div className="flex items-center gap-1 justify-end">
                        <button
                          onClick={() => setStarred((s) => ({ ...s, [f.id]: !s[f.id] }))}
                          className={cn("p-1 rounded hover:bg-surface-muted", isStar ? "text-amber-500" : "text-text-soft")}
                          aria-label={isStar ? "Unstar" : "Star"}
                        >
                          {isStar ? <IconStarFilled size={16} /> : <IconStar size={16} />}
                        </button>
                        <Link to="/app/documents/$id" params={{ id: f.id }} className="p-1 rounded hover:bg-surface-muted text-text-soft" aria-label="Download / open">
                          <IconDownload size={16} />
                        </Link>
                        <button className="p-1 rounded hover:bg-surface-muted text-text-soft" aria-label="More">
                          <IconDotsVertical size={16} />
                        </button>
                      </div>
                    </Td>
                  </Tr>
                );
              })}
            </tbody>
          </DataTable>
          <TablePagination
            {...pg}
            onPageChange={pg.setPage}
            onPageSizeChange={pg.setPageSize}
            isLoading={isLoading}
            disabled={isError}
            className="rounded-b-lg border border-t-0 -mt-px"
          />
        </div>

        {/* Right rail: storage overview */}
        <aside className="space-y-4">
          <div className="rounded-lg border bg-surface p-4">
            <div className="flex items-center justify-between mb-3">
              <div className="text-sm font-semibold">Storage Overview</div>
            </div>
            <div className="h-2 rounded-full bg-surface-muted overflow-hidden flex">
              {CATEGORY_ORDER.map((c) => {
                const pct = totalBytes > 0 ? (totals[c].bytes / totalBytes) * usedPct : 0;
                if (pct <= 0) return null;
                const color =
                  c === "image" ? "bg-violet-500" :
                  c === "video" ? "bg-rose-500" :
                  c === "document" ? "bg-amber-500" :
                  c === "archive" ? "bg-emerald-500" : "bg-slate-400";
                return <div key={c} className={color} style={{ width: `${pct}%` }} />;
              })}
            </div>
            <div className="flex items-center justify-between mt-2 text-xs">
              <span className="text-text-muted">{formatBytes(totalBytes)} of {formatBytes(STORAGE_QUOTA_BYTES)} used</span>
              <span className="font-medium">{Math.round(usedPct)}%</span>
            </div>
            <div className="grid grid-cols-2 gap-2 mt-4">
              {CATEGORY_ORDER.map((c) => {
                const meta = CATEGORY_META[c];
                const dot =
                  c === "image" ? "bg-violet-500" :
                  c === "video" ? "bg-rose-500" :
                  c === "document" ? "bg-amber-500" :
                  c === "archive" ? "bg-emerald-500" : "bg-slate-400";
                return (
                  <div key={c} className="flex items-center justify-between rounded-md border px-2 py-1.5">
                    <div className="flex items-center gap-1.5 text-xs">
                      <span className={cn("h-2 w-2 rounded-full", dot)} />
                      <span className="truncate">{meta.label}</span>
                    </div>
                    <div className="text-2xs text-text-muted">{formatBytes(totals[c].bytes)}</div>
                  </div>
                );
              })}
            </div>
          </div>

          <div className="rounded-lg border bg-surface p-4">
            <div className="text-sm font-semibold mb-3">Recent Uploads</div>
            <ul className="space-y-3">
              {files.slice(0, 5).map((f) => {
                const meta = CATEGORY_META[f.category];
                const Icon = meta.icon;
                return (
                  <li key={f.id} className="flex items-center gap-3">
                    <div className={cn("h-8 w-8 rounded-md flex items-center justify-center shrink-0", meta.tint)}>
                      <Icon size={14} className={meta.iconColor} />
                    </div>
                    <div className="min-w-0 flex-1">
                      <div className="text-xs font-medium truncate">{f.name}</div>
                      <div className="text-2xs text-text-muted truncate">{f.grantee} · {timeAgo(f.uploadedAt)}</div>
                    </div>
                  </li>
                );
              })}
              {files.length === 0 && (
                <li className="text-xs text-text-muted">No uploads yet.</li>
              )}
            </ul>
          </div>
        </aside>
      </div>
    </div>
  );
}
