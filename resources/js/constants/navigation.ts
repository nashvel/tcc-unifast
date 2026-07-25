import type { Component } from "vue";
import {
  IconBell,
  IconChecklist,
  IconDashboard,
  IconFileCheck,
  IconFileImport,
  IconFileInvoice,
  IconFolder,
  IconFolders,
  IconHistory,
  IconLifebuoy,
  IconReportAnalytics,
  IconReportMoney,
  IconSchool,
  IconSettings,
  IconShieldCheck,
  IconSpeakerphone,
  IconUpload,
  IconUserCircle,
  IconUserCog,
  IconUsersGroup,
  IconUserPlus,
} from "@tabler/icons-vue";

export type NavigationSection = {
  labelKey?: string;
  items: { labelKey: string; path: string; icon: Component }[];
};

export const adminNavigation: NavigationSection[] = [
  { items: [{ labelKey: "common.dashboard", path: "/app", icon: IconDashboard }] },
  {
    labelKey: "nav.communication",
    items: [
      { labelKey: "nav.announcements", path: "/app/announcements", icon: IconSpeakerphone },
      { labelKey: "nav.monitoringReports", path: "/app/reports", icon: IconReportAnalytics },
      { labelKey: "nav.billing", path: "/app/billing", icon: IconFileInvoice },
      { labelKey: "nav.distributionReport", path: "/app/distribution", icon: IconReportMoney },
      { labelKey: "nav.supportTickets", path: "/app/support", icon: IconLifebuoy },
    ],
  },
  {
    labelKey: "nav.administration",
    items: [
      { labelKey: "nav.auditTrail", path: "/app/audit", icon: IconHistory },
      { labelKey: "nav.securityFindings", path: "/app/security", icon: IconShieldCheck },
      { labelKey: "nav.securityMemory", path: "/app/security/memory", icon: IconShieldCheck },
      { labelKey: "nav.usersRoles", path: "/app/users", icon: IconUserCog },
      { labelKey: "common.settings", path: "/app/settings", icon: IconSettings },
    ],
  },
];

export const staffNavigation: NavigationSection[] = [
  { items: [{ labelKey: "common.dashboard", path: "/app", icon: IconDashboard }] },
  {
    labelKey: "nav.operations",
    items: [
      { labelKey: "nav.onboardingCenter", path: "/app/onboarding", icon: IconUserPlus },
      { labelKey: "nav.masterlist", path: "/app/masterlist", icon: IconFileImport },
      { labelKey: "nav.batches", path: "/app/batches", icon: IconFolders },
      { labelKey: "nav.grantees", path: "/app/grantees", icon: IconUsersGroup },
    ],
  },
  {
    labelKey: "nav.validation",
    items: [
      { labelKey: "nav.documentValidation", path: "/app/documents", icon: IconFileCheck },
      { labelKey: "nav.fileManager", path: "/app/files", icon: IconFolder },
      { labelKey: "nav.academicRecords", path: "/app/academic", icon: IconSchool },
      { labelKey: "nav.eligibility", path: "/app/eligibility", icon: IconChecklist },
    ],
  },
  {
    labelKey: "nav.communication",
    items: [
      { labelKey: "nav.announcements", path: "/app/announcements", icon: IconSpeakerphone },
      { labelKey: "nav.reports", path: "/app/reports", icon: IconReportAnalytics },
      { labelKey: "nav.billing", path: "/app/billing", icon: IconFileInvoice },
      { labelKey: "nav.distributionReport", path: "/app/distribution", icon: IconReportMoney },
      { labelKey: "nav.supportTickets", path: "/app/support", icon: IconLifebuoy },
    ],
  },
  {
    labelKey: "nav.administration",
    items: [
      { labelKey: "nav.auditTrail", path: "/app/audit", icon: IconHistory },
      { labelKey: "common.settings", path: "/app/settings", icon: IconSettings },
    ],
  },
];

export const studentNavigation: NavigationSection[] = [
  {
    items: [
      { labelKey: "common.dashboard", path: "/student", icon: IconDashboard },
      { labelKey: "nav.verifyIdentity", path: "/student/verify", icon: IconShieldCheck },
      { labelKey: "common.profile", path: "/student/profile", icon: IconUserCircle },
      { labelKey: "nav.requiredDocuments", path: "/student/documents", icon: IconFileCheck },
      { labelKey: "nav.uploadDocuments", path: "/student/upload", icon: IconUpload },
      { labelKey: "nav.announcements", path: "/student/announcements", icon: IconSpeakerphone },
      { labelKey: "shell.notifications", path: "/student/notifications", icon: IconBell },
      { labelKey: "common.settings", path: "/student/settings", icon: IconSettings },
    ],
  },
];

export const lockedStudentNavigation: NavigationSection[] = [
  {
    items: [
      { labelKey: "common.dashboard", path: "/student", icon: IconDashboard },
      { labelKey: "nav.verifyIdentity", path: "/student/verify", icon: IconShieldCheck },
      { labelKey: "common.settings", path: "/student/settings", icon: IconSettings },
    ],
  },
];
