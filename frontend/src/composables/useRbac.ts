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
  getUserModules,
  updateUserModule,
  syncUserModules,
} from "@/api/rbac";
import type {
  CreateRoleForm,
  RbacRole,
  RbacRoleDetail,
  UpdateRoleForm,
  RbacOperationalModule,
  RbacUserModuleRow,
  UserModulesResponse,
} from "@/api/rbac";

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

export function useRbacPermissions(scope?: string) {
  const query = useQuery({
    queryKey: scope ? [...queryKeys.rbacPermissions, scope] : queryKeys.rbacPermissions,
    queryFn: () => listPermissions(scope),
    staleTime: 60_000,
  });

  /** Flat list of all permissions across all categories. */
  const allPermissions = computed(() =>
    Object.values(query.data.value ?? {}).flat(),
  );

  /** Grouped by category — keeps the original structure from the API. */
  const grouped = computed(() => query.data.value ?? {});

  const preferredCategoryOrder = [
    "Operations",
    "Validation",
    "Communication",
    "Administration",
    "Users",
    "Batches",
    "Documents",
    "Grantees",
    "Academic",
    "Settings",
    "Audit",
  ];

  /** Sorted category names for consistent display order, prioritizing operational modules. */
  const categories = computed(() => {
    const rawCategories = Object.keys(query.data.value ?? {});
    return rawCategories.sort((a, b) => {
      const idxA = preferredCategoryOrder.indexOf(a);
      const idxB = preferredCategoryOrder.indexOf(b);
      if (idxA !== -1 && idxB !== -1) return idxA - idxB;
      if (idxA !== -1) return -1;
      if (idxB !== -1) return 1;
      return a.localeCompare(b);
    });
  });

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

// ─── Per-User Operational Modules ─────────────────────────────────────────────

export const userModulesQueryKey = ["rbac", "user-modules"] as const;

export function useUserModules() {
  const queryClient = useQueryClient();

  const query = useQuery({
    queryKey: userModulesQueryKey,
    queryFn: getUserModules,
    staleTime: 30_000,
  });

  const modules = computed<RbacOperationalModule[]>(() => query.data.value?.modules ?? []);
  const users = computed<RbacUserModuleRow[]>(() => query.data.value?.users ?? []);
  const developers = computed<RbacUserModuleRow[]>(() => query.data.value?.developers ?? []);
  const nonAssignable = computed(() => query.data.value?.non_assignable ?? null);

  // Group modules by Desk
  const desks = computed(() => {
    const map: Record<string, RbacOperationalModule[]> = {};
    for (const mod of modules.value) {
      if (!map[mod.desk]) map[mod.desk] = [];
      map[mod.desk].push(mod);
    }
    return map;
  });

  const deskOrder = [
    "Operations Desk",
    "Validation Desk",
    "Communication & Reports",
    "Administration & Security",
  ];

  const sortedDeskNames = computed(() => {
    const raw = Object.keys(desks.value);
    return raw.sort((a, b) => {
      const idxA = deskOrder.indexOf(a);
      const idxB = deskOrder.indexOf(b);
      if (idxA !== -1 && idxB !== -1) return idxA - idxB;
      if (idxA !== -1) return -1;
      if (idxB !== -1) return 1;
      return a.localeCompare(b);
    });
  });

  const updateMutation = useMutation({
    mutationFn: ({ userId, moduleKey, enabled }: { userId: number; moduleKey: string; enabled: boolean }) =>
      updateUserModule(userId, moduleKey, enabled),
    onSuccess: (updatedUser) => {
      queryClient.setQueryData<UserModulesResponse>(userModulesQueryKey, (prev) => {
        if (!prev) return prev;
        return {
          ...prev,
          users: prev.users.map((u) => (u.id === updatedUser.id ? updatedUser : u)),
        };
      });
      void queryClient.invalidateQueries({ queryKey: userModulesQueryKey });
    },
  });

  const syncMutation = useMutation({
    mutationFn: ({ userId, modules }: { userId: number; modules: string[] }) =>
      syncUserModules(userId, modules),
    onSuccess: (updatedUser) => {
      queryClient.setQueryData<UserModulesResponse>(userModulesQueryKey, (prev) => {
        if (!prev) return prev;
        return {
          ...prev,
          users: prev.users.map((u) => (u.id === updatedUser.id ? updatedUser : u)),
        };
      });
      void queryClient.invalidateQueries({ queryKey: userModulesQueryKey });
    },
  });

  return {
    query,
    modules,
    users,
    developers,
    nonAssignable,
    desks,
    sortedDeskNames,
    updateMutation,
    syncMutation,
  };
}
