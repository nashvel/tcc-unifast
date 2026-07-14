<script setup lang="ts">
import { ref } from "vue";
import { IconCheck, IconMoon, IconSun } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
const theme = ref("light");
const density = ref("comfortable");
const saved = ref(false);
</script>
<template>
  <div>
    <PageHeader
      title="Appearance"
      description="Choose how the UniFAST TES workspace looks on this device."
    />
    <section class="max-w-3xl space-y-4">
      <article class="rounded-lg border bg-surface p-5">
        <h2 class="text-sm font-semibold">Theme</h2>
        <div class="mt-4 grid grid-cols-2 gap-3">
          <button
            v-for="t in [
              { id: 'light', label: 'Light', icon: IconSun },
              { id: 'dark', label: 'Dark', icon: IconMoon },
            ]"
            :key="t.id"
            :class="[
              'relative rounded-lg border p-4 text-left',
              theme === t.id ? 'border-primary ring-1 ring-primary' : '',
            ]"
            @click="theme = t.id"
          >
            <component :is="t.icon" :size="20" />
            <p class="mt-3 text-sm font-medium">{{ t.label }}</p>
            <IconCheck
              v-if="theme === t.id"
              :size="16"
              class="absolute right-3 top-3 text-primary"
            />
          </button>
        </div>
      </article>
      <article class="rounded-lg border bg-surface p-5">
        <h2 class="text-sm font-semibold">Interface density</h2>
        <div class="mt-4 flex gap-2">
          <button
            v-for="d in ['compact', 'comfortable', 'spacious']"
            :key="d"
            :class="[
              'rounded-md border px-3 py-2 text-xs capitalize',
              density === d ? 'bg-primary text-white' : '',
            ]"
            @click="density = d"
          >
            {{ d }}
          </button>
        </div>
      </article>
      <div class="flex items-center justify-end gap-3">
        <span v-if="saved" class="text-xs text-success">Preferences saved.</span
        ><button class="rounded-md bg-primary px-4 py-2 text-xs text-white" @click="saved = true">
          Save preferences
        </button>
      </div>
    </section>
  </div>
</template>
