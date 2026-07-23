<script setup lang="ts">
import { ref } from "vue";
import { useI18n } from "vue-i18n";
import { IconCheck, IconMoon, IconSun } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";

const { t } = useI18n();
const theme = ref("light");
const density = ref("comfortable");
const saved = ref(false);
const themeOptions = [
  { id: "light", labelKey: "appearance.light", icon: IconSun },
  { id: "dark", labelKey: "appearance.dark", icon: IconMoon },
];
const densityOptions = [
  ["compact", "appearance.compact"],
  ["comfortable", "appearance.comfortable"],
  ["spacious", "appearance.spacious"],
] as const;
</script>
<template>
  <div>
    <PageHeader
      title="Appearance"
      description="Choose how the UniFAST TES workspace looks on this device."
    />
    <section class="max-w-3xl space-y-4">
      <article class="rounded-lg border bg-surface p-5">
        <h2 class="text-sm font-semibold">{{ t("appearance.theme") }}</h2>
        <div class="mt-4 grid grid-cols-2 gap-3">
          <button
            v-for="option in themeOptions"
            :key="option.id"
            :class="[
              'relative rounded-lg border p-4 text-left',
              theme === option.id ? 'border-primary ring-1 ring-primary' : '',
            ]"
            @click="theme = option.id"
          >
            <component :is="option.icon" :size="20" />
            <p class="mt-3 text-sm font-medium">{{ t(option.labelKey) }}</p>
            <IconCheck
              v-if="theme === option.id"
              :size="16"
              class="absolute right-3 top-3 text-primary"
            />
          </button>
        </div>
      </article>
      <article class="rounded-lg border bg-surface p-5">
        <h2 class="text-sm font-semibold">{{ t("appearance.interfaceDensity") }}</h2>
        <div class="mt-4 flex gap-2">
          <button
            v-for="d in densityOptions"
            :key="d[0]"
            :class="[
              'rounded-md border px-3 py-2 text-xs capitalize',
              density === d[0] ? 'bg-primary text-white' : '',
            ]"
            @click="density = d[0]"
          >
            {{ t(d[1]) }}
          </button>
        </div>
      </article>
      <div class="flex items-center justify-end gap-3">
        <span v-if="saved" class="text-xs text-success">{{ t("appearance.preferencesSaved") }}</span
        ><button class="rounded-md bg-primary px-4 py-2 text-xs text-white" @click="saved = true">
          {{ t("appearance.savePreferences") }}
        </button>
      </div>
    </section>
  </div>
</template>
