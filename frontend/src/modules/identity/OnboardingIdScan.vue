<script setup lang="ts">
import { apiFetch, apiUrl } from "@/api/client";
import { onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { authSession } from "@/auth/session";
import { toast } from "@/composables/useToast";
import SchoolIdCaptureFlow, {
  type SchoolIdCaptureComplete,
} from "@/modules/requirements/SchoolIdCaptureFlow.vue";

const router = useRouter();
const ready = ref(false);
const bootError = ref("");

onMounted(async () => {
  const accountStatus = authSession.user?.account_status;
  if (accountStatus === "unverified" || accountStatus === "pending_kyc") {
    await router.replace("/student/kyc");
    return;
  }
  const step = authSession.user?.onboarding_next_step;
  if (step === "kyc") {
    await router.replace("/student/kyc");
    return;
  }
  if (step === "liveness") {
    await router.replace("/student/onboarding/liveness");
    return;
  }
  if (step === "face_review") {
    await router.replace("/student/onboarding/pending-review");
    return;
  }
  if (step === "done") {
    await router.replace("/student");
    return;
  }

  // Server is source of truth — block camera if KYC/ID prerequisites fail.
  try {
    const payload = await apiFetch<{ data: { next_step: string } }>("/api/student/identity-onboarding");
    const next = payload.data.next_step as string;
    if (next === "kyc") {
      await router.replace("/student/kyc");
      return;
    }
    if (next === "liveness") {
      await router.replace("/student/onboarding/liveness");
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
  } catch (exception) {
    bootError.value = exception instanceof Error ? exception.message : "Unable to start ID scan.";
    toast.error(bootError.value);
    return;
  }

  ready.value = true;
});

function payloadMessage(payload: unknown, fallback: string) {
  if (!payload || typeof payload !== "object") return fallback;
  const data = payload as { message?: string; errors?: Record<string, string[]> };
  const validation = data.errors ? Object.values(data.errors).flat().join(" ") : "";
  return validation || data.message || fallback;
}

async function onFrontOcr(blob: Blob) {
  const body = new FormData();
  body.append("id_frame", new File([blob], "id_onboarding_front.jpg", { type: "image/jpeg" }));

  const response = await fetch(apiUrl("/api/student/identity-onboarding/id-scan/ocr-front"), {
    method: "POST",
    headers: { Accept: "application/json" },
    credentials: "include",
    body,
  });
  const payload = await response.json();
  if (!response.ok) {
    throw new Error(payloadMessage(payload, "Front OCR failed."));
  }
  if (!payload?.data?.ok) {
    throw new Error(payload.message || "Front OCR did not match name & student ID.");
  }
}

async function onComplete(payload: SchoolIdCaptureComplete) {
  const body = new FormData();
  body.append("id_frame", new File([payload.front], "id_onboarding_front.jpg", { type: "image/jpeg" }));
  body.append("id_back", new File([payload.back], "id_onboarding_back.jpg", { type: "image/jpeg" }));
  body.append(
    "id_face_crop",
    new File([payload.face.blob], "id_reference_face.jpg", { type: "image/jpeg" }),
  );
  payload.face.descriptor.forEach((value, index) =>
    body.append(`face_descriptor[${index}]`, String(value)),
  );
  body.append("face_quality_score", String(payload.face.quality));
  body.append("authenticity_skipped", "1");

  const response = await fetch(apiUrl("/api/student/identity-onboarding/id-scan"), {
    method: "POST",
    headers: { Accept: "application/json" },
    credentials: "include",
    body,
  });
  const result = await response.json();
  if (!response.ok) {
    throw new Error(payloadMessage(result, "ID scan failed."));
  }

  const nextStep = (result?.data?.next_step as string) || "liveness";
  // Must update session before navigating — route guard blocks liveness while still on id_scan.
  if (authSession.user) {
    authSession.user = {
      ...authSession.user,
      onboarding_next_step: nextStep as typeof authSession.user.onboarding_next_step,
      onboarding_path:
        nextStep === "liveness"
          ? "/student/onboarding/liveness"
          : nextStep === "face_review"
            ? "/student/onboarding/pending-review"
            : nextStep === "done"
              ? "/student"
              : authSession.user.onboarding_path,
    };
  }

  return { qrFound: Boolean(result?.data?.qr_found) };
}

async function onExit() {
  await router.push("/student/onboarding/liveness");
}
</script>

<template>
  <p v-if="bootError" class="rounded-md border border-danger/30 bg-danger-soft p-3 text-sm text-danger">
    {{ bootError }}
  </p>
  <SchoolIdCaptureFlow
    v-else-if="ready"
    mode="onboarding"
    title="Onboarding — Scan School ID"
    description="Step 2 of 3 · School ID front &amp; back"
    :validate-front="onFrontOcr"
    :submit-capture="onComplete"
    :exit-flow="onExit"
  />
</template>
