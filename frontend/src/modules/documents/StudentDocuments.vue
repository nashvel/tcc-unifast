<script setup lang="ts">
import { apiFetch, apiUrl } from "@/api/client";
import { computed, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import {
  IconCamera,
  IconCheck,
  IconFileText,
  IconHelp,
  IconId,
  IconInfoCircle,
  IconLock,
  IconRefresh,
  IconShieldCheck,
  IconSignature,
  IconUpload,
} from "@tabler/icons-vue";
import AppDialog from "@/components/dialogs/AppDialog.vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import CardSkeleton from "@/components/ui/CardSkeleton.vue";
import { toast } from "@/composables/useToast";
import { withLang } from "@/i18n/routeLang";
import { markVaultSchoolIdScanReady } from "@/modules/documents/vaultSchoolIdScanGate";
import { authSession } from "@/auth/session";

const router = useRouter();
const user = computed(() => authSession.user);

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
  review_notes: string | null;
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
    id_onboarding_frame_url?: string | null;
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
const courseHistoryInput = ref<HTMLInputElement | null>(null);
const gradeSlipInput = ref<HTMLInputElement | null>(null);
const specimenSignaturesInput = ref<HTMLInputElement | null>(null);
const specimenBlueInkAck = ref(false);

const precheck = ref<Record<string, boolean>>({
  lighting: false,
  steady: false,
  glare: false,
  internet: false,
  permission: false,
});
const consent = ref(false);
const confirmDialog = ref(false);
const confirmPin = ref("");
const tutorialDialog = ref(false);
const precheckDialog = ref(false);
const activeTutorialTab = ref<"overview" | "slot1" | "slot2" | "slot3">("overview");

function openTutorial(tab: "overview" | "slot1" | "slot2" | "slot3" = "overview") {
  activeTutorialTab.value = tab;
  tutorialDialog.value = true;
}

function selectAllPrecheck() {
  for (const key of Object.keys(precheck.value)) {
    precheck.value[key] = true;
  }
  consent.value = true;
}

const packageLocked = computed(() =>
  ["docs_submitted", "under_review", "verified"].includes(granteeStatus.value),
);
const inResubmissionMode = computed(
  () =>
    granteeStatus.value === "resubmission_requested" ||
    Object.values(slots.value).some((slot) => slot?.status === "resubmission"),
);
const allDocumentsUploaded = computed(
  () =>
    Boolean(slots.value.course_history) &&
    Boolean(slots.value.grade_slip) &&
    Boolean(slots.value.specimen_signatures),
);
const precheckReady = computed(() => Object.values(precheck.value).every(Boolean) && consent.value);
const canSubmitPackage = computed(
  () => allDocumentsUploaded.value && !packageLocked.value && !inResubmissionMode.value,
);
const packageReady = computed(() => canSubmitPackage.value);
const SLOT_KEYS = [
  "course_history",
  "grade_slip",
  "specimen_signatures",
] as const;
/** Returned slots still waiting for replace and/or single-slot resubmit. */
const openResubmissionSlots = computed(() =>
  SLOT_KEYS.filter((key) => {
    const slot = slots.value[key];
    if (!slot) return false;
    if (slot.status === "resubmission") return true;
    return slot.status === "draft" && granteeStatus.value === "resubmission_requested";
  }),
);
/** Vault progress is 4 document slots only (submit is a separate action, not a 5th step). */
const progress = computed(() => {
  if (inResubmissionMode.value) {
    const open = openResubmissionSlots.value;
    if (open.length === 0) return 100;
    const replaced = open.filter((key) => slots.value[key]?.status === "draft").length;
    // Cap below 100 until every returned slot is resubmitted (mode exits).
    return Math.min(90, Math.round((replaced / open.length) * 90));
  }
  const filled = [
    slots.value.course_history,
    slots.value.grade_slip,
    slots.value.specimen_signatures,
  ].filter(Boolean).length;
  return Math.round((filled / 3) * 100);
});
const progressStatusLabel = computed(() => {
  if (inResubmissionMode.value) {
    const open = openResubmissionSlots.value.length;
    const awaitingReplace = openResubmissionSlots.value.filter(
      (key) => slots.value[key]?.status === "resubmission",
    ).length;
    if (awaitingReplace > 0) {
      return `${awaitingReplace} returned slot${awaitingReplace === 1 ? "" : "s"} need${awaitingReplace === 1 ? "s" : ""} replacing`;
    }
    return open > 0
      ? `${open} replaced — resubmit when ready`
      : "Resubmission requested";
  }
  if (packageLocked.value) return granteeStatus.value.replaceAll("_", " ");
  if (packageReady.value) return "Ready to submit";
  return granteeStatus.value.replaceAll("_", " ");
});

const precheckItems = [
  ["lighting", "Good stable lighting"],
  ["steady", "Hold ID vertically — steady inside the portrait frame"],
  ["glare", "Remove glare or obstructions"],
  ["internet", "Stable internet connection"],
  ["permission", "Allow camera permission"],
] as const;

onMounted(loadVault);

function payloadMessage(payload: unknown, fallback: string) {
  if (!payload || typeof payload !== "object") return fallback;
  const data = payload as { message?: string; errors?: Record<string, string[]> };
  const validation = data.errors ? Object.values(data.errors).flat().join(" ") : "";
  return validation || data.message || fallback;
}

async function loadVault(options: { quiet?: boolean } = {}) {
  if (!options.quiet) loading.value = true;
  error.value = "";
  try {
    const payload = await apiFetch<VaultResponse>("/api/student/requirement-vault");
    windowOpen.value = Boolean(payload.window?.open);
    windowMessage.value = payload.window?.message || "";
    granteeStatus.value = payload.grantee?.submission_status || "not_submitted";
    slots.value = payload.slots || {};
    identityCheck.value = payload.identity_check || null;
    onboardingRefs.value = payload.onboarding_refs || null;
    if (
      !options.quiet &&
      (granteeStatus.value === "resubmission_requested" ||
        Object.values(slots.value).some((slot) => slot?.status === "resubmission"))
    ) {
      toast.message("Resubmission requested", {
        description: "Resubmit only returned document(s). Replace each returned file, then Resubmit that slot.",
      });
    }
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "Unable to load the requirement vault.";
  } finally {
    if (!options.quiet) loading.value = false;
  }
}

function slotUploaded(slotKey: "course_history" | "grade_slip" | "specimen_signatures") {
  return Boolean(slots.value[slotKey]);
}

function canEditSlot(
  slotKey: "course_history" | "grade_slip" | "specimen_signatures",
) {
  const slot = slots.value[slotKey];
  if (!slot) return false;
  if (slot.status === "resubmission") return true;
  if (slot.status === "draft" && granteeStatus.value === "resubmission_requested") return true;
  if (packageLocked.value || inResubmissionMode.value) return false;
  return slot.status === "draft";
}

function canResubmitSlot(
  slotKey: "course_history" | "grade_slip" | "specimen_signatures",
) {
  const slot = slots.value[slotKey];
  return (
    Boolean(slot) &&
    slot.status === "draft" &&
    granteeStatus.value === "resubmission_requested"
  );
}

function isSlotActionable(
  slotKey: "course_history" | "grade_slip" | "specimen_signatures",
) {
  if (inResubmissionMode.value) {
    return canEditSlot(slotKey) || canResubmitSlot(slotKey);
  }
  return slots.value[slotKey]?.status === "resubmission";
}

/** Already on file with staff and not returned — muted during resubmission. */
function isSlotLockedForResubmission(
  slotKey: "course_history" | "grade_slip" | "specimen_signatures",
) {
  return (
    inResubmissionMode.value &&
    Boolean(slots.value[slotKey]) &&
    !isSlotActionable(slotKey)
  );
}

/**
 * First-time / missing-slot uploads.
 * Empty slots stay uploadable during resubmission; returned slots use Edit/Replace.
 */
function canUploadNewSlot(
  slotKey: "course_history" | "grade_slip" | "specimen_signatures",
) {
  return !slotUploaded(slotKey);
}

function slotStatusLabel(status: string) {
  if (status === "draft") {
    return granteeStatus.value === "resubmission_requested" ? "Ready to resubmit" : "Draft saved";
  }
  if (status === "pending_review") return "Pending review";
  if (status === "resubmission") return "Resubmission requested";
  if (status === "approved") return "Approved";
  return status.replaceAll("_", " ");
}

function slotStatusClass(status: string) {
  if (status === "pending_review") {
    return "inline-flex rounded-full bg-info-soft px-2 py-0.5 text-micro font-semibold text-info";
  }
  if (status === "resubmission") {
    return "inline-flex rounded-full bg-warning-soft px-2 py-0.5 text-micro font-semibold text-warning";
  }
  if (status === "approved") {
    return "inline-flex rounded-full bg-success-soft px-2 py-0.5 text-micro font-semibold text-success";
  }
  if (status === "draft" && granteeStatus.value === "resubmission_requested") {
    return "inline-flex rounded-full bg-warning-soft px-2 py-0.5 text-micro font-semibold text-warning";
  }
  return "inline-flex rounded-full bg-surface-muted px-2 py-0.5 text-micro font-medium text-text";
}

function slotCardClass(
  slotKey: "course_history" | "grade_slip" | "specimen_signatures",
) {
  if (inResubmissionMode.value) {
    if (slots.value[slotKey]?.status === "resubmission") {
      return "border-warning bg-warning-soft/25 ring-2 ring-warning/35";
    }
    if (canResubmitSlot(slotKey)) {
      return "border-warning/60 bg-warning-soft/20 ring-1 ring-warning/30";
    }
    if (canEditSlot(slotKey)) {
      return "border-warning/40 ring-1 ring-warning/20";
    }
    // Never submitted — keep a normal uploadable card (not muted).
    if (!slots.value[slotKey]) return "";
    return "opacity-55 bg-surface-muted/40";
  }
  return "";
}

/** File summary panel: amber when action needed, muted when locked in resubmission, green when saved normally. */
function slotFilePanelClass(
  slotKey: "course_history" | "grade_slip" | "specimen_signatures",
) {
  const slot = slots.value[slotKey];
  if (!slot) return "";
  if (slot.status === "resubmission" || canResubmitSlot(slotKey)) {
    return "rounded-md border border-warning/40 bg-warning-soft p-3 text-xs text-warning";
  }
  if (inResubmissionMode.value && isSlotLockedForResubmission(slotKey)) {
    return "rounded-md border border-border bg-surface-muted/60 p-3 text-xs text-text-muted";
  }
  return "rounded-md border border-success/30 bg-success-soft p-3 text-xs text-success";
}

function slotReplaceButtonClass(
  slotKey: "course_history" | "grade_slip" | "specimen_signatures",
) {
  if (slots.value[slotKey]?.status === "resubmission" || canResubmitSlot(slotKey)) {
    return "inline-flex h-9 w-full items-center justify-center gap-2 rounded-md border border-warning/50 bg-warning-soft px-3 text-xs font-semibold text-warning disabled:opacity-50";
  }
  return "inline-flex h-9 w-full items-center justify-center gap-2 rounded-md border px-3 text-xs font-medium disabled:opacity-50";
}

function slotFileLabel(
  slotKey: "course_history" | "grade_slip" | "specimen_signatures",
  local: File | null,
  emptyLabel: string,
) {
  const server = slots.value[slotKey];
  if (server?.original_name) return server.original_name;
  if (local?.name) return local.name;
  return emptyLabel;
}

function openFilePicker(target: "course" | "grade" | "specimen", replace = false) {
  const slotKey =
    target === "course" ? "course_history" : target === "grade" ? "grade_slip" : "specimen_signatures";
  if (replace) {
    if (!canEditSlot(slotKey)) return;
  } else {
    if (!canUploadNewSlot(slotKey)) return;
  }
  if (slotUploaded(slotKey) && !replace) return;
  if (target === "specimen" && !specimenBlueInkAck.value && !replace) {
    error.value = "Confirm that the specimens were written with a blue ballpen before uploading.";
    return;
  }
  const input =
    target === "course"
      ? courseHistoryInput.value
      : target === "grade"
        ? gradeSlipInput.value
        : specimenSignaturesInput.value;
  input?.click();
}

function clearLocalDraft(slotKey: "course_history" | "grade_slip" | "specimen_signatures") {
  if (slotKey === "course_history") {
    courseHistory.value = null;
    if (courseHistoryInput.value) courseHistoryInput.value.value = "";
  } else if (slotKey === "grade_slip") {
    gradeSlip.value = null;
    if (gradeSlipInput.value) gradeSlipInput.value.value = "";
  } else {
    specimenSignatures.value = null;
    if (specimenSignaturesInput.value) specimenSignaturesInput.value.value = "";
  }
}

/** Hidden file input change: store File and upload immediately (one-click Upload flow). */
function onFilePicked(event: Event, target: "course" | "grade" | "specimen") {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0] ?? null;
  const slotKey =
    target === "course" ? "course_history" : target === "grade" ? "grade_slip" : "specimen_signatures";
  const replacing = slotUploaded(slotKey);
  if (!file || (replacing && !canEditSlot(slotKey))) {
    input.value = "";
    return;
  }
  if (target === "course") courseHistory.value = file;
  if (target === "grade") gradeSlip.value = file;
  if (target === "specimen") specimenSignatures.value = file;
  void uploadDocument(slotKey, file, { replace: replacing });
}


function openConfirmDialog() {
  if (!canSubmitPackage.value) return;
  confirmDialog.value = true;
}

async function confirmSubmission() {
  // Guard before setting busy — button :disabled alone still allows a double-click
  // race before Vue re-renders, which burns the confirm throttle.
  if (!canSubmitPackage.value || busy.value === "confirm") return;
  busy.value = "confirm";
  error.value = "";
  success.value = "";
  try {
    const payload = await apiFetch<{
      grantee?: { submission_status?: string };
      identity_check?: typeof identityCheck.value;
    }>("/api/student/requirement-vault/confirm", {
      method: "POST",
      body: user.value?.has_security_pin ? JSON.stringify({ pin: confirmPin.value }) : undefined,
    });
    granteeStatus.value = payload.grantee?.submission_status || "docs_submitted";
    if (payload.identity_check) identityCheck.value = payload.identity_check;
    confirmDialog.value = false;
    success.value = "All required documents submitted to staff Document Validation.";
    toast.success(success.value);
    await loadVault({ quiet: true });
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "Unable to submit requirements.";
    toast.error(error.value);
  } finally {
    busy.value = "";
  }
}

async function uploadDocument(
  slotKey: "course_history" | "grade_slip" | "specimen_signatures",
  file: File | null = null,
  options: { replace?: boolean } = {},
) {
  const replacing = Boolean(options.replace);
  if ((!replacing && slotUploaded(slotKey)) || busy.value === slotKey) return;
  if (replacing && !canEditSlot(slotKey)) return;
  if (!replacing && !canUploadNewSlot(slotKey)) return;

  if (slotKey === "specimen_signatures" && !specimenBlueInkAck.value && !replacing) {
    error.value = "Confirm that the specimens were written with a blue ballpen before uploading.";
    toast.error(error.value);
    return;
  }

  // One-click: Upload with no file opens the OS picker; onChange uploads immediately.
  if (!file) {
    openFilePicker(
      slotKey === "course_history" ? "course" : slotKey === "grade_slip" ? "grade" : "specimen",
      replacing,
    );
    return;
  }

  busy.value = slotKey;
  error.value = "";
  success.value = "";
  try {
    const body = new FormData();
    body.append("slot_key", slotKey);
    body.append("file", file);
    const response = await fetch(apiUrl("/api/student/requirement-vault/document"), {
      method: "POST",
      headers: { Accept: "application/json" },
      body,
      credentials: "include",
    });
    const payload = await response.json();
    if (!response.ok) throw new Error(payloadMessage(payload, "Upload failed."));
    if (!payload?.data?.slot_key) {
      throw new Error("Upload succeeded but the vault response was incomplete. Refresh and try again.");
    }
    const uploaded = payload.data as VaultDocument;
    // Persist from server payload first, then re-fetch so reload state matches.
    slots.value = { ...slots.value, [slotKey]: uploaded };
    clearLocalDraft(slotKey);
    success.value = replacing
      ? `${uploaded.document_type} replaced. Resubmit this slot when ready.`
      : `${uploaded.document_type} saved as draft.`;
    toast.success(success.value);
    await loadVault({ quiet: true });
    if (!slots.value[slotKey]) {
      // Keep the POST payload visible if GET briefly omits the draft.
      slots.value = { ...slots.value, [slotKey]: uploaded };
    }
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "Upload failed.";
    toast.error(error.value);
  } finally {
    busy.value = "";
  }
}

async function resubmitSlot(
  slotKey: "course_history" | "grade_slip" | "specimen_signatures",
) {
  if (!canResubmitSlot(slotKey) || busy.value === `resubmit-${slotKey}`) return;
  const label = slots.value[slotKey]?.document_type || slotKey.replaceAll("_", " ");
  busy.value = `resubmit-${slotKey}`;
  error.value = "";
  success.value = "";
  try {
    const payload = await apiFetch<{
      data?: VaultDocument;
      grantee?: { submission_status?: string };
    }>("/api/student/requirement-vault/resubmit-slot", {
      method: "POST",
      body: JSON.stringify({ slot_key: slotKey }),
    });
    if (payload.data) {
      slots.value = { ...slots.value, [slotKey]: payload.data as VaultDocument };
    }
    if (payload.grantee?.submission_status) {
      granteeStatus.value = payload.grantee.submission_status;
    }
    success.value = `${label} resubmitted for staff review.`;
    toast.success(success.value);
    await loadVault({ quiet: true });
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : `Unable to resubmit ${label}.`;
    toast.error(error.value);
  } finally {
    busy.value = "";
  }
}
</script>

<template>
  <div class="space-y-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <PageHeader
        title="Requirement Vault"
        description="Upload all required documents (Course History, Grade Slip, ID Back-to-Back with Specimen), then submit to staff. After a return, resubmit only returned document(s)."
      />
      <button
        type="button"
        @click="openTutorial('overview')"
        class="inline-flex shrink-0 items-center gap-1.5 self-start rounded-xl border bg-surface px-3.5 py-2 text-xs font-semibold text-primary shadow-sm transition hover:bg-primary-soft/40 hover:shadow"
      >
        <IconHelp :size="16" />
        Requirements Guide &amp; Tutorial
      </button>
    </div>

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
            <p class="text-xs font-medium uppercase text-text-muted">
              {{ inResubmissionMode ? "Resubmission progress" : "Submission progress" }}
            </p>
            <p class="mt-1 text-3xl font-semibold">{{ progress }}%</p>
            <p class="text-xs text-text-muted">{{ progressStatusLabel }}</p>
          </div>
          <span
            v-if="inResubmissionMode"
            class="inline-flex h-9 max-w-[16rem] items-center gap-2 rounded-md bg-warning-soft px-3 text-xs font-medium text-warning"
          >
            <IconRefresh :size="15" class="shrink-0" />
            Action needed — resubmit only returned document(s)
          </span>
          <span
            v-else-if="packageLocked"
            class="inline-flex h-9 items-center gap-2 rounded-md bg-success-soft px-3 text-xs font-medium text-success"
          >
            <IconShieldCheck :size="15" /> Submitted to staff
          </span>
        </div>
        <div class="mt-4 h-2 overflow-hidden rounded-full bg-surface-muted">
          <div
            class="h-full transition-all"
            :class="inResubmissionMode ? 'bg-warning' : 'bg-primary'"
            :style="{ width: `${progress}%` }"
          />
        </div>
      </section>

      <p v-if="error" class="rounded-md border border-danger/30 bg-danger-soft p-3 text-xs text-danger">{{ error }}</p>
      <p v-if="success" class="rounded-md border border-success/30 bg-success-soft p-3 text-xs text-success">{{ success }}</p>

      <section class="grid items-start gap-4 md:grid-cols-2 lg:grid-cols-3">
        <article class="rounded-lg border bg-surface p-4" :class="slotCardClass('course_history')">
          <div class="flex items-start gap-3">
            <span class="grid h-10 w-10 place-items-center rounded-lg bg-info-soft text-info"><IconFileText :size="20" /></span>
            <div class="min-w-0 flex-1">
              <h2 class="text-sm font-semibold">Slot 1: Course History</h2>
              <p class="text-xs text-text-muted">PDF only from TCC SIS</p>
            </div>
            <button
              type="button"
              @click="openTutorial('slot1')"
              class="text-xs text-primary hover:underline font-medium shrink-0"
              title="How to get from TCC SIS"
            >
              SIS Guide
            </button>
          </div>
          <div v-if="slots.course_history" class="mt-4 space-y-3">
            <div :class="slotFilePanelClass('course_history')">
              <IconRefresh v-if="isSlotActionable('course_history')" :size="14" class="inline" />
              <IconCheck v-else :size="14" class="inline text-success" />
              {{ slots.course_history.original_name }}
              <span class="mt-1 block">
                <span :class="slotStatusClass(slots.course_history.status)">{{
                  slotStatusLabel(slots.course_history.status)
                }}</span>
              </span>
              <p
                v-if="slots.course_history.review_notes"
                class="mt-2 rounded-md border border-warning/30 bg-warning-soft p-2 text-warning"
              >
                Staff notes: {{ slots.course_history.review_notes }}
              </p>
            </div>
            <p
              v-if="isSlotLockedForResubmission('course_history')"
              class="text-xs text-text-muted"
            >
              Not required for this resubmission.
            </p>
            <input
              ref="courseHistoryInput"
              :disabled="!canEditSlot('course_history') || busy === 'course_history'"
              class="hidden"
              type="file"
              accept=".pdf,application/pdf"
              @change="onFilePicked($event, 'course')"
            />
            <button
              v-if="canEditSlot('course_history')"
              type="button"
              :class="slotReplaceButtonClass('course_history')"
              :disabled="busy === 'course_history'"
              @click="openFilePicker('course', true)"
            >
              <IconRefresh :size="14" />
              {{ busy === "course_history" ? "Replacing…" : "Edit / Replace" }}
            </button>
            <button
              v-if="canResubmitSlot('course_history')"
              type="button"
              class="inline-flex h-9 w-full items-center justify-center gap-2 rounded-md bg-primary px-3 text-xs font-semibold text-white disabled:opacity-50"
              :disabled="busy === 'resubmit-course_history'"
              @click="resubmitSlot('course_history')"
            >
              <IconUpload :size="14" />
              {{ busy === "resubmit-course_history" ? "Resubmitting…" : "Resubmit Course History" }}
            </button>
          </div>
          <div v-else class="mt-4 space-y-3">
            <input
              ref="courseHistoryInput"
              :disabled="!canUploadNewSlot('course_history') || busy === 'course_history'"
              class="hidden"
              type="file"
              accept=".pdf,application/pdf"
              @change="onFilePicked($event, 'course')"
            />
            <p
              class="inline-flex h-9 w-full items-center gap-2 rounded-md border border-border bg-surface-muted/40 px-3 text-xs text-text-muted"
              aria-live="polite"
            >
              <IconFileText :size="14" class="shrink-0" />
              <span class="truncate">{{ slotFileLabel("course_history", courseHistory, "No PDF selected") }}</span>
            </p>
            <button
              type="button"
              class="inline-flex h-9 w-full items-center justify-center gap-2 rounded-md bg-primary px-3 text-xs font-medium text-white disabled:opacity-50"
              :disabled="!canUploadNewSlot('course_history') || busy === 'course_history'"
              @click="uploadDocument('course_history')"
            >
              <IconUpload :size="14" />
              {{ busy === "course_history" ? "Uploading…" : "Upload Course History" }}
            </button>
          </div>
        </article>

        <article class="rounded-lg border bg-surface p-4" :class="slotCardClass('grade_slip')">
          <div class="flex items-start gap-3">
            <span class="grid h-10 w-10 place-items-center rounded-lg bg-info-soft text-info"><IconFileText :size="20" /></span>
            <div class="min-w-0 flex-1">
              <h2 class="text-sm font-semibold">Slot 2: Grade Slip</h2>
              <p class="text-xs text-text-muted">
                PDF from TCC SIS · Current Grade Slip (pending/late grades accepted)
              </p>
            </div>
            <button
              type="button"
              @click="openTutorial('slot2')"
              class="text-xs text-primary hover:underline font-medium shrink-0"
              title="How to get from TCC SIS"
            >
              SIS Guide
            </button>
          </div>
          <div v-if="slots.grade_slip" class="mt-4 space-y-3">
            <div :class="slotFilePanelClass('grade_slip')">
              <IconRefresh v-if="isSlotActionable('grade_slip')" :size="14" class="inline" />
              <IconCheck v-else :size="14" class="inline text-success" />
              {{ slots.grade_slip.original_name }}
              <span class="mt-1 block">
                <span :class="slotStatusClass(slots.grade_slip.status)">{{
                  slotStatusLabel(slots.grade_slip.status)
                }}</span>
              </span>
              <p
                v-if="slots.grade_slip.review_notes"
                class="mt-2 rounded-md border border-warning/30 bg-warning-soft p-2 text-warning"
              >
                Staff notes: {{ slots.grade_slip.review_notes }}
              </p>
            </div>
            <p
              v-if="isSlotLockedForResubmission('grade_slip')"
              class="text-xs text-text-muted"
            >
              Not required for this resubmission.
            </p>
            <input
              ref="gradeSlipInput"
              :disabled="!canEditSlot('grade_slip') || busy === 'grade_slip'"
              class="hidden"
              type="file"
              accept=".pdf,application/pdf"
              @change="onFilePicked($event, 'grade')"
            />
            <button
              v-if="canEditSlot('grade_slip')"
              type="button"
              :class="slotReplaceButtonClass('grade_slip')"
              :disabled="busy === 'grade_slip'"
              @click="openFilePicker('grade', true)"
            >
              <IconRefresh :size="14" />
              {{ busy === "grade_slip" ? "Replacing…" : "Edit / Replace" }}
            </button>
            <button
              v-if="canResubmitSlot('grade_slip')"
              type="button"
              class="inline-flex h-9 w-full items-center justify-center gap-2 rounded-md bg-primary px-3 text-xs font-semibold text-white shadow-sm disabled:opacity-50"
              :disabled="busy === 'resubmit-grade_slip'"
              @click="resubmitSlot('grade_slip')"
            >
              <IconUpload :size="14" />
              {{ busy === "resubmit-grade_slip" ? "Resubmitting…" : "Resubmit Grade Slip" }}
            </button>
          </div>
          <div v-else class="mt-4 space-y-3">
            <input
              ref="gradeSlipInput"
              :disabled="!canUploadNewSlot('grade_slip') || busy === 'grade_slip'"
              class="hidden"
              type="file"
              accept=".pdf,application/pdf"
              @change="onFilePicked($event, 'grade')"
            />
            <p
              class="inline-flex h-9 w-full items-center gap-2 rounded-md border border-border bg-surface-muted/40 px-3 text-xs text-text-muted"
              aria-live="polite"
            >
              <IconFileText :size="14" class="shrink-0" />
              <span class="truncate">{{ slotFileLabel("grade_slip", gradeSlip, "No PDF selected") }}</span>
            </p>
            <button
              type="button"
              class="inline-flex h-9 w-full items-center justify-center gap-2 rounded-md bg-primary px-3 text-xs font-medium text-white disabled:opacity-50"
              :disabled="!canUploadNewSlot('grade_slip') || busy === 'grade_slip'"
              @click="uploadDocument('grade_slip')"
            >
              <IconUpload :size="14" />
              {{ busy === "grade_slip" ? "Uploading…" : "Upload Grade Slip" }}
            </button>
          </div>
        </article>

        <article class="rounded-lg border bg-surface p-4" :class="slotCardClass('specimen_signatures')">
          <div class="flex items-start gap-3">
            <span class="grid h-10 w-10 place-items-center rounded-lg bg-info-soft text-info"><IconSignature :size="20" /></span>
            <div class="min-w-0 flex-1">
              <h2 class="text-sm font-semibold">Slot 3: ID (Back-to-Back) &amp; Specimen</h2>
              <p class="text-xs text-text-muted">PDF or Image (PDF, JPG, PNG, WEBP) · ID front &amp; back + 3 blue signatures</p>
            </div>
            <button
              type="button"
              @click="openTutorial('slot3')"
              class="text-xs text-primary hover:underline font-medium shrink-0"
              title="How to prepare this document"
            >
              Guide
            </button>
          </div>
          <p
            v-if="!slots.specimen_signatures || !inResubmissionMode || isSlotActionable('specimen_signatures')"
            class="mt-3 text-xs text-text-muted"
          >
            Put your ID (Front &amp; Back) in a document with 3 specimen signatures in <span class="font-semibold text-text">blue ballpen</span>, then upload as PDF or Image.
          </p>
          <div v-if="slots.specimen_signatures" class="mt-4 space-y-3">
            <div :class="slotFilePanelClass('specimen_signatures')">
              <IconRefresh v-if="isSlotActionable('specimen_signatures')" :size="14" class="inline" />
              <IconCheck v-else :size="14" class="inline text-success" />
              {{ slots.specimen_signatures.original_name }}
              <span class="mt-1 block">
                <span :class="slotStatusClass(slots.specimen_signatures.status)">{{
                  slotStatusLabel(slots.specimen_signatures.status)
                }}</span>
              </span>
              <p
                v-if="slots.specimen_signatures.review_notes"
                class="mt-2 rounded-md border border-warning/30 bg-warning-soft p-2 text-warning"
              >
                Staff notes: {{ slots.specimen_signatures.review_notes }}
              </p>
            </div>
            <p
              v-if="isSlotLockedForResubmission('specimen_signatures')"
              class="text-xs text-text-muted"
            >
              Not required for this resubmission.
            </p>
            <input
              ref="specimenSignaturesInput"
              :disabled="!canEditSlot('specimen_signatures') || busy === 'specimen_signatures'"
              class="hidden"
              type="file"
              accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp"
              @change="onFilePicked($event, 'specimen')"
            />
            <button
              v-if="canEditSlot('specimen_signatures')"
              type="button"
              :class="slotReplaceButtonClass('specimen_signatures')"
              :disabled="busy === 'specimen_signatures'"
              @click="openFilePicker('specimen', true)"
            >
              <IconRefresh :size="14" />
              {{ busy === "specimen_signatures" ? "Replacing…" : "Edit / Replace" }}
            </button>
            <button
              v-if="canResubmitSlot('specimen_signatures')"
              type="button"
              class="inline-flex h-9 w-full items-center justify-center gap-2 rounded-md bg-primary px-3 text-xs font-semibold text-white disabled:opacity-50"
              :disabled="busy === 'resubmit-specimen_signatures'"
              @click="resubmitSlot('specimen_signatures')"
            >
              <IconUpload :size="14" />
              {{
                busy === "resubmit-specimen_signatures"
                  ? "Resubmitting…"
                  : "Resubmit ID (Back-to-Back) & Specimen"
              }}
            </button>
          </div>
          <div v-else class="mt-4 space-y-3">
            <label class="flex items-start gap-2 rounded-md border p-3 text-xs">
              <input v-model="specimenBlueInkAck" type="checkbox" />
              <span>I confirm the file contains my School ID (front &amp; back) with 3 blue-ink specimen signatures.</span>
            </label>
            <input
              ref="specimenSignaturesInput"
              :disabled="!canUploadNewSlot('specimen_signatures') || busy === 'specimen_signatures'"
              class="hidden"
              type="file"
              accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp"
              @change="onFilePicked($event, 'specimen')"
            />
            <p
              class="inline-flex h-9 w-full items-center gap-2 rounded-md border border-border bg-surface-muted/40 px-3 text-xs text-text-muted"
              aria-live="polite"
            >
              <IconSignature :size="14" class="shrink-0" />
              <span class="truncate">{{ slotFileLabel("specimen_signatures", specimenSignatures, "No file selected (PDF or Image)") }}</span>
            </p>
            <button
              type="button"
              class="inline-flex h-9 w-full items-center justify-center gap-2 rounded-md bg-primary px-3 text-xs font-medium text-white disabled:opacity-50"
              :disabled="!canUploadNewSlot('specimen_signatures') || !specimenBlueInkAck || busy === 'specimen_signatures'"
              @click="uploadDocument('specimen_signatures')"
            >
              <IconUpload :size="14" />
              {{ busy === "specimen_signatures" ? "Uploading…" : "Upload ID (Back-to-Back) & Specimen" }}
            </button>
          </div>
        </article>
      </section>

      <aside class="rounded-lg border bg-surface p-4 sm:p-5">
        <div
          v-if="inResubmissionMode"
          class="flex flex-wrap items-center justify-between gap-3"
        >
          <div>
            <h2 class="flex items-center gap-2 text-sm font-semibold">
              <IconRefresh :size="17" /> Resubmit only returned document(s)
            </h2>
            <p class="mt-2 text-xs text-text-muted">
              Replace each returned (amber) file, then Resubmit that slot. Do not re-submit approved or
              pending-review documents. Legacy missing slots can still be uploaded and go to staff.
            </p>
            <p class="mt-1 text-xs capitalize text-text-muted">{{ granteeStatus.replaceAll("_", " ") }}</p>
          </div>
        </div>
        <div
          v-else-if="packageLocked"
          class="flex flex-wrap items-center justify-between gap-3"
        >
          <div>
            <h2 class="flex items-center gap-2 text-sm font-semibold">
              <IconShieldCheck :size="17" /> Submission status
            </h2>
            <p class="mt-2 text-xs text-success">Requirements submitted to staff Document Validation.</p>
            <p class="mt-1 text-xs capitalize text-text-muted">{{ granteeStatus.replaceAll("_", " ") }}</p>
          </div>
          <span class="inline-flex h-10 items-center gap-2 rounded-md bg-success-soft px-4 text-xs font-medium text-success">
            <IconCheck :size="15" /> Locked for review
          </span>
        </div>
        <div v-else class="flex flex-wrap items-center justify-between gap-3">
          <div class="min-w-0 flex-1">
            <h2 class="flex items-center gap-2 text-sm font-semibold">
              <IconUpload :size="17" /> Submit all required documents to staff
            </h2>
            <p class="mt-1 max-w-xl text-xs text-text-muted">
              {{
                packageReady
                  ? "All required slots are filled. Confirm to send the full package to staff Document Validation."
                  : "Upload Course History, Grade Slip, and ID Back-to-Back with Specimen signatures — incomplete packages are not sent to staff."
              }}
            </p>
          </div>
          <button
            type="button"
            class="inline-flex h-10 shrink-0 items-center gap-2 rounded-md bg-primary px-4 text-xs font-medium text-white disabled:opacity-50"
            :disabled="!canSubmitPackage || busy === 'confirm'"
            @click="openConfirmDialog"
          >
            <IconUpload :size="15" />
            {{ busy === "confirm" ? "Submitting…" : "Submit all required documents" }}
          </button>
        </div>
      </aside>
    </template>

    <AppDialog
      v-model="confirmDialog"
      title="Submit all required documents to staff?"
      description="This locks your drafts and sends the complete package to staff Document Validation. OCR and PDF metadata extraction run after submit."
      size="md"
    >
      <ul class="space-y-2 text-xs text-text-muted mb-4">
        <li>Staff only see complete packages after you confirm — incomplete drafts never appear in Document Validation.</li>
        <li>After submit, Edit / Replace is locked unless staff returns a document. Then resubmit only returned document(s).</li>
      </ul>
      <div v-if="user?.has_security_pin" class="border-t border-border pt-4">
        <label class="block">
          <span class="mb-1.5 block text-xs font-medium text-text">Security PIN Required</span>
          <input
            v-model="confirmPin"
            type="password"
            maxlength="6"
            inputmode="numeric"
            placeholder="Enter your 4-6 digit PIN"
            class="h-9 w-full rounded-md border px-3 text-sm"
          />
        </label>
      </div>
      <template #footer="{ close }">
        <button class="rounded-md border px-4 py-2 text-xs" @click="close">Cancel</button>
        <button
          class="rounded-md bg-primary px-4 py-2 text-xs text-white disabled:opacity-50"
          :disabled="busy === 'confirm' || (user?.has_security_pin && !confirmPin)"
          @click="confirmSubmission"
        >
          {{ busy === "confirm" ? "Submitting…" : "Confirm — submit all" }}
        </button>
      </template>
    </AppDialog>

    <!-- Submission Guide & Tutorial Dialog -->
    <AppDialog
      v-model="tutorialDialog"
      title="How to Prepare & Submit Requirements"
      description="Follow these step-by-step instructions to prepare your 3 documents from TCC SIS and your student ID."
      size="lg"
    >
      <!-- Navigation Tabs -->
      <div class="flex flex-wrap gap-1.5 border-b pb-3 text-xs">
        <button
          type="button"
          @click="activeTutorialTab = 'overview'"
          class="rounded-lg px-3 py-1.5 font-medium transition"
          :class="activeTutorialTab === 'overview' ? 'bg-primary text-white shadow-sm' : 'border bg-surface text-text hover:bg-surface-muted'"
        >
          Overview (All 3)
        </button>
        <button
          type="button"
          @click="activeTutorialTab = 'slot1'"
          class="rounded-lg px-3 py-1.5 font-medium transition"
          :class="activeTutorialTab === 'slot1' ? 'bg-primary text-white shadow-sm' : 'border bg-surface text-text hover:bg-surface-muted'"
        >
          Slot 1: Course History (SIS)
        </button>
        <button
          type="button"
          @click="activeTutorialTab = 'slot2'"
          class="rounded-lg px-3 py-1.5 font-medium transition"
          :class="activeTutorialTab === 'slot2' ? 'bg-primary text-white shadow-sm' : 'border bg-surface text-text hover:bg-surface-muted'"
        >
          Slot 2: Grade Slip (SIS)
        </button>
        <button
          type="button"
          @click="activeTutorialTab = 'slot3'"
          class="rounded-lg px-3 py-1.5 font-medium transition"
          :class="activeTutorialTab === 'slot3' ? 'bg-primary text-white shadow-sm' : 'border bg-surface text-text hover:bg-surface-muted'"
        >
          Slot 3: ID (Back-to-Back) &amp; Specimen
        </button>
      </div>

      <!-- Tab 1: Overview -->
      <div v-if="activeTutorialTab === 'overview'" class="space-y-4 pt-2 text-xs">
        <p class="text-text-muted">
          All 3 required slots must be completed before you can submit the package to staff Document Validation:
        </p>
        <div class="grid gap-3 sm:grid-cols-3">
          <div class="rounded-xl border bg-surface-muted/30 p-3">
            <p class="font-semibold text-text flex items-center gap-1.5">
              <span class="grid size-5 place-items-center rounded bg-primary text-2xs text-white">1</span>
              Slot 1: Course History (PDF)
            </p>
            <p class="mt-1 text-text-muted">Downloaded directly from your TCC SIS student portal and saved as PDF.</p>
          </div>
          <div class="rounded-xl border bg-surface-muted/30 p-3">
            <p class="font-semibold text-text flex items-center gap-1.5">
              <span class="grid size-5 place-items-center rounded bg-primary text-2xs text-white">2</span>
              Slot 2: Grade Slip (PDF)
            </p>
            <p class="mt-1 text-text-muted">Downloaded from TCC SIS — your current Grade Slip (pending/late grades accepted).</p>
          </div>
          <div class="rounded-xl border bg-surface-muted/30 p-3">
            <p class="font-semibold text-text flex items-center gap-1.5">
              <span class="grid size-5 place-items-center rounded bg-primary text-2xs text-white">3</span>
              Slot 3: ID Back-to-Back &amp; Specimen
            </p>
            <p class="mt-1 text-text-muted">A document (PDF or Image) containing your ID front &amp; back side-by-side with 3 handwritten blue ballpen specimen signatures.</p>
          </div>
        </div>
      </div>

      <!-- Tab 2: Slot 1 Course History Guide -->
      <div v-else-if="activeTutorialTab === 'slot1'" class="space-y-3 pt-2 text-xs">
        <h3 class="font-semibold text-text">Slot 1: Downloading Course History from TCC SIS</h3>
        <ol class="list-decimal space-y-2 pl-4 text-text-muted">
          <li>Open and log in to the <strong>TCC Student Information System (SIS)</strong>.</li>
          <li>Navigate to <strong>Academic Records</strong> / <strong>Curriculum Checklist</strong>.</li>
          <li>Click <strong>Print / Download Course History</strong> and choose <strong>Save as PDF</strong>.</li>
          <li>Upload the resulting <code>.pdf</code> file to Slot 1.</li>
        </ol>
        <p class="rounded-lg bg-info-soft/40 border border-info/30 p-2.5 text-info text-2xs">
          💡 Note: Upload is enabled when your batch submission window is open.
        </p>
      </div>

      <!-- Tab 3: Slot 2 Grade Slip Guide -->
      <div v-else-if="activeTutorialTab === 'slot2'" class="space-y-3 pt-2 text-xs">
        <h3 class="font-semibold text-text">Slot 2: Downloading Grade Slip from TCC SIS</h3>
        <ol class="list-decimal space-y-2 pl-4 text-text-muted">
          <li>Log in to your <strong>TCC Student Information System (SIS)</strong> portal.</li>
          <li>Go to your <strong>Grade Reports / Semestral Grades</strong>.</li>
          <li>Download your <strong>current semester Grade Slip</strong> as a <strong>PDF</strong>.</li>
          <li>Upload the <code>.pdf</code> to Slot 2.</li>
        </ol>
        <div class="rounded-lg bg-info-soft/40 border border-info/30 p-3 text-info text-2xs space-y-1">
          <p class="font-semibold flex items-center gap-1.5 text-xs text-text">
            <span>ℹ️</span> Current Grade Slip &amp; Pending Grades:
          </p>
          <p class="text-text-muted">
            Your Grade Slip should be for the current semester. It is <strong>completely okay if some subjects have pending (P) or late grades</strong>, as some instructors post grades later than others. Just make sure to upload your official SIS Grade Slip.
          </p>
        </div>
      </div>

      <!-- Tab 4: Slot 3 ID Back-to-Back & Specimen Guide -->
      <div v-else-if="activeTutorialTab === 'slot3'" class="space-y-4 pt-2 text-xs">
        <div>
          <h3 class="font-semibold text-text text-sm">Slot 3: ID Back-to-Back with 3 Specimen Signatures</h3>
          <p class="mt-1 text-text-muted">Follow these 4 simple steps to prepare your Slot 3 submission file:</p>
        </div>

        <div class="grid gap-4 md:grid-cols-2 items-start">
          <div class="space-y-3">
            <div class="rounded-xl border p-3 bg-surface">
              <p class="font-semibold text-text mb-1 flex items-center gap-1.5">
                <span class="grid size-5 place-items-center rounded bg-primary text-2xs text-white">1</span>
                Photograph Your Student ID
              </p>
              <p class="text-text-muted">Take two clear, flat photos: one of the <strong>Front</strong> and one of the <strong>Back</strong> of your TCC Student ID.</p>
            </div>

            <div class="rounded-xl border p-3 bg-surface">
              <p class="font-semibold text-text mb-1 flex items-center gap-1.5">
                <span class="grid size-5 place-items-center rounded bg-primary text-2xs text-white">2</span>
                Insert into Word / Google Docs
              </p>
              <p class="text-text-muted">Open <strong>Microsoft Word (.docx)</strong> or <strong>Google Docs</strong>, and insert both ID pictures side-by-side on the page.</p>
            </div>

            <div class="rounded-xl border p-3 bg-surface">
              <p class="font-semibold text-text mb-1 flex items-center gap-1.5">
                <span class="grid size-5 place-items-center rounded bg-primary text-2xs text-white">3</span>
                Sign 3 Specimen Signatures in Blue Ballpen
              </p>
              <p class="text-text-muted">Directly below the two ID images, place <strong class="text-primary">3 specimen signatures written with a blue ballpen</strong>.</p>
            </div>

            <div class="rounded-xl border p-3 bg-surface">
              <p class="font-semibold text-text mb-1 flex items-center gap-1.5">
                <span class="grid size-5 place-items-center rounded bg-primary text-2xs text-white">4</span>
                Save as PDF / Image and Upload
              </p>
              <p class="text-text-muted">Save/export the document as <strong>PDF</strong> (or export/screenshot as JPG/PNG) and upload it to Slot 3.</p>
            </div>
          </div>

          <!-- Visual Sample Card -->
          <div class="rounded-xl border bg-surface-muted/20 p-3 text-center">
            <p class="font-semibold text-xs text-text mb-2">Sample Required Layout:</p>
            <div class="overflow-hidden rounded-lg border bg-white p-2 shadow-sm">
              <img
                src="/images/student-id-b2b-sample.jpg"
                alt="Student ID Back-to-Back with 3 Specimen Signatures Sample"
                class="mx-auto max-h-64 object-contain rounded"
              />
            </div>
            <p class="mt-2 text-2xs text-text-muted">
              Top: ID Front &amp; Back side-by-side<br/>
              Bottom: 3 handwritten blue-ink specimen signatures
            </p>
          </div>
        </div>
      </div>

      <template #footer="{ close }">
        <button class="rounded-md bg-primary px-4 py-2 text-xs font-medium text-white shadow-sm" @click="close">
          Got it, close guide
        </button>
      </template>
    </AppDialog>
  </div>
</template>
