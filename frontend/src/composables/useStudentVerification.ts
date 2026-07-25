import { ref } from "vue";
import { apiFetch } from "@/api";

export function useFaceVerification() {
  const faceScanned = ref(false);
  const matching = ref(false);
  const matchScore = ref<number | null>(null);
  const error = ref("");

  async function verify(idBlob: Blob, faceBlob: Blob) {
    matching.value = true;
    error.value = "";
    try {
      const body = new FormData();
      body.append("student_id_document", idBlob, "live-id-capture.jpg");
      body.append("face_capture", faceBlob, "live-face-capture.jpg");

      const payload = await apiFetch<{
        matched: boolean;
        score: number;
        threshold: number;
      }>("/api/student/identity/face-verify", {
        method: "POST",
        body,
      });

      faceScanned.value = Boolean(payload.matched);
      matchScore.value = Number(payload.score ?? 0);
      if (!payload.matched) {
        error.value = `Face match did not reach the ${payload.threshold}% threshold.`;
        return false;
      }
      return true;
    } catch (exception) {
      error.value = exception instanceof Error ? exception.message : "Face verification failed.";
      return false;
    } finally {
      matching.value = false;
    }
  }

  return { faceScanned, matching, matchScore, error, verify };
}
