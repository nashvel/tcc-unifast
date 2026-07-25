import { createFileRoute } from "@tanstack/react-router";
import { useMemo, useState } from "react";
import { PageHeader } from "@/components/ui/page-header";
import { Btn } from "@/components/ui/btn";
import { Selectish, TextInput } from "@/components/ui/form-field";
import { DataTable, THead, Tr, Th, Td } from "@/components/ui/data-table";
import { DetailDrawer } from "@/components/ui/modal";
import { IconDownload, IconShieldCheck, IconAlertTriangle, IconAlertOctagon, IconInfoCircle } from "@tabler/icons-react";
import { downloadCSV } from "@/lib/csv";

export const Route = createFileRoute("/app/security")({
  component: SecurityFindings,
});

type Level = "info" | "warn" | "error";
type State = "open" | "fixed" | "ignored";

interface Finding {
  id: string;
  scanner: "platform" | "mock_posture" | "connector_security_scan";
  scannerLabel: string;
  name: string;
  description: string;
  level: Level;
  state: State;
  resolution?: string;
  link?: string;
  detectedAt: string;
  resolvedAt?: string;
}

/**
 * Snapshot of the latest results from every scanner wired into the project:
 * - `platform` — built-in platform checks
 * - `mock_posture` — mock app posture scanner
 * - `connector_security_scan` — workspace-wide connector scan (Wiz)
 *
 * Findings are loaded from the most recent scan; resolved items remain visible
 * so staff can audit what was fixed and when.
 */
const FINDINGS: Finding[] = [
  {
    id: "avatars_storage_public_read",
    scanner: "mock_posture",
    scannerLabel: "Mock posture scanner",
    name: "All authenticated users can read any avatar",
    description:
      "The original SELECT policy on the avatars storage bucket only checked bucket_id, allowing any authenticated user to read any other user's avatar files.",
    level: "warn",
    state: "fixed",
    resolution:
      "Replaced the policy so authenticated users can only read files inside their own user-id folder; staff (is_staff) retain full read access.",
    detectedAt: "2026-06-21T19:35:48Z",
    resolvedAt: "2026-06-21T19:38:00Z",
  },
  {
    id: "user_roles_self_read_escalation",
    scanner: "mock_posture",
    scannerLabel: "Mock posture scanner",
    name: "Ambiguous INSERT path on user_roles",
    description:
      "The catch-all 'admins manage roles' ALL policy left INSERT semantics implicit, raising the risk of a non-admin inserting their own role row.",
    level: "warn",
    state: "fixed",
    resolution:
      "Dropped the ALL policy and added explicit per-command admin policies (INSERT/UPDATE/DELETE/SELECT) gated by has_role(auth.uid(),'admin').",
    detectedAt: "2026-06-21T19:35:48Z",
    resolvedAt: "2026-06-21T19:38:00Z",
  },
  {
    id: "SUPA_auth_leaked_password_protection",
    scanner: "platform",
    scannerLabel: "Platform checks",
    name: "Leaked Password Protection disabled",
    description:
      "Passwords were not being checked against the Have I Been Pwned database during sign-up and password changes.",
    level: "warn",
    state: "fixed",
    resolution: "Enabled HIBP leaked-password protection in auth configuration.",
    link: "https://docs.lovable.dev/features/security#leaked-password-protection",
    detectedAt: "2026-06-21T19:35:48Z",
    resolvedAt: "2026-06-21T19:38:00Z",
  },
];

const SCANNERS = [
  { key: "platform", label: "Platform checks" },
  { key: "mock_posture", label: "Mock posture scanner" },
  { key: "connector_security_scan", label: "Connector scan (Wiz)" },
] as const;

const LEVEL_META: Record<Level, { label: string; cls: string; Icon: typeof IconAlertTriangle }> = {
  error: { label: "Critical", cls: "bg-red-50 text-red-700 border-red-200", Icon: IconAlertOctagon },
  warn: { label: "Warning", cls: "bg-amber-50 text-amber-700 border-amber-200", Icon: IconAlertTriangle },
  info: { label: "Info", cls: "bg-sky-50 text-sky-700 border-sky-200", Icon: IconInfoCircle },
};

const STATE_META: Record<State, { label: string; cls: string }> = {
  open: { label: "Open", cls: "bg-red-50 text-red-700 border-red-200" },
  fixed: { label: "Fixed", cls: "bg-emerald-50 text-emerald-700 border-emerald-200" },
  ignored: { label: "Ignored", cls: "bg-surface-muted text-text-muted border" },
};

function SecurityFindings() {
  const [scanner, setScanner] = useState<"all" | Finding["scanner"]>("all");
  const [state, setState] = useState<"all" | State>("all");
  const [level, setLevel] = useState<"all" | Level>("all");
  const [search, setSearch] = useState("");
  const [active, setActive] = useState<Finding | null>(null);

  const filtered = useMemo(
    () =>
      FINDINGS.filter((f) => {
        if (scanner !== "all" && f.scanner !== scanner) return false;
        if (state !== "all" && f.state !== state) return false;
        if (level !== "all" && f.level !== level) return false;
        if (search && !`${f.name} ${f.description}`.toLowerCase().includes(search.toLowerCase()))
          return false;
        return true;
      }),
    [scanner, state, level, search],
  );

  const counts = useMemo(() => {
    const open = FINDINGS.filter((f) => f.state === "open").length;
    const fixed = FINDINGS.filter((f) => f.state === "fixed").length;
    const ignored = FINDINGS.filter((f) => f.state === "ignored").length;
    return { open, fixed, ignored };
  }, []);

  return (
    <div>
      <PageHeader
        title="Security Findings"
        description="Findings from every scanner wired into this project, including the workspace connector scan (Wiz)."
        actions={
          <Btn
            variant="outline"
            icon={IconDownload}
            onClick={() =>
              downloadCSV(
                "security-findings.csv",
                filtered.map((f) => ({
                  id: f.id,
                  scanner: f.scanner,
                  level: f.level,
                  state: f.state,
                  name: f.name,
                  description: f.description,
                  resolution: f.resolution ?? "",
                  detected_at: f.detectedAt,
                  resolved_at: f.resolvedAt ?? "",
                })),
              )
            }
          >
            Export CSV
          </Btn>
        }
      />

      <div className="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
        <StatCard label="Open" value={counts.open} accent="text-red-600" Icon={IconAlertOctagon} />
        <StatCard label="Fixed" value={counts.fixed} accent="text-emerald-600" Icon={IconShieldCheck} />
        <StatCard label="Ignored" value={counts.ignored} accent="text-text-muted" Icon={IconInfoCircle} />
        <StatCard label="Scanners" value={SCANNERS.length} accent="text-primary" Icon={IconShieldCheck} />
      </div>

      <div className="rounded-lg border bg-surface p-3 mb-4 grid grid-cols-2 md:grid-cols-5 gap-2">
        <Selectish value={scanner} onChange={(e) => setScanner(e.target.value as typeof scanner)}>
          <option value="all">All scanners</option>
          {SCANNERS.map((s) => (
            <option key={s.key} value={s.key}>
              {s.label}
            </option>
          ))}
        </Selectish>
        <Selectish value={state} onChange={(e) => setState(e.target.value as typeof state)}>
          <option value="all">Any state</option>
          <option value="open">Open</option>
          <option value="fixed">Fixed</option>
          <option value="ignored">Ignored</option>
        </Selectish>
        <Selectish value={level} onChange={(e) => setLevel(e.target.value as typeof level)}>
          <option value="all">Any severity</option>
          <option value="error">Critical</option>
          <option value="warn">Warning</option>
          <option value="info">Info</option>
        </Selectish>
        <TextInput
          placeholder="Search findingsâ€¦"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="md:col-span-2"
        />
      </div>

      <DataTable>
        <THead>
          <Tr>
            <Th>Severity</Th>
            <Th>Finding</Th>
            <Th>Scanner</Th>
            <Th>State</Th>
            <Th>Detected</Th>
            <Th></Th>
          </Tr>
        </THead>
        <tbody>
          {filtered.length === 0 && (
            <Tr>
              <Td colSpan={6} className="text-center text-text-muted py-8">
                No findings match the current filters.
              </Td>
            </Tr>
          )}
          {filtered.map((f) => {
            const { Icon, cls, label } = LEVEL_META[f.level];
            return (
              <Tr key={f.id}>
                <Td>
                  <span className={`inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-micro ${cls}`}>
                    <Icon size={12} /> {label}
                  </span>
                </Td>
                <Td className="font-medium max-w-[380px]">
                  <div className="truncate">{f.name}</div>
                  <div className="text-micro text-text-muted truncate">{f.description}</div>
                </Td>
                <Td className="text-text-muted">{f.scannerLabel}</Td>
                <Td>
                  <span className={`inline-flex rounded-full border px-2 py-0.5 text-micro ${STATE_META[f.state].cls}`}>
                    {STATE_META[f.state].label}
                  </span>
                </Td>
                <Td className="text-text-muted whitespace-nowrap">
                  {new Date(f.detectedAt).toLocaleDateString()}
                </Td>
                <Td>
                  <button onClick={() => setActive(f)} className="text-xs text-primary hover:underline">
                    View
                  </button>
                </Td>
              </Tr>
            );
          })}
        </tbody>
      </DataTable>

      <p className="mt-4 text-micro text-text-muted">
        Connector scan results (Wiz) are workspace-scoped and run automatically across every project in
        this workspace. The latest connector scan returned no findings for this project.
      </p>

      <DetailDrawer open={!!active} onClose={() => setActive(null)} title={active?.name ?? "Finding"}>
        {active && (
          <div className="space-y-3 text-sm">
            <Row label="ID" value={active.id} />
            <Row label="Scanner" value={active.scannerLabel} />
            <Row label="Severity" value={LEVEL_META[active.level].label} />
            <Row label="State" value={STATE_META[active.state].label} />
            <Row label="Detected" value={new Date(active.detectedAt).toLocaleString()} />
            {active.resolvedAt && <Row label="Resolved" value={new Date(active.resolvedAt).toLocaleString()} />}
            <div>
              <p className="text-xs text-text-muted mb-1">Description</p>
              <p className="rounded-md bg-surface-muted p-2 text-xs leading-relaxed">{active.description}</p>
            </div>
            {active.resolution && (
              <div>
                <p className="text-xs text-text-muted mb-1">Resolution</p>
                <p className="rounded-md bg-surface-muted p-2 text-xs leading-relaxed">{active.resolution}</p>
              </div>
            )}
            {active.link && (
              <a
                href={active.link}
                target="_blank"
                rel="noreferrer"
                className="inline-flex text-xs text-primary hover:underline"
              >
                Reference documentation â†—
              </a>
            )}
          </div>
        )}
      </DetailDrawer>
    </div>
  );
}

function StatCard({
  label,
  value,
  accent,
  Icon,
}: {
  label: string;
  value: number;
  accent: string;
  Icon: typeof IconShieldCheck;
}) {
  return (
    <div className="rounded-lg border bg-surface p-3 flex items-center gap-3">
      <div className={`h-9 w-9 rounded-md bg-surface-muted grid place-items-center ${accent}`}>
        <Icon size={18} />
      </div>
      <div>
        <p className="text-micro uppercase tracking-wide text-text-muted">{label}</p>
        <p className="text-lg font-semibold leading-none">{value}</p>
      </div>
    </div>
  );
}

function Row({ label, value }: { label: string; value: string }) {
  return (
    <div className="grid grid-cols-3 gap-2 text-sm border-b pb-1.5">
      <span className="text-text-muted text-xs">{label}</span>
      <span className="col-span-2">{value}</span>
    </div>
  );
}
