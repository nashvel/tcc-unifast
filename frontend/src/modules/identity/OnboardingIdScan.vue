<script setup lang="ts">
import { apiUrl } from "@/api/client";
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useRouter } from "vue-router";
import { IconAlertTriangle, IconCamera, IconCheck, IconId, IconRefresh } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import { getAuthToken } from "@/auth/session";
import { toast } from "@/composables/useToast";
import { getUserMediaSafe } from "@/modules/requirements/cameraAccess";
import {
  captureVideoFrame,
  cropFaceFromVideoFast,
  detectFaceInGuide,
  estimateIdFillInGuide,
  loadFaceModels,
  resetFaceModelLoad,
  type FaceBox,
  type FaceCropResult,
} from "@/modules/requirements/faceApi";
import { analyzeGuideQuality } from "@/modules/requirements/idCaptureQuality";

type CaptureSide = "front" | "back";

/** ~440ms at 220ms tick — green must hold before auto-capture. */
const AUTO_CAPTURE_FRAMES = 2;

const router = useRouter();
const video = ref<HTMLVideoElement | null>(null);
const guideEl = ref<HTMLElement | null>(null);
const cameraReady = ref(false);
const modelsReady = ref(false);
const modelsLoading = ref(false);
const modelsError = ref("");
const busy = ref(false);
const statusLabel = ref("");
const error = ref("");
const side = ref<CaptureSide>("front");
const captureReady = ref(false);
const liveHint = ref("Align your School ID to the edges of the frame (photo / name side).");
const frontBlob = ref<Blob | null>(null);
const frontFace = ref<FaceCropResult | null>(null);
const backBlob = ref<Blob | null>(null);
const flipBanner = ref("");
let stream: MediaStream | null = null;
let detectTimer: ReturnType<typeof setInterval> | null = null;
let detectBusy = false;
let lastFaceBox: FaceBox | null = null;
let readyStreak = 0;
let autoCaptureArmed = true;

const defaultFrontHint = "Fill the frame edge-to-edge with the FRONT of your School ID (photo side).";
const defaultBackHint = "Flip the ID — fill the frame edge-to-edge with the BACK.";

onMounted(async () => {
  await nextTick();
  await Promise.all([ensureModels(), startCamera()]);
});

onBeforeUnmount(() => {
  stopDetectLoop();
  stopCamera();
});

watch([cameraReady, modelsReady, side], () => {
  if (!cameraReady.value) {
    stopDetectLoop();
    return;
  }
  if (side.value === "front" && !modelsReady.value) {
    stopDetectLoop();
    captureReady.value = false;
    return;
  }
  startDetectLoop();
});

const canCapture = computed(() => {
  if (!cameraReady.value || busy.value) return false;
  if (side.value === "front") return modelsReady.value && captureReady.value;
  return captureReady.value;
});

const primaryLabel = computed(() => {
  if (busy.value) return statusLabel.value || "Working…";
  if (side.value === "front") {
    if (!modelsReady.value) return "Waiting for face models…";
    if (!captureReady.value) return "Align ID to capture";
    return "Capture front";
  }
  if (!captureReady.value) return "Fill frame to capture back";
  return "Capture back & continue";
});

const guideBorderClass = computed(() => {
  if (captureReady.value) return "border-emerald-400";
  return "border-amber-300/90";
});

const guideStyle = computed(() => {
  const vignette = "0 0 0 9999px rgba(0, 0, 0, 0.45)";
  if (captureReady.value) {
    return { boxShadow: `${vignette}, 0 0 18px rgba(52, 211, 153, 0.55)` };
  }
  return { boxShadow: vignette };
});

async function ensureModels() {
  if (modelsReady.value) return;
  modelsLoading.value = true;
  modelsError.value = "";
  try {
    await loadFaceModels();
    modelsReady.value = true;
  } catch (exception) {
    modelsReady.value = false;
    modelsError.value = exception instanceof Error ? exception.message : "Unable to load face models.";
  } finally {
    modelsLoading.value = false;
  }
}

async function retryModels() {
  resetFaceModelLoad();
  modelsReady.value = false;
  captureReady.value = false;
  await ensureModels();
  if (modelsReady.value) {
    toast.success("Face models loaded");
  }
}

async function startCamera() {
  stopCamera();
  cameraReady.value = false;
  error.value = "";
  try {
    stream = await getUserMediaSafe({
      video: {
        facingMode: { ideal: "environment" },
        width: { ideal: 960 },
        height: { ideal: 1280 },
      },
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

function startDetectLoop() {
  stopDetectLoop();
  detectTimer = setInterval(() => {
    void tickGuide();
  }, 220);
}

function stopDetectLoop() {
  if (detectTimer) {
    clearInterval(detectTimer);
    detectTimer = null;
  }
  detectBusy = false;
}

async function tickGuide() {
  if (detectBusy || busy.value) return;
  if (!video.value || !guideEl.value || !cameraReady.value) return;
  if (video.value.readyState < 2) return;

  detectBusy = true;
  try {
    const quality = analyzeGuideQuality(video.value, guideEl.value);

    if (side.value === "front") {
      if (!modelsReady.value) return;
      const hit = await detectFaceInGuide(video.value, guideEl.value);
      if (hit.box) lastFaceBox = hit.box;

      if (!quality.ok) {
        readyStreak = 0;
        captureReady.value = false;
        liveHint.value = quality.reason || defaultFrontHint;
        return;
      }

      if (hit.ready) {
        readyStreak += 1;
      } else {
        readyStreak = 0;
      }
      captureReady.value = hit.ready;
      if (captureReady.value) {
        liveHint.value =
          readyStreak >= AUTO_CAPTURE_FRAMES
            ? "Capturing…"
            : "Ready — hold steady (auto-capture)";
        maybeAutoCapture();
      } else if (hit.inGuide && hit.fillRatio < 0.58) {
        liveHint.value = "Move closer — align the ID to the edges of the frame";
      } else if (hit.detected) {
        liveHint.value = "Center the photo face inside the frame";
      } else {
        liveHint.value = defaultFrontHint;
      }
      return;
    }

    // Back: green when fill + sharpness/glare OK (no face).
    const fill = estimateIdFillInGuide(video.value, guideEl.value);
    if (!quality.ok) {
      readyStreak = 0;
      captureReady.value = false;
      liveHint.value = quality.reason || defaultBackHint;
      return;
    }

    if (fill.filled) {
      readyStreak += 1;
    } else {
      readyStreak = 0;
    }
    captureReady.value = fill.filled;
    if (captureReady.value) {
      liveHint.value =
        readyStreak >= AUTO_CAPTURE_FRAMES
          ? "Capturing…"
          : "Ready — hold steady (auto-capture)";
      maybeAutoCapture();
    } else if (fill.fillRatio > 0.35) {
      liveHint.value = "Move closer — align the back to the edges of the frame";
    } else {
      liveHint.value = defaultBackHint;
    }
  } catch (exception) {
    captureReady.value = false;
    const message = exception instanceof Error ? exception.message : "Guide check failed.";
    if (/models failed to load|failed to fetch/i.test(message)) {
      modelsReady.value = false;
      modelsError.value = message;
      stopDetectLoop();
    }
  } finally {
    detectBusy = false;
  }
}

function maybeAutoCapture() {
  if (!autoCaptureArmed || busy.value) return;
  if (readyStreak < AUTO_CAPTURE_FRAMES || !captureReady.value) return;
  autoCaptureArmed = false;
  void captureAndContinue();
}

function resetToFront() {
  side.value = "front";
  frontBlob.value = null;
  frontFace.value = null;
  backBlob.value = null;
  captureReady.value = false;
  readyStreak = 0;
  autoCaptureArmed = true;
  lastFaceBox = null;
  flipBanner.value = "";
  liveHint.value = defaultFrontHint;
  error.value = "";
}

async function captureAndContinue() {
  if (!video.value) return;
  if (!captureReady.value) {
    error.value =
      side.value === "front"
        ? "Wait for the green frame — face visible, sharp, and low glare."
        : "Wait for the green frame — fill the guide with a sharp, glare-free back.";
    autoCaptureArmed = true;
    return;
  }

  error.value = "";
  try {
    if (side.value === "front") {
      statusLabel.value = "Capturing…";
      busy.value = true;
      const [face, frameBlob] = await Promise.all([
        cropFaceFromVideoFast(video.value, lastFaceBox),
        captureVideoFrame(video.value, 0.85),
      ]);
      frontBlob.value = frameBlob;
      frontFace.value = face;
      side.value = "back";
      captureReady.value = false;
      readyStreak = 0;
      autoCaptureArmed = true;
      lastFaceBox = null;
      liveHint.value = defaultBackHint;
      flipBanner.value = "Flip your ID to the back — then hold steady for auto-capture";
      toast.success("Front captured — flip your ID to the back");
      busy.value = false;
      statusLabel.value = "";
      return;
    }

    if (!frontBlob.value || !frontFace.value) {
      resetToFront();
      throw new Error("Front capture missing. Capture the front of your School ID first.");
    }

    statusLabel.value = "Capturing…";
    busy.value = true;
    const frameBlob = await captureVideoFrame(video.value, 0.85);
    backBlob.value = frameBlob;
    flipBanner.value = "";
    statusLabel.value = "Reading ID…";
    await submitScan(frontBlob.value, frontFace.value, frameBlob);
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "ID scan failed.";
    toast.error(error.value);
    autoCaptureArmed = true;
    readyStreak = 0;
    captureReady.value = false;
  } finally {
    busy.value = false;
    statusLabel.value = "";
  }
}

async function submitScan(front: Blob, face: FaceCropResult, back: Blob) {
  const token = getAuthToken();
  if (!token) {
    throw new Error("Unauthenticated. Activate or sign in again, then retry the ID scan.");
  }

  statusLabel.value = "Reading ID…";
  const body = new FormData();
  body.append("id_frame", new File([front], "id_onboarding_front.jpg", { type: "image/jpeg" }));
  body.append("id_back", new File([back], "id_onboarding_back.jpg", { type: "image/jpeg" }));
  body.append("id_face_crop", new File([face.blob], "id_reference_face.jpg", { type: "image/jpeg" }));
  face.descriptor.forEach((value, index) => body.append(`face_descriptor[${index}]`, String(value)));
  body.append("face_quality_score", String(face.quality));
  body.append("authenticity_skipped", "1");

  const response = await fetch(apiUrl("/api/student/identity-onboarding/id-scan"), {
    method: "POST",
    headers: { Authorization: `Bearer ${token}`, Accept: "application/json" },
    body,
  });
  const payload = await response.json();
  if (!response.ok) {
    const validation = payload.errors ? Object.values(payload.errors).flat().join(" ") : "";
    throw new Error(validation || payload.message || "ID scan failed.");
  }

  toast.success("School ID verified — continue to liveness");
  await router.push("/student/onboarding/liveness");
}
</script>

<template>
  <div class="space-y-5">
    <PageHeader
      title="Onboarding — Scan School ID"
      description="Step 2 of 3. Align your School ID to the edges of the guide (full-bleed). Front runs OCR + face match; back also runs OCR (sparse text is OK)."
    />

    <section class="rounded-2xl border bg-surface p-4 shadow-sm sm:p-5">
      <div
        v-if="flipBanner"
        class="mb-3 rounded-lg border border-emerald-400/40 bg-emerald-500/15 px-4 py-3 text-center text-sm font-semibold text-emerald-800 dark:text-emerald-200"
        role="status"
      >
        {{ flipBanner }}
      </div>

      <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <p class="flex items-center gap-2 text-sm font-semibold">
          <IconId :size="16" class="text-primary" />
          {{ side === "front" ? "Front of School ID" : "Back of School ID" }}
          <span class="rounded-md bg-surface-muted px-2 py-0.5 text-xs font-medium text-text-muted">
            {{ side === "front" ? "1 / 2" : "2 / 2" }}
          </span>
        </p>
        <div class="flex flex-wrap gap-2">
          <button
            v-if="side === 'back'"
            class="inline-flex h-8 items-center gap-2 rounded-md border px-3 text-xs"
            type="button"
            @click="resetToFront"
          >
            Recapture front
          </button>
          <button class="inline-flex h-8 items-center gap-2 rounded-md border px-3 text-xs" type="button" @click="startCamera">
            <IconRefresh :size="14" /> Restart camera
          </button>
        </div>
      </div>

      <div class="relative mx-auto max-w-sm overflow-hidden rounded-xl border bg-black sm:max-w-md">
        <video ref="video" class="aspect-[3/4] w-full object-cover" autoplay playsinline muted />
        <div class="pointer-events-none absolute inset-0 flex items-center justify-center p-[3%]">
          <!-- Near full-bleed portrait School ID guide — align card to the edges -->
          <div
            ref="guideEl"
            class="aspect-[2.125/3.375] h-full max-h-full w-full max-w-full rounded-lg border-[3px] transition-[border-color,box-shadow] duration-150"
            :class="guideBorderClass"
            :style="guideStyle"
          />
        </div>
        <p
          class="absolute bottom-3 left-3 right-3 rounded-md px-3 py-2 text-center text-xs font-medium text-white transition-colors duration-150"
          :class="captureReady ? 'bg-emerald-600/85' : 'bg-black/60'"
        >
          {{ liveHint }}
        </p>
        <p
          v-if="captureReady"
          class="absolute left-1/2 top-3 -translate-x-1/2 rounded-full bg-emerald-500 px-3 py-1 text-[11px] font-semibold uppercase tracking-wide text-white shadow"
        >
          Ready
        </p>
        <p
          v-if="busy && statusLabel"
          class="absolute inset-x-0 top-1/2 -translate-y-1/2 bg-black/55 px-4 py-2 text-center text-sm font-medium text-white"
        >
          {{ statusLabel }}
        </p>
      </div>

      <p v-if="modelsError" class="mt-3 rounded-md border border-danger/30 bg-danger-soft p-3 text-xs text-danger">
        <IconAlertTriangle :size="14" class="mr-1 inline" /> {{ modelsError }}
        <button
          class="ml-2 inline-flex h-7 items-center gap-1 rounded-md border border-danger/40 bg-surface px-2 text-[11px] font-medium text-danger"
          type="button"
          :disabled="modelsLoading"
          @click="retryModels"
        >
          <IconRefresh :size="12" /> {{ modelsLoading ? "Loading…" : "Retry models" }}
        </button>
      </p>
      <p v-else-if="modelsLoading" class="mt-3 text-xs text-text-muted">Loading face detection models…</p>

      <p v-if="error" class="mt-3 rounded-md border border-danger/30 bg-danger-soft p-3 text-xs text-danger">
        <IconAlertTriangle :size="14" class="mr-1 inline" /> {{ error }}
      </p>

      <ul class="mt-4 grid gap-2 text-xs text-text-muted sm:grid-cols-2">
        <li class="rounded-md border p-3">
          <IconCheck :size="14" class="mr-1 inline text-success" /> Green frame auto-captures after a short hold (or tap Capture)
        </li>
        <li class="rounded-md border p-3">
          <IconCheck :size="14" class="mr-1 inline text-success" /> Retake if blurry or glare — tilt the ID
        </li>
        <li class="rounded-md border p-3">
          <IconCheck :size="14" class="mr-1 inline text-success" /> Front OCR matches name &amp; student ID
        </li>
        <li class="rounded-md border p-3">
          <IconCheck :size="14" class="mr-1 inline text-success" /> Back OCR runs (sparse text OK; QR best-effort)
        </li>
      </ul>

      <button
        class="mt-4 inline-flex h-10 items-center gap-2 rounded-md bg-primary px-4 text-sm font-medium text-white disabled:opacity-50"
        type="button"
        :disabled="!canCapture"
        @click="captureAndContinue"
      >
        <IconCamera :size="16" /> {{ primaryLabel }}
      </button>
    </section>
  </div>
</template>
