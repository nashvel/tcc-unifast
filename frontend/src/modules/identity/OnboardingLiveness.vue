<script setup lang="ts">
import { apiFetch, apiUrl } from "@/api/client";
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { IconAlertTriangle, IconRefresh, IconUserCheck } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import { authSession } from "@/auth/session";
import { useSuccessOverlay } from "@/composables/useSuccessOverlay";
import { toast } from "@/composables/useToast";
import { getUserMediaSafe } from "@/modules/requirements/cameraAccess";
import {
  captureVideoFrame,
  createBlinkTracker,
  descriptorFromVideo,
  detectChallenge,
  faceFrontalInOvalReady,
  faceInOvalReady,
  loadFaceModels,
  resetBlinkTracker,
  shuffleChallenges,
  tickBlinkTracker,
  type Challenge,
} from "@/modules/requirements/faceApi";

type OvalState = "idle" | "ready" | "success";

const LOOP_MS = 280;
const HINT_CENTER_TICKS = 8;
const HINT_SOFT_TICKS = 16;
const HINT_STUCK_TICKS = 36;
/** Hold a stable frontal pose briefly before capturing the match selfie. */
const FRONTAL_READY_TICKS = 2;

const router = useRouter();
const successOverlay = useSuccessOverlay();
const video = ref<HTMLVideoElement | null>(null);
const ovalEl = ref<HTMLElement | null>(null);
const cameraReady = ref(false);
const busy = ref("");
const error = ref("");
const challengeMessage = ref("");
const challengeSequence = ref<Challenge[]>([]);
const challengeIndex = ref(0);
/** Still frames from the first two successful challenges (review evidence). */
const challengeStills = ref<{ label: Challenge; blob: Blob }[]>([]);
const referenceUrl = ref<string | null>(null);
const ovalState = ref<OvalState>("idle");
const finishing = ref(false);
/** After challenges: wait for a front-facing pose before match capture. */
const awaitingFrontal = ref(false);

let stream: MediaStream | null = null;
let challengeTimer: ReturnType<typeof setTimeout> | null = null;
let successFlashTimer: ReturnType<typeof setTimeout> | null = null;
let loopBusy = false;
let consecutivePasses = 0;
let softFailTicks = 0;
let blinkTracker = createBlinkTracker();
let lastChallengeKey: Challenge | null = null;

const challengeLabels: Record<Challenge, string> = {
  blink: "Blink",
  turn_left: "Turn left",
  turn_right: "Turn right",
};

const challengePrompts: Record<Challenge, string> = {
  blink: "Blink now",
  turn_left: "Turn left",
  turn_right: "Turn right",
};

const challengeVerifiedLabels: Record<Challenge, string> = {
  blink: "Blink verified",
  turn_left: "Left turn verified",
  turn_right: "Right turn verified",
};

const currentChallenge = computed(() => challengeSequence.value[challengeIndex.value] ?? null);

const bigInstruction = computed(() => {
  if (busy.value === "match") return "Matching face…";
  if (!cameraReady.value) return "Starting camera…";
  if (awaitingFrontal.value) {
    if (ovalState.value === "success") return "Got it!";
    return "Look straight at the camera";
  }
  if (ovalState.value === "success") return "Got it!";
  if (ovalState.value === "idle") return "Fit your face in the oval";
  if (currentChallenge.value) return challengePrompts[currentChallenge.value];
  return "Preparing…";
});

const fitHint = computed(() => {
  if (!cameraReady.value || busy.value === "match") return "";
  if (awaitingFrontal.value) {
    if (ovalState.value === "success") return "Capturing match photo…";
    if (ovalState.value === "ready") return "Hold still — face the camera";
    return "Center your face, then look straight ahead";
  }
  if (ovalState.value === "success") return "Challenge passed";
  if (ovalState.value === "ready") return "Face fits — keep going";
  return "Move closer until the oval turns green";
});

const ovalBorderClass = computed(() => {
  if (ovalState.value === "success") return "border-emerald-300";
  if (ovalState.value === "ready") return "border-emerald-400";
  return "border-amber-400";
});

const ovalStyle = computed(() => {
  const vignette = "0 0 0 9999px rgba(0, 0, 0, 0.35)";
  if (ovalState.value === "success") {
    return {
      boxShadow: `${vignette}, 0 0 0 4px rgba(52, 211, 153, 0.95), 0 0 28px rgba(16, 185, 129, 0.85)`,
    };
  }
  if (ovalState.value === "ready") {
    return {
      boxShadow: `${vignette}, 0 0 0 4px rgba(52, 211, 153, 0.9), 0 0 24px rgba(16, 185, 129, 0.75)`,
    };
  }
  return {
    boxShadow: `${vignette}, 0 0 0 3px rgba(251, 191, 36, 0.75), 0 0 14px rgba(245, 158, 11, 0.4)`,
  };
});

const loopActive = computed(
  () =>
    cameraReady.value &&
    !finishing.value &&
    busy.value !== "match" &&
    Boolean(referenceUrl.value),
);

onMounted(async () => {
  challengeSequence.value = shuffleChallenges();
  challengeStills.value = [];
  document.addEventListener("visibilitychange", onVisibilityChange);

  // Fast client gate — never start camera while KYC is incomplete.
  const accountStatus = authSession.user?.account_status;
  if (accountStatus === "unverified" || accountStatus === "pending_kyc") {
    await router.replace("/student/kyc");
    return;
  }
  if (authSession.user?.onboarding_next_step && authSession.user.onboarding_next_step !== "liveness") {
    const next = authSession.user.onboarding_next_step;
    if (next === "kyc") {
      await router.replace("/student/kyc");
      return;
    }
    if (next === "id_scan") {
      await router.replace("/student/onboarding/id-scan");
      return;
    }
    if (next === "face_review") {
      await router.replace("/student/onboarding/pending-review");
      return;
    }
    if (next === "done") {
      await router.replace("/student");
      return;
    }
  }

  try {
    const ready = await loadStatus();
    if (!ready) return;
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "Unable to load onboarding status.";
    toast.error(error.value);
    return;
  }
  await nextTick();
  void loadFaceModels().catch(() => {
    /* loop / check will surface model errors if needed */
  });
  await startCamera();
});

onBeforeUnmount(() => {
  document.removeEventListener("visibilitychange", onVisibilityChange);
  stopChallengeLoop();
  if (successFlashTimer) {
    clearTimeout(successFlashTimer);
    successFlashTimer = null;
  }
  successOverlay.hide();
  stopCamera();
});

function onVisibilityChange() {
  if (document.visibilityState !== "visible" || finishing.value) return;
  if (!stream || stream.getTracks().every((track) => track.readyState !== "live")) {
    void startCamera();
  }
}

async function loadStatus(): Promise<boolean> {
  const payload = await apiFetch<{
    data: {
      next_step: string;
      account_status?: string;
      onboarding_path?: string;
      identity?: { id_reference_face_url?: string | null };
    };
  }>("/api/student/identity-onboarding");

  const next = payload.data.next_step as string;
  const accountStatus = payload.data.account_status as string | undefined;
  if (authSession.user && accountStatus) {
    authSession.user = {
      ...authSession.user,
      account_status: accountStatus as typeof authSession.user.account_status,
      onboarding_next_step: next as typeof authSession.user.onboarding_next_step,
      onboarding_path:
        next === "kyc"
          ? "/student/kyc"
          : next === "id_scan"
            ? "/student/onboarding/id-scan"
            : next === "liveness"
              ? "/student/onboarding/liveness"
              : next === "face_review"
                ? "/student/onboarding/pending-review"
                : next === "done"
                  ? "/student"
                  : authSession.user.onboarding_path,
    };
  }

  if (next === "kyc" || accountStatus === "unverified" || accountStatus === "pending_kyc") {
    await router.replace("/student/kyc");
    return false;
  }
  if (next === "id_scan") {
    await router.replace("/student/onboarding/id-scan");
    return false;
  }
  if (next === "done") {
    await router.replace("/student");
    return false;
  }
  if (next === "face_review") {
    await router.replace("/student/onboarding/pending-review");
    return false;
  }
  if (next !== "liveness") {
    await router.replace("/student/onboarding");
    return false;
  }

  referenceUrl.value = payload.data.identity?.id_reference_face_url || null;
  if (!referenceUrl.value) {
    await router.replace("/student/onboarding/id-scan");
    return false;
  }
  return true;
}

async function startCamera() {
  stopChallengeLoop();
  stopCamera();
  cameraReady.value = false;
  ovalState.value = "idle";
  awaitingFrontal.value = false;
  consecutivePasses = 0;
  softFailTicks = 0;
  resetBlinkTracker(blinkTracker);
  lastChallengeKey = null;
  try {
    stream = await getUserMediaSafe({
      video: { facingMode: "user", width: { ideal: 960 }, height: { ideal: 720 } },
      audio: false,
    });
    if (video.value) {
      video.value.srcObject = stream;
      await video.value.play();
      cameraReady.value = true;
      startChallengeLoop();
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

function stopChallengeLoop() {
  if (challengeTimer) {
    clearTimeout(challengeTimer);
    challengeTimer = null;
  }
}

function startChallengeLoop() {
  stopChallengeLoop();
  if (!loopActive.value) return;

  const schedule = () => {
    challengeTimer = window.setTimeout(async () => {
      await runChallengeTick();
      if (loopActive.value) schedule();
    }, LOOP_MS);
  };
  schedule();
}

function flashSuccess() {
  ovalState.value = "success";
  if (successFlashTimer) clearTimeout(successFlashTimer);
  successFlashTimer = window.setTimeout(() => {
    if (ovalState.value === "success") ovalState.value = "ready";
    successFlashTimer = null;
  }, 1000);
}

async function runChallengeTick() {
  if (loopBusy || finishing.value || busy.value === "match") return;
  if (!video.value || !ovalEl.value || !cameraReady.value) return;

  loopBusy = true;
  try {
    if (awaitingFrontal.value) {
      await runFrontalGateTick();
      return;
    }

    const oval = await faceInOvalReady(video.value, ovalEl.value);
    if (!oval.ready) {
      consecutivePasses = 0;
      if (ovalState.value !== "success") ovalState.value = "idle";
      softFailTicks += 1;
      if (softFailTicks === HINT_CENTER_TICKS) {
        challengeMessage.value = "Move closer — fit your whole face in the oval";
      }
      return;
    }

    if (ovalState.value !== "success") ovalState.value = "ready";
    if (challengeMessage.value.startsWith("Move closer") || challengeMessage.value.startsWith("Center")) {
      challengeMessage.value = "";
    }

    const challenge = challengeSequence.value[challengeIndex.value];
    if (!challenge) return;

    if (challenge !== lastChallengeKey) {
      lastChallengeKey = challenge;
      resetBlinkTracker(blinkTracker);
      softFailTicks = 0;
    }

    const passed =
      challenge === "blink"
        ? await tickBlinkTracker(video.value, blinkTracker)
        : await detectChallenge(video.value, challenge);
    if (!passed) {
      consecutivePasses = 0;
      softFailTicks += 1;
      if (softFailTicks === HINT_SOFT_TICKS) {
        challengeMessage.value =
          challenge === "blink"
            ? "Blink once — close then open your eyes."
            : `Keep going — ${challengePrompts[challenge].toLowerCase()}.`;
      } else if (softFailTicks === HINT_STUCK_TICKS) {
        challengeMessage.value =
          challenge === "blink"
            ? "Try a slower, clear blink while the oval is green."
            : "Turn your head a bit farther, then face the camera again.";
      }
      return;
    }

    softFailTicks = 0;
    consecutivePasses = 0;
    resetBlinkTracker(blinkTracker);
    challengeMessage.value = "";
    flashSuccess();

    // Capture stills for the first two challenges (pose variety for staff review).
    if (challengeStills.value.length < 2 && video.value) {
      try {
        const still = await captureVideoFrame(video.value, 0.85);
        challengeStills.value.push({ label: challenge, blob: still });
      } catch {
        // Soft-fail — match selfie still required; stills validated server-side.
      }
    }

    // Brief centered check before advancing so the student sees each step pass.
    await successOverlay.show(challengeVerifiedLabels[challenge]);

    if (challengeIndex.value < challengeSequence.value.length - 1) {
      challengeIndex.value += 1;
      challengeMessage.value = "";
      return;
    }

    // Challenges done — do not capture while still in a turn pose.
    awaitingFrontal.value = true;
    consecutivePasses = 0;
    softFailTicks = 0;
    challengeMessage.value = "Almost done — look straight at the camera";
    return;
  } catch (exception) {
    // Soft-fail in auto loop — avoid spamming hard errors every tick.
    if (exception instanceof Error && /models|Unauthenticated|reference face/i.test(exception.message)) {
      error.value = exception.message;
    }
  } finally {
    loopBusy = false;
  }
}

/** Auto-detect a front-facing pose, then run the existing match/submit flow. */
async function runFrontalGateTick() {
  if (!video.value || !ovalEl.value) return;

  const hit = await faceFrontalInOvalReady(video.value, ovalEl.value);
  if (!hit.inOval) {
    consecutivePasses = 0;
    if (ovalState.value !== "success") ovalState.value = "idle";
    softFailTicks += 1;
    if (softFailTicks === HINT_CENTER_TICKS) {
      challengeMessage.value = "Center your face in the oval";
    }
    return;
  }

  if (!hit.frontal) {
    consecutivePasses = 0;
    if (ovalState.value !== "success") ovalState.value = "ready";
    softFailTicks += 1;
    if (softFailTicks === HINT_SOFT_TICKS || softFailTicks === HINT_STUCK_TICKS) {
      challengeMessage.value = "Look straight at the camera — not left, right, or down";
    }
    return;
  }

  if (ovalState.value !== "success") ovalState.value = "ready";
  softFailTicks = 0;
  consecutivePasses += 1;
  if (challengeMessage.value) challengeMessage.value = "";

  if (consecutivePasses < FRONTAL_READY_TICKS) return;

  flashSuccess();
  finishing.value = true;
  stopChallengeLoop();
  await finishLiveness();
}

async function finishLiveness() {
  if (!video.value || !referenceUrl.value) {
    finishing.value = false;
    throw new Error("ID reference face is missing. Re-scan your School ID.");
  }

  busy.value = "match";
  stopChallengeLoop();
  try {
    // Do not re-detect the stored ID face crop — TinyFaceDetector often misses tight
    // 240×240 crops. Server already has id_reference_face_descriptor and recomputes match.
    const live = await descriptorFromVideo(video.value);
    const selfie = await captureVideoFrame(video.value, 0.9);

    if (challengeStills.value.length < 2) {
      throw new Error("Challenge stills missing — complete the blink / turn steps again.");
    }

    const body = new FormData();
    body.append("selfie", new File([selfie], "onboarding_selfie.jpg", { type: "image/jpeg" }));
    body.append(
      "challenge_still_1",
      new File([challengeStills.value[0].blob], "liveness_challenge_1.jpg", { type: "image/jpeg" }),
    );
    body.append(
      "challenge_still_2",
      new File([challengeStills.value[1].blob], "liveness_challenge_2.jpg", { type: "image/jpeg" }),
    );
    body.append("challenge_still_labels[0]", challengeStills.value[0].label);
    body.append("challenge_still_labels[1]", challengeStills.value[1].label);
    challengeSequence.value.forEach((step, index) => body.append(`challenge_sequence[${index}]`, step));
    live.descriptor.forEach((value, index) => body.append(`face_descriptor[${index}]`, String(value)));
    body.append("liveness_confirmed", "1");

    const response = await fetch(apiUrl("/api/student/identity-onboarding/liveness"), {
      method: "POST",
      headers: { Accept: "application/json" },
      credentials: "include",
      body,
    });
    const payload = await response.json();
    if (!response.ok) {
      const validation = payload.errors ? Object.values(payload.errors).flat().join(" ") : "";
      throw new Error(validation || payload.message || "Face did not match — try again.");
    }

    authSession.user = authSession.user
      ? {
          ...authSession.user,
          // Auto-pass now yields 'identity_verified' — the password is still unset,
          // so the account is not yet 'active'.
          account_status: payload.data.account_status || "identity_verified",
          onboarding_next_step: payload.data.next_step,
          onboarding_path:
            payload.data.next_step === "face_review"
              ? "/student/onboarding/pending-review"
              : payload.data.next_step === "credentials"
                ? "/student/onboarding/set-password"
                : payload.data.next_step === "done"
                  ? "/student"
                  : authSession.user.onboarding_path,
        }
      : null;
    stopCamera();

    if (payload.data.next_step === "face_review") {
      toast.info(payload.data.message || "Uncertain face match — under staff review (not blocked).");
      await router.push("/student/onboarding/pending-review");
      return;
    }

    await successOverlay.show("Identity verified — set your password");
    await router.push("/student/onboarding/set-password");
  } catch (exception) {
    finishing.value = false;
    busy.value = "";
    // Stay on frontal gate so a retry does not re-capture a turn pose.
    awaitingFrontal.value = true;
    consecutivePasses = 0;
    softFailTicks = 0;
    error.value = exception instanceof Error ? exception.message : "Liveness check failed.";
    if (cameraReady.value) startChallengeLoop();
    throw exception;
  }
}
</script>

<template>
  <div class="space-y-5">
    <PageHeader
      title="Onboarding — Liveness challenge"
      description="Step 3 of 3. Complete blink / turn challenges, then look straight at the camera for the match photo. If the face does not match, you can try again. Uncertain matches go to staff review — that is not a block."
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

      <div class="relative mx-auto max-w-md overflow-hidden rounded-xl border bg-black">
        <video
          ref="video"
          class="aspect-[3/4] w-full scale-x-[-1] object-cover"
          autoplay
          playsinline
          muted
        />
        <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
          <div
            ref="ovalEl"
            class="h-[84%] w-[80%] max-w-[min(94%,24rem)] rounded-[50%] border-4 transition-[border-color,box-shadow] duration-150"
            :class="ovalBorderClass"
            :style="ovalStyle"
          />
        </div>

        <p
          v-if="cameraReady && busy !== 'match'"
          class="pointer-events-none absolute left-1/2 top-3 z-10 -translate-x-1/2 rounded-full px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-white shadow"
          :class="
            ovalState === 'ready' || ovalState === 'success'
              ? 'bg-emerald-500'
              : 'bg-amber-500'
          "
        >
          {{
            ovalState === "success"
              ? "Good"
              : ovalState === "ready"
                ? "Face fits — green"
                : "Align face"
          }}
        </p>

        <div
          v-if="!cameraReady || busy === 'match'"
          class="absolute inset-0 flex items-center justify-center bg-black/55 px-4 text-center text-sm font-medium text-white"
        >
          {{ busy === "match" ? "Matching face…" : "Starting camera…" }}
        </div>
      </div>

      <div class="mt-5 space-y-3 text-center">
        <p class="text-2xl font-semibold tracking-tight sm:text-3xl">
          {{ bigInstruction }}
        </p>
        <p
          class="text-sm font-medium"
          :class="ovalState === 'ready' || ovalState === 'success' ? 'text-emerald-600' : 'text-amber-700'"
        >
          {{ fitHint }}
        </p>
        <div class="flex items-center justify-center gap-2" aria-label="Challenge progress">
          <span
            v-for="(step, index) in challengeSequence"
            :key="`${step}-${index}`"
            class="h-2.5 w-2.5 rounded-full transition-colors"
            :class="
              awaitingFrontal || index < challengeIndex
                ? 'bg-emerald-500'
                : index === challengeIndex
                  ? ovalState === 'success'
                    ? 'bg-emerald-400'
                    : 'bg-primary'
                  : 'bg-surface-muted'
            "
          />
          <span
            class="h-2.5 w-2.5 rounded-full transition-colors"
            :class="
              busy === 'match'
                ? 'bg-emerald-500'
                : awaitingFrontal
                  ? ovalState === 'ready' || ovalState === 'success'
                    ? 'bg-primary'
                    : 'bg-amber-400'
                  : 'bg-surface-muted'
            "
            title="Face match photo"
          />
        </div>
        <p class="text-xs text-text-muted">
          <template v-if="awaitingFrontal && cameraReady && busy !== 'match'">
            Final step · look straight ahead · auto-detect — no button
          </template>
          <template v-else>
            Step {{ Math.min(challengeIndex + 1, challengeSequence.length || 3) }} of
            {{ challengeSequence.length || 3 }}
            <span v-if="currentChallenge && cameraReady && busy !== 'match'">
              · {{ challengeLabels[currentChallenge] }}
            </span>
            · auto-detect — no button
          </template>
        </p>
      </div>

      <p v-if="challengeMessage" class="mt-3 rounded-md border bg-surface-muted p-3 text-center text-xs text-text-muted">
        {{ challengeMessage }}
      </p>
      <p v-if="error" class="mt-3 rounded-md border border-danger/30 bg-danger-soft p-3 text-xs text-danger">
        <IconAlertTriangle :size="14" class="mr-1 inline" /> {{ error }}
      </p>
    </section>
  </div>
</template>
