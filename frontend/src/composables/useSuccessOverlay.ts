import { ref } from "vue";

/** Brief centered check + message for onboarding milestones (not staff toasts). */
const visible = ref(false);
const message = ref("");

let hideTimer: ReturnType<typeof setTimeout> | null = null;
let generation = 0;

export const SUCCESS_OVERLAY_MS = 1000;

export function useSuccessOverlay() {
  function show(text: string, durationMs = SUCCESS_OVERLAY_MS): Promise<void> {
    generation += 1;
    const gen = generation;
    if (hideTimer) {
      clearTimeout(hideTimer);
      hideTimer = null;
    }
    message.value = text;
    visible.value = true;

    return new Promise((resolve) => {
      hideTimer = window.setTimeout(() => {
        if (gen === generation) {
          visible.value = false;
        }
        hideTimer = null;
        resolve();
      }, durationMs);
    });
  }

  function hide() {
    generation += 1;
    if (hideTimer) {
      clearTimeout(hideTimer);
      hideTimer = null;
    }
    visible.value = false;
  }

  return {
    visible,
    message,
    show,
    hide,
  };
}
