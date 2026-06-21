import { createFileRoute } from "@tanstack/react-router";
import { useState } from "react";
import { PageHeader } from "@/components/ui/page-header";
import { Btn } from "@/components/ui/btn";
import { Selectish, TextInput } from "@/components/ui/form-field";
import { DataTable, THead, Tr, Th, Td } from "@/components/ui/data-table";
import { DetailDrawer } from "@/components/ui/modal";
import { mockAuditLogs, type AuditLog } from "@/data/mockAuditLogs";
import { IconDownload } from "@tabler/icons-react";

export const Route = createFileRoute("/app/audit")({
  component: Audit,
});

function Audit() {
  const [user, setUser] = useState("all");
  const [module, setModule] = useState("all");
  const [action, setAction] = useState("");
  const [active, setActive] = useState<AuditLog | null>(null);

  const filtered = mockAuditLogs.filter((l) => {
    if (user !== "all" && l.user !== user) return false;
    if (module !== "all" && l.module !== module) return false;
    if (action && !l.action.includes(action.toLowerCase())) return false;
    return true;
  });

  return (
    <div>
      <PageHeader title="Audit Trail" description="System-wide audit log of all staff and user actions."
        actions={<Btn variant="outline" icon={IconDownload}>Export</Btn>} />
      <div className="rounded-lg border bg-surface p-3 mb-4 grid grid-cols-2 md:grid-cols-5 gap-2">
        <Selectish value={user} onChange={(e) => setUser(e.target.value)}>
          <option value="all">All users</option>
          {[...new Set(mockAuditLogs.map((l) => l.user))].map((u) => <option key={u}>{u}</option>)}
        </Selectish>
        <Selectish value={module} onChange={(e) => setModule(e.target.value)}>
          <option value="all">All modules</option>
          {[...new Set(mockAuditLogs.map((l) => l.module))].map((m) => <option key={m}>{m}</option>)}
        </Selectish>
        <TextInput placeholder="Action contains…" value={action} onChange={(e) => setAction(e.target.value)} />
        <TextInput type="date" />
        <TextInput type="date" />
      </div>
      <DataTable>
        <THead><Tr><Th>Timestamp</Th><Th>User</Th><Th>Role</Th><Th>Action</Th><Th>Module</Th><Th>Target</Th><Th>IP</Th><Th></Th></Tr></THead>
        <tbody>
          {filtered.map((l) => (
            <Tr key={l.id}>
              <Td className="text-text-muted whitespace-nowrap">{l.timestamp}</Td>
              <Td className="font-medium">{l.user}</Td>
              <Td className="text-text-muted">{l.role}</Td>
              <Td>{l.action}</Td>
              <Td>{l.module}</Td>
              <Td className="text-text-muted truncate max-w-[220px]">{l.target}</Td>
              <Td className="font-mono text-[11px] text-text-muted">{l.ip}</Td>
              <Td><button onClick={() => setActive(l)} className="text-xs text-primary hover:underline">View</button></Td>
            </Tr>
          ))}
        </tbody>
      </DataTable>

      <DetailDrawer open={!!active} onClose={() => setActive(null)} title="Audit Log Detail">
        {active && (
          <div className="space-y-3 text-sm">
            <Row label="Timestamp" value={active.timestamp} />
            <Row label="User" value={`${active.user} (${active.role})`} />
            <Row label="Action" value={active.action} />
            <Row label="Module" value={active.module} />
            <Row label="Target" value={active.target} />
            <Row label="IP Address" value={active.ip} />
            <div>
              <p className="text-xs text-text-muted mb-1">Before</p>
              <pre className="rounded-md bg-surface-muted p-2 text-[11px] font-mono">{JSON.stringify(active.before ?? {}, null, 2)}</pre>
            </div>
            <div>
              <p className="text-xs text-text-muted mb-1">After</p>
              <pre className="rounded-md bg-surface-muted p-2 text-[11px] font-mono">{JSON.stringify(active.after ?? {}, null, 2)}</pre>
            </div>
          </div>
        )}
      </DetailDrawer>
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
