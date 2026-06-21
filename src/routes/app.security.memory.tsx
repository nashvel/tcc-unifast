import { createFileRoute, Link } from "@tanstack/react-router";
import { useCallback, useEffect, useMemo, useState } from "react";
import { PageHeader } from "@/components/ui/page-header";
import { Btn } from "@/components/ui/btn";
import { FormField, TextInput, TextArea, Selectish } from "@/components/ui/form-field";
import { DataTable, THead, Tr, Th, Td } from "@/components/ui/data-table";
import { DetailDrawer, ConfirmModal } from "@/components/ui/modal";
import {
  IconArrowLeft, IconPlus, IconEdit, IconHistory, IconTrash,
  IconArchive, IconArchiveOff, IconShieldCheck, IconAlertTriangle,
} from "@tabler/icons-react";
import { supabase } from "@/integrations/supabase/client";
import { useAuthStore } from "@/stores/authStore";
import { toast } from "sonner";
import type { Database } from "@/integrations/supabase/types";

type Entry = Database["public"]["Tables"]["security_memory_entries"]["Row"];
type Revision = Database["public"]["Tables"]["security_memory_revisions"]["Row"];
type Category = Database["public"]["Enums"]["security_memory_category"];
type Status = Database["public"]["Enums"]["security_memory_status"];

export const Route = createFileRoute("/app/security/memory")({
  component: SecurityMemoryPage,
});

const CATEGORIES: { value: Category; label: string }[] = [
  { value: "invariant", label: "Invariant" },
  { value: "scanner_guidance", label: "Scanner guidance" },
  { value: "accepted_risk", label: "Accepted risk" },
  { value: "note", label: "Note" },
];

const CAT_META: Record<Category, { label: string; cls: string }> = {
  invariant:        { label: "Invariant",        cls: "bg-red-50 text-red-700 border-red-200" },
  scanner_guidance: { label: "Scanner guidance", cls: "bg-sky-50 text-sky-700 border-sky-200" },
  accepted_risk:    { label: "Accepted risk",    cls: "bg-amber-50 text-amber-700 border-amber-200" },
  note:             { label: "Note",             cls: "bg-surface-muted text-text-muted border-border" },
};

function CatBadge({ c }: { c: Category }) {
  const m = CAT_META[c];
  return <span className={`inline-flex items-center px-1.5 py-0.5 rounded border text-[10px] font-medium ${m.cls}`}>{m.label}</span>;
}

function fmt(iso: string) {
  return new Date(iso).toLocaleString();
}

function SecurityMemoryPage() {
  const role = useAuthStore((s) => s.role);
  const userId = useAuthStore((s) => s.userId);
  const isAdmin = role === "admin";

  const [entries, setEntries] = useState<Entry[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");
  const [filterCat, setFilterCat] = useState<"all" | Category>("all");
  const [filterStatus, setFilterStatus] = useState<"all" | Status>("active");

  const [editorOpen, setEditorOpen] = useState(false);
  const [editing, setEditing] = useState<Entry | null>(null);
  const [historyFor, setHistoryFor] = useState<Entry | null>(null);
  const [revisions, setRevisions] = useState<Revision[]>([]);
  const [confirmDel, setConfirmDel] = useState<Entry | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    const { data, error } = await supabase
      .from("security_memory_entries")
      .select("*")
      .order("updated_at", { ascending: false });
    if (error) toast.error(error.message);
    setEntries(data ?? []);
    setLoading(false);
  }, []);

  useEffect(() => { if (isAdmin) load(); }, [isAdmin, load]);

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    return entries.filter((e) => {
      if (filterStatus !== "all" && e.status !== filterStatus) return false;
      if (filterCat !== "all" && e.category !== filterCat) return false;
      if (!q) return true;
      return (
        e.title.toLowerCase().includes(q) ||
        e.body.toLowerCase().includes(q) ||
        (e.related_finding_id?.toLowerCase().includes(q) ?? false)
      );
    });
  }, [entries, search, filterCat, filterStatus]);

  const openHistory = useCallback(async (entry: Entry) => {
    setHistoryFor(entry);
    const { data, error } = await supabase
      .from("security_memory_revisions")
      .select("*")
      .eq("entry_id", entry.id)
      .order("version", { ascending: false });
    if (error) toast.error(error.message);
    setRevisions(data ?? []);
  }, []);

  const toggleArchive = useCallback(async (entry: Entry) => {
    const next: Status = entry.status === "active" ? "archived" : "active";
    const { error } = await supabase
      .from("security_memory_entries")
      .update({ status: next, updated_by: userId })
      .eq("id", entry.id);
    if (error) return toast.error(error.message);
    toast.success(next === "archived" ? "Entry archived" : "Entry restored");
    load();
  }, [load, userId]);

  const remove = useCallback(async (entry: Entry) => {
    const { error } = await supabase.from("security_memory_entries").delete().eq("id", entry.id);
    if (error) return toast.error(error.message);
    toast.success("Entry deleted");
    setConfirmDel(null);
    load();
  }, [load]);

  if (!isAdmin) {
    return (
      <div>
        <Link to="/app/security" className="inline-flex items-center gap-1 text-xs text-text-muted hover:text-text mb-3">
          <IconArrowLeft size={13} /> Back to findings
        </Link>
        <div className="border rounded-lg p-8 text-center bg-surface">
          <IconAlertTriangle size={28} className="mx-auto text-amber-500 mb-2" />
          <p className="font-medium">Admins only</p>
          <p className="text-sm text-text-muted mt-1">You need the admin role to view or edit security memory.</p>
        </div>
      </div>
    );
  }

  const counts = {
    active: entries.filter((e) => e.status === "active").length,
    archived: entries.filter((e) => e.status === "archived").length,
    invariants: entries.filter((e) => e.status === "active" && e.category === "invariant").length,
  };

  return (
    <div>
      <Link to="/app/security" className="inline-flex items-center gap-1 text-xs text-text-muted hover:text-text mb-3">
        <IconArrowLeft size={13} /> Back to findings
      </Link>
      <PageHeader
        title="Security Memory"
        description="Versioned knowledge base that guides future security scans. Admin-only."
        actions={
          <Btn variant="primary" icon={IconPlus} onClick={() => { setEditing(null); setEditorOpen(true); }}>
            New entry
          </Btn>
        }
      />

      <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
        <Stat label="Active entries" value={counts.active} icon={<IconShieldCheck size={16} className="text-emerald-600" />} />
        <Stat label="Invariants" value={counts.invariants} icon={<IconAlertTriangle size={16} className="text-red-600" />} />
        <Stat label="Archived" value={counts.archived} icon={<IconArchive size={16} className="text-text-muted" />} />
      </div>

      <div className="flex flex-wrap items-center gap-2 mb-3">
        <TextInput placeholder="Search title, body, or finding id…" value={search} onChange={(e) => setSearch(e.target.value)} className="max-w-xs" />
        <Selectish value={filterCat} onChange={(e) => setFilterCat(e.target.value as "all" | Category)}>
          <option value="all">All categories</option>
          {CATEGORIES.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
        </Selectish>
        <Selectish value={filterStatus} onChange={(e) => setFilterStatus(e.target.value as "all" | Status)}>
          <option value="active">Active</option>
          <option value="archived">Archived</option>
          <option value="all">All statuses</option>
        </Selectish>
      </div>

      <DataTable>
        <THead>
          <Tr>
            <Th>Title</Th>
            <Th>Category</Th>
            <Th>Finding</Th>
            <Th>Version</Th>
            <Th>Updated</Th>
            <Th className="text-right">Actions</Th>
          </Tr>
        </THead>
        <tbody>
          {loading && <Tr><Td colSpan={6} className="text-center text-text-muted py-6">Loading…</Td></Tr>}
          {!loading && filtered.length === 0 && (
            <Tr><Td colSpan={6} className="text-center text-text-muted py-8">
              No entries. Click <span className="font-medium">New entry</span> to capture the first one.
            </Td></Tr>
          )}
          {filtered.map((e) => (
            <Tr key={e.id}>
              <Td>
                <div className="font-medium">{e.title}</div>
                <div className="text-xs text-text-muted line-clamp-1">{e.body}</div>
              </Td>
              <Td><CatBadge c={e.category} /></Td>
              <Td className="text-xs text-text-muted">{e.related_finding_id ?? "—"}</Td>
              <Td className="text-xs">v{e.version}</Td>
              <Td className="text-xs text-text-muted">{fmt(e.updated_at)}</Td>
              <Td className="text-right">
                <div className="inline-flex gap-1">
                  <Btn size="sm" variant="ghost" icon={IconHistory} onClick={() => openHistory(e)}>History</Btn>
                  <Btn size="sm" variant="ghost" icon={IconEdit} onClick={() => { setEditing(e); setEditorOpen(true); }}>Edit</Btn>
                  <Btn size="sm" variant="ghost" icon={e.status === "active" ? IconArchive : IconArchiveOff} onClick={() => toggleArchive(e)}>
                    {e.status === "active" ? "Archive" : "Restore"}
                  </Btn>
                  <Btn size="sm" variant="ghost" icon={IconTrash} onClick={() => setConfirmDel(e)}>Delete</Btn>
                </div>
              </Td>
            </Tr>
          ))}
        </tbody>
      </DataTable>

      {editorOpen && (
        <EntryEditor
          entry={editing}
          userId={userId}
          onClose={() => setEditorOpen(false)}
          onSaved={() => { setEditorOpen(false); load(); }}
        />
      )}

      <DetailDrawer open={!!historyFor} onClose={() => setHistoryFor(null)} title={historyFor ? `History — ${historyFor.title}` : ""}>
        <div className="space-y-3">
          {revisions.length === 0 && <p className="text-sm text-text-muted">No history yet.</p>}
          {revisions.map((r) => (
            <div key={r.id} className="border rounded-md p-3 bg-surface">
              <div className="flex items-center justify-between mb-1">
                <span className="text-xs font-semibold">v{r.version}</span>
                <span className="text-[11px] text-text-muted">{fmt(r.created_at)}</span>
              </div>
              <div className="flex items-center gap-2 mb-2">
                <CatBadge c={r.category} />
                <span className="text-[11px] text-text-muted">{r.status}</span>
                {r.related_finding_id && <span className="text-[11px] text-text-muted">· {r.related_finding_id}</span>}
              </div>
              <p className="text-sm font-medium">{r.title}</p>
              <pre className="mt-1 text-xs whitespace-pre-wrap font-sans text-text-muted">{r.body}</pre>
            </div>
          ))}
        </div>
      </DetailDrawer>

      <ConfirmModal
        open={!!confirmDel}
        onClose={() => setConfirmDel(null)}
        onConfirm={() => { if (confirmDel) remove(confirmDel); }}
        title="Delete entry?"
        description="This permanently removes the entry and its full revision history."
        confirmLabel="Delete"
        danger
      />
    </div>
  );
}

function Stat({ label, value, icon }: { label: string; value: number; icon: React.ReactNode }) {
  return (
    <div className="border rounded-lg p-3 bg-surface flex items-center gap-3">
      <div className="h-8 w-8 rounded-md bg-surface-muted grid place-items-center">{icon}</div>
      <div>
        <p className="text-[11px] uppercase tracking-wide text-text-muted">{label}</p>
        <p className="text-lg font-semibold">{value}</p>
      </div>
    </div>
  );
}

function EntryEditor({
  entry, userId, onClose, onSaved,
}: {
  entry: Entry | null;
  userId: string | null;
  onClose: () => void;
  onSaved: () => void;
}) {
  const [title, setTitle] = useState(entry?.title ?? "");
  const [body, setBody] = useState(entry?.body ?? "");
  const [category, setCategory] = useState<Category>(entry?.category ?? "invariant");
  const [status, setStatus] = useState<Status>(entry?.status ?? "active");
  const [findingId, setFindingId] = useState(entry?.related_finding_id ?? "");
  const [saving, setSaving] = useState(false);

  const save = async () => {
    if (!title.trim() || !body.trim()) {
      toast.error("Title and body are required");
      return;
    }
    setSaving(true);
    const payload = {
      title: title.trim(),
      body: body.trim(),
      category,
      status,
      related_finding_id: findingId.trim() || null,
      updated_by: userId,
    };
    const res = entry
      ? await supabase.from("security_memory_entries").update(payload).eq("id", entry.id)
      : await supabase.from("security_memory_entries").insert({ ...payload, created_by: userId });
    setSaving(false);
    if (res.error) return toast.error(res.error.message);
    toast.success(entry ? `Saved as v${entry.version + 1}` : "Entry created");
    onSaved();
  };

  return (
    <DetailDrawer open onClose={onClose} title={entry ? `Edit entry · v${entry.version}` : "New security memory entry"}>
      <div className="space-y-3">
        <FormField label="Title" required>
          <TextInput value={title} onChange={(e) => setTitle(e.target.value)} placeholder="e.g. Staff directory is admin-read only" />
        </FormField>
        <div className="grid grid-cols-2 gap-3">
          <FormField label="Category" required>
            <Selectish value={category} onChange={(e) => setCategory(e.target.value as Category)}>
              {CATEGORIES.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
            </Selectish>
          </FormField>
          <FormField label="Status">
            <Selectish value={status} onChange={(e) => setStatus(e.target.value as Status)}>
              <option value="active">Active</option>
              <option value="archived">Archived</option>
            </Selectish>
          </FormField>
        </div>
        <FormField label="Related finding id" helper="Optional. Link this entry to a scanner finding id.">
          <TextInput value={findingId} onChange={(e) => setFindingId(e.target.value)} placeholder="e.g. staff_directory_sensitive_exposure" />
        </FormField>
        <FormField label="Body (markdown)" required helper="Editing creates a new version automatically.">
          <TextArea rows={10} value={body} onChange={(e) => setBody(e.target.value)} />
        </FormField>
        <div className="flex justify-end gap-2 pt-2">
          <Btn variant="ghost" onClick={onClose}>Cancel</Btn>
          <Btn variant="primary" onClick={save} disabled={saving}>{saving ? "Saving…" : entry ? "Save new version" : "Create entry"}</Btn>
        </div>
      </div>
    </DetailDrawer>
  );
}
