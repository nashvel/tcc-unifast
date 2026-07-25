import { apiFetch } from "../client";

export async function verifyFace(
  formData: FormData,
): Promise<{ matched: boolean; score: number; threshold: number }> {
  return apiFetch("/api/student/identity/face-verify", {
    method: "POST",
    body: formData,
  });
}
