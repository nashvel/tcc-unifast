<script setup lang="ts">
import { apiUrl } from "@/api/client";
import { nextTick, onBeforeUnmount, ref, watch } from "vue";
import { useRouter } from "vue-router";
import {
  IconCamera,
  IconCheck,
  IconChevronLeft,
  IconChevronRight,
  IconId,
  IconLock,
  IconRefresh,
  IconShieldCheck,
} from "@tabler/icons-vue";
import AppDialog from "@/components/dialogs/AppDialog.vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import { markStudentVerified } from "@/auth/studentVerification";
import { getAuthToken } from "@/auth/session";
import { getUserMediaSafe } from "@/modules/requirements/cameraAccess";
import sampleStudentId from "@/assets/sample-student-id.png";
import sampleStudentIdBack from "@/assets/sample-student-id-back.png";

const router = useRouter();
const faceScanned = ref(false);
const matching = ref(false);
const matchScore = ref<number | null>(null);
const error = ref("");
const idSide = ref<"front" | "back">("front");
const verifyDialog = ref(false);
const video = ref<HTMLVideoElement | null>(null);
const cameraReady = ref(false);
const cameraStep = ref<"id" | "face">("id");
const capturedIdBlob = ref<Blob | null>(null);
const capturedFaceBlob = ref<Blob | null>(null);
let cameraStream: MediaStream | null = null;

watch(verifyDialog, async (open) => {
  if (open) {
    await nextTick();
    await startCamera();
  } else {
    stopCamera();
  }
});

onBeforeUnmount(stopCamera);

async function openVerification() {
  error.value = "";
  cameraStep.value = "id";
  capturedIdBlob.value = null;
  capturedFaceBlob.value = null;
  verifyDialog.value = true;
}

async function startCamera() {
  error.value = "";
  cameraReady.value = false;
  stopCamera();

  try {
    cameraStream = await getUserMediaSafe({
      video: { facingMode: "user", width: { ideal: 960 }, height: { ideal: 720 } },
      audio: false,
    });

    if (video.value) {
      video.value.srcObject = cameraStream;
      await video.value.play();
      cameraReady.value = true;
    }
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : "Unable to open camera. Please allow camera permission and try again.";
  }
}

function stopCamera() {
  cameraStream?.getTracks().forEach((track) => track.stop());
  cameraStream = null;
  cameraReady.value = false;
  if (video.value) video.value.srcObject = null;
}

async function captureCameraBlob(): Promise<Blob> {
  if (!video.value || !cameraReady.value) {
    throw new Error("Camera is not ready yet.");
  }

  const canvas = document.createElement("canvas");
  canvas.width = video.value.videoWidth || 960;
  canvas.height = video.value.videoHeight || 720;
  const context = canvas.getContext("2d");
  if (!context) throw new Error("Unable to capture camera frame.");
  context.drawImage(video.value, 0, 0, canvas.width, canvas.height);

  return await new Promise((resolve, reject) => {
    canvas.toBlob(
      (blob) => {
        if (blob) resolve(blob);
        else reject(new Error("Unable to encode camera frame."));
      },
      "image/jpeg",
      0.92,
    );
  });
}

async function captureAndVerify() {
  matching.value = true;
  error.value = "";
  const body = new FormData();

  try {
    const idBlob = capturedIdBlob.value;
    const faceBlob = capturedFaceBlob.value;

    if (!idBlob || !faceBlob) {
      throw new Error("Capture the ID and face before submitting verification.");
    }

    body.append("student_id_document", idBlob, "live-id-capture.jpg");
    body.append("face_capture", faceBlob, "live-face-capture.jpg");

    const response = await fetch(apiUrl("/api/student/identity/face-verify"), {
      method: "POST",
      headers: { Authorization: `Bearer ${getAuthToken()}`, Accept: "application/json" },
      body,
    });
    const payload = await response.json();
    if (!response.ok) {
      throw new Error(payload.message || "Face verification failed.");
    }

    faceScanned.value = Boolean(payload.matched);
    matchScore.value = Number(payload.score ?? 0);
    if (!payload.matched) {
      error.value = `Face match did not reach the ${payload.threshold}% threshold.`;
      return;
    }

    verifyDialog.value = false;
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "Face verification failed.";
  } finally {
    matching.value = false;
  }
}

async function captureCurrentStep() {
  error.value = "";
  try {
    const blob = await captureCameraBlob();
    if (cameraStep.value === "id") {
      capturedIdBlob.value = blob;
      cameraStep.value = "face";
      return;
    }

    capturedFaceBlob.value = blob;
    await captureAndVerify();
  } catch (exception) {
    error.value =
      exception instanceof Error ? exception.message : "Unable to capture camera frame.";
  }
}

function retakeId() {
  capturedIdBlob.value = null;
  capturedFaceBlob.value = null;
  cameraStep.value = "id";
  error.value = "";
}

async function finish() {
  markStudentVerified();
  await router.push("/student");
}
</script>

<template>
  <div class="space-y-5">
    <PageHeader
      title="Identity Verification"
      description="Review the official ID reference, then scan your physical ID and face live through the camera."
    />

    <section class="space-y-4">
      <article class="overflow-hidden rounded-2xl border bg-surface shadow-sm">
        <div class="border-b bg-surface-muted px-4 py-3">
          <p class="flex items-center gap-2 text-sm font-semibold">
            <IconId :size="17" class="text-primary" /> Admin reference ID sample
          </p>
          <p class="mt-1 text-xs text-text-muted">
            Review the front first, then click Next to check the back side.
          </p>
        </div>
        <div
          class="grid gap-5 bg-[radial-gradient(circle_at_top,_rgba(147,25,45,0.10),transparent_55%)] p-5 lg:grid-cols-[300px_1fr]"
        >
          <figure class="mx-auto w-full max-w-[260px] rounded-2xl border bg-white p-3 shadow-xl">
            <img
              :src="idSide === 'front' ? sampleStudentId : sampleStudentIdBack"
              :alt="
                idSide === 'front'
                  ? 'Sample TCC student ID front reference'
                  : 'Sample TCC student ID back reference'
              "
              class="mx-auto max-h-[430px] w-full object-contain"
            />
            <figcaption
              class="mt-3 rounded-lg bg-surface-muted px-3 py-2 text-center text-xs font-semibold"
            >
              {{
                idSide === "front" ? "Front - face reference" : "Back - QR and emergency details"
              }}
            </figcaption>
            <div class="mt-3 grid grid-cols-2 gap-2">
              <button
                class="inline-flex items-center justify-center gap-1 rounded-md border px-3 py-2 text-xs disabled:opacity-50"
                :disabled="idSide === 'front'"
                @click="idSide = 'front'"
              >
                <IconChevronLeft :size="13" /> Front
              </button>
              <button
                class="inline-flex items-center justify-center gap-1 rounded-md border px-3 py-2 text-xs disabled:opacity-50"
                :disabled="idSide === 'back'"
                @click="idSide = 'back'"
              >
                Back <IconChevronRight :size="13" />
              </button>
            </div>
          </figure>

          <div class="flex flex-col justify-center">
            <span
              class="inline-flex w-fit items-center gap-1.5 rounded-full bg-success-soft px-3 py-1 text-xs font-semibold text-success"
            >
              <IconCheck :size="14" /> Reference sample ready
            </span>
            <h2 class="mt-4 text-2xl font-semibold tracking-tight">
              {{
                idSide === "front"
                  ? "Front side is used for face matching."
                  : "Back side supports QR and validity checks."
              }}
            </h2>
            <p class="mt-2 max-w-xl text-sm text-text-muted">
              {{
                idSide === "front"
                  ? "The live face scan compares against the front ID photo. Click Next to inspect the back side."
                  : "The back side contains school year, QR, emergency contact, and validity details. Click Front to return to face reference."
              }}
            </p>
            <div class="mt-5 flex max-w-md rounded-full border bg-surface p-1 text-xs">
              <button
                :class="[
                  'flex-1 rounded-full px-3 py-1.5',
                  idSide === 'front' ? 'bg-primary text-white' : 'text-text-muted',
                ]"
                @click="idSide = 'front'"
              >
                1. Front
              </button>
              <button
                :class="[
                  'flex-1 rounded-full px-3 py-1.5',
                  idSide === 'back' ? 'bg-primary text-white' : 'text-text-muted',
                ]"
                @click="idSide = 'back'"
              >
                2. Back
              </button>
            </div>

            <div class="mt-6 rounded-xl border bg-surface p-4">
              <div class="flex items-start gap-3">
                <IconLock :size="18" class="mt-0.5 shrink-0 text-warning" />
                <div>
                  <p class="text-sm font-semibold">Ready for live verification</p>
                  <p class="mt-1 text-xs text-text-muted">
                    The next step opens the camera, traces the ID mask first, then captures your
                    face.
                  </p>
                </div>
              </div>
              <button
                class="mt-4 inline-flex h-10 items-center gap-2 rounded-md bg-primary px-4 text-sm font-medium text-white"
                @click="openVerification"
              >
                <IconCamera :size="16" /> Get started verification
              </button>
            </div>
          </div>
        </div>
      </article>

      <div
        v-if="faceScanned && matchScore !== null"
        class="rounded-lg border border-success/30 bg-success-soft p-3"
      >
        <p class="flex items-center gap-2 text-sm font-semibold text-success">
          <IconCheck :size="16" /> Face match passed
        </p>
        <p class="mt-1 text-xs text-text-muted">
          Face API confidence score: {{ matchScore.toFixed(1) }}%.
        </p>
        <button
          class="mt-3 h-9 rounded-md bg-primary px-4 text-xs font-medium text-white"
          @click="finish"
        >
          Unlock dashboard and menus
        </button>
      </div>
      <p
        v-if="error && !verifyDialog"
        class="rounded-md border border-danger/30 bg-danger-soft p-3 text-xs text-danger"
      >
        {{ error }}
      </p>

      <article class="rounded-xl border bg-surface p-5">
        <h2 class="flex items-center gap-2 text-sm font-semibold">
          <IconShieldCheck :size="17" /> Verification flow
        </h2>
        <ol class="mt-3 grid gap-3 text-xs text-text-muted md:grid-cols-4">
          <li class="flex gap-2 rounded-lg bg-surface-muted p-3">
            <IconId :size="15" class="text-primary" /> Review front and back ID reference.
          </li>
          <li class="flex gap-2 rounded-lg bg-surface-muted p-3">
            <IconCamera :size="15" class="text-primary" /> Scan physical ID using the mask.
          </li>
          <li class="flex gap-2 rounded-lg bg-surface-muted p-3">
            <IconCheck :size="15" class="text-primary" /> Capture live face and upload both.
          </li>
          <li class="flex gap-2 rounded-lg bg-surface-muted p-3">
            <IconShieldCheck :size="15" class="text-primary" /> If matched, document upload unlocks.
          </li>
        </ol>
      </article>
    </section>

    <AppDialog
      v-model="verifyDialog"
      title="Live identity verification"
      :description="
        cameraStep === 'id'
          ? 'Place your physical student ID inside the traced card mask, then capture it live.'
          : 'Now position your face inside the oval mask, then capture and verify.'
      "
      size="xl"
    >
      <div class="space-y-4">
        <div class="grid grid-cols-2 gap-2 text-xs">
          <div
            :class="[
              'rounded-lg border p-2 text-center',
              capturedIdBlob
                ? 'border-success bg-success-soft text-success'
                : cameraStep === 'id'
                  ? 'border-primary bg-primary-soft text-primary'
                  : 'bg-surface-muted text-text-muted',
            ]"
          >
            1. Live ID scan
          </div>
          <div
            :class="[
              'rounded-lg border p-2 text-center',
              capturedFaceBlob
                ? 'border-success bg-success-soft text-success'
                : cameraStep === 'face'
                  ? 'border-primary bg-primary-soft text-primary'
                  : 'bg-surface-muted text-text-muted',
            ]"
          >
            2. Live face scan
          </div>
        </div>

        <div class="relative overflow-hidden rounded-xl border bg-black">
          <video
            ref="video"
            class="h-[min(58vh,34rem)] min-h-[20rem] w-full object-cover sm:min-h-[24rem]"
            autoplay
            playsinline
            muted
          />
          <div class="pointer-events-none absolute inset-0 bg-black/10">
            <div
              v-if="cameraStep === 'id'"
              class="absolute left-1/2 top-1/2 h-[78%] w-[76%] max-w-[26rem] -translate-x-1/2 -translate-y-1/2 rounded-[1.45rem] border-4 border-primary shadow-[0_0_0_999px_rgba(0,0,0,0.36)] sm:w-[58%]"
            >
              <span
                class="absolute -top-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full bg-primary px-3 py-1 text-xs font-semibold text-white"
              >
                Align ID card here
              </span>
              <span
                class="absolute left-1/2 top-4 h-5 w-20 -translate-x-1/2 rounded-full border-2 border-white/80"
              />
              <span
                class="absolute bottom-4 left-4 right-4 h-10 rounded border-2 border-dashed border-white/70"
              />
              <span
                class="absolute left-4 top-4 h-8 w-8 rounded-tl-xl border-l-4 border-t-4 border-white"
              />
              <span
                class="absolute right-4 top-4 h-8 w-8 rounded-tr-xl border-r-4 border-t-4 border-white"
              />
              <span
                class="absolute bottom-4 left-4 h-8 w-8 rounded-bl-xl border-b-4 border-l-4 border-white"
              />
              <span
                class="absolute bottom-4 right-4 h-8 w-8 rounded-br-xl border-b-4 border-r-4 border-white"
              />
            </div>
            <div
              v-else
              class="absolute left-1/2 top-1/2 h-[72%] w-[42%] -translate-x-1/2 -translate-y-1/2 rounded-[999px] border-4 border-primary shadow-[0_0_0_999px_rgba(0,0,0,0.36)]"
            >
              <span
                class="absolute -top-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full bg-primary px-3 py-1 text-xs font-semibold text-white"
              >
                Center face here
              </span>
              <span
                class="absolute left-1/2 top-[38%] h-3 w-3 -translate-x-1/2 rounded-full bg-white/80"
              />
              <span class="absolute left-[34%] top-[28%] h-3 w-3 rounded-full bg-white/80" />
              <span class="absolute right-[34%] top-[28%] h-3 w-3 rounded-full bg-white/80" />
            </div>
          </div>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-2">
          <p class="text-xs text-text-muted">
            {{
              cameraReady
                ? cameraStep === "id"
                  ? "Camera ready. Align the whole ID card inside the mask."
                  : "Camera ready. Keep your face centered and well lit."
                : "Opening camera..."
            }}
          </p>
          <div class="flex gap-2">
            <button
              v-if="capturedIdBlob"
              class="inline-flex items-center gap-1 rounded-md border px-3 py-2 text-xs"
              @click="retakeId"
            >
              Retake ID
            </button>
            <button
              class="inline-flex items-center gap-1 rounded-md border px-3 py-2 text-xs"
              @click="startCamera"
            >
              <IconRefresh :size="14" /> Restart camera
            </button>
          </div>
        </div>
        <p
          v-if="error"
          class="rounded-md border border-danger/30 bg-danger-soft p-3 text-xs text-danger"
        >
          {{ error }}
        </p>
      </div>
      <template #footer="{ close }">
        <button class="rounded-md border px-4 py-2 text-xs" @click="close">Cancel</button>
        <button
          class="rounded-md bg-primary px-4 py-2 text-xs text-white disabled:opacity-50"
          :disabled="matching || !cameraReady"
          @click="captureCurrentStep"
        >
          {{
            matching
              ? "Verifying..."
              : cameraStep === "id"
                ? "Capture ID and continue"
                : "Capture face and verify"
          }}
        </button>
      </template>
    </AppDialog>
  </div>
</template>
