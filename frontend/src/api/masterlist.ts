import { apiFetch } from "./client";
import type { ImportPreview } from "./types";

export async function previewMasterlist(
  formData: FormData,
): Promise<ImportPreview> {
  return apiFetch<ImportPreview>("/api/masterlist/imports/preview", {
    method: "POST",
    body: formData,
  });
}

export async function confirmMasterlistImport(
  id: number,
): Promise<{
  data: { imported: number; skipped: number };
  mail: { sent: number; failed: { email: string; message: string }[] };
}> {
  return apiFetch(`/api/masterlist/imports/${id}/confirm`, { method: "POST" });
}
