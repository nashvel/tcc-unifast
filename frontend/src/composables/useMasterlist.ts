import { ref } from "vue";
import { apiFetch } from "@/api";
import type { ImportPreview } from "@/api";

export function useMasterlistImport() {
  const preview = ref<ImportPreview | null>(null);
  const busy = ref(false);
  const confirming = ref(false);
  const error = ref("");
  const mailResult = ref<{ sent: number; failed: { email: string; message: string }[] } | null>(null);

  async function previewImport(formData: FormData) {
    busy.value = true;
    error.value = "";
    try {
      preview.value = await apiFetch<ImportPreview>("/api/masterlist/imports/preview", {
        method: "POST",
        body: formData,
      });
    } catch (exception) {
      error.value = exception instanceof Error ? exception.message : "Preview failed.";
    } finally {
      busy.value = false;
    }
  }

  async function confirmImport(id: number) {
    confirming.value = true;
    error.value = "";
    try {
      const result = await apiFetch<{
        data: { imported: number; skipped: number };
        mail: { sent: number; failed: { email: string; message: string }[] };
      }>(`/api/masterlist/imports/${id}/confirm`, { method: "POST" });
      mailResult.value = result.mail;
      return result;
    } catch (exception) {
      error.value = exception instanceof Error ? exception.message : "Import failed.";
      return null;
    } finally {
      confirming.value = false;
    }
  }

  return { preview, busy, confirming, error, mailResult, previewImport, confirmImport };
}
