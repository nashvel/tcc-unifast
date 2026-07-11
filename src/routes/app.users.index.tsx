import { createFileRoute, Link } from "@tanstack/react-router";
import { useMemo, useState } from "react";
import { PageHeader } from "@/components/ui/page-header";
import { Btn } from "@/components/ui/btn";
import { SearchInput } from "@/components/ui/search-input";
import { Selectish } from "@/components/ui/form-field";
import { DataTable, THead, Tr, Th, Td } from "@/components/ui/data-table";
import { TableStates } from "@/components/ui/table-states";
import { TablePagination } from "@/components/ui/table-pagination";
import { usePagination } from "@/hooks/use-pagination";
import { StatusBadge } from "@/components/ui/status-badge";
import { useStaffUsers } from "@/hooks/queries";
import { IconPlus, IconKey, IconDownload } from "@tabler/icons-react";
import { downloadCSV } from "@/lib/csv";

export const Route = createFileRoute("/app/users/")({
  component: UsersPage,
});

function UsersPage() {
  const { data: users = [], isLoading, isFetching, isError, error, refetch } = useStaffUsers();
  const [q, setQ] = useState("");
  const [role, setRole] = useState("all");
  const [status, setStatus] = useState("all");

  const filtered = useMemo(() => users.filter((u) => {
    if (q && !`${u.username} ${u.fullName} ${u.email}`.toLowerCase().includes(q.toLowerCase())) return false;
    if (role !== "all" && u.role !== role) return false;
    if (status === "active" && !u.active) return false;
    if (status === "disabled" && u.active) return false;
    return true;
  }), [users, q, role, status]);

  const roles = useMemo(() => [...new Set(users.map((u) => u.role))], [users]);
  const pg = usePagination(filtered, 15);

  return (
    <div>
      <PageHeader title="Users & Access" description="Manage staff accounts, roles, and permissions."
        actions={<>
          <Btn variant="outline" icon={IconDownload} onClick={() => downloadCSV("users.csv", filtered)}>Export</Btn>
          <Link to="/app/users/permissions"><Btn variant="outline">Permission matrix</Btn></Link>
          <Btn variant="primary" icon={IconPlus}>New user</Btn>
        </>} />
      <div className="rounded-lg border bg-surface p-3 mb-4 grid grid-cols-1 md:grid-cols-4 gap-2">
        <SearchInput placeholder="Search by name, username, or email" value={q} onChange={(e) => setQ(e.target.value)} className="md:col-span-2" />
        <Selectish value={role} onChange={(e) => setRole(e.target.value)}>
          <option value="all">All roles</option>
          {roles.map((r) => <option key={r}>{r}</option>)}
        </Selectish>
        <Selectish value={status} onChange={(e) => setStatus(e.target.value)}>
          <option value="all">All statuses</option>
          <option value="active">Active</option>
          <option value="disabled">Disabled</option>
        </Selectish>
      </div>
      <DataTable>
        <THead><Tr><Th>Username</Th><Th>Full Name</Th><Th>Email</Th><Th>Role</Th><Th>MFA</Th><Th>Status</Th><Th>Last login</Th><Th></Th></Tr></THead>
        <tbody>
          <TableStates
            colSpan={8}
            isLoading={isLoading}
            isFetching={isFetching}
            isError={isError}
            error={error}
            isEmpty={!isLoading && !isError && filtered.length === 0}
            onRetry={() => refetch()}
            emptyTitle="No staff users match"
            emptyHint="Try clearing filters or create a new account."
          />
          {pg.pageItems.map((u) => (
            <Tr key={u.id}>
              <Td className="font-mono text-xs">{u.username}</Td>
              <Td className="font-medium">{u.fullName}</Td>
              <Td className="text-text-muted">{u.email}</Td>
              <Td><StatusBadge variant="primary">{u.role}</StatusBadge></Td>
              <Td>{u.mfa ? <StatusBadge variant="success">Enabled</StatusBadge> : <StatusBadge variant="warning">Off</StatusBadge>}</Td>
              <Td>{u.active ? <StatusBadge variant="success">Active</StatusBadge> : <StatusBadge variant="danger">Disabled</StatusBadge>}</Td>
              <Td className="text-text-muted">{u.lastLogin}</Td>
              <Td className="text-right">
                <button className="text-xs text-primary hover:underline mr-2 inline-flex items-center gap-1"><IconKey size={12} /> Reset</button>
                <button className="text-xs text-text-muted hover:text-text">{u.active ? "Deactivate" : "Activate"}</button>
              </Td>
            </Tr>
          ))}
        </tbody>
      </DataTable>
      <TablePagination {...pg} onPageChange={pg.setPage} onPageSizeChange={pg.setPageSize} isLoading={isLoading} disabled={isError} className="rounded-b-lg border border-t-0 -mt-px" />
    </div>
  );
}
