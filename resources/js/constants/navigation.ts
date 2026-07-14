import type { Component } from "vue";
import {
  IconBell,
  IconChecklist,
  IconDashboard,
  IconFileCheck,
  IconFileImport,
  IconFolder,
  IconFolders,
  IconHistory,
  IconLifebuoy,
  IconReportAnalytics,
  IconSchool,
  IconSettings,
  IconShieldCheck,
  IconSpeakerphone,
  IconUpload,
  IconUserCircle,
  IconUserCog,
  IconUsersGroup,
} from "@tabler/icons-vue";

export type NavigationSection = {
  label?: string;
  items: { label: string; path: string; icon: Component }[];
};

export const adminNavigation: NavigationSection[] = [
  { items: [{ label: "Dashboard", path: "/app", icon: IconDashboard }] },
  {
    label: "Communication",
    items: [
      { label: "Announcements", path: "/app/announcements", icon: IconSpeakerphone },
      { label: "Monitoring & Reports", path: "/app/reports", icon: IconReportAnalytics },
      { label: "Support Tickets", path: "/app/support", icon: IconLifebuoy },
    ],
  },
  {
    label: "Administration",
    items: [
      { label: "Audit Trail", path: "/app/audit", icon: IconHistory },
      { label: "Security Findings", path: "/app/security", icon: IconShieldCheck },
      { label: "Security Memory", path: "/app/security/memory", icon: IconShieldCheck },
      { label: "Users & Roles", path: "/app/users", icon: IconUserCog },
      { label: "Settings", path: "/app/settings", icon: IconSettings },
    ],
  },
];

export const staffNavigation: NavigationSection[] = [
  { items: [{ label: "Dashboard", path: "/app", icon: IconDashboard }] },
  {
    label: "Operations",
    items: [
      { label: "Masterlist", path: "/app/masterlist", icon: IconFileImport },
      { label: "Batches", path: "/app/batches", icon: IconFolders },
      { label: "Grantees", path: "/app/grantees", icon: IconUsersGroup },
    ],
  },
  {
    label: "Validation",
    items: [
      { label: "Document Validation", path: "/app/documents", icon: IconFileCheck },
      { label: "File Manager", path: "/app/files", icon: IconFolder },
      { label: "Academic Records", path: "/app/academic", icon: IconSchool },
      { label: "Eligibility", path: "/app/eligibility", icon: IconChecklist },
    ],
  },
  {
    label: "Communication",
    items: [
      { label: "Announcements", path: "/app/announcements", icon: IconSpeakerphone },
      { label: "Reports", path: "/app/reports", icon: IconReportAnalytics },
      { label: "Support Tickets", path: "/app/support", icon: IconLifebuoy },
    ],
  },
  {
    label: "Administration",
    items: [
      { label: "Audit Trail", path: "/app/audit", icon: IconHistory },
      { label: "Settings", path: "/app/settings", icon: IconSettings },
    ],
  },
];

export const studentNavigation: NavigationSection[] = [
  {
    items: [
      { label: "Dashboard", path: "/student", icon: IconDashboard },
      { label: "Verify Identity", path: "/student/verify", icon: IconShieldCheck },
      { label: "Profile", path: "/student/profile", icon: IconUserCircle },
      { label: "Required Documents", path: "/student/documents", icon: IconFileCheck },
      { label: "Upload Documents", path: "/student/upload", icon: IconUpload },
      { label: "Announcements", path: "/student/announcements", icon: IconSpeakerphone },
      { label: "Notifications", path: "/student/notifications", icon: IconBell },
      { label: "Settings", path: "/student/settings", icon: IconSettings },
    ],
  },
];

export const lockedStudentNavigation: NavigationSection[] = [
  {
    items: [
      { label: "Dashboard", path: "/student", icon: IconDashboard },
      { label: "Verify Identity", path: "/student/verify", icon: IconShieldCheck },
      { label: "Settings", path: "/student/settings", icon: IconSettings },
    ],
  },
];
