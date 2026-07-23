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
  IconUpload,
} from "@tabler/icons-vue";
import AppDialog from "@/components/dialogs/AppDialog.vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import CardSkeleton from "@/components/ui/CardSkeleton.vue";
import { csrfToken } from "@/auth/session";
import { toast } from "@/composables/useToast";
import type { Challenge } from "@/modules/requirements/faceApi";

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
  confidence_score: number | null;
  manual_review_required: boolean;
  checked_at: string;
};

type VaultResponse = {
  window: { open: boolean; message: string };
  grantee: { submission_status: string; submitted_at: string | null } | null;
  slots: Record<string, VaultDocument>;
  identity_check: IdentityCheck | null;
};

const slots = ref<Record<string, VaultDocument>>({});
const identityCheck = ref<IdentityCheck | null>(null);
const granteeStatus = ref("not_submitted");
const windowOpen = ref(false);
const windowMessage = ref("");
const loading = ref(true);
const busy = ref("");
const error = ref("");
const success = ref("");

const idFront = ref<File | null>(null);
const idBack = ref<File | null>(null);
const courseHistory = ref<File | null>(null);
const gradeSlip = ref<File | null>(null);

const precheck = ref<Record<string, boolean>>({
  lighting: false,
  headwear: false,
  glasses: false,
  internet: false,
  permission: false,
});
const consent = ref(false);
const identityDialog = ref(false);
const video = ref<HTMLVideoElement | null>(null);
const cameraReady = ref(false);
const challengeSequence = ref<Challenge[]>([]);
const challengeIndex = ref(0);
const challengeMessage = ref("");
let stream: MediaStream | null = null;

const schoolIdUploaded = computed(() => Boolean(slots.value.school_id));
const allDocumentsUploaded = computed(
  () => Boolean(slots.value.school_id) && Boolean(slots.value.course_history) && Boolean(slots.value.grade_slip),
);
const canOpenIdentity = computed(
  () => allDocumentsUploaded.value && Object.values(precheck.value).every(Boolean) && consent.value,
);
const canConfirm = computed(() => allDocumentsUploaded.value && identityCheck.value);
const progress = computed(() => {
  const complete = [slots.value.school_id, slots.value.course_history, slots.value.grade_slip, identityCheck.value].filter(Boolean).length;
  return Math.round((complete / 4) * 100);
});

const challengeLabels: Record<Challenge, string> = {
  blink: "Blink",
  turn_left: "Turn left",
  turn_right: "Turn right",
};

const precheckItems = [
  ["lighting", "Good, stable lighting"],
  ["headwear", "Remove cap, hat, or hood"],
  ["glasses", "Remove sunglasses or tinted glasses"],
  ["internet", "Stable internet connection"],
  ["permission", "Allow camera permission when prompted"],
] as const;

onMounted(loadVault);
onBeforeUnmount(stopCamera);

function payloadMessage(payload: unknown, fallback: string) {
  if (!payload || typeof payload !== "object") return fallback;
  const data = payload as { message?: string; errors?: Record<string, string[]> };
  const validation = data.errors ? Object.values(data.errors)[0]?.[0] : "";
  return data.message || validation || fallback;
}

async function loadVault() {
  loading.value = true;
  error.value = "";
  try {
    const response = await fetch("/api/student/requirement-vault", { headers: { Accept: "application/json" } });
    const payload = (await response.json()) as VaultResponse;
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

function choose(event: Event, target: "front" | "back" | "course" | "grade") {
  const file = (event.target as HTMLInputElement).files?.[0] ?? null;
  if (target === "front") idFront.value = file;
  if (target === "back") idBack.value = file;
  if (target === "course") courseHistory.value = file;
  if (target === "grade") gradeSlip.value = file;
  error.value = "";
  success.value = "";
}

async function uploadId() {
  if (!idFront.value || !idBack.value) {
    error.value = "Upload both the front and back of your School ID.";
    return;
  }

  busy.value = "id";
  error.value = "";
  success.value = "";
  try {
    const { descriptorFromImage } = await faceApi();
    const face = await descriptorFromImage(idFront.value);
    const body = new FormData();
    body.append("id_front", idFront.value);
    body.append("id_back", idBack.value);
    face.descriptor.forEach((value, index) => body.append(`face_descriptor[${index}]`, String(value)));
    body.append("face_quality_score", String(face.quality));

    const response = await fetch("/api/student/requirement-vault/id", {
      method: "POST",
      headers: { "X-CSRF-TOKEN": csrfToken(), Accept: "application/json" },
      body,
    });
    const payload = await response.json();
    if (!response.ok) throw new Error(payloadMessage(payload, "School ID upload failed."));
    slots.value.school_id = payload.data;
    success.value = payload.data.identity_review_required
      ? "School ID uploaded. Face quality is low, so staff will review it manually."
      : "School ID uploaded. Course History and Grade Slip are now unlocked.";
    toast.success(success.value);
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : "School ID upload failed. Confirm the face-api.js model files are available.";
    toast.error(error.value);
  } finally {
    busy.value = "";
  }
}

async function uploadDocument(slotKey: "course_history" | "grade_slip", file: File | null) {
  if (!file) {
    error.value = "Choose a PDF file first.";
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
      headers: { "X-CSRF-TOKEN": csrfToken(), Accept: "application/json" },
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

async function openIdentityDialog() {
  if (!canOpenIdentity.value) return;
  identityDialog.value = true;
  challengeMessage.value = "";
  challengeIndex.value = 0;
  challengeSequence.value = shuffle(["blink", "turn_left", "turn_right"]);
  await nextTick();
  await startCamera();
}

async function startCamera() {
  stopCamera();
  cameraReady.value = false;
  try {
    stream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: "user", width: { ideal: 960 }, height: { ideal: 720 } },
      audio: false,
    });
    if (video.value) {
      video.value.srcObject = stream;
      await video.value.play();
      cameraReady.value = true;
    }
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "Unable to open camera.";
  }
}

function stopCamera() {
  stream?.getTracks().forEach((track) => track.stop());
  stream = null;
  cameraReady.value = false;
  if (video.value) video.value.srcObject = null;
}

async function checkCurrentChallenge() {
  if (!video.value) return;
  busy.value = "challenge";
  error.value = "";
  challengeMessage.value = "";
  try {
    const challenge = challengeSequence.value[challengeIndex.value];
    const { detectChallenge } = await faceApi();
    const passed = await detectChallenge(video.value, challenge);
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
  if (!video.value) return;
  const reference = slots.value.school_id?.face_descriptor;
  if (!reference?.length) throw new Error("The stored School ID reference face is missing.");

  const { descriptorFromVideo, euclideanDistance } = await faceApi();
  const live = await descriptorFromVideo(video.value);
  const distance = euclideanDistance(reference, live.descriptor);
  const matched = distance < 0.5;
  const body = {
    challenge_sequence: challengeSequence.value,
    result: matched ? "match" : "no_match",
    distance,
    confidence_score: Math.max(0, Math.min(100, (1 - distance) * 100)),
    consent_accepted: consent.value,
  };
  const response = await fetch("/api/student/requirement-vault/identity-check", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-CSRF-TOKEN": csrfToken(),
      Accept: "application/json",
    },
    body: JSON.stringify(body),
  });
  const payload = await response.json();
  if (!response.ok) throw new Error(payload.message || "Unable to log identity check.");

  identityCheck.value = payload.data;
  identityDialog.value = false;
  stopCamera();
  success.value = matched
    ? "Identity check matched. You can confirm your submission."
    : "Identity check logged as no match. Staff will manually review your submission.";
}

async function confirmSubmission() {
  busy.value = "confirm";
  error.value = "";
  success.value = "";
  try {
    const response = await fetch("/api/student/requirement-vault/confirm", {
      method: "POST",
      headers: { "X-CSRF-TOKEN": csrfToken(), Accept: "application/json" },
    });
    const payload = await response.json();
    if (!response.ok) throw new Error(payload.message || "Unable to confirm submission.");
    granteeStatus.value = payload.grantee.submission_status;
    success.value = "Requirements confirmed. Status updated to Docs Submitted.";
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "Unable to confirm submission.";
  } finally {
    busy.value = "";
  }
}

function shuffle(items: Challenge[]) {
  return [...items].sort(() => Math.random() - 0.5);
}
</script>

<template>
  <div class="space-y-5">
    <PageHeader
      title="Requirement Vault"
      description="Submit your School ID, Course History, Grade Slip, and final identity check."
    />

    <CardSkeleton v-if="loading" :lines="4" class-name="rounded-2xl p-6" />
    <section
      v-else-if="!windowOpen"
      class="rounded-2xl border bg-surface p-6 shadow-sm"
    >
      <span class="inline-flex items-center gap-2 rounded-full bg-warning-soft px-3 py-1 text-xs font-semibold text-warning">
        <IconLock :size="14" /> Locked vault
      </span>
      <h2 class="mt-4 text-2xl font-semibold tracking-tight">
        Submission window is closed
      </h2>
      <p class="mt-2 max-w-2xl text-sm text-text-muted">
        {{ windowMessage }}
      </p>
    </section>

    <template v-else>
      <section class="rounded-2xl border bg-surface p-4 shadow-sm sm:p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p class="text-xs font-medium uppercase text-text-muted">Submission progress</p>
            <p class="mt-1 text-3xl font-semibold">{{ progress }}%</p>
            <p class="text-xs capitalize text-text-muted">
              {{ granteeStatus.replaceAll("_", " ") }}
            </p>
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

      <p v-if="error" class="rounded-md border border-danger/30 bg-danger-soft p-3 text-xs text-danger">
        {{ error }}
      </p>
      <p v-if="success" class="rounded-md border border-success/30 bg-success-soft p-3 text-xs text-success">
        {{ success }}
      </p>

      <section class="grid gap-4 lg:grid-cols-3">
        <article class="rounded-lg border bg-surface p-4">
          <div class="flex items-start gap-3">
            <span class="grid h-10 w-10 place-items-center rounded-lg bg-primary-soft text-primary">
              <IconId :size="20" />
            </span>
            <div>
              <h2 class="text-sm font-semibold">Slot 1: School ID</h2>
              <p class="text-xs text-text-muted">Front and back image upload</p>
            </div>
          </div>
          <div v-if="slots.school_id" class="mt-4 rounded-md border border-success/30 bg-success-soft p-3 text-xs">
            <p class="font-semibold text-success"><IconCheck :size="14" class="inline" /> Uploaded</p>
            <p class="mt-1 text-text-muted">Quality: {{ slots.school_id.face_quality_score?.toFixed(2) || "n/a" }}</p>
            <p v-if="slots.school_id.identity_review_required" class="mt-1 text-warning">
              {{ slots.school_id.identity_review_reason }}
            </p>
          </div>
          <div class="mt-4 space-y-3">
            <label class="block text-xs font-medium">
              Front image
              <input class="mt-1 block w-full text-xs" type="file" accept=".jpg,.jpeg,.png,.webp" @change="choose($event, 'front')" />
            </label>
            <label class="block text-xs font-medium">
              Back image
              <input class="mt-1 block w-full text-xs" type="file" accept=".jpg,.jpeg,.png,.webp" @change="choose($event, 'back')" />
            </label>
            <button
              class="inline-flex h-9 items-center gap-2 rounded-md border px-3 text-xs disabled:opacity-50"
              :disabled="busy === 'id'"
              @click="uploadId"
            >
              <IconUpload :size="14" /> {{ busy === "id" ? "Scanning face..." : "Upload School ID" }}
            </button>
          </div>
        </article>

        <article class="rounded-lg border bg-surface p-4" :class="{ 'opacity-60': !schoolIdUploaded }">
          <div class="flex items-start gap-3">
            <span class="grid h-10 w-10 place-items-center rounded-lg bg-info-soft text-info">
              <IconFileText :size="20" />
            </span>
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
            <span class="grid h-10 w-10 place-items-center rounded-lg bg-info-soft text-info">
              <IconFileText :size="20" />
            </span>
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
      </section>

      <section class="grid gap-4 lg:grid-cols-[1fr_22rem]">
        <article class="rounded-lg border bg-surface p-4">
          <h2 class="flex items-center gap-2 text-sm font-semibold">
            <IconCamera :size="17" /> Pre-check and liveness
          </h2>
          <div class="mt-4 grid gap-2 sm:grid-cols-2">
            <label
              v-for="[key, label] in precheckItems"
              :key="key"
              class="flex items-center gap-2 rounded-md border p-3 text-xs"
            >
              <input v-model="precheck[key]" type="checkbox" />
              {{ label }}
            </label>
          </div>
          <label class="mt-3 flex items-start gap-2 rounded-md border p-3 text-xs">
            <input v-model="consent" type="checkbox" />
            <span>I accept the Data Privacy Act consent for identity verification processing.</span>
          </label>
          <button
            class="mt-4 inline-flex h-9 items-center gap-2 rounded-md bg-primary px-3 text-xs font-medium text-white disabled:opacity-50"
            :disabled="!canOpenIdentity"
            @click="openIdentityDialog"
          >
            <IconCamera :size="14" /> Start live identity check
          </button>
        </article>

        <aside class="rounded-lg border bg-surface p-4">
          <h2 class="text-sm font-semibold">Identity result</h2>
          <div v-if="identityCheck" class="mt-3 space-y-2 text-xs">
            <p :class="identityCheck.result === 'match' ? 'text-success' : 'text-warning'">
              {{ identityCheck.result === "match" ? "Match" : "No match - manual review" }}
            </p>
            <p class="text-text-muted">Distance: {{ identityCheck.distance.toFixed(4) }}</p>
            <p v-if="identityCheck.manual_review_required" class="flex gap-2 text-warning">
              <IconAlertTriangle :size="14" /> Staff review required.
            </p>
          </div>
          <p v-else class="mt-3 text-xs text-text-muted">
            Complete all slots and the pre-check before identity matching.
          </p>
        </aside>
      </section>
    </template>

    <AppDialog
      v-model="identityDialog"
      title="Live identity check"
      description="Complete the randomized liveness sequence, then the system will compare your live face with your School ID reference."
      size="xl"
      @update:model-value="(open) => !open && stopCamera()"
    >
      <div class="space-y-4">
        <div class="relative overflow-hidden rounded-xl border bg-black">
          <video ref="video" class="h-[min(58vh,34rem)] min-h-[20rem] w-full object-cover" autoplay playsinline muted />
          <div class="pointer-events-none absolute inset-0 shadow-[inset_0_0_0_999px_rgba(0,0,0,0.24)]" />
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <p class="text-sm font-semibold">
              {{ challengeSequence[challengeIndex] ? challengeLabels[challengeSequence[challengeIndex]] : "Preparing camera" }}
            </p>
            <p class="text-xs text-text-muted">
              Step {{ challengeIndex + 1 }} of {{ challengeSequence.length || 3 }}
            </p>
          </div>
          <button class="inline-flex h-9 items-center gap-2 rounded-md border px-3 text-xs" @click="startCamera">
            <IconRefresh :size="14" /> Restart camera
          </button>
        </div>
        <p v-if="challengeMessage" class="rounded-md border bg-surface-muted p-3 text-xs text-text-muted">
          {{ challengeMessage }}
        </p>
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
