<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import { IconCheck, IconChevronDown, IconLanguage } from "@tabler/icons-vue";
import {
  getCurrentLanguage,
  languageLabels,
  setLanguage,
  supportedLanguages,
  type SupportedLanguage,
} from "@/i18n";

const props = defineProps<{ dark?: boolean }>();

const route = useRoute();
const router = useRouter();
const { t } = useI18n();
const open = ref(false);
const root = ref<HTMLElement | null>(null);

const currentLanguage = computed(() => {
  const lang = Array.isArray(route.query.lang) ? route.query.lang[0] : route.query.lang;
  return supportedLanguages.includes(lang as SupportedLanguage)
    ? (lang as SupportedLanguage)
    : getCurrentLanguage();
});

const currentLanguageLabel = computed(() => languageLabels[currentLanguage.value]);

function close() {
  open.value = false;
}

function handleDocumentClick(event: MouseEvent) {
  if (!root.value?.contains(event.target as Node)) close();
}

function handleEscape(event: KeyboardEvent) {
  if (event.key === "Escape") close();
}

async function changeLanguage(lang: SupportedLanguage) {
  await setLanguage(lang);
  await router.replace({
    path: route.path,
    hash: route.hash,
    query: { ...route.query, lang },
  });
  close();
}

onMounted(() => {
  document.addEventListener("click", handleDocumentClick);
  document.addEventListener("keydown", handleEscape);
});

onBeforeUnmount(() => {
  document.removeEventListener("click", handleDocumentClick);
  document.removeEventListener("keydown", handleEscape);
});
</script>

<template>
  <div ref="root" class="relative inline-flex">
    <button
      type="button"
      :class="[
        'inline-flex h-8 items-center gap-1.5 rounded-md px-2.5 text-xs font-medium transition focus:outline-none',
        dark
          ? 'border border-[var(--border)] bg-[var(--surface)] text-[var(--text-muted)] hover:bg-[var(--surface-muted)]'
          : 'border bg-surface px-3 text-text shadow-sm hover:bg-surface-muted focus:ring-2 focus:ring-primary/40',
      ]"
      :aria-label="t('language.select')"
      :aria-expanded="open"
      aria-haspopup="menu"
      @click.stop="open = !open"
    >
      <IconLanguage :size="14" :class="dark ? 'text-[var(--text-soft)]' : 'text-text-muted'" />
      <span class="hidden sm:inline">{{ currentLanguageLabel }}</span>
      <span class="sm:hidden">{{ currentLanguage.toUpperCase() }}</span>
      <IconChevronDown
        :size="12"
        :class="['transition-transform', dark ? 'text-[var(--text-soft)]' : 'text-text-muted', { 'rotate-180': open }]"
      />
    </button>

    <div
      v-if="open"
      :class="[
        'absolute right-0 top-full z-50 mt-1 w-40 overflow-hidden rounded-lg border py-1 shadow-xl',
        dark
          ? 'border-[var(--border)] bg-[var(--surface)]'
          : 'border bg-surface',
      ]"
      role="menu"
    >
      <button
        v-for="lang in supportedLanguages"
        :key="lang"
        type="button"
        :class="[
          'flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm transition focus:outline-none',
          dark
            ? lang === currentLanguage
              ? 'font-semibold text-[var(--primary)]'
              : 'text-[var(--text)] hover:bg-[var(--surface-muted)]'
            : lang === currentLanguage
              ? 'font-semibold text-primary'
              : 'text-text hover:bg-surface-muted',
        ]"
        role="menuitemradio"
        :aria-checked="lang === currentLanguage"
        :aria-label="t('language.changeTo', { language: languageLabels[lang] })"
        @click="changeLanguage(lang)"
      >
        <span>{{ languageLabels[lang] }}</span>
        <IconCheck v-if="lang === currentLanguage" :size="14" />
      </button>
    </div>
  </div>
</template>
