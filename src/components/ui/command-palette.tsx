import { useEffect, useMemo, useState } from "react";
import { useNavigate } from "@tanstack/react-router";
import { IconSearch, IconArrowRight } from "@tabler/icons-react";
import { useAuthStore } from "@/stores/authStore";

interface Cmd {
  label: string;
  hint: string;
  to: string;
  roles: ("admin" | "staff" | "head" | "student")[];
}

const COMMANDS: Cmd[] = [
  { label: "Dashboard", hint: "Overview & KPIs", to: "/app", roles: ["admin", "staff", "head"] },
  { label: "Masterlist", hint: "Import & manage rows", to: "/app/masterlist", roles: ["admin", "staff", "head"] },
  { label: "Batches", hint: "Academic year batches", to: "/app/batches", roles: ["admin", "staff", "head"] },
  { label: "Grantees", hint: "All grantee records", to: "/app/grantees", roles: ["admin", "staff", "head"] },
  { label: "Document Validation", hint: "Review uploaded docs", to: "/app/documents", roles: ["admin", "staff", "head"] },
  { label: "Academic Records", hint: "GWA & retention", to: "/app/academic", roles: ["admin", "staff", "head"] },
  { label: "Eligibility", hint: "Evaluate grantees", to: "/app/eligibility", roles: ["admin", "staff", "head"] },
  { label: "Announcements", hint: "Compose & publish", to: "/app/announcements", roles: ["admin", "staff", "head"] },
  { label: "Reports", hint: "Generate exports", to: "/app/reports", roles: ["admin", "staff", "head"] },
  { label: "Audit Trail", hint: "System activity log", to: "/app/audit", roles: ["admin", "head"] },
  { label: "Users & Roles", hint: "Manage staff access", to: "/app/users", roles: ["admin", "head"] },
  { label: "Settings", hint: "Org & validation", to: "/app/settings", roles: ["admin", "head"] },
  { label: "My Dashboard", hint: "Student home", to: "/student", roles: ["student"] },
  { label: "Upload Document", hint: "Submit a requirement", to: "/student/upload", roles: ["student"] },
  { label: "My Submissions", hint: "Status of uploads", to: "/student/submissions", roles: ["student"] },
  { label: "Announcements", hint: "Office updates", to: "/student/announcements", roles: ["student"] },
  { label: "Notifications", hint: "Inbox", to: "/student/notifications", roles: ["student"] },
  { label: "Profile", hint: "Personal info", to: "/student/profile", roles: ["student"] },
];

export function CommandPalette() {
  const [open, setOpen] = useState(false);
  const [q, setQ] = useState("");
  const [idx, setIdx] = useState(0);
  const navigate = useNavigate();
  const role = useAuthStore((s) => s.role);

  useEffect(() => {
    function onKey(e: KeyboardEvent) {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === "k") {
        e.preventDefault();
        setOpen((v) => !v);
      } else if (e.key === "Escape") {
        setOpen(false);
      }
    }
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, []);

  const filtered = useMemo(() => {
    const items = COMMANDS.filter((c) => !role || c.roles.includes(role));
    if (!q) return items;
    const s = q.toLowerCase();
    return items.filter((c) => c.label.toLowerCase().includes(s) || c.hint.toLowerCase().includes(s));
  }, [q, role]);

  useEffect(() => { setIdx(0); }, [q, open]);

  if (!open) return null;

  function go(cmd: Cmd) {
    setOpen(false);
    setQ("");
    navigate({ to: cmd.to });
  }

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center pt-[10vh] px-4" onClick={() => setOpen(false)}>
      <div className="absolute inset-0 bg-black/40" />
      <div className="relative w-full max-w-lg rounded-xl border bg-surface shadow-2xl overflow-hidden" onClick={(e) => e.stopPropagation()}>
        <div className="flex items-center gap-2 px-3 h-12 border-b">
          <IconSearch size={16} className="text-text-muted" />
          <input
            autoFocus
            value={q}
            onChange={(e) => setQ(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === "ArrowDown") { e.preventDefault(); setIdx((i) => Math.min(i + 1, filtered.length - 1)); }
              else if (e.key === "ArrowUp") { e.preventDefault(); setIdx((i) => Math.max(i - 1, 0)); }
              else if (e.key === "Enter" && filtered[idx]) { e.preventDefault(); go(filtered[idx]); }
            }}
            placeholder="Jump to a page or action…"
            className="flex-1 bg-transparent outline-none text-sm placeholder:text-text-soft"
          />
          <kbd className="hidden sm:inline-flex text-2xs px-1.5 py-0.5 rounded border text-text-muted">ESC</kbd>
        </div>
        <ul className="max-h-80 overflow-y-auto py-1">
          {filtered.length === 0 && <li className="px-3 py-6 text-center text-xs text-text-muted">No matches</li>}
          {filtered.map((c, i) => (
            <li key={c.to}>
              <button
                onMouseEnter={() => setIdx(i)}
                onClick={() => go(c)}
                className={`w-full flex items-center justify-between gap-3 px-3 py-2 text-left text-sm ${i === idx ? "bg-primary-soft/40" : "hover:bg-surface-muted"}`}
              >
                <div className="min-w-0">
                  <p className="font-medium truncate">{c.label}</p>
                  <p className="text-micro text-text-muted truncate">{c.hint}</p>
                </div>
                <IconArrowRight size={14} className="text-text-soft" />
              </button>
            </li>
          ))}
        </ul>
        <div className="border-t px-3 py-1.5 flex items-center justify-between text-2xs text-text-soft">
          <span>↑↓ navigate · ↵ open</span>
          <span>⌘K to toggle</span>
        </div>
      </div>
    </div>
  );
}
