import { computed, ref } from "vue";
import { apiFetch } from "@/api";
import type { VaultDocument, IdentityCheck, VaultResponse } from "@/api";
import type { Challenge } from "@/modules/requirements/faceApi";

export function useRequirementVault() {
  const slots = ref<Record<string, VaultDocument>>({});
  const identityCheck = ref<IdentityCheck | null>(null);
  const granteeStatus = ref("not_submitted");
  const windowOpen = ref(false);
  const windowMessage = ref("");
  const loading = ref(true);
  const busy = ref("");
  const error = ref("");
  const success = ref("");

  const schoolIdUploaded = computed(() => Boolean(slots.value.school_id));
  const allDocumentsUploaded = computed(
    () => Boolean(slots.value.school_id) && Boolean(slots.value.course_history) && Boolean(slots.value.grade_slip),
  );
  const canConfirm = computed(() => allDocumentsUploaded.value && identityCheck.value);
  const progress = computed(() => {
    const complete = [slots.value.school_id, slots.value.course_history, slots.value.grade_slip, identityCheck.value].filter(Boolean).length;
    return Math.round((complete / 4) * 100);
  });

  async function loadVault() {
    loading.value = true;
    error.value = "";
    try {
      const payload = await apiFetch<VaultResponse>("/api/student/requirement-vault", {
        headers: { Accept: "application/json" },
      });
      windowOpen.value = Boolean(payload.window?.open);
      windowMessage.value = payload.window?.message || "";
      granteeStatus.value = payload.grantee?.submission_status || "not_submitted";
      slots.value = payload.slots || {};
      identityCheck.value = payload.identity_check || null;
    } catch (exception) {
      error.value = exception instanceof Error ? exception.message : "Unable to load the requirement vault.";
    } finally {
      loading.value = false;
    }
  }

  async function uploadId(front: File, back: File, faceDescriptor: number[], faceQuality: number) {
    busy.value = "id";
    error.value = "";
    success.value = "";
    try {
      const body = new FormData();
      body.append("id_front", front);
      body.append("id_back", back);
      faceDescriptor.forEach((value, index) => body.append(`face_descriptor[${index}]`, String(value)));
      body.append("face_quality_score", String(faceQuality));

      const payload = await apiFetch<{ data: VaultDocument }>("/api/student/requirement-vault/id", {
        method: "POST",
        body,
      });
      slots.value.school_id = payload.data;
      success.value = payload.data.identity_review_required
        ? "School ID uploaded. Face quality is low, so staff will review it manually."
        : "School ID uploaded. Course History and Grade Slip are now unlocked.";
      return success.value;
    } catch (exception) {
      error.value = exception instanceof Error ? exception.message : "School ID upload failed.";
      throw exception;
    } finally {
      busy.value = "";
    }
  }

  async function uploadDocument(slotKey: "course_history" | "grade_slip", file: File) {
    busy.value = slotKey;
    error.value = "";
    success.value = "";
    try {
      const body = new FormData();
      body.append("slot_key", slotKey);
      body.append("file", file);
      const payload = await apiFetch<{ data: VaultDocument }>("/api/student/requirement-vault/document", {
        method: "POST",
        body,
      });
      slots.value[slotKey] = payload.data;
      success.value = `${payload.data.document_type} uploaded.`;
      return success.value;
    } catch (exception) {
      error.value = exception instanceof Error ? exception.message : "Upload failed.";
      throw exception;
    } finally {
      busy.value = "";
    }
  }

  async function submitIdentityCheck(data: {
    challenge_sequence: Challenge[];
    result: string;
    distance: number;
    confidence_score: number;
    consent_accepted: boolean;
  }) {
    const payload = await apiFetch<{ data: IdentityCheck }>("/api/student/requirement-vault/identity-check", {
      method: "POST",
      body: JSON.stringify(data),
    });
    identityCheck.value = payload.data;
    return payload.data;
  }

  async function confirmSubmission() {
    busy.value = "confirm";
    error.value = "";
    success.value = "";
    try {
      const payload = await apiFetch<{ grantee: { submission_status: string } }>("/api/student/requirement-vault/confirm", {
        method: "POST",
      });
      granteeStatus.value = payload.grantee.submission_status;
      success.value = "Requirements confirmed. Status updated to Docs Submitted.";
      return true;
    } catch (exception) {
      error.value = exception instanceof Error ? exception.message : "Unable to confirm submission.";
      return false;
    } finally {
      busy.value = "";
    }
  }

  return {
    slots,
    identityCheck,
    granteeStatus,
    windowOpen,
    windowMessage,
    loading,
    busy,
    error,
    success,
    schoolIdUploaded,
    allDocumentsUploaded,
    canConfirm,
    progress,
    loadVault,
    uploadId,
    uploadDocument,
    submitIdentityCheck,
    confirmSubmission,
  };
}
