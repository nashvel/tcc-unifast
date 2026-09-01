import { computed } from "vue";
import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/vue-query";
import { queryKeys } from "@/api/queryKeys";
import {
  listRoles,
  getRole,
  createRole,
  updateRole,
  deleteRole,
  listPermissions,
  createPermission,
  deletePermission,
  syncUserRoles,
  assignUserRole,
  removeUserRole,
  getUserRoles,
} from "@/api/rbac";
import type { CreateRoleForm, RbacRole, RbacRoleDetail, UpdateRoleForm } from "@/api/rbac";

// ─── Roles ────────────────────────────────────────────────────────────────────

export function useRbacRoles() {
  const query = useQuery({
    queryKey: queryKeys.rbacRoles,
    queryFn: listRoles,
    placeholderData: keepPreviousData,
    staleTime: 60_000,
  });

  const roles = computed<RbacRole[]>(() => query.data.value ?? []);
  const nonSystemRoles = computed(() => roles.value.filter((r) => !r.is_system));

  return { query, roles, nonSystemRoles };
}

export function useRbacRoleDetail(id: number) {
  const query = useQuery({
    queryKey: queryKeys.rbacRole(id),
    queryFn: () => getRole(id),
    staleTime: 60_000,
  });

  const role = computed<RbacRoleDetail | null>(() => query.data.value ?? null);

  return { query, role };
}

// ─── Permissions ──────────────────────────────────────────────────────────────

export function useRbacPermissions() {
  const query = useQuery({
    queryKey: queryKeys.rbacPermissions,
    queryFn: listPermissions,
    staleTime: 60_000,
  });

  /** Flat list of all permissions across all categories. */
  const allPermissions = computed(() =>
    Object.values(query.data.value ?? {}).flat(),
  );

  /** Grouped by category — keeps the original structure from the API. */
  const grouped = computed(() => query.data.value ?? {});

  /** Sorted category names for consistent display order. */
  const categories = computed(() =>
    Object.keys(query.data.value ?? {}).sort(),
  );

  return { query, allPermissions, grouped, categories };
}

// ─── Create role ──────────────────────────────────────────────────────────────

export function useCreateRole() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (form: CreateRoleForm) => createRole(form),
    onSuccess: (newRole) => {
      // Optimistically append to list cache.
      queryClient.setQueryData<RbacRole[]>(queryKeys.rbacRoles, (prev = []) => [
        ...prev,
        {
          id: newRole.id,
          name: newRole.name,
          description: newRole.description,
          color: newRole.color,
          is_system: newRole.is_system,
          users_count: 0,
          permissions_count: newRole.permissions.length,
          permissions: newRole.permissions.map((p) => p.name),
        },
      ]);
      void queryClient.invalidateQueries({ queryKey: queryKeys.rbacRoles });
    },
  });
}

// ─── Update role (rename / description / permission toggle) ───────────────────

export function useUpdateRole() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, form }: { id: number; form: UpdateRoleForm }) => updateRole(id, form),
    onSuccess: (updated) => {
      // Patch the list cache entry.
      queryClient.setQueryData<RbacRole[]>(queryKeys.rbacRoles, (prev = []) =>
        prev.map((r) =>
          r.id === updated.id
            ? {
                ...r,
                name: updated.name,
                description: updated.description,
                color: updated.color,
                permissions_count: updated.permissions.length,
                permissions: updated.permissions.map((p) => p.name),
              }
            : r,
        ),
      );
      queryClient.setQueryData(queryKeys.rbacRole(updated.id), updated);
      void queryClient.invalidateQueries({ queryKey: queryKeys.rbacRoles });
    },
  });
}

// ─── Delete role ──────────────────────────────────────────────────────────────

export function useDeleteRole() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => deleteRole(id),
    onSuccess: (_data, id) => {
      queryClient.setQueryData<RbacRole[]>(queryKeys.rbacRoles, (prev = []) =>
        prev.filter((r) => r.id !== id),
      );
      queryClient.removeQueries({ queryKey: queryKeys.rbacRole(id) });
    },
  });
}

// ─── Create / delete permission ───────────────────────────────────────────────

export function useCreatePermission() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (form: { name: string; description?: string; category: string }) =>
      createPermission(form),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: queryKeys.rbacPermissions });
    },
  });
}

export function useDeletePermission() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => deletePermission(id),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: queryKeys.rbacPermissions });
      void queryClient.invalidateQueries({ queryKey: queryKeys.rbacRoles });
    },
  });
}

// ─── User ↔ Role ──────────────────────────────────────────────────────────────

export function useUserRoles(userId: number) {
  const query = useQuery({
    queryKey: queryKeys.rbacUserRoles(userId),
    queryFn: () => getUserRoles(userId),
    staleTime: 30_000,
  });

  const userRoles = computed(() => query.data.value ?? []);

  return { query, userRoles };
}

export function useSyncUserRoles(userId: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (roleIds: number[]) => syncUserRoles(userId, roleIds),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: queryKeys.rbacUserRoles(userId) });
    },
  });
}

export function useAssignUserRole(userId: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (roleId: number) => assignUserRole(userId, roleId),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: queryKeys.rbacUserRoles(userId) });
    },
  });
}

export function useRemoveUserRole(userId: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (roleId: number) => removeUserRole(userId, roleId),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: queryKeys.rbacUserRoles(userId) });
    },
  });
}
