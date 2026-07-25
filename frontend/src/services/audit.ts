import type { Router } from "vue-router";
import { apiFetch } from "@/api/client";

type AuditPayload = {
  action: string;
  module: string;
  target?: string;
  context?: Record<string, unknown>;
};

const ignoredTargets = new Set(["HTML", "BODY"]);

function moduleFromPath(path: string) {
  const clean = path.replace(/^\/+/, "");
  if (!clean) return "Public";
  const [, module = "dashboard"] = clean.split("/");
  return module
    .split("-")
    .filter(Boolean)
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(" ");
}

function compact(value: string | null | undefined, fallback = "Untitled") {
  const normalized = value?.replace(/\s+/g, " ").trim();
  if (!normalized) return fallback;
  return normalized.length > 180 ? `${normalized.slice(0, 177)}...` : normalized;
}

function describeElement(element: HTMLElement) {
  return compact(
    element.dataset.auditLabel ||
      element.getAttribute("aria-label") ||
      element.getAttribute("title") ||
      element.innerText ||
      element.textContent ||
      element.getAttribute("placeholder") ||
      element.tagName,
  );
}

function emitAudit(payload: AuditPayload) {
  apiFetch("/api/audit-events", {
    method: "POST",
    body: JSON.stringify(payload),
  }).catch(() => {});
}

export function installAuditLogger(router: Router) {
  if (typeof window === "undefined" || typeof document === "undefined") return;

  router.afterEach((to, from) => {
    if (!to.path.startsWith("/app") && !to.path.startsWith("/student")) return;

    emitAudit({
      action: "route_view",
      module: moduleFromPath(to.path),
      target: to.fullPath,
      context: {
        from: from.fullPath,
        title: document.title,
      },
    });
  });

  document.addEventListener(
    "click",
    (event) => {
      const raw = event.target;
      if (!(raw instanceof HTMLElement)) return;

      const element = raw.closest<HTMLElement>(
        '[data-audit], button, a, input, select, textarea, summary, [role="button"], [role="tab"], [role="menuitem"]',
      );
      if (!element || ignoredTargets.has(element.tagName)) return;

      const path = router.currentRoute.value.path;
      if (!path.startsWith("/app") && !path.startsWith("/student")) return;

      emitAudit({
        action: element.dataset.auditAction || "ui_click",
        module: element.dataset.auditModule || moduleFromPath(path),
        target: describeElement(element),
        context: {
          path: router.currentRoute.value.fullPath,
          tag: element.tagName.toLowerCase(),
          type: element.getAttribute("type"),
          href: element instanceof HTMLAnchorElement ? element.href : undefined,
          auditId: element.dataset.audit,
        },
      });
    },
    true,
  );
}
