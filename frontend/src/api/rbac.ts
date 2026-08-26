import { apiFetch } from "./client";

// ─── Types ────────────────────────────────────────────────────────────────────

export type RbacPermission = {
  id: number;
  name: string;
  description: string | null;
  category: string;
};

export type RbacRole = {
  id: number;
  name: string;
  description: string | null;
  color: string | null;
  is_system: boolean;
  users_count: number;
  permissions_count: number;
  /** '*' means all permissions (developer superuser role). */
  permissions: string[] | ["*"];
};

export type RbacRoleDetail = {
  id: number;
  name: string;
  description: string | null;
  color: string | null;
  is_system: boolean;
  permissions: RbacPermission[];
};

export type CreateRoleForm = {
  name: string;
  description?: string;
  color?: string;
  permission_ids?: (number | string)[];
};

export type UpdateRoleForm = Partial<CreateRoleForm>;

export type UserRoleRow = {
  id: number;
  name: string;
  description: string | null;
  color: string | null;
  is_system: boolean;
};

// ─── Role endpoints ───────────────────────────────────────────────────────────

/** GET /api/rbac/roles — returns all roles with permission slugs + user/permission counts. */
export async function listRoles(): Promise<RbacRole[]> {
  const payload = await apiFetch<{ data: RbacRole[] }>("/api/rbac/roles");
  return payload.data;
}

/** GET /api/rbac/roles/:id */
export async function getRole(id: number): Promise<RbacRoleDetail> {
  const payload = await apiFetch<{ data: RbacRoleDetail }>(`/api/rbac/roles/${id}`);
  return payload.data;
}

/** POST /api/rbac/roles */
export async function createRole(form: CreateRoleForm): Promise<RbacRoleDetail> {
  const payload = await apiFetch<{ data: RbacRoleDetail }>("/api/rbac/roles", {
    method: "POST",
    body: JSON.stringify(form),
  });
  return payload.data;
}

/** PUT /api/rbac/roles/:id */
export async function updateRole(id: number, form: UpdateRoleForm): Promise<RbacRoleDetail> {
  const payload = await apiFetch<{ data: RbacRoleDetail }>(`/api/rbac/roles/${id}`, {
    method: "PUT",
    body: JSON.stringify(form),
  });
  return payload.data;
}

/** DELETE /api/rbac/roles/:id */
export async function deleteRole(id: number): Promise<void> {
  await apiFetch(`/api/rbac/roles/${id}`, { method: "DELETE" });
}

// ─── Permission endpoints ─────────────────────────────────────────────────────

/** GET /api/rbac/permissions — returns permissions grouped by category. */
export async function listPermissions(): Promise<Record<string, RbacPermission[]>> {
  const payload = await apiFetch<{ data: Record<string, RbacPermission[]> }>(
    "/api/rbac/permissions",
  );
  return payload.data;
}

/** POST /api/rbac/permissions */
export async function createPermission(form: {
  name: string;
  description?: string;
  category: string;
}): Promise<RbacPermission> {
  const payload = await apiFetch<{ data: RbacPermission }>("/api/rbac/permissions", {
    method: "POST",
    body: JSON.stringify(form),
  });
  return payload.data;
}

/** DELETE /api/rbac/permissions/:id */
export async function deletePermission(id: number): Promise<void> {
  await apiFetch(`/api/rbac/permissions/${id}`, { method: "DELETE" });
}

// ─── User ↔ Role endpoints ────────────────────────────────────────────────────

/** GET /api/rbac/users/:userId/roles */
export async function getUserRoles(userId: number): Promise<UserRoleRow[]> {
  const payload = await apiFetch<{ data: UserRoleRow[] }>(`/api/rbac/users/${userId}/roles`);
  return payload.data;
}

/** PUT /api/rbac/users/:userId/roles — replaces the full role set. */
export async function syncUserRoles(userId: number, roleIds: number[]): Promise<void> {
  await apiFetch(`/api/rbac/users/${userId}/roles`, {
    method: "PUT",
    body: JSON.stringify({ role_ids: roleIds }),
  });
}

/** POST /api/rbac/users/:userId/roles — add a single role. */
export async function assignUserRole(userId: number, roleId: number): Promise<void> {
  await apiFetch(`/api/rbac/users/${userId}/roles`, {
    method: "POST",
    body: JSON.stringify({ role_id: roleId }),
  });
}

/** DELETE /api/rbac/users/:userId/roles/:roleId */
export async function removeUserRole(userId: number, roleId: number): Promise<void> {
  await apiFetch(`/api/rbac/users/${userId}/roles/${roleId}`, { method: "DELETE" });
}

/** POST /api/rbac/check-permission — check current user's permission. */
export async function checkPermission(permission: string): Promise<boolean> {
  const payload = await apiFetch<{ has_permission: boolean }>("/api/rbac/check-permission", {
    method: "POST",
    body: JSON.stringify({ permission }),
  });
  return payload.has_permission;
}
