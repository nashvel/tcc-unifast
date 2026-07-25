import { apiFetch } from "../client";
import type { StudentSubmission } from "../types";

export async function listStudentSubmissions(): Promise<{ data: StudentSubmission[] }> {
  return apiFetch("/api/document-submissions?student_view=1");
}

export async function submitOcrDocument(formData: FormData): Promise<{
  ocr: {
    document_type: string;
    result: {
      combined_text?: string;
      cleaned_text?: string;
      average_confidence?: number;
    };
  };
}> {
  return apiFetch("/api/student/submissions/ocr", {
    method: "POST",
    body: formData,
  });
}
