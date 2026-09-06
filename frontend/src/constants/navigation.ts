import type { Component } from "vue";
import {
  IconBell,
  IconChecklist,
  IconCode,
  IconDashboard,
  IconFileCheck,
  IconFileImport,
  IconFileInvoice,
  IconFolder,
  IconFolders,
  IconForms,
  IconHistory,
  IconLifebuoy,
  IconReportAnalytics,
  IconReportMoney,
  IconSchool,
  IconSeedling,
  IconSettings,
  IconShieldCheck,
  IconSpeakerphone,
  IconBrandFacebook,
  IconTerminal,
  IconUpload,
  IconUserCircle,
  IconUserCog,
  IconUsersGroup,
  IconUserPlus,
  IconUserCheck,
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
      { labelKey: "nav.formBuilder", path: "/app/forms", icon: IconForms },
      { labelKey: "nav.reports", path: "/app/reports", icon: IconReportAnalytics },
      { labelKey: "nav.supportTickets", path: "/app/support", icon: IconLifebuoy },
    ],
  },
  {
    labelKey: "nav.administration",
    items: [
      { labelKey: "nav.auditTrail", path: "/app/audit", icon: IconHistory },
      { labelKey: "nav.security", path: "/app/security", icon: IconShieldCheck },
      { labelKey: "nav.usersRoles", path: "/app/users", icon: IconUserCog },
      { labelKey: "common.settings", path: "/app/settings", icon: IconSettings },
    ],
  },
];

export const developerNavigation: NavigationSection[] = [
  { items: [{ labelKey: "common.dashboard", path: "/app", icon: IconDashboard }] },
  {
    labelKey: "nav.developer.system",
    items: [
      { labelKey: "nav.developer.rbac", path: "/app/developer/rbac", icon: IconShieldCheck },
      { labelKey: "nav.developer.apiDocs", path: "/app/developer/api-docs", icon: IconCode },
      { labelKey: "nav.developer.flowChart", path: "/app/developer/flow-chart", icon: IconTerminal },
    ],
  },
  {
    labelKey: "nav.developer.operations",
    items: [
      { labelKey: "nav.developer.supportTickets", path: "/app/developer/support", icon: IconLifebuoy },
      { labelKey: "nav.developer.auditTrail", path: "/app/developer/audit", icon: IconHistory },
      { labelKey: "nav.developer.collaborators", path: "/app/developer/collaborators", icon: IconUsersGroup },
    ],
  },
  {
    labelKey: "nav.administration",
    items: [
      { labelKey: "nav.usersRoles", path: "/app/developer/users", icon: IconUserCog },
      { labelKey: "nav.activationSeeder", path: "/app/activation-seeder", icon: IconSeedling },
      { labelKey: "common.settings", path: "/app/developer/settings", icon: IconSettings },
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
      { labelKey: "nav.programs", path: "/app/programs", icon: IconSchool },
      { labelKey: "nav.grantees", path: "/app/grantees", icon: IconUsersGroup },
    ],
  },
  {
    labelKey: "nav.validation",
    items: [
      { labelKey: "nav.documentValidation", path: "/app/documents", icon: IconFileCheck },
      { labelKey: "nav.faceReviews", path: "/app/face-reviews", icon: IconUserCheck },
      { labelKey: "nav.fileManager", path: "/app/files", icon: IconFolder },
      { labelKey: "nav.academicRecords", path: "/app/academic", icon: IconSchool },
      { labelKey: "nav.eligibility", path: "/app/eligibility", icon: IconChecklist },
    ],
  },
  {
    labelKey: "nav.communication",
    items: [
      { labelKey: "nav.announcements", path: "/app/announcements", icon: IconSpeakerphone },
      { labelKey: "nav.formBuilder", path: "/app/forms", icon: IconForms },
      { labelKey: "nav.reports", path: "/app/reports", icon: IconReportAnalytics },
      { labelKey: "nav.supportTickets", path: "/app/support", icon: IconLifebuoy },
    ],
  },
  {
    labelKey: "nav.administration",
    items: [
      { labelKey: "nav.auditTrail", path: "/app/audit", icon: IconHistory },
      { labelKey: "nav.security", path: "/app/security", icon: IconShieldCheck },
      { labelKey: "common.settings", path: "/app/settings", icon: IconSettings },
    ],
  },
];

export const studentNavigation: NavigationSection[] = [
  {
    items: [
      { labelKey: "common.dashboard", path: "/student", icon: IconDashboard },
      { labelKey: "nav.requiredDocuments", path: "/student/documents", icon: IconFileCheck },
      { labelKey: "nav.studentForms", path: "/student/forms", icon: IconForms },
      { labelKey: "nav.announcements", path: "/student/announcements", icon: IconSpeakerphone },
      { labelKey: "common.profile", path: "/student/profile", icon: IconUserCircle },
    ],
  },
];


export const lockedStudentNavigation: NavigationSection[] = [
  {
    items: [
      { labelKey: "nav.completeOnboarding", path: "/student/kyc", icon: IconShieldCheck },
      { labelKey: "nav.helpSupport", path: "/help/support", icon: IconLifebuoy },
    ],
  },
];
