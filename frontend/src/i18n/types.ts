export const supportedLanguages = ["en", "tl", "ceb"] as const;

export type SupportedLanguage = (typeof supportedLanguages)[number];

export type LocaleMessageSchema = Record<string, any>;

export const fallbackLanguage: SupportedLanguage = "en";

export const languageLabels: Record<SupportedLanguage, string> = {
  en: "English",
  tl: "Tagalog",
  ceb: "Bisaya",
};

export function isSupportedLanguage(value: unknown): value is SupportedLanguage {
  return typeof value === "string" && supportedLanguages.includes(value as SupportedLanguage);
}
