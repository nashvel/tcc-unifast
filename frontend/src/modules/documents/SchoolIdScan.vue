<script setup lang="ts">
import { apiFetch, apiUrl } from "@/api/client";
import { onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import CardSkeleton from "@/components/ui/CardSkeleton.vue";
import { toast } from "@/composables/useToast";
import { withLang } from "@/i18n/routeLang";
import SchoolIdCaptureFlow, {
  type SchoolIdCaptureComplete,
} from "@/modules/requirements/SchoolIdCaptureFlow.vue";
import {
  clearVaultSchoolIdScanReady,
  isVaultSchoolIdScanReady,
} from "@/modules/documents/vaultSchoolIdScanGate";

const router = useRouter();
const ready = ref(false);
const bootError = ref("");

onMounted(async () => {
  if (!isVaultSchoolIdScanReady()) {
    toast.info("Complete the pre-check and consent on Documents first.");
    await router.replace(withLang("/student/documents"));
    return;
  }

  try {
    const payload = await apiFetch<{
      window?: { open?: boolean; message?: string };
      onboarding_refs?: { completed?: boolean };
      message?: string;
    }>("/api/student/requirement-vault");
    if (!payload.window?.open) {
      toast.info(payload.window?.message || "Submission window is closed.");
      clearVaultSchoolIdScanReady();
      await router.replace(withLang("/student/documents"));
      return;
    }
    if (!payload.onboarding_refs?.completed) {
      toast.info("Complete identity onboarding before scanning School ID for requirements.");
      clearVaultSchoolIdScanReady();
      await router.replace(withLang("/student/documents"));
      return;
    }
  } catch (exception) {
    bootError.value = exception instanceof Error ? exception.message : "Unable to start School ID scan.";
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

/** Front OCR gate via vault ocr-front — does not persist Slot 1; fail → retake front. */
async function onFrontOcr(blob: Blob) {
  const body = new FormData();
  body.append("id_frame", new File([blob], "id_vault_front.jpg", { type: "image/jpeg" }));

  const response = await fetch(apiUrl("/api/student/requirement-vault/id/ocr-front"), {
    method: "POST",
    headers: { Accept: "application/json" },
    body,
    credentials: "include",
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
  body.append("id_frame", new File([payload.front], "id_frame.jpg", { type: "image/jpeg" }));
  body.append("id_back", new File([payload.back], "id_back.jpg", { type: "image/jpeg" }));
  body.append(
    "id_face_crop",
    new File([payload.face.blob], "id_scan_submission.jpg", { type: "image/jpeg" }),
  );
  if (payload.qr) {
    body.append("qr_payload", payload.qr);
  }
  payload.face.descriptor.forEach((value, index) =>
    body.append(`face_descriptor[${index}]`, String(value)),
  );
  body.append("face_quality_score", String(payload.face.quality));
  body.append("consent_accepted", "1");
  body.append("precheck_accepted", "1");

  const response = await fetch(apiUrl("/api/student/requirement-vault/id"), {
    method: "POST",
    headers: { Accept: "application/json" },
    body,
    credentials: "include",
  });
  const result = await response.json();
  if (!response.ok) {
    throw new Error(payloadMessage(result, "School ID scan failed."));
  }

  toast.success("School ID scan confirmed. Slots 2–4 are unlocked.");
  return { qrFound: Boolean(payload.qr) };
}

async function onExit() {
  clearVaultSchoolIdScanReady();
  try {
    await router.replace(withLang("/student/documents"));
  } catch {
    await router.push(withLang("/student/documents"));
  }
}
</script>

<template>
  <CardSkeleton v-if="!ready && !bootError" :lines="6" />
  <p v-else-if="bootError" class="rounded-md border border-danger/30 bg-danger-soft p-3 text-sm text-danger">
    {{ bootError }}
  </p>
  <!-- Same shared capture UX as OnboardingIdScan (green frame, OCR gate, back, milestones). -->
  <SchoolIdCaptureFlow
    v-else-if="ready"
    mode="vault"
    title="Requirements — Scan School ID"
    description="Slot 1 · Front &amp; back · QR optional (staff can return if missing)"
    verified-label="Back to documents"
    verified-hint="Returning to documents… slots 2–4 unlock"
    :validate-front="onFrontOcr"
    :submit-capture="onComplete"
    :exit-flow="onExit"
  />
</template>
