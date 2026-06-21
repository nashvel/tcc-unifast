import { createFileRoute, Link } from "@tanstack/react-router";
import { PageHeader } from "@/components/ui/page-header";
import { Btn } from "@/components/ui/btn";
import { DataTable, THead, Tr, Th, Td } from "@/components/ui/data-table";
import { StatusBadge } from "@/components/ui/status-badge";
import { mockUsers } from "@/data/mockUsers";
import { IconPlus, IconKey, IconDownload } from "@tabler/icons-react";
import { downloadCSV } from "@/lib/csv";

export const Route = createFileRoute("/app/users/")({
  component: () => (
    <div>
      <PageHeader title="Users & Access" description="Manage staff accounts, roles, and permissions."
        actions={<>
          <Btn variant="outline" icon={IconDownload} onClick={() => downloadCSV("users.csv", mockUsers)}>Export</Btn>
          <Link to="/app/users/permissions"><Btn variant="outline">Permission matrix</Btn></Link>
          <Btn variant="primary" icon={IconPlus}>New user</Btn>
        </>} />
      <DataTable>
        <THead><Tr><Th>Username</Th><Th>Full Name</Th><Th>Email</Th><Th>Role</Th><Th>MFA</Th><Th>Status</Th><Th>Last login</Th><Th></Th></Tr></THead>
        <tbody>
          {mockUsers.map((u) => (
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
    </div>
  ),
});
