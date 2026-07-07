import systemLogo from "@/assets/system-logo.png";

/**
 * Central branding config.
 * `systemLogoUrl` is a build-time hashed asset URL served from the app CDN.
 */
export const branding = {
  systemName: "UniFAST TES",
  systemTagline: "Grantee Management",
  studentPortalName: "Student Portal",
  systemLogoUrl: systemLogo,
  institution: "Tagoloan Community College",
} as const;
