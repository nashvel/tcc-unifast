import type { DocSubmission, DocSubmissionDetail } from "@/api/types";

export const mockDocuments: DocSubmission[] = [
  { id: 1, student_name: "Maria Clara Dela Cruz", student_id: "2024-001", document_type: "School ID", slot_key: "school_id", status: "approved", risk_level: "low", identity_review_required: false, created_at: "2026-07-10T08:30:00Z" },
  { id: 2, student_name: "Juan Carlos Reyes", student_id: "2024-002", document_type: "Course History", slot_key: "course_history", status: "pending_review", risk_level: "low", identity_review_required: false, created_at: "2026-07-11T10:15:00Z" },
  { id: 3, student_name: "Ana Santos Garcia", student_id: "2024-003", document_type: "Grade Slip", slot_key: "grade_slip", status: "resubmission", risk_level: "medium", identity_review_required: true, created_at: "2026-07-09T14:45:00Z" },
  { id: 4, student_name: "Pedro Miguel Torres", student_id: "2024-004", document_type: "School ID", slot_key: "school_id", status: "pending_review", risk_level: "low", identity_review_required: false, created_at: "2026-07-12T09:00:00Z" },
];

export const mockDocumentDetail: DocSubmissionDetail = {
  ...mockDocuments[0],
  original_name: "student_id_front.jpg",
  secondary_original_name: "student_id_back.jpg",
  file_url: "https://via.placeholder.com/400x300?text=ID+Front",
  secondary_file_url: "https://via.placeholder.com/400x300?text=ID+Back",
  mime_type: "image/jpeg",
  secondary_mime_type: "image/jpeg",
  face_quality_score: 0.92,
  identity_review_required: false,
  identity_review_reason: null,
  identity_check: {
    result: "match",
    distance: 0.35,
    confidence_score: 87.5,
    manual_review_required: false,
    challenge_sequence: ["blink", "turn_left", "turn_right"],
    checked_at: "2026-07-10T08:35:00Z",
  },
  extracted_text: "TAGOLOAN COMMUNITY COLLEGE\nStudent ID: 2024-001\nName: MARIA CLARA DELA CRUZ\nProgram: BS Information Technology\nValid Until: 2027-05-31",
  ocr_confidence: 94.2,
  metadata_payload: { source: "ocr", model_version: "2.1" },
  review_notes: "ID verified. Face match passed.",
};
