<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { IconAlertTriangle, IconCamera, IconRefresh, IconUserCheck } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import { authSession, getAuthToken } from "@/auth/session";
import { toast } from "@/composables/useToast";
import { getUserMediaSafe } from "@/modules/requirements/cameraAccess";
import {
  captureVideoFrame,
  descriptorFromUrl,
  descriptorFromVideo,
  detectChallenge,
  euclideanDistance,
  shuffleChallenges,
  type Challenge,
} from "@/modules/requirements/faceApi";

const router = useRouter();
const video = ref<HTMLVideoElement | null>(null);
const cameraReady = ref(false);
const busy = ref("");
const error = ref("");
const challengeMessage = ref("");
const challengeSequence = ref<Challenge[]>([]);
const challengeIndex = ref(0);
const referenceUrl = ref<string | null>(null);
let stream: MediaStream | null = null;

const challengeLabels: Record<Challenge, string> = {
  blink: "Blink",
  turn_left: "Turn left",
  turn_right: "Turn right",
};

onMounted(async () => {
  challengeSequence.value = shuffleChallenges();
  try {
    await loadStatus();
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "Unable to load onboarding status.";
    toast.error(error.value);
    return;
  }
  await nextTick();
  await startCamera();
});

onBeforeUnmount(stopCamera);

async function loadStatus() {
  const token = getAuthToken();
  if (!token) {
    throw new Error("Unauthenticated. Activate or sign in again to continue liveness.");
  }
  const response = await fetch("/api/student/identity-onboarding", {
    headers: { Accept: "application/json", Authorization: `Bearer ${token}` },
  });
  const payload = await response.json();
  if (!response.ok) throw new Error(payload.message || "Unable to load onboarding status.");
  if (payload.data.next_step === "id_scan") {
    await router.replace("/student/onboarding/id-scan");
    return;
  }
  if (payload.data.next_step === "done") {
    await router.replace("/student");
    return;
  }
  referenceUrl.value = payload.data.identity?.id_reference_face_url || null;
}

async function startCamera() {
  stopCamera();
  cameraReady.value = false;
  try {
    stream = await getUserMediaSafe({
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
    const passed = await detectChallenge(video.value, challenge);
    if (!passed) {
      challengeMessage.value = `Movement not detected. Try ${challengeLabels[challenge].toLowerCase()} again.`;
      return;
    }
    if (challengeIndex.value < challengeSequence.value.length - 1) {
      challengeIndex.value += 1;
      challengeMessage.value = "Detected. Continue to the next challenge.";
      return;
    }
    await finishLiveness();
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "Liveness check failed.";
  } finally {
    busy.value = "";
  }
}

async function finishLiveness() {
  if (!video.value || !referenceUrl.value) {
    throw new Error("ID reference face is missing. Re-scan your School ID.");
  }

  busy.value = "match";
  const reference = await descriptorFromUrl(referenceUrl.value);
  const live = await descriptorFromVideo(video.value);
  const distance = euclideanDistance(reference.descriptor, live.descriptor);
  // Descriptors discarded after compare (locals go out of scope).
  const selfie = await captureVideoFrame(video.value, 0.9);

  const body = new FormData();
  body.append("selfie", new File([selfie], "onboarding_selfie.jpg", { type: "image/jpeg" }));
  challengeSequence.value.forEach((step, index) => body.append(`challenge_sequence[${index}]`, step));
  live.descriptor.forEach((value, index) => body.append(`face_descriptor[${index}]`, String(value)));
  body.append("distance", String(distance)); // informational only; server recomputes
  body.append("liveness_confirmed", "1");

  const token = getAuthToken();
  if (!token) {
    throw new Error("Unauthenticated. Activate or sign in again to continue liveness.");
  }
  const response = await fetch("/api/student/identity-onboarding/liveness", {
    method: "POST",
    headers: { Authorization: `Bearer ${token}`, Accept: "application/json" },
    body,
  });
  const payload = await response.json();
  if (!response.ok) {
    const validation = payload.errors ? Object.values(payload.errors).flat().join(" ") : "";
    authSession.user = authSession.user
      ? { ...authSession.user, account_status: payload.data?.account_status || "blocked" }
      : null;
    throw new Error(validation || payload.message || "Face did not match the School ID.");
  }

  authSession.user = authSession.user
    ? { ...authSession.user, account_status: payload.data.account_status || "active" }
    : null;
  stopCamera();
  toast.success("Identity verified — account activated");
  await router.push("/student");
}
</script>

<template>
  <div class="space-y-5">
    <PageHeader
      title="Onboarding — Liveness challenge"
      description="Step 3 of 3. Complete the randomized blink / turn sequence, then your live face is matched to the ID reference."
    />

    <section class="rounded-2xl border bg-surface p-4 shadow-sm sm:p-5">
      <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <p class="flex items-center gap-2 text-sm font-semibold">
          <IconUserCheck :size="16" class="text-primary" /> Front camera liveness
        </p>
        <button class="inline-flex h-8 items-center gap-2 rounded-md border px-3 text-xs" type="button" @click="startCamera">
          <IconRefresh :size="14" /> Restart camera
        </button>
      </div>

      <div class="relative mx-auto max-w-xl overflow-hidden rounded-xl border bg-black">
        <video ref="video" class="aspect-[3/4] w-full object-cover" autoplay playsinline muted />
        <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
          <div class="h-[70%] w-[55%] rounded-[50%] border-2 border-white/75 shadow-[0_0_0_9999px_rgba(0,0,0,0.28)]" />
        </div>
      </div>

      <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <div>
          <p class="text-sm font-semibold">
            {{ challengeSequence[challengeIndex] ? challengeLabels[challengeSequence[challengeIndex]] : "Preparing…" }}
          </p>
          <p class="text-xs text-text-muted">Step {{ challengeIndex + 1 }} of {{ challengeSequence.length || 3 }}</p>
        </div>
        <button
          class="inline-flex h-10 items-center gap-2 rounded-md bg-primary px-4 text-sm font-medium text-white disabled:opacity-50"
          type="button"
          :disabled="!cameraReady || Boolean(busy)"
          @click="checkCurrentChallenge"
        >
          <IconCamera :size="16" />
          {{ busy === "match" ? "Matching face…" : busy === "challenge" ? "Checking…" : "Check challenge" }}
        </button>
      </div>

      <p v-if="challengeMessage" class="mt-3 rounded-md border bg-surface-muted p-3 text-xs text-text-muted">
        {{ challengeMessage }}
      </p>
      <p v-if="error" class="mt-3 rounded-md border border-danger/30 bg-danger-soft p-3 text-xs text-danger">
        <IconAlertTriangle :size="14" class="mr-1 inline" /> {{ error }}
      </p>
    </section>
  </div>
</template>
