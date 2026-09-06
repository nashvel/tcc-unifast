<script setup lang="ts">
import { apiUrl } from "@/api/client";
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { IconAlertTriangle, IconCamera, IconCheck, IconId, IconInfoCircle, IconRefresh } from "@tabler/icons-vue";
import AppDialog from "@/components/dialogs/AppDialog.vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import { useSuccessOverlay } from "@/composables/useSuccessOverlay";
import { toast } from "@/composables/useToast";
import { getUserMediaSafe } from "@/modules/requirements/cameraAccess";
import {
  captureGuideVideoFrame,
  cropFaceFromVideoWithTimeout,
  detectFaceInGuide,
  estimateIdFillInGuide,
  loadFaceModels,
  resetFaceModelLoad,
  type FaceBox,
  type FaceCropResult,
} from "@/modules/requirements/faceApi";
import { analyzeBlobQuality, analyzeGuideQuality } from "@/modules/requirements/idCaptureQuality";
import { decodeQrFromBlob, decodeQrFromVideo, isTccRegistrarQr } from "@/modules/requirements/idQr";

export type SchoolIdCaptureMode = "onboarding" | "vault";

export type SchoolIdCaptureComplete = {
  front: Blob;
  back: Blob;
  face: FaceCropResult;
  qr: string | null;
};

const props = withDefaults(
  defineProps<{
    mode: SchoolIdCaptureMode;
    title: string;
    description?: string;
    /** Primary label after both-sides verify succeeds. */
    verifiedLabel?: string;
    /** Hint under verified status before auto-exit. */
    verifiedHint?: string;
    validateFront: (blob: Blob) => Promise<void>;
    submitCapture: (payload: SchoolIdCaptureComplete) => Promise<{ qrFound?: boolean } | void>;
    exitFlow: () => void | Promise<void>;
  }>(),
  {
    description: "",
    verifiedLabel: undefined,
    verifiedHint: undefined,
  },
);

type CaptureSide = "front" | "back";
type ScanPhase = "live" | "review";

const FACE_CROP_TIMEOUT_MS = 1800;
/** Detect loop interval; READY_STABLE_TICKS × DETECT_MS ≈ hold-before-auto-capture. */
const DETECT_MS = 220;
/** ~0.9s of continuous green-ready before auto-capture (mirrors liveness consecutivePasses). */
const READY_STABLE_TICKS = 4;
/** Prefer a sharper frame than the live green gate before auto-firing. */
const AUTO_CAPTURE_MIN_SHARPNESS = 40;
const CAPTURE_JPEG_QUALITY = 0.95;
const readyCaptureHint = "Ready — hold steady (auto-captures) or tap Capture";
const holdingSteadyHint = "Hold steady — auto-capturing…";
const needSharperHint = "Almost — hold still for a sharper shot";
const readyBackHint = "Ready — tap Capture (back is manual only)";
const manualFrontHint = "Frame not green — tap Capture anyway when the ID looks clear";
const manualBackHint = "Frame not green — tap Capture anyway when the back looks clear";
const ocrUnavailableHint = "OCR unavailable — retry health before capturing";
const readingFrontHint = "Reading front ID…";
const checkingFrontHint = "Checking name & student ID…";
const frontVerifiedHint = "Front verified — flip to back";
/** Auto-navigate after successful both-sides submit (lets milestone overlay show). */
const EXIT_AUTO_REDIRECT_MS = 1100;

/** Vault polls/decodes QR for staff flags; never hard-blocks submit (same soft spirit as onboarding). */
const enableQrPoll = computed(() => props.mode === "vault");
const resolvedVerifiedLabel = computed(
  () => props.verifiedLabel ?? (props.mode === "vault" ? "Back to documents" : "Continue to liveness"),
);
const resolvedVerifiedHint = computed(
  () =>
    props.verifiedHint ??
    (props.mode === "vault"
      ? "Returning to documents… or tap Back now"
      : "Redirecting to liveness… or tap Continue now"),
);

const successOverlay = useSuccessOverlay();
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
const phase = ref<ScanPhase>("live");
const captureReady = ref(false);
/** Both-sides submit succeeded — show Continue and auto-redirect. */
const idScanVerified = ref(false);
const liveHint = ref(
  props.mode === "vault"
    ? "Front: green auto-captures. Front OCR must pass before back. QR on the back is optional — retake if missing."
    : "Front: green auto-captures, or tap Capture anytime. Front OCR must pass before back. Back is tap Capture only.",
);
const frontBlob = ref<Blob | null>(null);
const frontFace = ref<FaceCropResult | null>(null);
const frontOcrPassed = ref(false);
const backBlob = ref<Blob | null>(null);
const frontPreviewUrl = ref("");
const backPreviewUrl = ref("");
const flipBanner = ref("");
const ocrHealthOk = ref<boolean | null>(null);
const ocrHealthChecking = ref(false);
const ocrHealthMessage = ref("");
const qrBanner = ref("");
const lastQr = ref("");
const guideOpen = ref(false);
let stream: MediaStream | null = null;
let detectTimer: ReturnType<typeof setInterval> | null = null;
let qrTimer: ReturnType<typeof setInterval> | null = null;
let detectBusy = false;
let lastFaceBox: FaceBox | null = null;
/** Consecutive green-ready ticks (reset when alignment drops). */
let consecutiveReadyTicks = 0;
/** Peak sharpness while green — used so auto-capture prefers a crisp frame. */
let bestReadySharpness = 0;
/** Debounce: prevent double auto-capture while captureSide is in flight. */
let autoCapturePending = false;
let exitRedirectTimer: ReturnType<typeof setTimeout> | null = null;

const defaultFrontHint =
  "Fill the frame with the FRONT of your School ID (photo side). Green auto-captures; you can tap Capture anytime.";
const defaultBackHint = computed(() =>
  props.mode === "vault"
    ? "Flip the ID — fill the frame with the back (QR side), then tap Capture. QR optional — retake back if missing."
    : "Flip the ID — fill the frame, then tap Capture (no auto). Green helps; Capture works without it.",
);

function resetReadyHold() {
  consecutiveReadyTicks = 0;
  bestReadySharpness = 0;
}

/**
 * Front only: after green for READY_STABLE_TICKS and sharpness is good enough, auto-fire.
 * Back never auto-captures (flip motion used to fire on the wrong/blurry side).
 */
function maybeAutoCapture(sharpness: number) {
  if (side.value !== "front") {
    resetReadyHold();
    return;
  }
  if (
    !captureReady.value ||
    busy.value ||
    autoCapturePending ||
    phase.value !== "live" ||
    ocrHealthOk.value === false
  ) {
    if (!captureReady.value) resetReadyHold();
    return;
  }

  consecutiveReadyTicks += 1;
  bestReadySharpness = Math.max(bestReadySharpness, sharpness);

  const stable = consecutiveReadyTicks >= READY_STABLE_TICKS;
  const sharpEnough = bestReadySharpness >= AUTO_CAPTURE_MIN_SHARPNESS;

  if (stable && sharpEnough) {
    autoCapturePending = true;
    resetReadyHold();
    liveHint.value = "Capturing…";
    void captureSide().finally(() => {
      autoCapturePending = false;
      resetReadyHold();
    });
    return;
  }

  if (stable && !sharpEnough) {
    liveHint.value = needSharperHint;
  } else if (consecutiveReadyTicks >= 2) {
    liveHint.value = holdingSteadyHint;
  } else {
    liveHint.value = readyCaptureHint;
  }
}

onMounted(async () => {
  await nextTick();
  void checkOcrHealth();
  await Promise.all([ensureModels(), startCamera()]);
  if (enableQrPoll.value) startQrPoll();
});

onBeforeUnmount(() => {
  clearExitRedirect();
  stopDetectLoop();
  stopQrPoll();
  stopCamera();
  revokePreview("front");
  revokePreview("back");
  successOverlay.hide();
});

watch([cameraReady, modelsReady, side, phase], () => {
  if (phase.value !== "live" || !cameraReady.value) {
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

const bothSidesSaved = computed(
  () => Boolean(frontBlob.value && frontFace.value && backBlob.value),
);

const canCapture = computed(() => {
  if (phase.value !== "live" || !cameraReady.value || busy.value) return false;
  if (ocrHealthOk.value === false) return false;
  if (side.value === "front") return modelsReady.value;
  return true;
});

const canReadId = computed(() => {
  if (idScanVerified.value) return false;
  if (phase.value !== "review" || busy.value) return false;
  if (ocrHealthOk.value === false) return false;
  return bothSidesSaved.value;
});

const primaryLabel = computed(() => {
  if (idScanVerified.value) return resolvedVerifiedLabel.value;
  if (busy.value) return statusLabel.value || "Working…";
  if (ocrHealthOk.value === false) return "OCR unavailable — retry health";
  if (phase.value === "review") return "Read ID";
  if (side.value === "front") {
    if (!modelsReady.value) return "Waiting for face models…";
    if (!captureReady.value) return "Capture front anyway";
    return "Capture front (or wait for auto)";
  }
  if (!captureReady.value) return "Capture back anyway";
  return "Capture back";
});

const primaryDisabled = computed(() => {
  if (idScanVerified.value) return busy.value;
  if (phase.value === "review") return !canReadId.value;
  return !canCapture.value;
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

function revokePreview(which: CaptureSide) {
  if (which === "front" && frontPreviewUrl.value) {
    URL.revokeObjectURL(frontPreviewUrl.value);
    frontPreviewUrl.value = "";
  }
  if (which === "back" && backPreviewUrl.value) {
    URL.revokeObjectURL(backPreviewUrl.value);
    backPreviewUrl.value = "";
  }
}

function setPreview(which: CaptureSide, blob: Blob) {
  revokePreview(which);
  const url = URL.createObjectURL(blob);
  if (which === "front") frontPreviewUrl.value = url;
  else backPreviewUrl.value = url;
}

async function checkOcrHealth() {
  ocrHealthChecking.value = true;
  try {
    const response = await fetch(apiUrl("/api/student/identity-onboarding/ocr-health"), {
      headers: { Accept: "application/json" },
      credentials: "include",
    });
    const payload = await response.json();
    const ok = Boolean(payload?.data?.ok);
    ocrHealthOk.value = ok;
    ocrHealthMessage.value = ok
      ? ""
      : String(payload?.data?.message || "Local OCR (:8081) is unavailable");
  } catch {
    ocrHealthOk.value = false;
    ocrHealthMessage.value = "Local OCR (:8081) is unavailable";
  } finally {
    ocrHealthChecking.value = false;
  }
}

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
        // Higher ideal → sharper OCR stills on modern phones (browser may downscale).
        width: { ideal: 1440 },
        height: { ideal: 1920 },
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

function startQrPoll() {
  stopQrPoll();
  qrTimer = setInterval(pollQr, 700);
}

function stopQrPoll() {
  if (qrTimer) {
    clearInterval(qrTimer);
    qrTimer = null;
  }
}

function pollQr() {
  if (!enableQrPoll.value || !video.value || busy.value || phase.value !== "live") return;
  const payload = decodeQrFromVideo(video.value);
  if (!payload) return;
  lastQr.value = payload;
  qrBanner.value = isTccRegistrarQr(payload)
    ? "TCC registrar QR detected"
    : "QR found (non-TCC) — still optional";
}

function startDetectLoop() {
  stopDetectLoop();
  resetReadyHold();
  detectTimer = setInterval(() => {
    void tickGuide();
  }, DETECT_MS);
}

function stopDetectLoop() {
  if (detectTimer) {
    clearInterval(detectTimer);
    detectTimer = null;
  }
  detectBusy = false;
  resetReadyHold();
}

async function tickGuide() {
  if (detectBusy || busy.value || autoCapturePending || phase.value !== "live") return;
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
        captureReady.value = false;
        resetReadyHold();
        liveHint.value = quality.reason || defaultFrontHint;
        return;
      }

      captureReady.value = hit.ready;
      if (ocrHealthOk.value === false) {
        error.value = "";
        resetReadyHold();
        liveHint.value = ocrUnavailableHint;
        return;
      }
      if (captureReady.value) {
        maybeAutoCapture(quality.sharpness);
      } else if (hit.inGuide && hit.fillRatio < 0.48) {
        resetReadyHold();
        liveHint.value = "Move closer — or tap Capture if the ID already looks clear";
      } else if (hit.detected) {
        resetReadyHold();
        liveHint.value = "Center the photo face — or tap Capture anyway";
      } else {
        resetReadyHold();
        liveHint.value = manualFrontHint;
      }
      return;
    }

    // Back: green only with a card-like rectangle in frame (strict fill — no face gate).
    const fill = estimateIdFillInGuide(video.value, guideEl.value, { strict: true });
    if (!quality.ok) {
      captureReady.value = false;
      resetReadyHold();
      liveHint.value = quality.reason || defaultBackHint.value;
      return;
    }

    captureReady.value = fill.filled;
    if (ocrHealthOk.value === false) {
      error.value = "";
      resetReadyHold();
      liveHint.value = ocrUnavailableHint;
      return;
    }
    // Back: never auto-capture — flip / motion would fire on the wrong side.
    resetReadyHold();
    if (captureReady.value) {
      liveHint.value = readyBackHint;
    } else if (fill.fillRatio > 0.4 || fill.edgeSides >= 2) {
      liveHint.value = "Center the ID card — or tap Capture if the back already looks clear";
    } else {
      liveHint.value = manualBackHint;
    }
  } catch (exception) {
    captureReady.value = false;
    resetReadyHold();
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

function setCameraTracksEnabled(enabled: boolean) {
  stream?.getVideoTracks().forEach((track) => {
    track.enabled = enabled;
  });
}

function enterReview() {
  phase.value = "review";
  captureReady.value = false;
  flipBanner.value = "";
  autoCapturePending = false;
  resetReadyHold();
  stopDetectLoop();
  setCameraTracksEnabled(false);
  liveHint.value = "Both sides saved. Reading ID…";
}

function clearExitRedirect() {
  if (exitRedirectTimer) {
    clearTimeout(exitRedirectTimer);
    exitRedirectTimer = null;
  }
}

function scheduleExitRedirect() {
  clearExitRedirect();
  exitRedirectTimer = setTimeout(() => {
    exitRedirectTimer = null;
    void goExit();
  }, EXIT_AUTO_REDIRECT_MS);
}

async function goExit() {
  clearExitRedirect();
  await props.exitFlow();
}

function retakeFront() {
  clearExitRedirect();
  idScanVerified.value = false;
  frontBlob.value = null;
  frontFace.value = null;
  frontOcrPassed.value = false;
  revokePreview("front");
  side.value = "front";
  phase.value = "live";
  captureReady.value = false;
  lastFaceBox = null;
  autoCapturePending = false;
  resetReadyHold();
  flipBanner.value = "";
  qrBanner.value = "";
  error.value = "";
  statusLabel.value = "";
  liveHint.value = defaultFrontHint;
  setCameraTracksEnabled(true);
  void video.value?.play().catch(() => undefined);
}

function retakeBack() {
  clearExitRedirect();
  idScanVerified.value = false;
  backBlob.value = null;
  revokePreview("back");
  side.value = "back";
  phase.value = "live";
  captureReady.value = false;
  lastFaceBox = null;
  autoCapturePending = false;
  resetReadyHold();
  flipBanner.value = frontBlob.value
    ? props.mode === "vault"
      ? "Flip your ID to the back — QR optional; retake if missing, then tap Capture"
      : "Flip your ID to the back — tap Capture when ready (green optional)"
    : "";
  qrBanner.value = "";
  error.value = "";
  statusLabel.value = "";
  liveHint.value = defaultBackHint.value;
  setCameraTracksEnabled(true);
  void video.value?.play().catch(() => undefined);
}

function resetAll() {
  retakeFront();
  backBlob.value = null;
  revokePreview("back");
}

async function resolveQr(front: Blob, back: Blob): Promise<string | null> {
  let qr = lastQr.value.trim();
  if (qr) return qr;

  const fromBack = await decodeQrFromBlob(back);
  if (fromBack) {
    lastQr.value = fromBack;
    return fromBack;
  }

  const fromFront = await decodeQrFromBlob(front);
  if (fromFront) {
    lastQr.value = fromFront;
    return fromFront;
  }

  return null;
}

async function captureSide() {
  if (!video.value || !guideEl.value || phase.value !== "live") return;
  if (busy.value) return;
  if (ocrHealthOk.value === false) {
    error.value = "";
    liveHint.value = ocrUnavailableHint;
    return;
  }
  if (side.value === "front" && !modelsReady.value) {
    error.value = "Face models are still loading — wait a moment, then capture.";
    return;
  }

  const capturingWithoutGreen = !captureReady.value;
  error.value = "";
  busy.value = true;
  resetReadyHold();
  statusLabel.value = "Capturing…";
  liveHint.value = capturingWithoutGreen
    ? "Capturing (frame was not green)…"
    : "Capturing…";
  try {
    if (side.value === "front") {
      const hintBox = lastFaceBox;
      // Start face crop in parallel, but leave "Capturing…" as soon as the still is saved.
      const facePromise = cropFaceFromVideoWithTimeout(video.value, hintBox, FACE_CROP_TIMEOUT_MS);
      const frameBlob = await captureGuideVideoFrame(video.value, guideEl.value, CAPTURE_JPEG_QUALITY);

      statusLabel.value = readingFrontHint;
      liveHint.value = checkingFrontHint;

      const stillQuality = await analyzeBlobQuality(frameBlob);
      if (!stillQuality.ok) {
        void facePromise.catch(() => undefined);
        const reason = stillQuality.reason || "Photo quality too low — retake.";
        toast.error(reason);
        error.value = reason;
        liveHint.value = reason;
        statusLabel.value = "";
        return;
      }

      frontBlob.value = frameBlob;
      setPreview("front", frameBlob);

      const face = await facePromise;
      frontFace.value = face;
      frontOcrPassed.value = false;

      statusLabel.value = readingFrontHint;
      liveHint.value = checkingFrontHint;
      await props.validateFront(frameBlob);

      frontOcrPassed.value = true;
      statusLabel.value = "";
      // Hold the milestone overlay briefly before unlocking back (parity with liveness).
      await successOverlay.show(frontVerifiedHint);
      side.value = "back";
      captureReady.value = false;
      lastFaceBox = null;
      resetReadyHold();
      liveHint.value = defaultBackHint.value;
      flipBanner.value = frontVerifiedHint;
      return;
    }

    if (!frontBlob.value || !frontFace.value || !frontOcrPassed.value) {
      resetAll();
      throw new Error("Front capture missing or not verified. Capture the front of your School ID first.");
    }

    const frameBlob = await captureGuideVideoFrame(video.value, guideEl.value, CAPTURE_JPEG_QUALITY);
    const stillQuality = await analyzeBlobQuality(frameBlob);
    if (!stillQuality.ok) {
      const reason = stillQuality.reason || "Photo quality too low — retake.";
      toast.error(reason);
      error.value = reason;
      liveHint.value = reason;
      return;
    }

    backBlob.value = frameBlob;
    setPreview("back", frameBlob);
    enterReview();
    void successOverlay.show("Back saved — reading ID…");
    // Final submit: front + back + face (+ optional QR). Read ID remains for retry.
    await readId();
    return;
  } catch (exception) {
    if (side.value === "front" || !frontOcrPassed.value) {
      // Stay on front and force retake after OCR / face / quality failures.
      frontBlob.value = null;
      frontFace.value = null;
      frontOcrPassed.value = false;
      revokePreview("front");
      side.value = "front";
      phase.value = "live";
      flipBanner.value = "";
      setCameraTracksEnabled(true);
      void video.value?.play().catch(() => undefined);
    }
    error.value = exception instanceof Error ? exception.message : "Capture failed.";
    toast.error(error.value);
    statusLabel.value = "";
    liveHint.value = side.value === "front" ? defaultFrontHint : defaultBackHint.value;
  } finally {
    // Keep busy/status if verify succeeded and we are redirecting — readId clears them.
    if (!idScanVerified.value) {
      busy.value = false;
      if (phase.value === "live") statusLabel.value = "";
    }
  }
}

async function readId() {
  if (idScanVerified.value) {
    void goExit();
    return;
  }
  if (!frontBlob.value || !frontFace.value || !backBlob.value) {
    error.value = "Save both front and back photos before reading the ID.";
    return;
  }
  if (ocrHealthOk.value === false) {
    error.value = "";
    liveHint.value = ocrUnavailableHint;
    return;
  }

  error.value = "";
  busy.value = true;
  statusLabel.value = "Reading ID…";
  liveHint.value = "Reading ID…";
  try {
    await submitScan(frontBlob.value, frontFace.value, backBlob.value);
  } catch (exception) {
    // Stay on review with both stills — do not restart live auto-capture.
    phase.value = "review";
    stopDetectLoop();
    idScanVerified.value = false;
    error.value = exception instanceof Error ? exception.message : "ID scan failed.";
    toast.error(error.value);
    const mismatch = /does not match|Expected \d|name is unreadable/i.test(error.value);
    liveHint.value = mismatch
      ? "Front OCR unclear — retake the front (Capture works without green), then tap Read ID again."
      : "OCR failed. Retake a side or tap Read ID again.";
  } finally {
    if (!idScanVerified.value) {
      busy.value = false;
      statusLabel.value = "";
    }
  }
}

async function submitScan(front: Blob, face: FaceCropResult, back: Blob) {
  statusLabel.value = "Reading ID…";
  const qr = await resolveQr(front, back);

  if (qr) {
    qrBanner.value = isTccRegistrarQr(qr) ? "TCC registrar QR ready" : "QR found (non-TCC) — optional";
  } else {
    qrBanner.value =
      props.mode === "vault"
        ? "QR optional — retake back if missing"
        : "QR not found";
  }

  const result = await props.submitCapture({ front, back, face, qr });

  if (typeof result?.qrFound === "boolean") {
    qrBanner.value = result.qrFound
      ? "QR read"
      : props.mode === "vault"
        ? "QR optional — retake back if missing"
        : "QR not found";
    if (result.qrFound) {
      toast.info("QR read", { description: "School ID QR decoded (non-blocking)." });
    } else {
      toast.info("QR not found", {
        description:
          props.mode === "vault"
            ? "You can continue — QR is optional; staff can review or return."
            : "You can continue — QR is optional for onboarding.",
      });
    }
  }

  idScanVerified.value = true;
  busy.value = false;
  statusLabel.value = "";
  phase.value = "review";
  stopDetectLoop();
  setCameraTracksEnabled(false);
  const milestone =
    props.mode === "vault" ? "School ID verified" : "School ID verified — continue to liveness";
  liveHint.value = milestone;
  void successOverlay.show(milestone);
  scheduleExitRedirect();
}

function onPrimaryClick() {
  if (idScanVerified.value) {
    void goExit();
    return;
  }
  if (phase.value === "review") {
    void readId();
    return;
  }
  void captureSide();
}
</script>

<template>
  <div class="space-y-3 sm:space-y-5">
    <PageHeader :title="title" :description="description" />

    <section class="rounded-2xl border bg-surface p-3 shadow-sm sm:p-5">
      <div
        v-if="ocrHealthOk === false"
        class="mb-2 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-amber-400/50 bg-amber-500/15 px-3 py-2 text-xs text-amber-950 dark:text-amber-100 sm:mb-3 sm:px-4 sm:py-3 sm:text-sm"
        role="alert"
      >
        <span class="flex items-center gap-2 font-medium">
          <IconAlertTriangle :size="16" />
          {{ ocrHealthMessage || "Local OCR (:8081) is unavailable" }}
        </span>
        <button
          class="inline-flex h-8 items-center gap-1.5 rounded-md border border-amber-500/40 bg-surface px-3 text-xs font-medium"
          type="button"
          :disabled="ocrHealthChecking"
          @click="checkOcrHealth"
        >
          <IconRefresh :size="14" /> {{ ocrHealthChecking ? "Checking…" : "Retry" }}
        </button>
      </div>
      <p v-else-if="ocrHealthChecking" class="mb-2 text-xs text-text-muted sm:mb-3">Checking local OCR health…</p>

      <div
        v-if="flipBanner"
        class="mb-2 rounded-lg border border-emerald-400/40 bg-emerald-500/15 px-3 py-2 text-center text-xs font-semibold text-emerald-800 dark:text-emerald-200 sm:mb-3 sm:px-4 sm:py-3 sm:text-sm"
        role="status"
      >
        {{ flipBanner }}
      </div>

      <div
        v-if="qrBanner"
        class="mb-2 rounded-lg border bg-surface-muted px-3 py-1.5 text-center text-xs font-medium text-text-muted sm:mb-3 sm:px-4 sm:py-2"
        role="status"
      >
        {{ qrBanner }}
      </div>

      <!-- Compact header: side title + restart + info -->
      <div class="mb-2 flex items-center justify-between gap-2 sm:mb-3">
        <p class="flex min-w-0 items-center gap-2 text-sm font-semibold">
          <IconId :size="16" class="shrink-0 text-primary" />
          <span class="truncate">
            <template v-if="idScanVerified">School ID verified</template>
            <template v-else-if="phase === 'review'">Both sides saved</template>
            <template v-else>{{ side === "front" ? "Front of School ID" : "Back of School ID" }}</template>
          </span>
          <span class="shrink-0 rounded-md bg-surface-muted px-2 py-0.5 text-xs font-medium text-text-muted">
            <template v-if="idScanVerified">Verified</template>
            <template v-else-if="phase === 'review'">Ready</template>
            <template v-else>{{ side === "front" ? "1 / 2" : "2 / 2" }}</template>
          </span>
        </p>
        <div class="flex shrink-0 items-center gap-1.5">
          <button
            v-if="frontBlob && !idScanVerified"
            class="inline-flex h-8 items-center rounded-md border px-2 text-xs sm:px-3 disabled:opacity-50 disabled:cursor-not-allowed transition-opacity"
            type="button"
            :disabled="busy"
            @click="retakeFront"
          >
            Retake front
          </button>
          <button
            v-if="backBlob && !idScanVerified"
            class="inline-flex h-8 items-center rounded-md border px-2 text-xs sm:px-3 disabled:opacity-50 disabled:cursor-not-allowed transition-opacity"
            type="button"
            :disabled="busy"
            @click="retakeBack"
          >
            Retake back
          </button>
          <button
            v-if="phase === 'live'"
            class="inline-flex h-8 items-center gap-1.5 rounded-md border px-2 text-xs sm:px-3 disabled:opacity-50 disabled:cursor-not-allowed transition-opacity"
            type="button"
            title="Restart camera"
            :disabled="busy"
            @click="startCamera"
          >
            <IconRefresh :size="14" />
            <span class="hidden sm:inline">Restart camera</span>
          </button>
          <button
            class="inline-flex h-8 w-8 items-center justify-center rounded-md border text-primary disabled:opacity-50 disabled:cursor-not-allowed"
            type="button"
            aria-label="Scan guidelines"
            title="Scan guidelines"
            :disabled="busy"
            @click="guideOpen = true"
          >
            <IconInfoCircle :size="18" />
          </button>
        </div>
      </div>

      <!-- Compact saved stills (thumbnails) — keep camera tall on phones -->
      <div
        v-if="frontPreviewUrl || backPreviewUrl"
        class="mb-2 flex gap-2 sm:mb-3"
        :class="phase === 'review' ? 'sm:grid sm:grid-cols-2 sm:gap-3' : ''"
      >
        <figure
          v-if="frontPreviewUrl"
          class="overflow-hidden rounded-lg border bg-surface-muted"
          :class="phase === 'review' ? 'flex-1 sm:block' : 'w-16 shrink-0 sm:w-20'"
        >
          <img
            :src="frontPreviewUrl"
            alt="Saved front of School ID"
            class="aspect-[3/4] w-full object-cover"
          />
          <figcaption
            class="px-1 py-1 text-center text-[10px] font-medium text-text-muted sm:px-2 sm:text-xs"
            :class="phase === 'live' ? 'hidden' : ''"
          >
            Front saved
          </figcaption>
        </figure>
        <figure
          v-if="backPreviewUrl"
          class="overflow-hidden rounded-lg border bg-surface-muted"
          :class="phase === 'review' ? 'flex-1 sm:block' : 'w-16 shrink-0 sm:w-20'"
        >
          <img
            :src="backPreviewUrl"
            alt="Saved back of School ID"
            class="aspect-[3/4] w-full object-cover"
          />
          <figcaption
            class="px-1 py-1 text-center text-[10px] font-medium text-text-muted sm:px-2 sm:text-xs"
            :class="phase === 'live' ? 'hidden' : ''"
          >
            Back saved
          </figcaption>
        </figure>
      </div>

      <!-- Live camera — tall viewport so Capture stays on-screen -->
      <div
        v-show="phase === 'live'"
        class="relative mx-auto w-full max-w-md overflow-hidden rounded-xl border bg-black"
      >
        <video
          ref="video"
          class="aspect-[3/4] h-auto max-h-[min(62dvh,36rem)] min-h-[16rem] w-full object-cover"
          autoplay
          playsinline
          muted
        />
        <div class="pointer-events-none absolute inset-0 flex items-center justify-center p-[3%]">
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

      <div
        v-if="phase === 'review'"
        class="rounded-lg border border-emerald-400/30 bg-emerald-500/10 px-4 py-3 text-center text-sm text-emerald-900 dark:text-emerald-100"
        role="status"
      >
        <p class="font-medium">{{ liveHint }}</p>
        <p v-if="busy && statusLabel" class="mt-1 text-xs">{{ statusLabel }}</p>
        <p v-else-if="idScanVerified" class="mt-1 text-xs opacity-90">
          {{ resolvedVerifiedHint }}
        </p>
      </div>

      <p v-if="modelsError" class="mt-2 rounded-md border border-danger/30 bg-danger-soft p-2 text-xs text-danger sm:mt-3 sm:p-3">
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
      <p v-else-if="modelsLoading" class="mt-2 text-xs text-text-muted sm:mt-3">Loading face detection models…</p>

      <p v-if="error" class="mt-2 rounded-md border border-danger/30 bg-danger-soft p-2 text-xs text-danger sm:mt-3 sm:p-3">
        <IconAlertTriangle :size="14" class="mr-1 inline" /> {{ error }}
      </p>

      <!-- Sticky Capture / Read ID / Continue — visible without scrolling on typical phones -->
      <div
        class="sticky bottom-0 z-10 -mx-3 mt-3 border-t bg-surface/95 px-3 pt-3 backdrop-blur supports-[backdrop-filter]:bg-surface/90 sm:-mx-5 sm:px-5"
        style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom))"
      >
        <button
          class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-md bg-primary px-4 text-sm font-medium text-white disabled:opacity-50 sm:w-auto"
          type="button"
          :disabled="primaryDisabled"
          @click="onPrimaryClick"
        >
          <IconCheck v-if="idScanVerified" :size="16" />
          <IconCamera v-else :size="16" />
          {{ primaryLabel }}
        </button>
      </div>
    </section>

    <AppDialog
      v-model="guideOpen"
      title="School ID scan tips"
      :description="
        mode === 'vault'
          ? 'How front, back, OCR, and optional TCC QR work on this step.'
          : 'How front, back, OCR, and QR work on this step.'
      "
      size="sm"
    >
      <ul class="space-y-3 text-sm text-text-muted">
        <li class="flex gap-2">
          <IconCheck :size="16" class="mt-0.5 shrink-0 text-success" />
          <span>Front: Capture always available; green auto-captures after ~1s steady hold.</span>
        </li>
        <li class="flex gap-2">
          <IconCheck :size="16" class="mt-0.5 shrink-0 text-success" />
          <span>Front OCR must pass (name &amp; student ID) before the back step unlocks.</span>
        </li>
        <li class="flex gap-2">
          <IconCheck :size="16" class="mt-0.5 shrink-0 text-success" />
          <span>Back: tap Capture anytime (green optional; never auto).</span>
        </li>
        <li class="flex gap-2">
          <IconId :size="16" class="mt-0.5 shrink-0 text-primary" />
          <span v-if="mode === 'vault'">
            After both sides, Read ID verifies front OCR and face match. QR is best-effort (staff can return if missing).
          </span>
          <span v-else>After both sides, Read ID re-checks front + runs back OCR. QR is best-effort.</span>
        </li>
      </ul>
      <template #footer="{ close }">
        <button class="rounded-md bg-primary px-4 py-2 text-xs font-medium text-white" type="button" @click="close">
          Got it
        </button>
      </template>
    </AppDialog>
  </div>
</template>
