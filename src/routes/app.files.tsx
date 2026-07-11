import { createFileRoute, Link } from "@tanstack/react-router";
import { useMemo, useState } from "react";
import {
  IconPhoto, IconVideo, IconFileText, IconArchive, IconFile,
  IconFolder, IconStar, IconStarFilled, IconDotsVertical, IconDownload,
  IconUpload, IconX, IconEye,
} from "@tabler/icons-react";
import { PageHeader } from "@/components/ui/page-header";
import { SearchInput } from "@/components/ui/search-input";
import { Selectish } from "@/components/ui/form-field";
import { DataTable, THead, Tr, Th, Td } from "@/components/ui/data-table";
import { TableStates } from "@/components/ui/table-states";
import { TablePagination } from "@/components/ui/table-pagination";
import { usePagination } from "@/hooks/use-pagination";
import { StatusBadge, statusVariantFor, formatStatus } from "@/components/ui/status-badge";
import { Btn } from "@/components/ui/btn";
import { FileUpload } from "@/components/ui/file-upload";
import { useDocuments, useUploadDocument, useAppendAuditLog, useReassignDocument, useGrantees } from "@/hooks/queries";
import { useAuthStore } from "@/stores/authStore";
import { toast } from "sonner";
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
  const role = useAuthStore((s) => s.role);
  const canManage = role !== "admin"; // staff/head can upload & manage; admin is monitor-only
  const { data: docs = [], isLoading, isFetching, isError, error, refetch } = useDocuments();
  const [q, setQ] = useState("");
  const [category, setCategory] = useState<Category | "all">("all");
  const [status, setStatus] = useState("all");
  const [starred, setStarred] = useState<Record<string, boolean>>({});
  const [uploadOpen, setUploadOpen] = useState(false);
  const [pendingFiles, setPendingFiles] = useState<File[]>([]);
  const [uploadType, setUploadType] = useState("Certificate of Registration");
  const [uploadGranteeId, setUploadGranteeId] = useState<string>("");
  const { data: granteeList = [] } = useGrantees();
  const uploadMut = useUploadDocument();
  const auditMut = useAppendAuditLog();
  const reassignMut = useReassignDocument();

  async function submitUploads() {
    if (!canManage) { toast.error("Admins have monitor-only access."); return; }
    if (pendingFiles.length === 0) return;
    if (!uploadGranteeId) { toast.error("Pick a grantee to link this upload to."); return; }
    try {
      for (const f of pendingFiles) {
        const res = await uploadMut.mutateAsync({ type: uploadType, filename: f.name, granteeId: uploadGranteeId });
        await auditMut.mutateAsync({
          action: "file.upload",
          module: "File Manager",
          target: `${f.name} → ${res.grantee_name} (${res.student_number})`,
          after: {
            documentId: res.id,
            granteeId: uploadGranteeId,
            grantee: res.grantee_name,
            studentNumber: res.student_number,
            type: uploadType,
            size: f.size,
            filename: f.name,
          },
        });
      }
      toast.success(`Uploaded ${pendingFiles.length} file${pendingFiles.length === 1 ? "" : "s"}`);
      setPendingFiles([]);
      setUploadOpen(false);
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Upload failed");
    }
  }

  type PreviewFile = { id: string; name: string; category: Category; grantee: string; type: string; size: number };
  const [preview, setPreview] = useState<PreviewFile | null>(null);

  function previewUrlFor(f: PreviewFile): string | null {
    if (f.category === "image") {
      // deterministic placeholder image per file id
      let h = 0;
      for (let i = 0; i < f.id.length; i++) h = (h * 31 + f.id.charCodeAt(i)) >>> 0;
      return `https://picsum.photos/seed/${h}/900/1200`;
    }
    if (f.category === "document" && f.name.toLowerCase().endsWith(".pdf")) {
      const body = `BT /F1 18 Tf 60 760 Td (${f.name.replace(/[()\\]/g, "")}) Tj 0 -28 Td /F1 12 Tf (Grantee: ${f.grantee}) Tj 0 -18 Td (Type: ${f.type}) Tj 0 -18 Td (Preview \\(mock\\)) Tj ET`;
      const pdf =
        `%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n` +
        `2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n` +
        `3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>endobj\n` +
        `4 0 obj<</Length ${body.length}>>stream\n${body}\nendstream endobj\n` +
        `5 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj\n` +
        `xref\n0 6\n0000000000 65535 f \ntrailer<</Size 6/Root 1 0 R>>\nstartxref\n0\n%%EOF`;
      return `data:application/pdf;base64,${btoa(pdf)}`;
    }
    return null;
  }

  function logAudit(action: string, target: string, extra?: Record<string, unknown>, before?: Record<string, unknown>) {
    auditMut.mutate({ action, module: "File Manager", target, after: extra, before });
  }

  async function reassignFile(f: PreviewFile) {
    if (!canManage) { toast.error("Admins have monitor-only access."); return; }
    const newType = window.prompt(`Reassign document type for "${f.name}"`, f.type);
    if (!newType || newType === f.type) return;
    try {
      const res = await reassignMut.mutateAsync({ id: f.id, type: newType });
      logAudit("file.reassign", f.name, res.after, res.before);
      toast.success(`Reassigned "${f.name}" to ${newType}`);
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Reassign failed");
    }
  }

  function downloadFile(f: PreviewFile) {
    logAudit("file.download", f.name, { type: f.type, grantee: f.grantee, size: f.size });
    const url = previewUrlFor(f);
    if (url) {
      const a = document.createElement("a");
      a.href = url;
      a.download = f.name;
      a.rel = "noopener";
      a.target = "_blank";
      document.body.appendChild(a);
      a.click();
      a.remove();
      toast.success(`Downloading ${f.name}`);
      return;
    }
    // synthesize a minimal text blob for non-previewable types
    const blob = new Blob(
      [`Mock file\n\nName: ${f.name}\nType: ${f.type}\nGrantee: ${f.grantee}\nSize: ${formatBytes(f.size)}\n`],
      { type: "text/plain" },
    );
    const objUrl = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = objUrl;
    a.download = f.name.replace(/\.[^.]+$/, "") + ".txt";
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(objUrl);
    toast.success(`Downloading ${f.name}`);
  }

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
        description={canManage
          ? "Browse every submitted file across grantees. Connected to the document submission pipeline."
          : "Read-only view of all submitted files. Admins have monitor-only access."}
        actions={canManage
          ? <Btn onClick={() => setUploadOpen(true)}><IconUpload size={14} className="mr-1.5" />Upload</Btn>
          : <StatusBadge variant="neutral">Monitor mode</StatusBadge>}
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
                      {canManage && (
                        <button className="text-text-soft hover:text-text" aria-label="Folder actions">
                          <IconDotsVertical size={16} />
                        </button>
                      )}
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
                        {canManage && (
                          <button
                            onClick={() => setStarred((s) => ({ ...s, [f.id]: !s[f.id] }))}
                            className={cn("p-1 rounded hover:bg-surface-muted", isStar ? "text-amber-500" : "text-text-soft")}
                            aria-label={isStar ? "Unstar" : "Star"}
                          >
                            {isStar ? <IconStarFilled size={16} /> : <IconStar size={16} />}
                          </button>
                        )}
                        <button
                          onClick={() => setPreview(f)}
                          className="p-1 rounded hover:bg-surface-muted text-text-soft"
                          aria-label="Preview"
                          title="Preview"
                        >
                          <IconEye size={16} />
                        </button>
                        <button
                          onClick={() => downloadFile(f)}
                          className="p-1 rounded hover:bg-surface-muted text-text-soft"
                          aria-label="Download"
                          title="Download"
                        >
                          <IconDownload size={16} />
                        </button>
                        {canManage && (
                          <button
                            onClick={() => reassignFile(f)}
                            className="p-1 rounded hover:bg-surface-muted text-text-soft"
                            aria-label="Reassign"
                            title="Reassign document type"
                          >
                            <IconDotsVertical size={16} />
                          </button>
                        )}
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

      {/* Upload drawer */}
      {uploadOpen && canManage && (
        <div className="fixed inset-0 z-50">
          <div
            className="absolute inset-0 bg-black/40 backdrop-blur-sm"
            onClick={() => !uploadMut.isPending && setUploadOpen(false)}
          />
          <div className="absolute right-0 top-0 h-full w-full sm:w-[440px] bg-surface border-l shadow-xl flex flex-col animate-in slide-in-from-right duration-200">
            <div className="h-14 flex items-center justify-between px-4 border-b">
              <div>
                <div className="text-sm font-semibold">Upload files</div>
                <div className="text-2xs text-text-muted">Drag & drop or pick from your device</div>
              </div>
              <button
                onClick={() => !uploadMut.isPending && setUploadOpen(false)}
                className="p-1.5 rounded-md hover:bg-surface-muted text-text-soft"
                aria-label="Close"
              >
                <IconX size={16} />
              </button>
            </div>
            <div className="flex-1 overflow-y-auto p-4 space-y-4">
              <div>
                <label className="text-xs font-medium text-text-muted mb-1 block">Link to grantee</label>
                <Selectish value={uploadGranteeId} onChange={(e) => setUploadGranteeId(e.target.value)}>
                  <option value="">Select a grantee…</option>
                  {granteeList.map((g) => (
                    <option key={g.id} value={g.id}>
                      {g.firstName} {g.lastName} · {g.studentNumber}
                    </option>
                  ))}
                </Selectish>
                <p className="text-2xs text-text-muted mt-1">
                  Uploaded files attach to this grantee's submission &amp; validation record.
                </p>
              </div>
              <div>
                <label className="text-xs font-medium text-text-muted mb-1 block">Document type</label>
                <Selectish value={uploadType} onChange={(e) => setUploadType(e.target.value)}>
                  <option>Certificate of Registration</option>
                  <option>Grades / Transcript</option>
                  <option>Enrollment Form</option>
                  <option>Valid ID</option>
                  <option>Other</option>
                </Selectish>
              </div>
              <FileUpload
                multiple
                hint="PDF, JPG, PNG, DOCX up to 10MB"
                onFiles={(f) => setPendingFiles((prev) => [...prev, ...f])}
              />
              {pendingFiles.length > 0 && (
                <div className="text-2xs text-text-muted">
                  {pendingFiles.length} file{pendingFiles.length === 1 ? "" : "s"} ready to upload
                </div>
              )}
            </div>
            <div className="border-t p-3 flex items-center justify-end gap-2">
              <Btn variant="ghost" onClick={() => { setPendingFiles([]); setUploadOpen(false); }} disabled={uploadMut.isPending}>
                Cancel
              </Btn>
              <Btn onClick={submitUploads} disabled={pendingFiles.length === 0 || uploadMut.isPending}>
                {uploadMut.isPending ? "Uploading…" : `Upload ${pendingFiles.length || ""}`.trim()}
              </Btn>
            </div>
          </div>
        </div>
      )}

      {/* Preview modal */}
      {preview && (() => {
        const url = previewUrlFor(preview);
        return (
          <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" onClick={() => setPreview(null)} />
            <div className="relative z-10 w-full max-w-4xl h-[85vh] bg-surface border rounded-lg shadow-xl flex flex-col overflow-hidden">
              <div className="h-14 flex items-center justify-between px-4 border-b">
                <div className="min-w-0">
                  <div className="text-sm font-semibold truncate">{preview.name}</div>
                  <div className="text-2xs text-text-muted truncate">
                    {preview.grantee} · {preview.type} · {formatBytes(preview.size)}
                  </div>
                </div>
                <div className="flex items-center gap-1">
                  <Btn size="sm" variant="secondary" onClick={() => downloadFile(preview)}>
                    <IconDownload size={14} className="mr-1.5" />Download
                  </Btn>
                  <button
                    onClick={() => setPreview(null)}
                    className="p-1.5 rounded-md hover:bg-surface-muted text-text-soft"
                    aria-label="Close"
                  >
                    <IconX size={16} />
                  </button>
                </div>
              </div>
              <div className="flex-1 bg-surface-muted/40 overflow-auto flex items-center justify-center">
                {url && preview.category === "image" && (
                  <img src={url} alt={preview.name} className="max-h-full max-w-full object-contain" />
                )}
                {url && preview.category === "document" && preview.name.toLowerCase().endsWith(".pdf") && (
                  <iframe src={url} title={preview.name} className="w-full h-full bg-white" />
                )}
                {!url && (
                  <div className="text-center p-8">
                    <IconFile size={40} className="mx-auto text-text-soft mb-3" />
                    <div className="text-sm font-medium">No preview available</div>
                    <div className="text-xs text-text-muted mt-1">This file type can't be previewed in-browser.</div>
                    <div className="mt-4">
                      <Btn size="sm" onClick={() => downloadFile(preview)}>
                        <IconDownload size={14} className="mr-1.5" />Download instead
                      </Btn>
                    </div>
                  </div>
                )}
              </div>
            </div>
          </div>
        );
      })()}
    </div>
  );
}
