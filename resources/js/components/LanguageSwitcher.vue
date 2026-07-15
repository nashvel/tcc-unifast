<script setup lang="ts">
import { computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import {
  getCurrentLanguage,
  languageLabels,
  setLanguage,
  supportedLanguages,
  type SupportedLanguage,
} from "@/i18n";

const route = useRoute();
const router = useRouter();
const { t } = useI18n();

const currentLanguage = computed(() => {
  const lang = Array.isArray(route.query.lang) ? route.query.lang[0] : route.query.lang;
  return supportedLanguages.includes(lang as SupportedLanguage)
    ? (lang as SupportedLanguage)
    : getCurrentLanguage();
});

async function changeLanguage(event: Event) {
  const target = event.target as HTMLSelectElement;
  const lang = target.value as SupportedLanguage;
  await setLanguage(lang);
  await router.replace({
    path: route.path,
    hash: route.hash,
    query: { ...route.query, lang },
  });
}
</script>

<template>
  <label class="inline-flex items-center gap-2 text-xs text-text-muted">
    <span class="sr-only">{{ t("language.select") }}</span>
    <select
      :value="currentLanguage"
      class="h-9 rounded-md border bg-surface px-2 text-xs text-text"
      :aria-label="t('language.select')"
      @change="changeLanguage"
    >
      <option v-for="lang in supportedLanguages" :key="lang" :value="lang">
        {{ languageLabels[lang] }}
      </option>
    </select>
  </label>
</template>
