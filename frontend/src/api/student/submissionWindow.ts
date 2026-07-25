import { apiFetch } from "../client";
import type { SubmissionWindow } from "../types";

export async function getSubmissionWindow(): Promise<{ data: SubmissionWindow }> {
  return apiFetch<{ data: SubmissionWindow }>("/api/student/submission-window");
}
