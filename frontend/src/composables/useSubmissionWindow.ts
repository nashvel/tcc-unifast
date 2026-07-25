import { ref } from "vue";
import { apiFetch } from "@/api";
import type { SubmissionWindow } from "@/api";

export function useSubmissionWindow() {
  const windowState = ref<SubmissionWindow | null>(null);
  const loadingWindow = ref(true);
  const windowError = ref("");

  async function loadWindow() {
    loadingWindow.value = true;
    windowError.value = "";
    try {
      const payload = await apiFetch<{ data: SubmissionWindow }>("/api/student/submission-window", {
        headers: { Accept: "application/json" },
      });
      windowState.value = payload.data;
    } catch (exception) {
      windowError.value =
        exception instanceof Error ? exception.message : "Unable to load submission window.";
    } finally {
      loadingWindow.value = false;
    }
  }

  return { windowState, loadingWindow, windowError, loadWindow };
}
