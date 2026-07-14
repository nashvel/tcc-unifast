<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useRoute } from "vue-router";
import { IconSparkles, IconX } from "@tabler/icons-vue";
import axlGuide from "@/assets/student/axl-guide.png";
import AppTour from "@/components/tour/AppTour.vue";
import { resolveTour } from "@/components/tour/tour-registry";

const route = useRoute();
const hidden = ref(false);
const tour = computed(() => resolveTour(route.path));

watch(
  () => route.path,
  () => {
    hidden.value = false;
  },
);
</script>

<template>
  <div
    v-if="tour && !hidden"
    class="axl-tour-guide fixed bottom-4 right-4 z-40 hidden max-w-[22rem] items-end gap-2 lg:flex"
  >
    <div
      class="axl-tour-bubble relative mb-6 rounded-2xl rounded-br-md border bg-surface px-4 py-3 shadow-xl"
    >
      <button
        class="absolute right-2 top-2 rounded p-1 text-text-soft hover:bg-surface-muted"
        aria-label="Hide AXL guide"
        @click="hidden = true"
      >
        <IconX :size="13" />
      </button>
      <p class="pr-5 text-xs font-semibold text-primary">AXL is here to guide you.</p>
      <p class="mt-1 max-w-[13rem] text-micro leading-4 text-text-muted">
        Start the {{ tour.title.toLowerCase() }} and I'll walk you through this menu.
      </p>
      <AppTour
        custom
        class="mt-3 inline-flex h-8 items-center gap-1.5 rounded-md bg-primary px-3 text-xs font-semibold text-white shadow-sm hover:bg-primary-hover"
      >
        <IconSparkles :size="14" />
        Start tour
      </AppTour>
    </div>
    <div class="relative">
      <img
        :src="axlGuide"
        alt="AXL student guide"
        class="axl-tour-character h-36 w-28 object-contain drop-shadow-2xl"
      />
      <div
        class="absolute bottom-1 left-1/2 flex -translate-x-1/2 items-center gap-1.5 rounded-md border bg-white/95 px-2 py-1 shadow"
      >
        <span class="text-xs font-bold text-primary">AXL</span>
        <span class="text-micro text-text-muted">Guide</span>
      </div>
    </div>
  </div>
</template>
