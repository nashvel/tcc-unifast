import { ref } from "vue";

export type SubmissionWindow = {
  open: boolean;
  status: "active" | "expired" | "closed" | "draft" | "unassigned";
  message: string;
  batch: null | {
    id: number;
    name: string;
    academic_year: string;
    semester: string;
    submission_deadline: string | null;
    window_status: string;
  };
};

export function useSubmissionWindow() {
  const windowState = ref<SubmissionWindow | null>(null);
  const loadingWindow = ref(true);
  const windowError = ref("");

  async function loadWindow() {
    loadingWindow.value = true;
    windowError.value = "";
    try {
      const response = await fetch("/api/student/submission-window", {
        headers: { Accept: "application/json" },
      });
      const payload = await response.json();
      if (!response.ok) throw new Error(payload.message || "Unable to load submission window.");
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
