import { createFileRoute, Link } from "@tanstack/react-router";
import { PageHeader } from "@/components/ui/page-header";
import { DataTable, THead, Tr, Th, Td } from "@/components/ui/data-table";
import { permissionModules, rolePermissions } from "@/data/mockUsers";
import { IconArrowLeft, IconCheck, IconX } from "@tabler/icons-react";

export const Route = createFileRoute("/app/users/permissions")({
  component: () => {
    const roles = Object.keys(rolePermissions);
    return (
      <div>
        <Link to="/app/users" className="inline-flex items-center gap-1 text-xs text-text-muted hover:text-text mb-3">
          <IconArrowLeft size={13} /> Back
        </Link>
        <PageHeader title="Permission Matrix" description="Module-level permissions per role." />
        {permissionModules.map((m) => (
          <div key={m.module} className="mb-4">
            <p className="text-xs font-semibold uppercase tracking-wide text-text-muted mb-1.5">{m.module}</p>
            <DataTable>
              <THead><Tr><Th>Permission</Th>{roles.map((r) => <Th key={r}>{r}</Th>)}</Tr></THead>
              <tbody>
                {m.perms.map((p) => (
                  <Tr key={p}>
                    <Td className="capitalize">{p.replace(/_/g, " ")}</Td>
                    {roles.map((r) => {
                      const has = rolePermissions[r as keyof typeof rolePermissions][m.module]?.includes(p);
                      return <Td key={r}>{has ? <IconCheck size={14} className="text-success" /> : <IconX size={14} className="text-text-soft" />}</Td>;
                    })}
                  </Tr>
                ))}
              </tbody>
            </DataTable>
          </div>
        ))}
      </div>
    );
  },
});
