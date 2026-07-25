import { nextTick } from "vue";
import { createI18n } from "vue-i18n";
import {
  fallbackLanguage,
  isSupportedLanguage,
  type LocaleMessageSchema,
  type SupportedLanguage,
} from "./types";

const localeLoaders = import.meta.glob<LocaleMessageSchema>("../locales/*.json", {
  import: "default",
});

const loadedLanguages = new Set<SupportedLanguage>();
const pendingLoads = new Map<SupportedLanguage, Promise<void>>();

export { fallbackLanguage, isSupportedLanguage };
export type { SupportedLanguage };
export { languageLabels, supportedLanguages } from "./types";

export const i18n = createI18n({
  legacy: false as const,
  globalInjection: true,
  locale: fallbackLanguage,
  fallbackLocale: fallbackLanguage,
  missingWarn: false,
  fallbackWarn: false,
  messages: {
    en: {},
  } as Record<string, LocaleMessageSchema>,
});

function normalizeLanguage(lang: unknown): SupportedLanguage {
  return isSupportedLanguage(lang) ? lang : fallbackLanguage;
}

function localePath(lang: SupportedLanguage) {
  return `../locales/${lang}.json`;
}

export function getCurrentLanguage(): SupportedLanguage {
  return normalizeLanguage(i18n.global.locale.value);
}

export async function loadLanguageAsync(lang: unknown): Promise<SupportedLanguage> {
  const language = normalizeLanguage(lang);

  if (loadedLanguages.has(language)) return language;
  const pending = pendingLoads.get(language);
  if (pending) {
    await pending;
    return language;
  }

  const loader = localeLoaders[localePath(language)];
  if (!loader) return fallbackLanguage;

  const load = loader().then((messages) => {
    i18n.global.setLocaleMessage(language, messages);
    loadedLanguages.add(language);
    pendingLoads.delete(language);
  });

  pendingLoads.set(language, load);
  await load;

  return language;
}

export async function setLanguage(lang: unknown): Promise<SupportedLanguage> {
  const requestedLanguage = normalizeLanguage(lang);
  if (requestedLanguage !== fallbackLanguage) {
    await loadLanguageAsync(fallbackLanguage);
  }
  const language = await loadLanguageAsync(requestedLanguage);
  i18n.global.locale.value = language;

  if (typeof document !== "undefined") {
    document.documentElement.lang = language;
  }

  await nextTick();
  return language;
}

export function languageFromBrowser(): SupportedLanguage {
  if (typeof navigator === "undefined") return fallbackLanguage;

  const candidates = navigator.languages?.length ? navigator.languages : [navigator.language];
  for (const candidate of candidates) {
    const normalized = candidate.toLowerCase();
    if (normalized.startsWith("ceb")) return "ceb";
    if (normalized.startsWith("tl") || normalized.startsWith("fil")) return "tl";
    if (normalized.startsWith("en")) return "en";
  }

  return fallbackLanguage;
}
