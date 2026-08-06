import type {
  DocSubmission,
  DocSubmissionDetail,
  DocSubmissionPackage,
} from "@/api/types";

export const mockDocuments: DocSubmission[] = [
  {
    id: 1,
    student_name: "Maria Clara Dela Cruz",
    student_id: "2024-001",
    document_type: "School ID",
    slot_key: "school_id",
    status: "approved",
    risk_level: "low",
    identity_review_required: false,
    created_at: "2026-07-10T08:30:00Z",
    grantee_id: 1,
    batch_id: 1,
  },
  {
    id: 2,
    student_name: "Maria Clara Dela Cruz",
    student_id: "2024-001",
    document_type: "Course History",
    slot_key: "course_history",
    status: "pending_review",
    risk_level: "low",
    identity_review_required: false,
    created_at: "2026-07-10T08:32:00Z",
    grantee_id: 1,
    batch_id: 1,
  },
  {
    id: 3,
    student_name: "Maria Clara Dela Cruz",
    student_id: "2024-001",
    document_type: "Grade Slip",
    slot_key: "grade_slip",
    status: "pending_review",
    risk_level: "medium",
    identity_review_required: false,
    created_at: "2026-07-10T08:33:00Z",
    grantee_id: 1,
    batch_id: 1,
  },
  {
    id: 4,
    student_name: "Maria Clara Dela Cruz",
    student_id: "2024-001",
    document_type: "3 Specimen Signatures",
    slot_key: "specimen_signatures",
    status: "pending_review",
    risk_level: "low",
    identity_review_required: false,
    created_at: "2026-07-10T08:34:00Z",
    grantee_id: 1,
    batch_id: 1,
  },
  {
    id: 5,
    student_name: "Juan Carlos Reyes",
    student_id: "2024-002",
    document_type: "School ID",
    slot_key: "school_id",
    status: "pending_review",
    risk_level: "low",
    identity_review_required: false,
    created_at: "2026-07-11T10:15:00Z",
    grantee_id: 2,
    batch_id: 1,
  },
];

const slotLabels: Record<string, string> = {
  school_id: "School ID",
  course_history: "Course History",
  grade_slip: "Grade Slip",
  specimen_signatures: "Specimen",
};

function toPackage(docs: DocSubmission[], batchName: string): DocSubmissionPackage {
  const first = docs[0];
  return {
    grantee_id: first.grantee_id!,
    batch_id: first.batch_id!,
    batch_name: batchName,
    student_name: first.student_name,
    student_id: first.student_id,
    status: docs.every((d) => d.status === "approved")
      ? "approved"
      : docs.some((d) => d.status === "pending_review")
        ? "pending_review"
        : docs[0].status,
    risk_level: docs.some((d) => d.risk_level === "medium") ? "medium" : "low",
    identity_review_required: docs.some((d) => d.identity_review_required),
    submitted_at: docs.map((d) => d.created_at).sort().at(-1) ?? null,
    slots_expected: 4,
    slots_submitted: docs.length,
    slots_reviewed: docs.filter((d) =>
      ["approved", "rejected", "resubmission"].includes(d.status),
    ).length,
    progress: `${docs.length}/4`,
    documents: docs.map((d) => ({
      id: d.id,
      slot_key: d.slot_key,
      document_type: d.document_type,
      tab_label: slotLabels[d.slot_key ?? ""] ?? d.document_type,
      status: d.status,
      risk_level: d.risk_level,
      identity_review_required: d.identity_review_required,
    })),
  };
}

export const mockDocumentPackages: DocSubmissionPackage[] = [
  toPackage(
    mockDocuments.filter((d) => d.grantee_id === 1),
    "AY 2026-1",
  ),
  toPackage(
    mockDocuments.filter((d) => d.grantee_id === 2),
    "AY 2026-1",
  ),
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
  extracted_text:
    "TAGOLOAN COMMUNITY COLLEGE\nStudent ID: 2024-001\nName: MARIA CLARA DELA CRUZ\nProgram: BS Information Technology\nValid Until: 2027-05-31",
  ocr_confidence: 94.2,
  metadata_payload: { source: "ocr", model_version: "2.1" },
  review_notes: "ID verified. Face match passed.",
};
