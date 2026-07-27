<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from "vue";
import {
  IconAlertTriangle,
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
import type { Challenge } from "@/modules/requirements/faceApi";
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
const identityDialog = ref(false);
const idVideo = ref<HTMLVideoElement | null>(null);
const liveVideo = ref<HTMLVideoElement | null>(null);
const cameraReady = ref(false);
const qrHint = ref("Align your School ID inside the card frame.");
const lastQr = ref("");
const challengeSequence = ref<Challenge[]>([]);
const challengeIndex = ref(0);
const challengeMessage = ref("");
let stream: MediaStream | null = null;
let qrTimer: number | null = null;

const schoolIdUploaded = computed(() => Boolean(slots.value.school_id));
const allDocumentsUploaded = computed(
  () =>
    Boolean(slots.value.school_id) &&
    Boolean(slots.value.course_history) &&
    Boolean(slots.value.grade_slip) &&
    Boolean(slots.value.specimen_signatures),
);
const precheckReady = computed(() => Object.values(precheck.value).every(Boolean) && consent.value);
const canOpenIdScan = computed(() => precheckReady.value && !schoolIdUploaded.value);
const canOpenIdentity = computed(() => allDocumentsUploaded.value && precheckReady.value);
const canConfirm = computed(() => allDocumentsUploaded.value && identityCheck.value);
const progress = computed(() => {
  const complete = [
    slots.value.school_id,
    slots.value.course_history,
    slots.value.grade_slip,
    slots.value.specimen_signatures,
    identityCheck.value,
  ].filter(Boolean).length;
  return Math.round((complete / 5) * 100);
});

const challengeLabels: Record<Challenge, string> = {
  blink: "Blink",
  turn_left: "Turn left",
  turn_right: "Turn right",
};

const precheckItems = [
  ["lighting", "Good stable lighting"],
  ["steady", "Hold ID steady inside the guide frame"],
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

async function loadVault() {
  loading.value = true;
  error.value = "";
  try {
    const response = await fetch("/api/student/requirement-vault", { headers: { Accept: "application/json" } });
    const payload = (await response.json()) as VaultResponse & { message?: string; errors?: Record<string, string[]> };
    if (!response.ok) throw new Error(payloadMessage(payload, "Unable to load the requirement vault."));
    windowOpen.value = Boolean(payload.window?.open);
    windowMessage.value = payload.window?.message || "";
    granteeStatus.value = payload.grantee?.submission_status || "not_submitted";
    slots.value = payload.slots || {};
    identityCheck.value = payload.identity_check || null;
    onboardingRefs.value = payload.onboarding_refs || null;
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "Unable to load the requirement vault.";
  } finally {
    loading.value = false;
  }
}

function choose(event: Event, target: "course" | "grade" | "specimen") {
  const file = (event.target as HTMLInputElement).files?.[0] ?? null;
  if (target === "course") courseHistory.value = file;
  if (target === "grade") gradeSlip.value = file;
  if (target === "specimen") specimenSignatures.value = file;
  error.value = "";
  success.value = "";
}

async function openIdDialog() {
  if (!canOpenIdScan.value) return;
  idDialog.value = true;
  lastQr.value = "";
  qrHint.value = "Align your School ID inside the card frame.";
  await nextTick();
  await startCamera("environment");
  qrTimer = window.setInterval(pollQr, 700);
}

async function openIdentityDialog() {
  if (!canOpenIdentity.value) return;
  identityDialog.value = true;
  challengeMessage.value = "";
  challengeIndex.value = 0;
  const { shuffleChallenges } = await faceApi();
  challengeSequence.value = shuffleChallenges();
  await nextTick();
  await startCamera("user");
}

async function startCamera(facing: "user" | "environment") {
  stopCamera();
  cameraReady.value = false;
  const target = facing === "user" ? liveVideo.value : idVideo.value;
  try {
    stream = await navigator.mediaDevices.getUserMedia({
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
  if (liveVideo.value) liveVideo.value.srcObject = null;
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
    slots.value.school_id = payload.data;
    idDialog.value = false;
    stopCamera();
    success.value = "School ID scan confirmed. Slots 2–4 are unlocked.";
    toast.success(success.value);
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "School ID scan failed.";
    toast.error(error.value);
  } finally {
    busy.value = "";
  }
}

async function uploadDocument(
  slotKey: "course_history" | "grade_slip" | "specimen_signatures",
  file: File | null,
) {
  if (!file) {
    error.value =
      slotKey === "specimen_signatures"
        ? "Choose an image of your 3 specimen signatures first."
        : "Choose a PDF file first.";
    return;
  }

  if (slotKey === "specimen_signatures" && !specimenBlueInkAck.value) {
    error.value = "Confirm that the specimens were written with a blue ballpen before uploading.";
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
    });
    const payload = await response.json();
    if (!response.ok) throw new Error(payloadMessage(payload, "Upload failed."));
    slots.value[slotKey] = payload.data;
    success.value = `${payload.data.document_type} uploaded.`;
    toast.success(success.value);
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "Upload failed.";
    toast.error(error.value);
  } finally {
    busy.value = "";
  }
}

async function checkCurrentChallenge() {
  if (!liveVideo.value) return;
  busy.value = "challenge";
  error.value = "";
  challengeMessage.value = "";
  try {
    const challenge = challengeSequence.value[challengeIndex.value];
    const { detectChallenge } = await faceApi();
    const passed = await detectChallenge(liveVideo.value, challenge);
    if (!passed) {
      challengeMessage.value = `Face movement not detected yet. Try ${challengeLabels[challenge].toLowerCase()} again.`;
      return;
    }

    if (challengeIndex.value < challengeSequence.value.length - 1) {
      challengeIndex.value += 1;
      challengeMessage.value = "Detected. Continue to the next challenge.";
      return;
    }

    await finishIdentityCheck();
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "Unable to validate the liveness challenge.";
  } finally {
    busy.value = "";
  }
}

async function finishIdentityCheck() {
  if (!liveVideo.value) return;
  if (!slots.value.school_id?.face_descriptor?.length) {
    throw new Error("The stored School ID reference face is missing.");
  }
  if (!onboardingRefs.value?.id_reference_face_url || !onboardingRefs.value?.onboarding_selfie_url) {
    throw new Error("Onboarding reference photos are missing.");
  }

  const api = await faceApi();
  const live = await api.descriptorFromVideo(liveVideo.value);
  const vsSubmission = api.euclideanDistance(slots.value.school_id.face_descriptor, live.descriptor);
  const refDesc = await api.descriptorFromUrl(onboardingRefs.value.id_reference_face_url);
  const selfieDesc = await api.descriptorFromUrl(onboardingRefs.value.onboarding_selfie_url);
  const vsReference = api.euclideanDistance(refDesc.descriptor, live.descriptor);
  const vsOnboardingSelfie = api.euclideanDistance(selfieDesc.descriptor, live.descriptor);
  const distances = {
    vs_submission_id: vsSubmission,
    vs_id_reference: vsReference,
    vs_onboarding_selfie: vsOnboardingSelfie,
  };
  const matched = Object.values(distances).every((distance) => distance < 0.5);
  const selfie = await api.captureVideoFrame(liveVideo.value, 0.9);

  const body = new FormData();
  challengeSequence.value.forEach((step, index) => body.append(`challenge_sequence[${index}]`, step));
  body.append("result", matched ? "match" : "no_match");
  body.append("distance", String(Math.max(...Object.values(distances))));
  body.append("distances[vs_submission_id]", String(vsSubmission));
  body.append("distances[vs_id_reference]", String(vsReference));
  body.append("distances[vs_onboarding_selfie]", String(vsOnboardingSelfie));
  body.append("confidence_score", String(Math.max(0, Math.min(100, (1 - Math.max(...Object.values(distances))) * 100))));
  body.append("consent_accepted", "1");
  body.append("liveness_confirmed", "1");
  body.append("selfie", new File([selfie], "submission_selfie.jpg", { type: "image/jpeg" }));

  const response = await fetch("/api/student/requirement-vault/identity-check", {
    method: "POST",
    headers: { Authorization: `Bearer ${getAuthToken()}`, Accept: "application/json" },
    body,
  });
  const payload = await response.json();
  if (!response.ok) throw new Error(payloadMessage(payload, "Unable to log identity check."));

  identityCheck.value = payload.data;
  identityDialog.value = false;
  stopCamera();
  success.value = matched
    ? "All three face matches passed. You can confirm your submission."
    : "Identity flagged for manual review. You may still confirm for staff evaluation.";
}

async function confirmSubmission() {
  busy.value = "confirm";
  error.value = "";
  success.value = "";
  try {
    const response = await fetch("/api/student/requirement-vault/confirm", {
      method: "POST",
      headers: { Authorization: `Bearer ${getAuthToken()}`, Accept: "application/json" },
    });
    const payload = await response.json();
    if (!response.ok) throw new Error(payload.message || "Unable to confirm submission.");
    granteeStatus.value = payload.grantee.submission_status;
    success.value = "Requirements confirmed. Status updated to Docs Submitted. Backend pipeline queued.";
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "Unable to confirm submission.";
  } finally {
    busy.value = "";
  }
}
</script>

<template>
  <div class="space-y-5">
    <PageHeader
      title="Requirement Vault"
      description="Live School ID scan, Course History, Grade Slip, specimen signatures, and submission liveness."
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
            <p class="text-xs font-medium uppercase text-text-muted">Submission progress</p>
            <p class="mt-1 text-3xl font-semibold">{{ progress }}%</p>
            <p class="text-xs capitalize text-text-muted">{{ granteeStatus.replaceAll("_", " ") }}</p>
          </div>
          <button
            class="inline-flex h-9 items-center gap-2 rounded-md bg-primary px-3 text-xs font-medium text-white disabled:opacity-50"
            :disabled="!canConfirm || busy === 'confirm' || granteeStatus === 'docs_submitted'"
            @click="confirmSubmission"
          >
            <IconShieldCheck :size="15" /> Confirm submission
          </button>
        </div>
        <div class="mt-4 h-2 overflow-hidden rounded-full bg-surface-muted">
          <div class="h-full bg-primary transition-all" :style="{ width: `${progress}%` }" />
        </div>
      </section>

      <p v-if="error" class="rounded-md border border-danger/30 bg-danger-soft p-3 text-xs text-danger">{{ error }}</p>
      <p v-if="success" class="rounded-md border border-success/30 bg-success-soft p-3 text-xs text-success">{{ success }}</p>

      <article class="rounded-lg border bg-surface p-4">
        <h2 class="flex items-center gap-2 text-sm font-semibold"><IconCamera :size="17" /> Pre-check &amp; consent (required before Slot 1)</h2>
        <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
          <label v-for="[key, label] in precheckItems" :key="key" class="flex items-center gap-2 rounded-md border p-3 text-xs">
            <input v-model="precheck[key]" type="checkbox" />
            {{ label }}
          </label>
        </div>
        <label class="mt-3 flex items-start gap-2 rounded-md border p-3 text-xs">
          <input v-model="consent" type="checkbox" />
          <span>I accept the Data Privacy Act consent for identity verification processing.</span>
        </label>
      </article>

      <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-lg border bg-surface p-4" :class="{ 'opacity-60': !precheckReady && !schoolIdUploaded }">
          <div class="flex items-start gap-3">
            <span class="grid h-10 w-10 place-items-center rounded-lg bg-primary-soft text-primary"><IconId :size="20" /></span>
            <div>
              <h2 class="text-sm font-semibold">Slot 1: School ID Scan</h2>
              <p class="text-xs text-text-muted">Live camera + QR / OCR / face match</p>
            </div>
          </div>
          <div v-if="slots.school_id" class="mt-4 rounded-md border border-success/30 bg-success-soft p-3 text-xs">
            <p class="font-semibold text-success"><IconCheck :size="14" class="inline" /> Confirmed</p>
            <p class="mt-1 text-text-muted">Quality: {{ slots.school_id.face_quality_score?.toFixed(2) || "n/a" }}</p>
          </div>
          <button
            class="mt-4 inline-flex h-9 items-center gap-2 rounded-md bg-primary px-3 text-xs font-medium text-white disabled:opacity-50"
            :disabled="!canOpenIdScan || busy === 'id'"
            @click="openIdDialog"
          >
            <IconCamera :size="14" /> Start live ID scan
          </button>
        </article>

        <article class="rounded-lg border bg-surface p-4" :class="{ 'opacity-60': !schoolIdUploaded }">
          <div class="flex items-start gap-3">
            <span class="grid h-10 w-10 place-items-center rounded-lg bg-info-soft text-info"><IconFileText :size="20" /></span>
            <div>
              <h2 class="text-sm font-semibold">Slot 2: Course History</h2>
              <p class="text-xs text-text-muted">PDF only</p>
            </div>
          </div>
          <p v-if="slots.course_history" class="mt-4 rounded-md border border-success/30 bg-success-soft p-3 text-xs text-success">
            <IconCheck :size="14" class="inline" /> {{ slots.course_history.original_name }}
          </p>
          <div class="mt-4 space-y-3">
            <input :disabled="!schoolIdUploaded" class="block w-full text-xs" type="file" accept=".pdf" @change="choose($event, 'course')" />
            <button
              class="inline-flex h-9 items-center gap-2 rounded-md border px-3 text-xs disabled:opacity-50"
              :disabled="!schoolIdUploaded || busy === 'course_history'"
              @click="uploadDocument('course_history', courseHistory)"
            >
              <IconUpload :size="14" /> Upload Course History
            </button>
          </div>
        </article>

        <article class="rounded-lg border bg-surface p-4" :class="{ 'opacity-60': !schoolIdUploaded }">
          <div class="flex items-start gap-3">
            <span class="grid h-10 w-10 place-items-center rounded-lg bg-info-soft text-info"><IconFileText :size="20" /></span>
            <div>
              <h2 class="text-sm font-semibold">Slot 3: Grade Slip</h2>
              <p class="text-xs text-text-muted">PDF only</p>
            </div>
          </div>
          <p v-if="slots.grade_slip" class="mt-4 rounded-md border border-success/30 bg-success-soft p-3 text-xs text-success">
            <IconCheck :size="14" class="inline" /> {{ slots.grade_slip.original_name }}
          </p>
          <div class="mt-4 space-y-3">
            <input :disabled="!schoolIdUploaded" class="block w-full text-xs" type="file" accept=".pdf" @change="choose($event, 'grade')" />
            <button
              class="inline-flex h-9 items-center gap-2 rounded-md border px-3 text-xs disabled:opacity-50"
              :disabled="!schoolIdUploaded || busy === 'grade_slip'"
              @click="uploadDocument('grade_slip', gradeSlip)"
            >
              <IconUpload :size="14" /> Upload Grade Slip
            </button>
          </div>
        </article>

        <article class="rounded-lg border bg-surface p-4" :class="{ 'opacity-60': !schoolIdUploaded }">
          <div class="flex items-start gap-3">
            <span class="grid h-10 w-10 place-items-center rounded-lg bg-info-soft text-info"><IconSignature :size="20" /></span>
            <div>
              <h2 class="text-sm font-semibold">Slot 4: 3 Specimen Signatures</h2>
              <p class="text-xs text-text-muted">Image only (JPG, PNG, WEBP)</p>
            </div>
          </div>
          <p class="mt-3 text-xs text-text-muted">
            Write three specimen signatures on one sheet using a <span class="font-semibold text-text">blue ballpen</span>.
          </p>
          <p v-if="slots.specimen_signatures" class="mt-4 rounded-md border border-success/30 bg-success-soft p-3 text-xs text-success">
            <IconCheck :size="14" class="inline" /> {{ slots.specimen_signatures.original_name }}
          </p>
          <div class="mt-4 space-y-3">
            <label class="flex items-start gap-2 rounded-md border p-3 text-xs">
              <input v-model="specimenBlueInkAck" type="checkbox" :disabled="!schoolIdUploaded" />
              <span>I confirm the three specimens were written with a blue ballpen.</span>
            </label>
            <input
              :disabled="!schoolIdUploaded"
              class="block w-full text-xs"
              type="file"
              accept=".jpg,.jpeg,.png,.webp"
              @change="choose($event, 'specimen')"
            />
            <button
              class="inline-flex h-9 items-center gap-2 rounded-md border px-3 text-xs disabled:opacity-50"
              :disabled="!schoolIdUploaded || busy === 'specimen_signatures'"
              @click="uploadDocument('specimen_signatures', specimenSignatures)"
            >
              <IconUpload :size="14" /> Upload Specimen Signatures
            </button>
          </div>
        </article>
      </section>

      <section class="grid gap-4 lg:grid-cols-[1fr_22rem]">
        <article class="rounded-lg border bg-surface p-4">
          <h2 class="flex items-center gap-2 text-sm font-semibold"><IconCamera :size="17" /> Submission liveness</h2>
          <p class="mt-2 text-xs text-text-muted">
            After all four slots are complete, run the randomized liveness challenge with three face matches.
          </p>
          <button
            class="mt-4 inline-flex h-9 items-center gap-2 rounded-md bg-primary px-3 text-xs font-medium text-white disabled:opacity-50"
            :disabled="!canOpenIdentity"
            @click="openIdentityDialog"
          >
            <IconCamera :size="14" /> Start submission liveness
          </button>
        </article>

        <aside class="rounded-lg border bg-surface p-4">
          <h2 class="text-sm font-semibold">Identity result</h2>
          <div v-if="identityCheck" class="mt-3 space-y-2 text-xs">
            <p :class="identityCheck.result === 'match' ? 'text-success' : 'text-warning'">
              {{ identityCheck.result === "match" ? "Match" : "No match - manual review" }}
            </p>
            <p class="text-text-muted">Max distance: {{ identityCheck.distance.toFixed(4) }}</p>
            <p v-if="identityCheck.distances" class="text-text-muted">
              vs ID {{ identityCheck.distances.vs_submission_id?.toFixed(3) }} · vs ref
              {{ identityCheck.distances.vs_id_reference?.toFixed(3) }} · vs selfie
              {{ identityCheck.distances.vs_onboarding_selfie?.toFixed(3) }}
            </p>
            <p v-if="identityCheck.manual_review_required" class="flex gap-2 text-warning">
              <IconAlertTriangle :size="14" /> Staff review required.
            </p>
          </div>
          <p v-else class="mt-3 text-xs text-text-muted">Complete all slots before identity matching.</p>
        </aside>
      </section>
    </template>

    <AppDialog
      v-model="idDialog"
      title="Live School ID scan"
      description="Hold your physical School ID inside the card frame. QR, OCR, and face matches run on capture."
      size="xl"
      @update:model-value="(open) => !open && stopCamera()"
    >
      <div class="space-y-4">
        <div class="relative overflow-hidden rounded-xl border bg-black">
          <video ref="idVideo" class="h-[min(58vh,34rem)] min-h-[20rem] w-full object-cover" autoplay playsinline muted />
          <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
            <div class="h-[58%] w-[78%] rounded-xl border-2 border-white/80 shadow-[0_0_0_9999px_rgba(0,0,0,0.35)]" />
          </div>
          <p class="absolute bottom-3 left-3 right-3 rounded-md bg-black/55 px-3 py-2 text-center text-xs text-white">
            {{ qrHint }}
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
      v-model="identityDialog"
      title="Submission liveness"
      description="Complete the randomized challenges. Live face is matched to submission ID, onboarding ID, and onboarding selfie."
      size="xl"
      @update:model-value="(open) => !open && stopCamera()"
    >
      <div class="space-y-4">
        <div class="relative overflow-hidden rounded-xl border bg-black">
          <video ref="liveVideo" class="h-[min(58vh,34rem)] min-h-[20rem] w-full object-cover" autoplay playsinline muted />
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <p class="text-sm font-semibold">
              {{ challengeSequence[challengeIndex] ? challengeLabels[challengeSequence[challengeIndex]] : "Preparing camera" }}
            </p>
            <p class="text-xs text-text-muted">Step {{ challengeIndex + 1 }} of {{ challengeSequence.length || 3 }}</p>
          </div>
          <button class="inline-flex h-9 items-center gap-2 rounded-md border px-3 text-xs" @click="startCamera('user')">
            <IconRefresh :size="14" /> Restart camera
          </button>
        </div>
        <p v-if="challengeMessage" class="rounded-md border bg-surface-muted p-3 text-xs text-text-muted">{{ challengeMessage }}</p>
      </div>
      <template #footer="{ close }">
        <button class="rounded-md border px-4 py-2 text-xs" @click="close">Cancel</button>
        <button
          class="rounded-md bg-primary px-4 py-2 text-xs text-white disabled:opacity-50"
          :disabled="!cameraReady || busy === 'challenge'"
          @click="checkCurrentChallenge"
        >
          {{ busy === "challenge" ? "Checking..." : "Check challenge" }}
        </button>
      </template>
    </AppDialog>
  </div>
</template>
