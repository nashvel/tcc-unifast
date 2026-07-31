<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from "vue";
import {
  IconCamera,
  IconCheck,
  IconFileText,
  IconId,
  IconLock,
  IconRefresh,
  IconShieldCheck,
  IconSignature,
  IconUpload,
} from "@tabler/icons-vue";
import AppDialog from "@/components/dialogs/AppDialog.vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import CardSkeleton from "@/components/ui/CardSkeleton.vue";
import { getAuthToken } from "@/auth/session";
import { toast } from "@/composables/useToast";
import { getUserMediaSafe } from "@/modules/requirements/cameraAccess";
import { decodeQrFromBlob, decodeQrFromVideo, isTccRegistrarQr } from "@/modules/requirements/idQr";

async function faceApi() {
  return import("@/modules/requirements/faceApi");
}

type VaultDocument = {
  id: number;
  slot_key: string;
  document_type: string;
  original_name: string;
  secondary_original_name: string | null;
  status: string;
  risk_level: string;
  face_quality_score: number | null;
  identity_review_required: boolean;
  identity_review_reason: string | null;
  review_notes: string | null;
  face_descriptor: number[] | null;
};

type IdentityCheck = {
  id: number;
  result: "match" | "no_match";
  distance: number;
  distances?: Record<string, number> | null;
  confidence_score: number | null;
  manual_review_required: boolean;
  checked_at: string;
};

type VaultResponse = {
  window: { open: boolean; message: string };
  grantee: { submission_status: string; submitted_at: string | null } | null;
  slots: Record<string, VaultDocument>;
  identity_check: IdentityCheck | null;
  onboarding_refs: {
    id_reference_face_url: string | null;
    onboarding_selfie_url: string | null;
    completed: boolean;
  } | null;
};

const slots = ref<Record<string, VaultDocument>>({});
const identityCheck = ref<IdentityCheck | null>(null);
const onboardingRefs = ref<VaultResponse["onboarding_refs"]>(null);
const granteeStatus = ref("not_submitted");
const windowOpen = ref(false);
const windowMessage = ref("");
const loading = ref(true);
const busy = ref("");
const error = ref("");
const success = ref("");

const courseHistory = ref<File | null>(null);
const gradeSlip = ref<File | null>(null);
const specimenSignatures = ref<File | null>(null);
const courseHistoryInput = ref<HTMLInputElement | null>(null);
const gradeSlipInput = ref<HTMLInputElement | null>(null);
const specimenSignaturesInput = ref<HTMLInputElement | null>(null);
const specimenBlueInkAck = ref(false);

const precheck = ref<Record<string, boolean>>({
  lighting: false,
  steady: false,
  glare: false,
  internet: false,
  permission: false,
});
const consent = ref(false);
const idDialog = ref(false);
const confirmDialog = ref(false);
const idVideo = ref<HTMLVideoElement | null>(null);
const cameraReady = ref(false);
const qrHint = ref("Hold your School ID vertically inside the portrait frame.");
const lastQr = ref("");
let stream: MediaStream | null = null;
let qrTimer: number | null = null;

const schoolIdUploaded = computed(() => Boolean(slots.value.school_id));
const packageLocked = computed(() =>
  ["docs_submitted", "under_review", "verified"].includes(granteeStatus.value),
);
const inResubmissionMode = computed(
  () =>
    granteeStatus.value === "resubmission_requested" ||
    Object.values(slots.value).some((slot) => slot?.status === "resubmission"),
);
const allDocumentsUploaded = computed(
  () =>
    Boolean(slots.value.school_id) &&
    Boolean(slots.value.course_history) &&
    Boolean(slots.value.grade_slip) &&
    Boolean(slots.value.specimen_signatures),
);
const precheckReady = computed(() => Object.values(precheck.value).every(Boolean) && consent.value);
const canOpenIdScan = computed(
  () =>
    (precheckReady.value && !schoolIdUploaded.value) ||
    (inResubmissionMode.value && canEditSlot("school_id")),
);
/** Precheck/consent stay editable when School ID was never uploaded. */
const schoolIdPrecheckLocked = computed(
  () => inResubmissionMode.value && schoolIdUploaded.value && !canEditSlot("school_id"),
);
const canSubmitPackage = computed(
  () => allDocumentsUploaded.value && !packageLocked.value && !inResubmissionMode.value,
);
const packageReady = computed(() => canSubmitPackage.value);
const SLOT_KEYS = [
  "school_id",
  "course_history",
  "grade_slip",
  "specimen_signatures",
] as const;
/** Returned slots still waiting for replace and/or single-slot resubmit. */
const openResubmissionSlots = computed(() =>
  SLOT_KEYS.filter((key) => {
    const slot = slots.value[key];
    if (!slot) return false;
    if (slot.status === "resubmission") return true;
    return slot.status === "draft" && granteeStatus.value === "resubmission_requested";
  }),
);
/** Vault progress is 4 document slots only (submit is a separate action, not a 5th step). */
const progress = computed(() => {
  if (inResubmissionMode.value) {
    const open = openResubmissionSlots.value;
    if (open.length === 0) return 100;
    const replaced = open.filter((key) => slots.value[key]?.status === "draft").length;
    // Cap below 100 until every returned slot is resubmitted (mode exits).
    return Math.min(90, Math.round((replaced / open.length) * 90));
  }
  const filled = [
    slots.value.school_id,
    slots.value.course_history,
    slots.value.grade_slip,
    slots.value.specimen_signatures,
  ].filter(Boolean).length;
  return Math.round((filled / 4) * 100);
});
const progressStatusLabel = computed(() => {
  if (inResubmissionMode.value) {
    const open = openResubmissionSlots.value.length;
    const awaitingReplace = openResubmissionSlots.value.filter(
      (key) => slots.value[key]?.status === "resubmission",
    ).length;
    if (awaitingReplace > 0) {
      return `${awaitingReplace} returned slot${awaitingReplace === 1 ? "" : "s"} need${awaitingReplace === 1 ? "s" : ""} replacing`;
    }
    return open > 0
      ? `${open} replaced — resubmit when ready`
      : "Resubmission requested";
  }
  if (packageLocked.value) return granteeStatus.value.replaceAll("_", " ");
  if (packageReady.value) return "Ready to submit";
  return granteeStatus.value.replaceAll("_", " ");
});

const precheckItems = [
  ["lighting", "Good stable lighting"],
  ["steady", "Hold ID vertically — steady inside the portrait frame"],
  ["glare", "Remove glare or obstructions"],
  ["internet", "Stable internet connection"],
  ["permission", "Allow camera permission"],
] as const;

onMounted(loadVault);
onBeforeUnmount(() => {
  if (qrTimer) window.clearInterval(qrTimer);
  stopCamera();
});

function payloadMessage(payload: unknown, fallback: string) {
  if (!payload || typeof payload !== "object") return fallback;
  const data = payload as { message?: string; errors?: Record<string, string[]> };
  const validation = data.errors ? Object.values(data.errors).flat().join(" ") : "";
  return validation || data.message || fallback;
}

async function loadVault(options: { quiet?: boolean } = {}) {
  if (!options.quiet) loading.value = true;
  error.value = "";
  try {
    const response = await fetch("/api/student/requirement-vault", {
      headers: {
        Accept: "application/json",
        Authorization: `Bearer ${getAuthToken()}`,
      },
      credentials: "include",
    });
    const payload = (await response.json()) as VaultResponse & { message?: string; errors?: Record<string, string[]> };
    if (!response.ok) throw new Error(payloadMessage(payload, "Unable to load the requirement vault."));
    windowOpen.value = Boolean(payload.window?.open);
    windowMessage.value = payload.window?.message || "";
    granteeStatus.value = payload.grantee?.submission_status || "not_submitted";
    slots.value = payload.slots || {};
    identityCheck.value = payload.identity_check || null;
    onboardingRefs.value = payload.onboarding_refs || null;
    if (
      !options.quiet &&
      (granteeStatus.value === "resubmission_requested" ||
        Object.values(slots.value).some((slot) => slot?.status === "resubmission"))
    ) {
      toast.message("Resubmission requested", {
        description: "Resubmit only returned document(s). Replace each returned file, then Resubmit that slot.",
      });
    }
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "Unable to load the requirement vault.";
  } finally {
    if (!options.quiet) loading.value = false;
  }
}

function slotUploaded(slotKey: "course_history" | "grade_slip" | "specimen_signatures") {
  return Boolean(slots.value[slotKey]);
}

function canEditSlot(
  slotKey: "school_id" | "course_history" | "grade_slip" | "specimen_signatures",
) {
  const slot = slots.value[slotKey];
  if (!slot) return false;
  if (slot.status === "resubmission") return true;
  if (slot.status === "draft" && granteeStatus.value === "resubmission_requested") return true;
  if (packageLocked.value || inResubmissionMode.value) return false;
  return slot.status === "draft";
}

function canResubmitSlot(
  slotKey: "school_id" | "course_history" | "grade_slip" | "specimen_signatures",
) {
  const slot = slots.value[slotKey];
  return (
    Boolean(slot) &&
    slot.status === "draft" &&
    granteeStatus.value === "resubmission_requested"
  );
}

function isSlotActionable(
  slotKey: "school_id" | "course_history" | "grade_slip" | "specimen_signatures",
) {
  return canEditSlot(slotKey) || canResubmitSlot(slotKey);
}

/** Already on file with staff and not returned — muted during resubmission. */
function isSlotLockedForResubmission(
  slotKey: "school_id" | "course_history" | "grade_slip" | "specimen_signatures",
) {
  return (
    inResubmissionMode.value &&
    Boolean(slots.value[slotKey]) &&
    !isSlotActionable(slotKey)
  );
}

/**
 * First-time / missing-slot uploads.
 * Empty slots stay uploadable during resubmission; returned slots use Edit/Replace.
 */
function canUploadNewSlot(
  slotKey: "course_history" | "grade_slip" | "specimen_signatures",
) {
  return schoolIdUploaded.value && !slotUploaded(slotKey);
}

function slotStatusLabel(status: string) {
  if (status === "draft") {
    return granteeStatus.value === "resubmission_requested" ? "Ready to resubmit" : "Draft";
  }
  if (status === "pending_review") return "Pending review";
  if (status === "resubmission") return "Resubmission requested";
  if (status === "approved") return "Approved";
  return status.replaceAll("_", " ");
}

function slotStatusClass(status: string) {
  if (status === "pending_review") {
    return "inline-flex rounded-full bg-info-soft px-2 py-0.5 text-micro font-semibold text-info";
  }
  if (status === "resubmission") {
    return "inline-flex rounded-full bg-warning-soft px-2 py-0.5 text-micro font-semibold text-warning";
  }
  if (status === "approved") {
    return "inline-flex rounded-full bg-success-soft px-2 py-0.5 text-micro font-semibold text-success";
  }
  if (status === "draft" && granteeStatus.value === "resubmission_requested") {
    return "inline-flex rounded-full bg-warning-soft px-2 py-0.5 text-micro font-semibold text-warning";
  }
  return "inline-flex rounded-full bg-surface-muted px-2 py-0.5 text-micro font-medium text-text";
}

function slotCardClass(
  slotKey: "school_id" | "course_history" | "grade_slip" | "specimen_signatures",
) {
  if (inResubmissionMode.value) {
    if (slots.value[slotKey]?.status === "resubmission") {
      return "border-warning bg-warning-soft/25 ring-2 ring-warning/35";
    }
    if (canResubmitSlot(slotKey)) {
      return "border-warning/60 bg-warning-soft/20 ring-1 ring-warning/30";
    }
    if (canEditSlot(slotKey)) {
      return "border-warning/40 ring-1 ring-warning/20";
    }
    // Never submitted — keep a normal uploadable card (not muted).
    if (!slots.value[slotKey]) return "";
    return "opacity-55 bg-surface-muted/40";
  }
  if (slotKey === "school_id") {
    return !precheckReady.value && !schoolIdUploaded.value ? "opacity-60" : "";
  }
  if (!schoolIdUploaded.value) return "opacity-60";
  return "";
}

/** File summary panel: amber when action needed, muted when locked in resubmission, green when saved normally. */
function slotFilePanelClass(
  slotKey: "school_id" | "course_history" | "grade_slip" | "specimen_signatures",
) {
  const slot = slots.value[slotKey];
  if (!slot) return "";
  if (slot.status === "resubmission" || canResubmitSlot(slotKey)) {
    return "rounded-md border border-warning/40 bg-warning-soft p-3 text-xs text-warning";
  }
  if (inResubmissionMode.value && isSlotLockedForResubmission(slotKey)) {
    return "rounded-md border border-border bg-surface-muted/60 p-3 text-xs text-text-muted";
  }
  return "rounded-md border border-success/30 bg-success-soft p-3 text-xs text-success";
}

function slotReplaceButtonClass(
  slotKey: "school_id" | "course_history" | "grade_slip" | "specimen_signatures",
) {
  if (slots.value[slotKey]?.status === "resubmission" || canResubmitSlot(slotKey)) {
    return "inline-flex h-9 w-full items-center justify-center gap-2 rounded-md border border-warning/50 bg-warning-soft px-3 text-xs font-semibold text-warning disabled:opacity-50";
  }
  return "inline-flex h-9 w-full items-center justify-center gap-2 rounded-md border px-3 text-xs font-medium disabled:opacity-50";
}

function slotFileLabel(
  slotKey: "course_history" | "grade_slip" | "specimen_signatures",
  local: File | null,
  emptyLabel: string,
) {
  const server = slots.value[slotKey];
  if (server?.original_name) return server.original_name;
  if (local?.name) return local.name;
  return emptyLabel;
}

function openFilePicker(target: "course" | "grade" | "specimen", replace = false) {
  const slotKey =
    target === "course" ? "course_history" : target === "grade" ? "grade_slip" : "specimen_signatures";
  if (replace) {
    if (!canEditSlot(slotKey)) return;
  } else {
    if (!canUploadNewSlot(slotKey)) return;
  }
  if (slotUploaded(slotKey) && !replace) return;
  if (target === "specimen" && !specimenBlueInkAck.value && !replace) {
    error.value = "Confirm that the specimens were written with a blue ballpen before uploading.";
    return;
  }
  const input =
    target === "course"
      ? courseHistoryInput.value
      : target === "grade"
        ? gradeSlipInput.value
        : specimenSignaturesInput.value;
  input?.click();
}

function clearLocalDraft(slotKey: "course_history" | "grade_slip" | "specimen_signatures") {
  if (slotKey === "course_history") {
    courseHistory.value = null;
    if (courseHistoryInput.value) courseHistoryInput.value.value = "";
  } else if (slotKey === "grade_slip") {
    gradeSlip.value = null;
    if (gradeSlipInput.value) gradeSlipInput.value.value = "";
  } else {
    specimenSignatures.value = null;
    if (specimenSignaturesInput.value) specimenSignaturesInput.value.value = "";
  }
}

/** Hidden file input change: store File and upload immediately (one-click Upload flow). */
function onFilePicked(event: Event, target: "course" | "grade" | "specimen") {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0] ?? null;
  const slotKey =
    target === "course" ? "course_history" : target === "grade" ? "grade_slip" : "specimen_signatures";
  const replacing = slotUploaded(slotKey);
  if (!file || (replacing && !canEditSlot(slotKey))) {
    input.value = "";
    return;
  }
  if (target === "course") courseHistory.value = file;
  if (target === "grade") gradeSlip.value = file;
  if (target === "specimen") specimenSignatures.value = file;
  void uploadDocument(slotKey, file, { replace: replacing });
}

async function openIdDialog() {
  if (!canOpenIdScan.value) return;
  idDialog.value = true;
  lastQr.value = "";
  qrHint.value = "Hold your School ID vertically inside the portrait frame.";
  await nextTick();
  await startCamera("environment");
  qrTimer = window.setInterval(pollQr, 700);
}

function openConfirmDialog() {
  if (!canSubmitPackage.value) return;
  confirmDialog.value = true;
}

async function confirmSubmission() {
  if (!canSubmitPackage.value) return;
  busy.value = "confirm";
  error.value = "";
  success.value = "";
  try {
    const response = await fetch("/api/student/requirement-vault/confirm", {
      method: "POST",
      headers: {
        Accept: "application/json",
        Authorization: `Bearer ${getAuthToken()}`,
      },
      credentials: "include",
    });
    const payload = await response.json();
    if (!response.ok) throw new Error(payloadMessage(payload, "Unable to submit requirements."));
    granteeStatus.value = payload.grantee?.submission_status || "docs_submitted";
    if (payload.identity_check) identityCheck.value = payload.identity_check;
    confirmDialog.value = false;
    success.value = "All four documents submitted to staff Document Validation.";
    toast.success(success.value);
    await loadVault({ quiet: true });
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "Unable to submit requirements.";
    toast.error(error.value);
  } finally {
    busy.value = "";
  }
}

async function startCamera(facing: "user" | "environment" = "environment") {
  stopCamera();
  cameraReady.value = false;
  const target = idVideo.value;
  try {
    stream = await getUserMediaSafe({
      video: { facingMode: facing === "user" ? "user" : { ideal: "environment" }, width: { ideal: 1280 }, height: { ideal: 720 } },
      audio: false,
    });
    if (target) {
      target.srcObject = stream;
      await target.play();
      cameraReady.value = true;
    }
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "Unable to open camera.";
  }
}

function stopCamera() {
  if (qrTimer) {
    window.clearInterval(qrTimer);
    qrTimer = null;
  }
  stream?.getTracks().forEach((track) => track.stop());
  stream = null;
  cameraReady.value = false;
  if (idVideo.value) idVideo.value.srcObject = null;
}

function pollQr() {
  if (!idVideo.value || busy.value) return;
  const payload = decodeQrFromVideo(idVideo.value);
  if (!payload) {
    qrHint.value = "Searching for School ID QR…";
    return;
  }
  lastQr.value = payload;
  qrHint.value = isTccRegistrarQr(payload)
    ? "TCC registrar QR detected — ready to capture."
    : "QR found but domain is not TCC registrar.";
}

async function captureIdScan() {
  if (!idVideo.value) return;
  if (!onboardingRefs.value?.id_reference_face_url || !onboardingRefs.value?.onboarding_selfie_url) {
    error.value = "Onboarding reference photos are missing. Complete identity onboarding first.";
    return;
  }

  busy.value = "id";
  error.value = "";
  success.value = "";
  try {
    const api = await faceApi();
    const frameBlob = await api.captureVideoFrame(idVideo.value, 0.92);
    const qrPayload = lastQr.value || (await decodeQrFromBlob(frameBlob));
    if (!qrPayload || !isTccRegistrarQr(qrPayload)) {
      throw new Error("Valid TCC registrar QR code not found. Retry with the QR visible.");
    }

    const face = await api.cropFaceFromImage(new File([frameBlob], "id_frame.jpg", { type: "image/jpeg" }));
    const refDesc = await api.descriptorFromUrl(onboardingRefs.value.id_reference_face_url);
    const selfieDesc = await api.descriptorFromUrl(onboardingRefs.value.onboarding_selfie_url);
    const vsReference = api.euclideanDistance(face.descriptor, refDesc.descriptor);
    const vsSelfie = api.euclideanDistance(face.descriptor, selfieDesc.descriptor);

    const body = new FormData();
    body.append("id_frame", new File([frameBlob], "id_frame.jpg", { type: "image/jpeg" }));
    body.append("id_face_crop", new File([face.blob], "id_scan_submission.jpg", { type: "image/jpeg" }));
    body.append("qr_payload", qrPayload);
    face.descriptor.forEach((value, index) => body.append(`face_descriptor[${index}]`, String(value)));
    body.append("face_quality_score", String(face.quality));
    body.append("distance_vs_reference", String(vsReference));
    body.append("distance_vs_onboarding_selfie", String(vsSelfie));
    body.append("consent_accepted", "1");
    body.append("precheck_accepted", "1");

    const response = await fetch("/api/student/requirement-vault/id", {
      method: "POST",
      headers: { Authorization: `Bearer ${getAuthToken()}`, Accept: "application/json" },
      body,
    });
    const payload = await response.json();
    if (!response.ok) throw new Error(payloadMessage(payload, "School ID scan failed."));
    slots.value = { ...slots.value, school_id: payload.data };
    idDialog.value = false;
    stopCamera();
    success.value = "School ID scan confirmed. Slots 2–4 are unlocked.";
    toast.success(success.value);
    await loadVault({ quiet: true });
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "School ID scan failed.";
    toast.error(error.value);
  } finally {
    busy.value = "";
  }
}

async function uploadDocument(
  slotKey: "course_history" | "grade_slip" | "specimen_signatures",
  file: File | null = null,
  options: { replace?: boolean } = {},
) {
  const replacing = Boolean(options.replace);
  if ((!replacing && slotUploaded(slotKey)) || busy.value === slotKey) return;
  if (replacing && !canEditSlot(slotKey)) return;
  if (!replacing && !canUploadNewSlot(slotKey)) return;

  if (slotKey === "specimen_signatures" && !specimenBlueInkAck.value && !replacing) {
    error.value = "Confirm that the specimens were written with a blue ballpen before uploading.";
    toast.error(error.value);
    return;
  }

  // One-click: Upload with no file opens the OS picker; onChange uploads immediately.
  if (!file) {
    openFilePicker(
      slotKey === "course_history" ? "course" : slotKey === "grade_slip" ? "grade" : "specimen",
      replacing,
    );
    return;
  }

  busy.value = slotKey;
  error.value = "";
  success.value = "";
  try {
    const body = new FormData();
    body.append("slot_key", slotKey);
    body.append("file", file);
    const response = await fetch("/api/student/requirement-vault/document", {
      method: "POST",
      headers: { Authorization: `Bearer ${getAuthToken()}`, Accept: "application/json" },
      body,
      credentials: "include",
    });
    const payload = await response.json();
    if (!response.ok) throw new Error(payloadMessage(payload, "Upload failed."));
    if (!payload?.data?.slot_key) {
      throw new Error("Upload succeeded but the vault response was incomplete. Refresh and try again.");
    }
    const uploaded = payload.data as VaultDocument;
    // Persist from server payload first, then re-fetch so reload state matches.
    slots.value = { ...slots.value, [slotKey]: uploaded };
    clearLocalDraft(slotKey);
    success.value = replacing
      ? `${uploaded.document_type} replaced. Resubmit this slot when ready.`
      : `${uploaded.document_type} saved as draft.`;
    toast.success(success.value);
    await loadVault({ quiet: true });
    if (!slots.value[slotKey]) {
      // Keep the POST payload visible if GET briefly omits the draft.
      slots.value = { ...slots.value, [slotKey]: uploaded };
    }
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "Upload failed.";
    toast.error(error.value);
  } finally {
    busy.value = "";
  }
}

async function resubmitSlot(
  slotKey: "school_id" | "course_history" | "grade_slip" | "specimen_signatures",
) {
  if (!canResubmitSlot(slotKey) || busy.value === `resubmit-${slotKey}`) return;
  const label = slots.value[slotKey]?.document_type || slotKey.replaceAll("_", " ");
  busy.value = `resubmit-${slotKey}`;
  error.value = "";
  success.value = "";
  try {
    const response = await fetch("/api/student/requirement-vault/resubmit-slot", {
      method: "POST",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        Authorization: `Bearer ${getAuthToken()}`,
      },
      credentials: "include",
      body: JSON.stringify({ slot_key: slotKey }),
    });
    const payload = await response.json();
    if (!response.ok) throw new Error(payloadMessage(payload, `Unable to resubmit ${label}.`));
    if (payload.data) {
      slots.value = { ...slots.value, [slotKey]: payload.data as VaultDocument };
    }
    if (payload.grantee?.submission_status) {
      granteeStatus.value = payload.grantee.submission_status;
    }
    success.value = `${label} resubmitted for staff review.`;
    toast.success(success.value);
    await loadVault({ quiet: true });
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : `Unable to resubmit ${label}.`;
    toast.error(error.value);
  } finally {
    busy.value = "";
  }
}
</script>

<template>
  <div class="space-y-5">
    <PageHeader
      title="Requirement Vault"
      description="Upload all four documents (School ID, Course History, Grade Slip, Specimen), then submit to staff. After a return, resubmit only returned document(s)."
    />

    <CardSkeleton v-if="loading" :lines="4" class-name="rounded-2xl p-6" />
    <section v-else-if="!windowOpen" class="rounded-2xl border bg-surface p-6 shadow-sm">
      <span class="inline-flex items-center gap-2 rounded-full bg-warning-soft px-3 py-1 text-xs font-semibold text-warning">
        <IconLock :size="14" /> Locked vault
      </span>
      <h2 class="mt-4 text-2xl font-semibold tracking-tight">Submission window is closed</h2>
      <p class="mt-2 max-w-2xl text-sm text-text-muted">{{ windowMessage }}</p>
    </section>

    <template v-else>
      <section class="rounded-2xl border bg-surface p-4 shadow-sm sm:p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p class="text-xs font-medium uppercase text-text-muted">
              {{ inResubmissionMode ? "Resubmission progress" : "Submission progress" }}
            </p>
            <p class="mt-1 text-3xl font-semibold">{{ progress }}%</p>
            <p class="text-xs text-text-muted">{{ progressStatusLabel }}</p>
          </div>
          <span
            v-if="inResubmissionMode"
            class="inline-flex h-9 max-w-[16rem] items-center gap-2 rounded-md bg-warning-soft px-3 text-xs font-medium text-warning"
          >
            <IconRefresh :size="15" class="shrink-0" />
            Action needed — resubmit only returned document(s)
          </span>
          <span
            v-else-if="packageLocked"
            class="inline-flex h-9 items-center gap-2 rounded-md bg-success-soft px-3 text-xs font-medium text-success"
          >
            <IconShieldCheck :size="15" /> Submitted to staff
          </span>
        </div>
        <div class="mt-4 h-2 overflow-hidden rounded-full bg-surface-muted">
          <div
            class="h-full transition-all"
            :class="inResubmissionMode ? 'bg-warning' : 'bg-primary'"
            :style="{ width: `${progress}%` }"
          />
        </div>
      </section>

      <p v-if="error" class="rounded-md border border-danger/30 bg-danger-soft p-3 text-xs text-danger">{{ error }}</p>
      <p v-if="success" class="rounded-md border border-success/30 bg-success-soft p-3 text-xs text-success">{{ success }}</p>

      <article
        class="rounded-lg border bg-surface p-4"
        :class="{
          'opacity-55 bg-surface-muted/40': schoolIdPrecheckLocked,
        }"
      >
        <h2 class="flex items-center gap-2 text-sm font-semibold"><IconCamera :size="17" /> Pre-check &amp; consent (required before Slot 1)</h2>
        <p v-if="schoolIdPrecheckLocked" class="mt-2 text-xs text-text-muted">
          Pre-check is not required to replace a returned document.
        </p>
        <p v-else-if="inResubmissionMode && !schoolIdUploaded" class="mt-2 text-xs text-text-muted">
          Complete pre-check to upload a missing School ID during this resubmission.
        </p>
        <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
          <label v-for="[key, label] in precheckItems" :key="key" class="flex items-center gap-2 rounded-md border p-3 text-xs">
            <input
              v-model="precheck[key]"
              type="checkbox"
              :disabled="schoolIdPrecheckLocked"
            />
            {{ label }}
          </label>
        </div>
        <label class="mt-3 flex items-start gap-2 rounded-md border p-3 text-xs">
          <input
            v-model="consent"
            type="checkbox"
            :disabled="schoolIdPrecheckLocked"
          />
          <span>I accept the Data Privacy Act consent for identity verification processing.</span>
        </label>
      </article>

      <section class="grid items-start gap-4 md:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-lg border bg-surface p-4" :class="slotCardClass('school_id')">
          <div class="flex items-start gap-3">
            <span class="grid h-10 w-10 place-items-center rounded-lg bg-primary-soft text-primary"><IconId :size="20" /></span>
            <div>
              <h2 class="text-sm font-semibold">Slot 1: School ID Scan</h2>
              <p class="text-xs text-text-muted">Live camera + QR / OCR / face match</p>
            </div>
          </div>
          <div v-if="slots.school_id" class="mt-4 space-y-2" :class="slotFilePanelClass('school_id')">
            <p class="font-semibold">
              <IconCheck v-if="!isSlotActionable('school_id')" :size="14" class="inline" />
              <IconRefresh v-else :size="14" class="inline" />
              {{ isSlotActionable("school_id") ? "Action needed" : "On file with staff" }}
            </p>
            <p>
              <span :class="slotStatusClass(slots.school_id.status)">{{
                slotStatusLabel(slots.school_id.status)
              }}</span>
            </p>
            <p class="text-text-muted">Quality: {{ slots.school_id.face_quality_score?.toFixed(2) || "n/a" }}</p>
            <p
              v-if="slots.school_id.review_notes"
              class="rounded-md border border-warning/30 bg-warning-soft p-2 text-warning"
            >
              Staff notes: {{ slots.school_id.review_notes }}
            </p>
          </div>
          <p
            v-if="isSlotLockedForResubmission('school_id')"
            class="mt-3 text-xs text-text-muted"
          >
            Not required for this resubmission.
          </p>
          <button
            v-if="!schoolIdUploaded || canEditSlot('school_id')"
            class="mt-4 inline-flex h-9 items-center gap-2 rounded-md bg-primary px-3 text-xs font-medium text-white disabled:opacity-50"
            :disabled="!canOpenIdScan || busy === 'id'"
            @click="openIdDialog"
          >
            <IconCamera :size="14" />
            {{ canEditSlot("school_id") ? "Re-scan School ID" : "Start live ID scan" }}
          </button>
          <button
            v-if="canResubmitSlot('school_id')"
            type="button"
            class="mt-2 inline-flex h-9 w-full items-center justify-center gap-2 rounded-md bg-primary px-3 text-xs font-semibold text-white disabled:opacity-50"
            :disabled="busy === 'resubmit-school_id'"
            @click="resubmitSlot('school_id')"
          >
            <IconUpload :size="14" />
            {{ busy === "resubmit-school_id" ? "Resubmitting…" : "Resubmit School ID" }}
          </button>
        </article>

        <article class="rounded-lg border bg-surface p-4" :class="slotCardClass('course_history')">
          <div class="flex items-start gap-3">
            <span class="grid h-10 w-10 place-items-center rounded-lg bg-info-soft text-info"><IconFileText :size="20" /></span>
            <div>
              <h2 class="text-sm font-semibold">Slot 2: Course History</h2>
              <p class="text-xs text-text-muted">PDF only</p>
            </div>
          </div>
          <div v-if="slots.course_history" class="mt-4 space-y-3">
            <div :class="slotFilePanelClass('course_history')">
              <IconRefresh v-if="isSlotActionable('course_history')" :size="14" class="inline" />
              <IconCheck v-else :size="14" class="inline" />
              {{ slots.course_history.original_name }}
              <span class="mt-1 block">
                <span :class="slotStatusClass(slots.course_history.status)">{{
                  slotStatusLabel(slots.course_history.status)
                }}</span>
              </span>
              <p
                v-if="slots.course_history.review_notes"
                class="mt-2 rounded-md border border-warning/30 bg-warning-soft p-2 text-warning"
              >
                Staff notes: {{ slots.course_history.review_notes }}
              </p>
            </div>
            <p
              v-if="isSlotLockedForResubmission('course_history')"
              class="text-xs text-text-muted"
            >
              Not required for this resubmission.
            </p>
            <input
              ref="courseHistoryInput"
              :disabled="!canEditSlot('course_history') || busy === 'course_history'"
              class="hidden"
              type="file"
              accept=".pdf,application/pdf"
              @change="onFilePicked($event, 'course')"
            />
            <button
              v-if="canEditSlot('course_history')"
              type="button"
              :class="slotReplaceButtonClass('course_history')"
              :disabled="busy === 'course_history'"
              @click="openFilePicker('course', true)"
            >
              <IconRefresh :size="14" />
              {{ busy === "course_history" ? "Replacing…" : "Edit / Replace" }}
            </button>
            <button
              v-if="canResubmitSlot('course_history')"
              type="button"
              class="inline-flex h-9 w-full items-center justify-center gap-2 rounded-md bg-primary px-3 text-xs font-semibold text-white disabled:opacity-50"
              :disabled="busy === 'resubmit-course_history'"
              @click="resubmitSlot('course_history')"
            >
              <IconUpload :size="14" />
              {{ busy === "resubmit-course_history" ? "Resubmitting…" : "Resubmit Course History" }}
            </button>
          </div>
          <div v-else class="mt-4 space-y-3">
            <input
              ref="courseHistoryInput"
              :disabled="!canUploadNewSlot('course_history') || busy === 'course_history'"
              class="hidden"
              type="file"
              accept=".pdf,application/pdf"
              @change="onFilePicked($event, 'course')"
            />
            <p
              class="inline-flex h-9 w-full items-center gap-2 rounded-md border border-border bg-surface-muted/40 px-3 text-xs text-text-muted"
              aria-live="polite"
            >
              <IconFileText :size="14" class="shrink-0" />
              <span class="truncate">{{ slotFileLabel("course_history", courseHistory, "No PDF selected") }}</span>
            </p>
            <button
              type="button"
              class="inline-flex h-9 w-full items-center justify-center gap-2 rounded-md bg-primary px-3 text-xs font-medium text-white disabled:opacity-50"
              :disabled="!canUploadNewSlot('course_history') || busy === 'course_history'"
              @click="uploadDocument('course_history')"
            >
              <IconUpload :size="14" />
              {{ busy === "course_history" ? "Uploading…" : "Upload Course History" }}
            </button>
          </div>
        </article>

        <article class="rounded-lg border bg-surface p-4" :class="slotCardClass('grade_slip')">
          <div class="flex items-start gap-3">
            <span class="grid h-10 w-10 place-items-center rounded-lg bg-info-soft text-info"><IconFileText :size="20" /></span>
            <div>
              <h2 class="text-sm font-semibold">Slot 3: Grade Slip</h2>
              <p class="text-xs text-text-muted">PDF only</p>
            </div>
          </div>
          <div v-if="slots.grade_slip" class="mt-4 space-y-3">
            <div :class="slotFilePanelClass('grade_slip')">
              <IconRefresh v-if="isSlotActionable('grade_slip')" :size="14" class="inline" />
              <IconCheck v-else :size="14" class="inline" />
              {{ slots.grade_slip.original_name }}
              <span class="mt-1 block">
                <span :class="slotStatusClass(slots.grade_slip.status)">{{
                  slotStatusLabel(slots.grade_slip.status)
                }}</span>
              </span>
              <p
                v-if="slots.grade_slip.review_notes"
                class="mt-2 rounded-md border border-warning/30 bg-warning-soft p-2 text-warning"
              >
                Staff notes: {{ slots.grade_slip.review_notes }}
              </p>
            </div>
            <p
              v-if="isSlotLockedForResubmission('grade_slip')"
              class="text-xs text-text-muted"
            >
              Not required for this resubmission.
            </p>
            <input
              ref="gradeSlipInput"
              :disabled="!canEditSlot('grade_slip') || busy === 'grade_slip'"
              class="hidden"
              type="file"
              accept=".pdf,application/pdf"
              @change="onFilePicked($event, 'grade')"
            />
            <button
              v-if="canEditSlot('grade_slip')"
              type="button"
              :class="slotReplaceButtonClass('grade_slip')"
              :disabled="busy === 'grade_slip'"
              @click="openFilePicker('grade', true)"
            >
              <IconRefresh :size="14" />
              {{ busy === "grade_slip" ? "Replacing…" : "Edit / Replace" }}
            </button>
            <button
              v-if="canResubmitSlot('grade_slip')"
              type="button"
              class="inline-flex h-9 w-full items-center justify-center gap-2 rounded-md bg-primary px-3 text-xs font-semibold text-white shadow-sm disabled:opacity-50"
              :disabled="busy === 'resubmit-grade_slip'"
              @click="resubmitSlot('grade_slip')"
            >
              <IconUpload :size="14" />
              {{ busy === "resubmit-grade_slip" ? "Resubmitting…" : "Resubmit Grade Slip" }}
            </button>
          </div>
          <div v-else class="mt-4 space-y-3">
            <input
              ref="gradeSlipInput"
              :disabled="!canUploadNewSlot('grade_slip') || busy === 'grade_slip'"
              class="hidden"
              type="file"
              accept=".pdf,application/pdf"
              @change="onFilePicked($event, 'grade')"
            />
            <p
              class="inline-flex h-9 w-full items-center gap-2 rounded-md border border-border bg-surface-muted/40 px-3 text-xs text-text-muted"
              aria-live="polite"
            >
              <IconFileText :size="14" class="shrink-0" />
              <span class="truncate">{{ slotFileLabel("grade_slip", gradeSlip, "No PDF selected") }}</span>
            </p>
            <button
              type="button"
              class="inline-flex h-9 w-full items-center justify-center gap-2 rounded-md bg-primary px-3 text-xs font-medium text-white disabled:opacity-50"
              :disabled="!canUploadNewSlot('grade_slip') || busy === 'grade_slip'"
              @click="uploadDocument('grade_slip')"
            >
              <IconUpload :size="14" />
              {{ busy === "grade_slip" ? "Uploading…" : "Upload Grade Slip" }}
            </button>
          </div>
        </article>

        <article class="rounded-lg border bg-surface p-4" :class="slotCardClass('specimen_signatures')">
          <div class="flex items-start gap-3">
            <span class="grid h-10 w-10 place-items-center rounded-lg bg-info-soft text-info"><IconSignature :size="20" /></span>
            <div>
              <h2 class="text-sm font-semibold">Slot 4: 3 Specimen Signatures</h2>
              <p class="text-xs text-text-muted">Image only (JPG, PNG, WEBP)</p>
            </div>
          </div>
          <p
            v-if="!slots.specimen_signatures || !inResubmissionMode || isSlotActionable('specimen_signatures')"
            class="mt-3 text-xs text-text-muted"
          >
            Write three specimen signatures on one sheet using a <span class="font-semibold text-text">blue ballpen</span>.
          </p>
          <div v-if="slots.specimen_signatures" class="mt-4 space-y-3">
            <div :class="slotFilePanelClass('specimen_signatures')">
              <IconRefresh v-if="isSlotActionable('specimen_signatures')" :size="14" class="inline" />
              <IconCheck v-else :size="14" class="inline" />
              {{ slots.specimen_signatures.original_name }}
              <span class="mt-1 block">
                <span :class="slotStatusClass(slots.specimen_signatures.status)">{{
                  slotStatusLabel(slots.specimen_signatures.status)
                }}</span>
              </span>
              <p
                v-if="slots.specimen_signatures.review_notes"
                class="mt-2 rounded-md border border-warning/30 bg-warning-soft p-2 text-warning"
              >
                Staff notes: {{ slots.specimen_signatures.review_notes }}
              </p>
            </div>
            <p
              v-if="isSlotLockedForResubmission('specimen_signatures')"
              class="text-xs text-text-muted"
            >
              Not required for this resubmission.
            </p>
            <input
              ref="specimenSignaturesInput"
              :disabled="!canEditSlot('specimen_signatures') || busy === 'specimen_signatures'"
              class="hidden"
              type="file"
              accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
              @change="onFilePicked($event, 'specimen')"
            />
            <button
              v-if="canEditSlot('specimen_signatures')"
              type="button"
              :class="slotReplaceButtonClass('specimen_signatures')"
              :disabled="busy === 'specimen_signatures'"
              @click="openFilePicker('specimen', true)"
            >
              <IconRefresh :size="14" />
              {{ busy === "specimen_signatures" ? "Replacing…" : "Edit / Replace" }}
            </button>
            <button
              v-if="canResubmitSlot('specimen_signatures')"
              type="button"
              class="inline-flex h-9 w-full items-center justify-center gap-2 rounded-md bg-primary px-3 text-xs font-semibold text-white disabled:opacity-50"
              :disabled="busy === 'resubmit-specimen_signatures'"
              @click="resubmitSlot('specimen_signatures')"
            >
              <IconUpload :size="14" />
              {{
                busy === "resubmit-specimen_signatures"
                  ? "Resubmitting…"
                  : "Resubmit Specimen Signatures"
              }}
            </button>
          </div>
          <div v-else class="mt-4 space-y-3">
            <label class="flex items-start gap-2 rounded-md border p-3 text-xs">
              <input v-model="specimenBlueInkAck" type="checkbox" :disabled="!schoolIdUploaded" />
              <span>I confirm the three specimens were written with a blue ballpen.</span>
            </label>
            <input
              ref="specimenSignaturesInput"
              :disabled="!canUploadNewSlot('specimen_signatures') || busy === 'specimen_signatures'"
              class="hidden"
              type="file"
              accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
              @change="onFilePicked($event, 'specimen')"
            />
            <p
              class="inline-flex h-9 w-full items-center gap-2 rounded-md border border-border bg-surface-muted/40 px-3 text-xs text-text-muted"
              aria-live="polite"
            >
              <IconSignature :size="14" class="shrink-0" />
              <span class="truncate">{{ slotFileLabel("specimen_signatures", specimenSignatures, "No image selected") }}</span>
            </p>
            <button
              type="button"
              class="inline-flex h-9 w-full items-center justify-center gap-2 rounded-md bg-primary px-3 text-xs font-medium text-white disabled:opacity-50"
              :disabled="!canUploadNewSlot('specimen_signatures') || !specimenBlueInkAck || busy === 'specimen_signatures'"
              @click="uploadDocument('specimen_signatures')"
            >
              <IconUpload :size="14" />
              {{ busy === "specimen_signatures" ? "Uploading…" : "Upload Specimen Signatures" }}
            </button>
          </div>
        </article>
      </section>

      <aside class="rounded-lg border bg-surface p-4 sm:p-5">
        <div
          v-if="inResubmissionMode"
          class="flex flex-wrap items-center justify-between gap-3"
        >
          <div>
            <h2 class="flex items-center gap-2 text-sm font-semibold">
              <IconRefresh :size="17" /> Resubmit only returned document(s)
            </h2>
            <p class="mt-2 text-xs text-text-muted">
              Replace each returned (amber) file, then Resubmit that slot. Do not re-submit approved or
              pending-review documents. Legacy missing slots can still be uploaded and go to staff.
            </p>
            <p class="mt-1 text-xs capitalize text-text-muted">{{ granteeStatus.replaceAll("_", " ") }}</p>
          </div>
        </div>
        <div
          v-else-if="packageLocked"
          class="flex flex-wrap items-center justify-between gap-3"
        >
          <div>
            <h2 class="flex items-center gap-2 text-sm font-semibold">
              <IconShieldCheck :size="17" /> Submission status
            </h2>
            <p class="mt-2 text-xs text-success">Requirements submitted to staff Document Validation.</p>
            <p class="mt-1 text-xs capitalize text-text-muted">{{ granteeStatus.replaceAll("_", " ") }}</p>
          </div>
          <span class="inline-flex h-10 items-center gap-2 rounded-md bg-success-soft px-4 text-xs font-medium text-success">
            <IconCheck :size="15" /> Locked for review
          </span>
        </div>
        <div v-else class="flex flex-wrap items-center justify-between gap-3">
          <div class="min-w-0 flex-1">
            <h2 class="flex items-center gap-2 text-sm font-semibold">
              <IconUpload :size="17" /> Submit all four documents to staff
            </h2>
            <p class="mt-1 max-w-xl text-xs text-text-muted">
              {{
                packageReady
                  ? "All four slots are filled. Confirm to send the full package to staff Document Validation."
                  : "Upload School ID, Course History, Grade Slip, and specimen signatures — incomplete packages are not sent to staff."
              }}
            </p>
          </div>
          <button
            type="button"
            class="inline-flex h-10 shrink-0 items-center gap-2 rounded-md bg-primary px-4 text-xs font-medium text-white disabled:opacity-50"
            :disabled="!canSubmitPackage || busy === 'confirm'"
            @click="openConfirmDialog"
          >
            <IconUpload :size="15" />
            {{ busy === "confirm" ? "Submitting…" : "Submit all four documents" }}
          </button>
        </div>
      </aside>
    </template>

    <AppDialog
      v-model="idDialog"
      title="Live School ID scan"
      description="Hold your physical School ID vertically in the portrait frame. QR, OCR, and face matches run on capture."
      size="xl"
      @update:model-value="(open) => !open && stopCamera()"
    >
      <div class="space-y-4">
        <div class="relative mx-auto max-w-sm overflow-hidden rounded-xl border bg-black sm:max-w-md">
          <video ref="idVideo" class="aspect-[3/4] h-auto max-h-[min(58vh,34rem)] min-h-[20rem] w-full object-cover" autoplay playsinline muted />
          <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
            <div
              class="aspect-[2.125/3.375] h-[78%] max-w-[72%] rounded-xl border-[3px] border-white/80"
              style="box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.4)"
            />
          </div>
          <p class="absolute bottom-3 left-3 right-3 rounded-md bg-black/55 px-3 py-2 text-center text-xs text-white">
            {{ qrHint || "Hold your School ID vertically inside the portrait frame." }}
          </p>
        </div>
      </div>
      <template #footer="{ close }">
        <button class="rounded-md border px-4 py-2 text-xs" @click="close">Cancel</button>
        <button
          class="rounded-md bg-primary px-4 py-2 text-xs text-white disabled:opacity-50"
          :disabled="!cameraReady || busy === 'id'"
          @click="captureIdScan"
        >
          {{ busy === "id" ? "Verifying…" : "Capture & verify" }}
        </button>
      </template>
    </AppDialog>

    <AppDialog
      v-model="confirmDialog"
      title="Submit all four documents to staff?"
      description="This locks your drafts and sends the complete package to staff Document Validation. OCR and PDF metadata extraction run after submit."
      size="md"
    >
      <ul class="space-y-2 text-xs text-text-muted">
        <li>Staff only see complete packages after you confirm — incomplete drafts never appear in Document Validation.</li>
        <li>After submit, Edit / Replace is locked unless staff returns a document. Then resubmit only returned document(s).</li>
      </ul>
      <template #footer="{ close }">
        <button class="rounded-md border px-4 py-2 text-xs" @click="close">Cancel</button>
        <button
          class="rounded-md bg-primary px-4 py-2 text-xs text-white disabled:opacity-50"
          :disabled="busy === 'confirm'"
          @click="confirmSubmission"
        >
          {{ busy === "confirm" ? "Submitting…" : "Confirm — submit all four" }}
        </button>
      </template>
    </AppDialog>
  </div>
</template>
