import type { RouteLocationRaw, Router, RouteLocationNormalized } from "vue-router";
import {
  fallbackLanguage,
  isSupportedLanguage,
  languageFromBrowser,
  setLanguage,
  type SupportedLanguage,
} from "@/i18n";

function queryLang(value: unknown): SupportedLanguage | null {
  const candidate = Array.isArray(value) ? value[0] : value;
  return isSupportedLanguage(candidate) ? candidate : null;
}

export function routeLanguage(route?: Pick<RouteLocationNormalized, "query">): SupportedLanguage {
  return queryLang(route?.query.lang) ?? fallbackLanguage;
}

export function initialLanguage(route?: Pick<RouteLocationNormalized, "query">): SupportedLanguage {
  const fromUrl = queryLang(route?.query.lang);
  if (fromUrl) return fromUrl;

  const browserLanguage = languageFromBrowser();
  if (browserLanguage !== fallbackLanguage) return browserLanguage;

  const boot = typeof window !== "undefined" ? window.__APP_LOCALE__?.currentLanguage : undefined;
  if (isSupportedLanguage(boot)) return boot;

  return fallbackLanguage;
}

export function withLang(to: RouteLocationRaw, lang?: unknown): RouteLocationRaw {
  const currentLang =
    queryLang(lang) ??
    (typeof window !== "undefined"
      ? queryLang(new URLSearchParams(window.location.search).get("lang"))
      : null) ??
    fallbackLanguage;

  if (typeof to === "string") {
    const url = new URL(to, typeof window === "undefined" ? "http://localhost" : window.location.href);
    url.searchParams.set("lang", currentLang);
    return `${url.pathname}${url.search}${url.hash}`;
  }

  return {
    ...to,
    query: {
      ...(to.query ?? {}),
      lang: currentLang,
    },
  };
}

export function installLanguageRouting(router: Router): void {
  router.beforeEach(async (to, from) => {
    const toLang = queryLang(to.query.lang);
    const rawLang = Array.isArray(to.query.lang) ? to.query.lang[0] : to.query.lang;
    const fromLang = queryLang(from.query.lang);
    const desiredLang = toLang ?? fromLang ?? initialLanguage(to);

    if (rawLang !== undefined && !toLang) {
      return {
        path: to.path,
        params: to.params,
        hash: to.hash,
        query: { ...to.query, lang: fallbackLanguage },
        replace: true,
      };
    }

    if (!toLang) {
      return {
        path: to.path,
        params: to.params,
        hash: to.hash,
        query: { ...to.query, lang: desiredLang },
        replace: true,
      };
    }

    await setLanguage(desiredLang);
    return true;
  });
}

declare global {
  interface Window {
    __APP_LOCALE__?: {
      currentLanguage: string;
      availableLanguages: string[];
      fallbackLanguage: string;
    };
  }
}
