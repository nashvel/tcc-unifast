<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from "vue";
import { IconCheck } from "@tabler/icons-vue";
import { useSuccessOverlay } from "@/composables/useSuccessOverlay";

const { visible, message } = useSuccessOverlay();

/**
 * Pin the overlay shell to the visible viewport.
 * - Avoids right-shift from Transition `transform` on the fixed root (scale lives on the card).
 * - Counters `html { zoom: 1.25 }` (see app.css / AppTour) which skews `fixed inset-0`.
 * - Follows visualViewport so mobile browser chrome / tunnel viewports stay centered.
 * - Uses flex centering on the pinned shell (not absolute left/top of the card) so the
 *   checkmark stays dead-center of the visible viewport on phones.
 */
const rootStyle = ref<Record<string, string>>(fallbackRootStyle());

function cssZoomFactor(): number {
  return typeof window !== "undefined" && window.innerWidth >= 1024 ? 1.25 : 1;
}

function fallbackRootStyle(): Record<string, string> {
  return {
    position: "fixed",
    inset: "0",
    width: "100%",
    height: "100%",
    margin: "0",
    transform: "none",
  };
}

function syncViewport() {
  if (typeof window === "undefined") {
    rootStyle.value = fallbackRootStyle();
    return;
  }

  const z = cssZoomFactor();
  const vv = window.visualViewport;

  if (vv) {
    // visualViewport is in CSS px; divide by html zoom so fixed coords match after zoom paints.
    rootStyle.value = {
      position: "fixed",
      top: `${vv.offsetTop / z}px`,
      left: `${vv.offsetLeft / z}px`,
      width: `${vv.width / z}px`,
      height: `${vv.height / z}px`,
      right: "auto",
      bottom: "auto",
      margin: "0",
      transform: "none",
    };
    return;
  }

  if (z !== 1) {
    rootStyle.value = {
      position: "fixed",
      top: "0",
      left: "0",
      width: `${100 / z}vw`,
      height: `${100 / z}vh`,
      right: "auto",
      bottom: "auto",
      margin: "0",
      transform: "none",
    };
    return;
  }

  rootStyle.value = fallbackRootStyle();
}

function bindViewport() {
  syncViewport();
  window.visualViewport?.addEventListener("resize", syncViewport);
  window.visualViewport?.addEventListener("scroll", syncViewport);
  window.addEventListener("resize", syncViewport);
}

function unbindViewport() {
  window.visualViewport?.removeEventListener("resize", syncViewport);
  window.visualViewport?.removeEventListener("scroll", syncViewport);
  window.removeEventListener("resize", syncViewport);
}

watch(visible, (isVisible) => {
  if (isVisible) {
    bindViewport();
  } else {
    unbindViewport();
  }
});

onBeforeUnmount(unbindViewport);
</script>

<template>
  <Teleport to="body">
    <Transition name="milestone">
      <div
        v-if="visible"
        class="pointer-events-none z-[200] box-border flex items-center justify-center"
        :style="{
          ...rootStyle,
          paddingTop: 'max(1.5rem, env(safe-area-inset-top, 0px))',
          paddingRight: 'max(1.5rem, env(safe-area-inset-right, 0px))',
          paddingBottom: 'max(1.5rem, env(safe-area-inset-bottom, 0px))',
          paddingLeft: 'max(1.5rem, env(safe-area-inset-left, 0px))',
        }"
        role="status"
        aria-live="polite"
      >
        <div
          class="milestone-card pointer-events-none flex flex-col items-center gap-3 rounded-2xl bg-surface/95 px-8 py-7 shadow-lg ring-1 ring-black/8 backdrop-blur-sm dark:ring-white/10"
        >
          <div
            class="grid size-14 place-items-center rounded-full bg-primary text-white shadow-md"
            aria-hidden="true"
          >
            <IconCheck :size="28" stroke-width="2.5" class="milestone-check" />
          </div>
          <p class="max-w-[16rem] text-center text-sm font-semibold tracking-tight text-text">
            {{ message }}
          </p>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
/*
  Animate only the card — never put `transform` on the fixed root.
  A transform on the positioning layer reparents fixed layout and, with
  `html { zoom: 1.25 }`, shifts the card toward the right on phones/tunnels.
*/
.milestone-enter-active,
.milestone-leave-active {
  transition: opacity 0.2s ease;
}
.milestone-enter-active .milestone-card,
.milestone-leave-active .milestone-card {
  transition:
    opacity 0.2s ease,
    transform 0.2s ease;
}
.milestone-enter-from,
.milestone-leave-to {
  opacity: 0;
}
.milestone-enter-from .milestone-card,
.milestone-leave-to .milestone-card {
  opacity: 0;
  transform: scale(0.92);
  transform-origin: center center;
}
.milestone-check {
  animation: milestone-pop 0.35s ease-out;
}
@keyframes milestone-pop {
  0% {
    transform: scale(0.4);
    opacity: 0;
  }
  60% {
    transform: scale(1.12);
    opacity: 1;
  }
  100% {
    transform: scale(1);
  }
}
</style>
