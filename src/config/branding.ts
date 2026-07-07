import systemLogo from "@/assets/system-logo.png";
import dashboardHeader from "@/assets/dashboard-header.jpg";

/**
 * Central branding config.
 * Asset URLs are build-time hashed and served from the app CDN.
 */
export const branding = {
  systemName: "UniFAST TES",
  systemTagline: "Grantee Management",
  studentPortalName: "Student Portal",
  systemLogoUrl: systemLogo,
  dashboardHeaderUrl: dashboardHeader,
  institution: "Tagoloan Community College",
} as const;
