<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useRoute } from "vue-router";
import { useI18n } from "vue-i18n";
import { IconHelpCircle, IconX } from "@tabler/icons-vue";
import axlGuide from "@/assets/student/axl-guide.png";
import { resolveTour } from "./tour-registry";

defineOptions({ inheritAttrs: false });

const props = withDefaults(
  defineProps<{
    class?: string;
    label?: string;
    custom?: boolean;
  }>(),
  {
    class: "",
    label: "Tour",
    custom: false,
  },
);

const route = useRoute();
const { t } = useI18n();
const active = ref(false);
const index = ref(0);
const targetRect = ref<DOMRect | null>(null);
const tooltip = ref({ left: 16, top: 16 });
const axl = ref({ left: 16, top: 16 });
let interval = 0;

const tour = computed(() => resolveTour(route.path));
const step = computed(() => tour.value?.steps[index.value] ?? null);
const stepCount = computed(() => tour.value?.steps.length ?? 0);
const axlMessages = [
  "tour.axl.checkFirst",
  "tour.axl.important",
  "tour.axl.lookHere",
  "tour.axl.rightSpot",
  "tour.axl.action",
  "tour.axl.quickTip",
];
const axlMessage = computed(() => t(axlMessages[index.value % axlMessages.length]));
const zoom = computed(() =>
  typeof window !== "undefined" && window.innerWidth >= 1024 ? 1.25 : 1,
);
const hasTarget = computed(() => step.value?.target !== "body" && targetRect.value !== null);
const spotlightStyle = computed(() => {
  if (!targetRect.value) return {};
  const z = zoom.value;
  return {
    left: `${(targetRect.value.left - 8) / z}px`,
    top: `${(targetRect.value.top - 8) / z}px`,
    width: `${(targetRect.value.width + 16) / z}px`,
    height: `${(targetRect.value.height + 16) / z}px`,
  };
});
const tooltipStyle = computed(() => ({
  left: `${tooltip.value.left / zoom.value}px`,
  top: `${tooltip.value.top / zoom.value}px`,
}));
const axlStyle = computed(() => ({
  left: `${axl.value.left / zoom.value}px`,
  top: `${axl.value.top / zoom.value}px`,
}));

function findTarget(selector: string) {
  if (selector === "body") return document.body;
  return document.querySelector<HTMLElement>(selector);
}

function clamp(value: number, min: number, max: number) {
  return Math.min(Math.max(value, min), max);
}

async function updatePosition() {
  if (!active.value || !step.value) return;
  await nextTick();

  const target = findTarget(step.value.target);
  const rect = target?.getBoundingClientRect() ?? null;
  targetRect.value = step.value.target === "body" ? null : rect;

  if (target && step.value.target !== "body") {
    target.scrollIntoView({ behavior: "smooth", block: "center", inline: "nearest" });
  }

  window.requestAnimationFrame(() => {
    const latest = step.value?.target
      ? findTarget(step.value.target)?.getBoundingClientRect()
      : null;
    if (step.value?.target !== "body" && latest) targetRect.value = latest;

    const z = zoom.value;
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    const width = Math.min(340 * z, viewportWidth - 32);
    const height = 190 * z;
    const margin = 24;
    const basis = latest && step.value?.target !== "body" ? latest : null;
    const preferredLeft = basis ? basis.left : (viewportWidth - width) / 2;
    const preferredTop = basis ? basis.bottom + 12 * z : (viewportHeight - height) / 2;
    const fallbackTop = basis ? basis.top - height - 12 * z : preferredTop;
    const top = preferredTop + height > viewportHeight - 16 ? fallbackTop : preferredTop;

    tooltip.value = {
      left: clamp(preferredLeft, margin, viewportWidth - width - margin),
      top: clamp(top, 16, viewportHeight - height - 16),
    };

    const guideWidth = 216 * z;
    const guideHeight = 330 * z;
    const gap = 34 * z;
    const rightOfTarget = basis ? basis.right + gap : tooltip.value.left + width + gap;
    const rightOfTooltip = tooltip.value.left + width + gap;
    const leftOfTooltip = tooltip.value.left - guideWidth - gap;
    const lowerOffset = 44 * z;
    const targetTop = basis
      ? basis.top + basis.height / 2 - guideHeight / 2 + lowerOffset
      : tooltip.value.top + lowerOffset;
    const preferredGuideLeft =
      rightOfTarget + guideWidth <= viewportWidth - margin ? rightOfTarget : rightOfTooltip;

    axl.value = {
      left:
        preferredGuideLeft + guideWidth <= viewportWidth - margin
          ? preferredGuideLeft
          : leftOfTooltip >= margin
            ? leftOfTooltip
            : clamp(
                tooltip.value.left + width - guideWidth,
                margin,
                viewportWidth - guideWidth - margin,
              ),
      top: clamp(targetTop, 16, viewportHeight - guideHeight - 16),
    };
  });
}

function start() {
  if (!tour.value) return;
  active.value = true;
  index.value = 0;
  updatePosition();
  window.clearInterval(interval);
  interval = window.setInterval(updatePosition, 180);
}

function close() {
  active.value = false;
  targetRect.value = null;
  window.clearInterval(interval);
}

function next() {
  if (!tour.value) return;
  if (index.value >= tour.value.steps.length - 1) {
    close();
    return;
  }
  index.value += 1;
  updatePosition();
}

function back() {
  index.value = Math.max(0, index.value - 1);
  updatePosition();
}

watch(
  () => route.path,
  () => close(),
);
watch(index, updatePosition);

onMounted(() => {
  window.addEventListener("resize", updatePosition);
  window.addEventListener("scroll", updatePosition, true);
});
onBeforeUnmount(() => {
  window.clearInterval(interval);
  window.removeEventListener("resize", updatePosition);
  window.removeEventListener("scroll", updatePosition, true);
});
</script>

<template>
  <button
    v-if="tour"
    type="button"
    :class="[
      custom
        ? ''
        : 'inline-flex h-8 items-center gap-1.5 rounded-md border bg-surface px-2.5 text-xs font-medium text-text-muted hover:bg-surface-muted hover:text-text',
      props.class,
    ]"
    :aria-label="t('tour.show')"
    :title="t('tour.show')"
    @click="start"
  >
    <slot>
      <IconHelpCircle :size="15" />
      <span class="hidden sm:inline">{{ label === "Tour" ? t("tour.label") : label }}</span>
    </slot>
  </button>

  <Teleport to="body">
    <Transition name="tour">
      <div v-if="active && step" class="tour-layer" role="dialog" aria-modal="true">
        <div class="tour-backdrop" @click="close" />
        <div v-if="hasTarget" class="tour-spotlight" :style="spotlightStyle" />
        <div class="tour-axl-guide" :style="axlStyle" aria-hidden="true">
          <div class="tour-axl-bubble">
            <p class="text-xs font-semibold text-primary">AXL</p>
            <p class="text-micro text-text-muted">{{ axlMessage }}</p>
          </div>
          <img :src="axlGuide" alt="" class="tour-axl-image" />
        </div>
        <section class="tour-tooltip" :style="tooltipStyle">
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="text-sm font-semibold">{{ step.title }}</p>
              <p class="mt-1 text-xs leading-5 text-text-muted">{{ step.body }}</p>
            </div>
            <button class="rounded p-1 text-text-soft hover:bg-surface-muted" @click="close">
              <IconX :size="15" />
            </button>
          </div>
          <footer class="mt-4 flex items-center justify-between gap-3 border-t pt-3">
            <span class="text-micro text-text-soft">
              {{ t("tour.stepCount", { current: index + 1, total: stepCount }) }}
            </span>
            <div class="flex gap-2">
              <button
                class="h-8 rounded-md px-2.5 text-xs text-text-muted hover:bg-surface-muted"
                @click="close"
              >
                {{ t("tour.skip") }}
              </button>
              <button
                class="h-8 rounded-md border px-2.5 text-xs disabled:opacity-40"
                :disabled="index === 0"
                @click="back"
              >
                {{ t("common.back") }}
              </button>
              <button
                class="h-8 rounded-md bg-primary px-3 text-xs font-medium text-white"
                @click="next"
              >
                {{ index === stepCount - 1 ? t("tour.done") : t("tour.next") }}
              </button>
            </div>
          </footer>
        </section>
      </div>
    </Transition>
  </Teleport>
</template>
