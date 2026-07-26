<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { IconAlertTriangle, IconCamera, IconCheck, IconId, IconRefresh } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import { getAuthToken } from "@/auth/session";
import { toast } from "@/composables/useToast";
import { captureVideoFrame, cropFaceFromImage } from "@/modules/requirements/faceApi";
import { decodeQrFromBlob, decodeQrFromVideo, isTccRegistrarQr } from "@/modules/requirements/idQr";

const router = useRouter();
const video = ref<HTMLVideoElement | null>(null);
const cameraReady = ref(false);
const busy = ref(false);
const error = ref("");
const qrHint = ref("Hold your School ID steady inside the card frame.");
const lastQr = ref("");
let stream: MediaStream | null = null;
let scanTimer: number | null = null;

onMounted(async () => {
  await nextTick();
  await startCamera();
  scanTimer = window.setInterval(pollQr, 700);
});

onBeforeUnmount(() => {
  if (scanTimer) window.clearInterval(scanTimer);
  stopCamera();
});

const canCapture = computed(() => cameraReady.value && !busy.value);

async function startCamera() {
  stopCamera();
  cameraReady.value = false;
  error.value = "";
  try {
    stream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: { ideal: "environment" }, width: { ideal: 1280 }, height: { ideal: 720 } },
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

function pollQr() {
  if (!video.value || busy.value) return;
  const payload = decodeQrFromVideo(video.value);
  if (!payload) {
    qrHint.value = "Searching for School ID QR…";
    return;
  }
  lastQr.value = payload;
  qrHint.value = isTccRegistrarQr(payload)
    ? "TCC registrar QR detected — ready to capture."
    : "QR found but domain is not TCC registrar. Adjust the card.";
}

async function captureAndSubmit() {
  if (!video.value) return;
  busy.value = true;
  error.value = "";
  try {
    const frameBlob = await captureVideoFrame(video.value, 0.92);
    const qrPayload = lastQr.value || (await decodeQrFromBlob(frameBlob));
    if (!qrPayload || !isTccRegistrarQr(qrPayload)) {
      throw new Error("Valid TCC registrar QR code not found on the ID. Retry with the QR visible.");
    }

    const face = await cropFaceFromImage(new File([frameBlob], "id_frame.jpg", { type: "image/jpeg" }));
    const body = new FormData();
    body.append("id_frame", new File([frameBlob], "id_onboarding_frame.jpg", { type: "image/jpeg" }));
    body.append("id_face_crop", new File([face.blob], "id_reference_face.jpg", { type: "image/jpeg" }));
    body.append("qr_payload", qrPayload);
    body.append("face_quality_score", String(face.quality));
    body.append("authenticity_skipped", "1");

    const response = await fetch("/api/student/identity-onboarding/id-scan", {
      method: "POST",
      headers: { Authorization: `Bearer ${getAuthToken()}`, Accept: "application/json" },
      body,
    });
    const payload = await response.json();
    if (!response.ok) {
      const validation = payload.errors ? Object.values(payload.errors).flat().join(" ") : "";
      throw new Error(validation || payload.message || "ID scan failed.");
    }

    toast.success("School ID verified — continue to liveness");
    await router.push("/student/onboarding/liveness");
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "ID scan failed.";
    toast.error(error.value);
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <div class="space-y-5">
    <PageHeader
      title="Onboarding — Scan School ID"
      description="Step 2 of 3. Align your physical School ID inside the card frame. QR, OCR, and face crop run on capture."
    />

    <section class="rounded-2xl border bg-surface p-4 shadow-sm sm:p-5">
      <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <p class="flex items-center gap-2 text-sm font-semibold">
          <IconId :size="16" class="text-primary" /> Live ID scan
        </p>
        <button class="inline-flex h-8 items-center gap-2 rounded-md border px-3 text-xs" type="button" @click="startCamera">
          <IconRefresh :size="14" /> Restart camera
        </button>
      </div>

      <div class="relative mx-auto max-w-3xl overflow-hidden rounded-xl border bg-black">
        <video ref="video" class="aspect-[4/3] w-full object-cover" autoplay playsinline muted />
        <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
          <div class="h-[58%] w-[78%] max-w-xl rounded-xl border-2 border-white/80 shadow-[0_0_0_9999px_rgba(0,0,0,0.35)]" />
        </div>
        <p class="absolute bottom-3 left-3 right-3 rounded-md bg-black/55 px-3 py-2 text-center text-xs text-white">
          {{ qrHint }}
        </p>
      </div>

      <p v-if="error" class="mt-3 rounded-md border border-danger/30 bg-danger-soft p-3 text-xs text-danger">
        <IconAlertTriangle :size="14" class="mr-1 inline" /> {{ error }}
      </p>

      <ul class="mt-4 grid gap-2 text-xs text-text-muted sm:grid-cols-2">
        <li class="rounded-md border p-3"><IconCheck :size="14" class="mr-1 inline text-success" /> jsQR verifies TCC registrar domain</li>
        <li class="rounded-md border p-3"><IconCheck :size="14" class="mr-1 inline text-success" /> OCR matches name &amp; student ID to KYC/masterlist</li>
        <li class="rounded-md border p-3"><IconCheck :size="14" class="mr-1 inline text-success" /> face-api crops reference face</li>
        <li class="rounded-md border p-3"><IconAlertTriangle :size="14" class="mr-1 inline text-warning" /> Pillow moiré check stubbed server-side</li>
      </ul>

      <button
        class="mt-4 inline-flex h-10 items-center gap-2 rounded-md bg-primary px-4 text-sm font-medium text-white disabled:opacity-50"
        type="button"
        :disabled="!canCapture"
        @click="captureAndSubmit"
      >
        <IconCamera :size="16" /> {{ busy ? "Verifying…" : "Capture & verify ID" }}
      </button>
    </section>
  </div>
</template>
