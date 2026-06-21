export type Role = "Admin" | "UniFAST Staff" | "Office Head" | "Student Grantee";

export interface SystemUser {
  id: string;
  username: string;
  fullName: string;
  email: string;
  role: Role;
  active: boolean;
  mfa: boolean;
  lastLogin: string;
}

export const mockUsers: SystemUser[] = [
  { id: "u1", username: "admin", fullName: "System Administrator", email: "admin@unifast.gov.ph", role: "Admin", active: true, mfa: true, lastLogin: "2026-06-20 08:11" },
  { id: "u2", username: "r.santos", fullName: "Ricardo Santos", email: "r.santos@unifast.gov.ph", role: "Office Head", active: true, mfa: true, lastLogin: "2026-06-20 07:55" },
  { id: "u3", username: "j.cruz", fullName: "Jessica Cruz", email: "j.cruz@unifast.gov.ph", role: "UniFAST Staff", active: true, mfa: false, lastLogin: "2026-06-20 09:02" },
  { id: "u4", username: "p.tan", fullName: "Patricia Tan", email: "p.tan@unifast.gov.ph", role: "UniFAST Staff", active: true, mfa: false, lastLogin: "2026-06-19 17:40" },
  { id: "u5", username: "k.aquino", fullName: "Kris Aquino", email: "k.aquino@unifast.gov.ph", role: "UniFAST Staff", active: false, mfa: false, lastLogin: "2026-05-14 11:21" },
];

export const permissionModules = [
  { module: "Dashboard", perms: ["view"] },
  { module: "Masterlist", perms: ["view", "import", "delete"] },
  { module: "Batches", perms: ["view", "create", "edit", "archive"] },
  { module: "Grantees", perms: ["view", "edit", "update_status"] },
  { module: "Documents", perms: ["view", "approve", "reject", "flag_suspicious"] },
  { module: "Academic", perms: ["view", "edit"] },
  { module: "Eligibility", perms: ["view", "evaluate", "override"] },
  { module: "Announcements", perms: ["view", "create", "publish"] },
  { module: "Reports", perms: ["view", "export"] },
  { module: "Audit Trail", perms: ["view", "export"] },
  { module: "Users & Roles", perms: ["view", "manage"] },
] as const;

export const rolePermissions: Record<Role, Record<string, string[]>> = {
  Admin: Object.fromEntries(permissionModules.map((m) => [m.module, [...m.perms]])),
  "Office Head": {
    Dashboard: ["view"], Masterlist: ["view"], Batches: ["view", "create", "edit", "archive"], Grantees: ["view", "edit", "update_status"],
    Documents: ["view", "approve", "reject", "flag_suspicious"], Academic: ["view"], Eligibility: ["view", "evaluate", "override"],
    Announcements: ["view", "create", "publish"], Reports: ["view", "export"], "Audit Trail": ["view", "export"], "Users & Roles": ["view"],
  },
  "UniFAST Staff": {
    Dashboard: ["view"], Masterlist: ["view", "import"], Batches: ["view"], Grantees: ["view", "edit"],
    Documents: ["view", "approve", "reject", "flag_suspicious"], Academic: ["view", "edit"], Eligibility: ["view", "evaluate"],
    Announcements: ["view", "create"], Reports: ["view"], "Audit Trail": ["view"], "Users & Roles": [],
  },
  "Student Grantee": {
    Dashboard: ["view"], Masterlist: [], Batches: [], Grantees: [], Documents: [], Academic: ["view"], Eligibility: ["view"],
    Announcements: ["view"], Reports: [], "Audit Trail": [], "Users & Roles": [],
  },
};
