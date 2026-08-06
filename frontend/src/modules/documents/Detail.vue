<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useQueryClient } from "@tanstack/vue-query";
import { IconArrowLeft, IconFile, IconScan, IconShieldExclamation } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import CardSkeleton from "@/components/ui/CardSkeleton.vue";
import EmptyState from "@/components/ui/EmptyState.vue";
import { useDocumentDetail, useDocumentPackage } from "@/composables/useDocuments";
import { apiFetch, queryKeys } from "@/api";
import type { DocSubmissionDetail } from "@/api";
import { getAuthToken } from "@/auth/session";
import { toast } from "@/composables/useToast";
import { scheduleUndo } from "@/composables/useUndo";
import { useBreadcrumbTail } from "@/composables/useBreadcrumbTail";
import { extractQrPayload, type QrExtraction } from "@/modules/requirements/idQr";

function documentStatusClass(status: string) {
  if (status === "pending_review") {
    return "rounded-full bg-info-soft px-1.5 py-0.5 text-micro font-semibold capitalize text-info";
  }
  if (status === "resubmission") {
    return "rounded-full bg-warning-soft px-1.5 py-0.5 text-micro font-semibold capitalize text-warning";
  }
  if (status === "approved") {
    return "rounded-full bg-success-soft px-1.5 py-0.5 text-micro font-semibold capitalize text-success";
  }
  if (status === "rejected") {
    return "rounded-full bg-danger-soft px-1.5 py-0.5 text-micro font-semibold capitalize text-danger";
  }
  return "rounded-full bg-surface-muted px-1.5 py-0.5 text-micro font-medium capitalize text-text";
}

const route = useRoute();
const router = useRouter();
const queryClient = useQueryClient();
const { setBreadcrumbTail } = useBreadcrumbTail();
const notes = ref("");
const pendingDecision = ref<string | null>(null);
const primaryPreviewUrl = ref<string | null>(null);
const secondaryPreviewUrl = ref<string | null>(null);
const previewError = ref("");
let primaryObjectUrl: string | null = null;
let secondaryObjectUrl: string | null = null;

const isPackageRoute = computed(
  () => route.params.granteeId != null && route.params.batchId != null,
);

const packageGranteeId = computed(() =>
  isPackageRoute.value ? String(route.params.granteeId) : null,
);
const packageBatchId = computed(() =>
  isPackageRoute.value ? String(route.params.batchId) : null,
);

const { pkg, query: packageQuery } = useDocumentPackage(packageGranteeId, packageBatchId);

const legacyId = computed(() =>
  !isPackageRoute.value && route.params.id != null ? String(route.params.id) : null,
);

const legacyBootstrapping = ref(false);

watch(
  legacyId,
  async (id) => {
    if (!id || isPackageRoute.value) return;
    legacyBootstrapping.value = true;
    try {
      const payload = await apiFetch<{ data: DocSubmissionDetail }>(
        `/api/document-submissions/${id}`,
      );
      const doc = payload.data;
      if (doc.grantee_id && doc.batch_id) {
        await router.replace({
          path: `/app/documents/package/${doc.grantee_id}/${doc.batch_id}`,
          query: {
            ...route.query,
            slot: doc.slot_key || undefined,
          },
        });
        return;
      }
      toast.error("This submission is not part of a vault package.");
    } catch (error) {
      toast.error(error instanceof Error ? error.message : "Unable to load submission.");
    } finally {
      legacyBootstrapping.value = false;
    }
  },
  { immediate: true },
);

const activeSlot = ref<string | null>(null);

watch(
  [pkg, () => route.query.slot],
  ([packageData, slotQuery]) => {
    if (!packageData?.documents?.length) return;
    const requested =
      typeof slotQuery === "string" && slotQuery
        ? slotQuery
        : activeSlot.value;
    const match = packageData.documents.find((doc) => doc.slot_key === requested);
    activeSlot.value = match?.slot_key ?? packageData.documents[0]?.slot_key ?? null;
  },
  { immediate: true },
);

const activeTabDoc = computed(
  () => pkg.value?.documents.find((doc) => doc.slot_key === activeSlot.value) ?? null,
);

const activeDocId = computed(() =>
  activeTabDoc.value?.id != null ? String(activeTabDoc.value.id) : null,
);

const { item, query: detailQuery, reviewMutation } = useDocumentDetail(activeDocId);

const metadataDisplay = computed(() => {
  const payload = item.value?.metadata_payload;
  if (payload == null || (typeof payload === "object" && Object.keys(payload).length === 0)) {
    return "No metadata recorded.";
  }
  return JSON.stringify(payload, null, 2);
});

type PdfMetaFields = {
  format?: string | null;
  title?: string | null;
  author?: string | null;
  creator?: string | null;
  producer?: string | null;
  creationDate?: string | null;
  modDate?: string | null;
  encryption?: string | null;
  is_encrypted?: boolean;
  page_count?: number;
  engine?: string;
};

const pdfMetadataScan = computed(() => {
  const payload = item.value?.metadata_payload;
  if (!payload || typeof payload !== "object") return null;

  const analysis =
    (payload.pdf_metadata_analysis as Record<string, unknown> | undefined) ??
    ((payload.pdf_document as Record<string, unknown> | undefined)?.pdf_metadata_analysis as
      | Record<string, unknown>
      | undefined);
  const fieldsRaw =
    (payload.pdf_metadata as PdfMetaFields | undefined) ??
    ((payload.pdf_document as Record<string, unknown> | undefined)?.pdf_metadata as
      | PdfMetaFields
      | undefined) ??
    (analysis?.fields as PdfMetaFields | undefined);

  if (!fieldsRaw && !analysis) return null;

  const fields = (fieldsRaw && typeof fieldsRaw === "object" ? fieldsRaw : {}) as PdfMetaFields;
  const reasons = Array.isArray(analysis?.reasons)
    ? (analysis.reasons as string[])
    : [];
  const notes = Array.isArray(analysis?.notes)
    ? (analysis.notes as string[])
    : [];
  const suspicious = Boolean(analysis?.suspicious);
  const source = typeof analysis?.source === "string" ? analysis.source : "unknown";
  const hasAnyField = Object.values(fields).some(
    (value) => value !== null && value !== undefined && value !== "",
  );

  if (!hasAnyField && source === "unavailable") return null;

  return { fields, reasons, notes, suspicious, source };
});

const pdfMetadataLines = computed(() => {
  const scan = pdfMetadataScan.value;
  if (!scan) return [];
  const { fields } = scan;
  return [
    ["Format", fields.format],
    ["Title", fields.title],
    ["Author", fields.author],
    ["Creator", fields.creator],
    ["Producer", fields.producer],
    ["Created", fields.creationDate],
    ["Modified", fields.modDate],
    ["Pages", fields.page_count != null ? String(fields.page_count) : null],
    ["Engine", fields.engine],
  ].filter(([, value]) => value != null && String(value).trim() !== "") as [string, string][];
});

const ocrCourses = computed(() => {
  const courses = item.value?.ocr_payload?.result?.courses;
  return Array.isArray(courses) ? courses : [];
});

const ocrTerms = computed(() => {
  const terms = item.value?.ocr_payload?.result?.terms;
  if (!Array.isArray(terms)) return [];
  return terms.filter(
    (term) =>
      term &&
      typeof term === "object" &&
      Array.isArray(term.courses) &&
      term.courses.length > 0,
  );
});

const hasTermBlocks = computed(() => ocrTerms.value.length > 0);

const isGradeDocument = computed(() => {
  const slot = item.value?.slot_key ?? "";
  return slot === "grade_slip" || slot === "course_history";
});

const isCourseHistory = computed(() => item.value?.slot_key === "course_history");

const gradeSummary = computed(() => {
  const fromOcr = item.value?.ocr_payload?.result?.grade_summary;
  const fromMeta = item.value?.metadata_payload?.grade_summary;
  const raw =
    fromOcr && typeof fromOcr === "object"
      ? fromOcr
      : fromMeta && typeof fromMeta === "object"
        ? fromMeta
        : null;
  if (!raw || typeof raw !== "object") return null;
  const summary = raw as Record<string, unknown>;
  const blank = Number(summary.blank_count ?? 0);
  const pending = Number(summary.pending_count ?? 0);
  const failed = Number(summary.numeric_failed_count ?? summary.failed_count ?? 0);
  const dropped = Number(summary.dropped_count ?? 0);
  const retention = Number(summary.retention_count ?? failed + dropped);
  const maxFailed = Number(summary.max_failed ?? 0);
  const overLimit = Boolean(summary.over_limit) || (maxFailed > 0 && retention >= maxFailed);
  const message = typeof summary.message === "string" ? summary.message : null;
  const gradeSlipTerm =
    typeof summary.grade_slip_term === "string" ? summary.grade_slip_term : null;
  const termsMissing = Boolean(summary.terms_missing_warning);
  const enrollmentSlipWarning = Boolean(summary.enrollment_slip_warning);
  const gradeMismatchCount = Number(summary.grade_mismatch_count ?? 0);
  const gradeMismatches = Array.isArray(summary.grade_mismatches)
    ? (summary.grade_mismatches as Array<{ code?: string }>).map((m) => String(m.code ?? "")).filter(Boolean)
    : [];
  return {
    blank,
    pending,
    failed,
    dropped,
    retention,
    maxFailed,
    overLimit,
    message,
    gradeSlipTerm,
    termsMissing,
    enrollmentSlipWarning,
    gradeMismatchCount,
    gradeMismatches,
  };
});

const gradeBlankHelp = computed(() => {
  if (!isGradeDocument.value || !gradeSummary.value) return "";
  if (isCourseHistory.value) {
    return "Course History blanks on the Grade Slip term and any newer enrollment term are Pending (not retention). Older-term blanks count as Dropped. Upload the last graded Grade Slip (not an empty current-enrollment slip) so pending terms are anchored correctly.";
  }
  return "Upload the last Grade Slip that already has grades. Blank grades on Grade Slip are review-only and are not counted toward eligibility.";
});

const gradeCountsLine = computed(() => {
  if (!gradeSummary.value) return "";
  const { failed, dropped, blank, pending, gradeSlipTerm } = gradeSummary.value;
  if (isCourseHistory.value) {
    const termHint =
      ocrTerms.value.length > 1
        ? ` · ${ocrTerms.value.length} program/term blocks`
        : "";
    const gsHint = gradeSlipTerm ? ` · GS term ${gradeSlipTerm}` : "";
    return `Grade counts: ${failed} failed · ${dropped} dropped · ${pending} pending${termHint}${gsHint}`;
  }
  return `Grade counts: ${failed} failed · ${dropped} dropped · ${blank} blank (blanks ignored for eligibility)`;
});

function termHeaderLabel(term: {
  academic_term?: string | null;
  program_raw?: string | null;
  program_code?: string | null;
  year_level?: string | null;
  enrollment_status?: string | null;
}): string {
  const termPart = [term.academic_term, term.program_raw || term.program_code]
    .filter((v) => typeof v === "string" && v.trim() !== "")
    .join(" ");
  const yearPart =
    typeof term.year_level === "string" && term.year_level.trim() !== ""
      ? term.year_level.trim().startsWith("Year")
        ? term.year_level.trim()
        : `Year ${term.year_level.trim()}`
      : "";
  const status =
    typeof term.enrollment_status === "string" && term.enrollment_status.trim() !== ""
      ? term.enrollment_status.trim()
      : "";
  if (termPart && yearPart) {
    return [termPart, "—", yearPart, status].filter(Boolean).join(" ");
  }
  return [termPart || yearPart || "Term", status].filter(Boolean).join(" · ");
}

function courseRemarkDisplay(row: {
  remarks?: string | null;
  fail_reason?: string | null;
  grade?: string | null;
  pass_grade?: number | string | null;
}): string {
  if (row.remarks) return row.remarks;
  if (row.fail_reason === "dropped") return "Dropped";
  if (row.fail_reason === "pending") return "Pending";
  if (row.fail_reason === "numeric_fail") return "Failed";
  if (row.fail_reason === "blank") return "—";

  // TCC SIS CH often omits Remarks; derive Passed/Failed for staff UI only.
  const gradeText = String(row.grade ?? "").trim();
  if (gradeText !== "" && !Number.isNaN(Number(gradeText))) {
    const grade = Number(gradeText);
    const pass =
      row.pass_grade !== null &&
      row.pass_grade !== undefined &&
      row.pass_grade !== "" &&
      !Number.isNaN(Number(row.pass_grade))
        ? Number(row.pass_grade)
        : 3.0;
    return grade <= pass ? "Passed" : "Failed";
  }

  return "—";
}

function isDroppedRow(row: { fail_reason?: string | null; remarks?: string | null }): boolean {
  if (row.fail_reason === "dropped") return true;
  if (row.fail_reason === "pending" || row.fail_reason === "blank") return false;
  return /dropped|drp|withdrawn/i.test(String(row.remarks ?? ""));
}

function isPendingRow(row: { fail_reason?: string | null }): boolean {
  return row.fail_reason === "pending";
}

function isFailedRow(row: { fail_reason?: string | null; counts_as_fail?: boolean }): boolean {
  return row.fail_reason === "numeric_fail" || (row.counts_as_fail === true && row.fail_reason !== "dropped");
}

function gradeCellBadge(row: {
  fail_reason?: string | null;
  grade?: string | null;
  counts_as_fail?: boolean;
}): { label: string; className: string } | null {
  if (isPendingRow(row)) {
    return { label: "Pending", className: "bg-warning-soft text-warning" };
  }
  if (isDroppedRow(row) && !row.grade) {
    return { label: "Dropped", className: "bg-danger-soft text-danger" };
  }
  if (isFailedRow(row) && row.grade) {
    return { label: "Failed", className: "bg-danger-soft text-danger" };
  }
  if (row.fail_reason === "blank" && !row.grade) {
    return { label: "Blank", className: "bg-surface-muted text-text-muted" };
  }
  return null;
}

const formattedOcrText = computed(() => {
  return (
    item.value?.ocr_payload?.result?.formatted_table_text ||
    item.value?.extracted_text ||
    "No readable text detected."
  );
});

const isSchoolId = computed(() => item.value?.slot_key === "school_id");

function coalesceStoredQrExtraction(
  stored: unknown,
  fallbackPayload: string | null,
): QrExtraction {
  const parsed = extractQrPayload(fallbackPayload);
  if (!stored || typeof stored !== "object") return parsed;

  const row = stored as Record<string, unknown>;
  const queryRaw = row.query;
  const query: Record<string, string> = {};
  if (queryRaw && typeof queryRaw === "object" && !Array.isArray(queryRaw)) {
    for (const [key, value] of Object.entries(queryRaw as Record<string, unknown>)) {
      if (typeof value === "string" && value.trim()) query[key] = value;
    }
  }

  const kind =
    row.kind === "url" || row.kind === "text" ? row.kind : parsed.kind;
  const host = typeof row.host === "string" ? row.host : parsed.host;
  const path = typeof row.path === "string" ? row.path : parsed.path;
  const studentId =
    typeof row.student_id === "string" ? row.student_id : parsed.student_id;
  const raw = typeof row.raw === "string" ? row.raw : parsed.raw;
  const parseable =
    typeof row.parseable === "boolean" ? row.parseable : parsed.parseable;

  return {
    parseable,
    kind,
    raw,
    scheme: typeof row.scheme === "string" ? row.scheme : parsed.scheme,
    host,
    path,
    query: Object.keys(query).length ? query : parsed.query,
    student_id: studentId,
  };
}

const schoolIdOcrPanel = computed(() => {
  if (!isSchoolId.value || !item.value) return null;
  const meta = (item.value.metadata_payload ?? {}) as Record<string, unknown>;
  const ocrMeta = (meta.ocr ?? {}) as Record<string, unknown>;
  const ocrPayload = (item.value.ocr_payload ?? {}) as Record<string, unknown>;

  const extractedName =
    (typeof ocrMeta.extracted_name === "string" && ocrMeta.extracted_name) ||
    (typeof ocrPayload.extracted_name === "string" && ocrPayload.extracted_name) ||
    null;
  const extractedStudentId =
    (typeof ocrMeta.extracted_student_id === "string" && ocrMeta.extracted_student_id) ||
    (typeof ocrPayload.extracted_student_id === "string" && ocrPayload.extracted_student_id) ||
    null;

  const qrPayload = typeof meta.qr_payload === "string" ? meta.qr_payload : null;
  const qrFound = meta.qr_found === true || Boolean(qrPayload);
  const qrValid = meta.qr_valid === true;
  const storedExtraction =
    meta.qr_extraction ??
    (ocrPayload.qr_extraction as unknown) ??
    null;
  const qrExtraction = coalesceStoredQrExtraction(storedExtraction, qrPayload);
  const qrQueryEntries = Object.entries(qrExtraction.query);
  const hasExtractedFields =
    qrFound &&
    Boolean(
      qrExtraction.host ||
        qrExtraction.path ||
        qrExtraction.student_id ||
        qrQueryEntries.length,
    );

  const ayOcr =
    (typeof meta.academic_year_ocr === "string" && meta.academic_year_ocr) ||
    (typeof ocrPayload.academic_year_ocr === "string" && ocrPayload.academic_year_ocr) ||
    null;
  const ayExpected =
    (typeof meta.academic_year_expected === "string" && meta.academic_year_expected) ||
    (typeof ocrPayload.academic_year_expected === "string" && ocrPayload.academic_year_expected) ||
    null;
  const ayMatch =
    typeof meta.academic_year_match === "boolean"
      ? meta.academic_year_match
      : typeof ocrPayload.academic_year_match === "boolean"
        ? ocrPayload.academic_year_match
        : null;

  return {
    extractedName,
    extractedStudentId,
    qrPayload,
    qrFound,
    qrValid,
    qrExtraction,
    qrQueryEntries,
    hasExtractedFields,
    ayOcr,
    ayExpected,
    ayMatch,
  };
});

function selectSlot(slotKey: string | null) {
  if (!slotKey || slotKey === activeSlot.value) return;
  activeSlot.value = slotKey;
  void router.replace({
    path: route.path,
    query: { ...route.query, slot: slotKey },
  });
}

watch(
  () => detailQuery.data.value,
  (data) => {
    if (data) notes.value = data.review_notes || "";
  },
);

watch(
  () => item.value,
  (doc) => {
    void loadPreviews(doc);
  },
  { immediate: true },
);

onBeforeUnmount(() => {
  revokePreviewUrls();
  setBreadcrumbTail(null);
});

function revokePreviewUrls() {
  if (primaryObjectUrl) {
    URL.revokeObjectURL(primaryObjectUrl);
    primaryObjectUrl = null;
  }
  if (secondaryObjectUrl) {
    URL.revokeObjectURL(secondaryObjectUrl);
    secondaryObjectUrl = null;
  }
  primaryPreviewUrl.value = null;
  secondaryPreviewUrl.value = null;
}

async function fetchAuthBlob(url: string | null | undefined): Promise<string | null> {
  if (!url) return null;
  const response = await fetch(url, {
    headers: {
      Accept: "*/*",
      Authorization: `Bearer ${getAuthToken()}`,
    },
    credentials: "include",
  });
  if (!response.ok) return null;
  const blob = await response.blob();
  return URL.createObjectURL(blob);
}

async function loadPreviews(
  doc: {
    file_url?: string | null;
    secondary_file_url?: string | null;
  } | null,
) {
  revokePreviewUrls();
  previewError.value = "";
  if (!doc) return;

  try {
    // Auth blob URLs are same-origin to the SPA and bypass X-Frame-Options on the API host.
    primaryObjectUrl = await fetchAuthBlob(doc.file_url);
    primaryPreviewUrl.value = primaryObjectUrl;
    if (!primaryPreviewUrl.value) {
      previewError.value = "Unable to load file preview.";
    }
  } catch {
    previewError.value = "Unable to load file preview.";
  }

  if (doc.secondary_file_url) {
    try {
      secondaryObjectUrl = await fetchAuthBlob(doc.secondary_file_url);
      secondaryPreviewUrl.value = secondaryObjectUrl;
    } catch {
      secondaryPreviewUrl.value = null;
    }
  }
}

async function onPrimaryPreviewError() {
  previewError.value = "Unable to load file preview.";
}

async function decide(decision: string) {
  if (!item.value || !activeDocId.value || pendingDecision.value) return;
  const previous = { ...item.value };
  const docId = activeDocId.value;
  const label =
    decision === "approved" ? "Approve" : decision === "rejected" ? "Reject" : "Return";

  pendingDecision.value = decision;
  const result = await scheduleUndo(`document-decide-${docId}`, {
    message: `${label} scheduled`,
    description: "Undo within 5 seconds to cancel this decision.",
    optimistic: () => {
      queryClient.setQueryData(queryKeys.document(docId), {
        ...previous,
        status: decision,
        review_notes: notes.value,
      });
      return () => {
        queryClient.setQueryData(queryKeys.document(docId), previous);
      };
    },
    commit: async () => {
      const payload = await reviewMutation.mutateAsync({
        decision,
        notes: notes.value,
      });
      return payload.data;
    },
    onUndo: () => {
      toast.info("Decision cancelled");
    },
    onError: (error: unknown) => {
      toast.error(error instanceof Error ? error.message : "Decision failed.");
    },
  });
  if (result) {
    const successMessage =
      decision === "approved"
        ? "Document approved"
        : decision === "rejected"
          ? "Document rejected"
          : "Document returned — resubmission requested";
    toast.success(successMessage);
  }
  pendingDecision.value = null;
}

const pageLoading = computed(
  () =>
    legacyBootstrapping.value ||
    (isPackageRoute.value && packageQuery.isLoading.value) ||
    (Boolean(activeDocId.value) && detailQuery.isLoading.value && !item.value),
);

const pageError = computed(() => {
  if (isPackageRoute.value && packageQuery.isError.value) return packageQuery.error.value;
  if (detailQuery.isError.value && !item.value) return detailQuery.error.value;
  return null;
});

watch(
  () => pkg.value?.student_name,
  (name) => {
    if (!isPackageRoute.value) {
      setBreadcrumbTail(null);
      return;
    }
    setBreadcrumbTail(name || "Package");
  },
  { immediate: true },
);
</script>

<template>
  <div v-if="pkg && item">
    <RouterLink
      to="/app/documents"
      class="mb-3 inline-flex items-center gap-1 text-xs text-text-muted"
    >
      <IconArrowLeft :size="14" />Validation queue
    </RouterLink>
    <PageHeader
      :title="pkg.student_name"
      :description="`${pkg.student_id} · ${pkg.batch_name || `Batch #${pkg.batch_id}`} · ${pkg.progress} slots`"
    />

    <div class="mb-4 flex flex-wrap gap-1 border-b" role="tablist" aria-label="Submitted documents">
      <button
        v-for="doc in pkg.documents"
        :key="doc.id"
        type="button"
        role="tab"
        :aria-selected="activeSlot === doc.slot_key"
        :class="[
          'inline-flex items-center gap-1.5 border-b-2 px-3 py-2 text-xs',
          activeSlot === doc.slot_key
            ? 'border-primary text-primary'
            : 'border-transparent text-text-muted',
        ]"
        @click="selectSlot(doc.slot_key)"
      >
        {{ doc.tab_label }}
        <span :class="documentStatusClass(doc.status)">
          {{ doc.status.replaceAll("_", " ") }}
        </span>
      </button>
    </div>

    <section class="grid gap-4 xl:grid-cols-[1.4fr_1fr]">
      <div class="rounded-lg border bg-surface p-4">
        <h2 class="flex items-center gap-2 text-sm font-semibold">
          <IconFile :size="17" />File preview
        </h2>
        <p v-if="previewError" class="mt-3 text-xs text-danger">{{ previewError }}</p>
        <div class="mt-4 grid gap-3" :class="secondaryPreviewUrl ? 'lg:grid-cols-2' : ''">
          <figure>
            <figcaption class="mb-2 text-xs font-semibold text-text-muted">
              {{ item.slot_key === "school_id" ? "Front" : item.original_name }}
            </figcaption>
            <iframe
              v-if="item.mime_type === 'application/pdf' && primaryPreviewUrl"
              :src="primaryPreviewUrl"
              class="h-[34rem] w-full rounded-md border"
              @error="onPrimaryPreviewError"
            />
            <img
              v-else-if="primaryPreviewUrl"
              :src="primaryPreviewUrl"
              :alt="item.original_name"
              class="max-h-[34rem] w-full rounded-md bg-surface-muted object-contain"
              @error="onPrimaryPreviewError"
            />
            <p v-else class="rounded-md border bg-surface-muted p-6 text-center text-xs text-text-muted">
              Preview unavailable.
            </p>
          </figure>
          <figure v-if="secondaryPreviewUrl">
            <figcaption class="mb-2 text-xs font-semibold text-text-muted">Back</figcaption>
            <img
              :src="secondaryPreviewUrl"
              :alt="item.secondary_original_name || 'School ID back'"
              class="max-h-[34rem] w-full rounded-md bg-surface-muted object-contain"
            />
          </figure>
        </div>
      </div>
      <div class="space-y-4">
        <article class="rounded-lg border bg-surface p-4">
          <h2 class="flex items-center gap-2 text-sm font-semibold">
            <IconScan :size="17" />OCR extraction
          </h2>
          <p v-if="item.ocr_confidence !== null && item.ocr_confidence !== undefined" class="mt-3 text-xs text-text-muted">
            Average confidence: {{ item.ocr_confidence.toFixed(1) }}%
          </p>
          <div
            v-if="schoolIdOcrPanel"
            class="mt-3 space-y-3"
            aria-label="School ID OCR summary"
          >
            <div class="flex flex-wrap gap-2">
              <span class="inline-flex items-center rounded-md bg-surface-muted px-2 py-1 text-xs">
                Name:
                <span class="ml-1 font-medium text-text">{{ schoolIdOcrPanel.extractedName || "—" }}</span>
              </span>
              <span class="inline-flex items-center rounded-md bg-surface-muted px-2 py-1 text-xs">
                Student ID:
                <span class="ml-1 font-medium text-text">{{ schoolIdOcrPanel.extractedStudentId || "—" }}</span>
              </span>
            </div>
            <div
              class="rounded-md border border-black/5 bg-surface-muted/50 p-3"
              aria-label="QR extraction"
            >
              <p class="text-xs font-semibold text-text">QR extraction</p>
              <template v-if="!schoolIdOcrPanel.qrFound">
                <p class="mt-2 text-xs text-text-muted">No QR extracted</p>
                <p class="mt-1 text-micro text-text-muted">
                  Soft flag only — students can submit without a readable QR.
                </p>
              </template>
              <template v-else>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                  <span
                    class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium"
                    :class="
                      schoolIdOcrPanel.qrValid
                        ? 'bg-success-soft text-success'
                        : 'bg-warning-soft text-warning'
                    "
                  >
                    {{ schoolIdOcrPanel.qrValid ? "Valid TCC domain" : "Non-TCC domain" }}
                  </span>
                  <span
                    v-if="schoolIdOcrPanel.qrExtraction.kind"
                    class="inline-flex items-center rounded-md bg-surface px-2 py-1 text-xs text-text-muted"
                  >
                    {{ schoolIdOcrPanel.qrExtraction.kind === "url" ? "URL payload" : "Text payload" }}
                  </span>
                </div>
                <div class="mt-3 space-y-2">
                  <div>
                    <p class="text-micro font-medium uppercase tracking-wide text-text-muted">Raw payload</p>
                    <p
                      class="mt-1 break-all font-mono text-xs text-text"
                      :title="schoolIdOcrPanel.qrPayload || undefined"
                    >
                      {{ schoolIdOcrPanel.qrPayload || "—" }}
                    </p>
                  </div>
                  <div v-if="schoolIdOcrPanel.hasExtractedFields" class="space-y-2">
                    <p class="text-micro font-medium uppercase tracking-wide text-text-muted">
                      Extracted fields
                    </p>
                    <div class="flex flex-wrap gap-2">
                      <span
                        v-if="schoolIdOcrPanel.qrExtraction.host"
                        class="inline-flex items-center rounded-md bg-surface px-2 py-1 text-xs"
                      >
                        Host:
                        <span class="ml-1 font-medium text-text">{{ schoolIdOcrPanel.qrExtraction.host }}</span>
                      </span>
                      <span
                        v-if="schoolIdOcrPanel.qrExtraction.path"
                        class="inline-flex max-w-full items-center rounded-md bg-surface px-2 py-1 text-xs"
                      >
                        Path:
                        <span
                          class="ml-1 truncate font-mono font-medium text-text"
                          :title="schoolIdOcrPanel.qrExtraction.path"
                        >
                          {{ schoolIdOcrPanel.qrExtraction.path }}
                        </span>
                      </span>
                      <span
                        v-if="schoolIdOcrPanel.qrExtraction.student_id"
                        class="inline-flex items-center rounded-md bg-surface px-2 py-1 text-xs"
                      >
                        Student ID (QR):
                        <span class="ml-1 font-medium text-text">
                          {{ schoolIdOcrPanel.qrExtraction.student_id }}
                        </span>
                      </span>
                      <span
                        v-for="[key, value] in schoolIdOcrPanel.qrQueryEntries"
                        :key="`qr-q-${key}`"
                        class="inline-flex max-w-full items-center rounded-md bg-surface px-2 py-1 text-xs"
                      >
                        {{ key }}:
                        <span class="ml-1 truncate font-medium text-text" :title="value">{{ value }}</span>
                      </span>
                    </div>
                  </div>
                  <p
                    v-else
                    class="text-xs text-text-muted"
                  >
                    Payload stored, but no structured fields could be parsed.
                  </p>
                </div>
              </template>
            </div>
            <div class="rounded-md border border-black/5 bg-surface-muted/50 p-3">
              <p class="text-xs font-semibold text-text">Academic year</p>
              <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                <span class="inline-flex items-center rounded-md bg-surface px-2 py-1">
                  OCR:
                  <span class="ml-1 font-medium">{{ schoolIdOcrPanel.ayOcr || "—" }}</span>
                </span>
                <span class="inline-flex items-center rounded-md bg-surface px-2 py-1">
                  Organization:
                  <span class="ml-1 font-medium">{{ schoolIdOcrPanel.ayExpected || "—" }}</span>
                </span>
                <span
                  class="inline-flex items-center rounded-md px-2 py-1 font-medium"
                  :class="
                    schoolIdOcrPanel.ayMatch === true
                      ? 'bg-success-soft text-success'
                      : schoolIdOcrPanel.ayMatch === false
                        ? 'bg-danger-soft text-danger'
                        : 'bg-warning-soft text-warning'
                  "
                >
                  {{
                    schoolIdOcrPanel.ayMatch === true
                      ? "Match"
                      : schoolIdOcrPanel.ayMatch === false
                        ? "Mismatch"
                        : "Incomplete"
                  }}
                </span>
              </div>
              <p class="mt-2 text-micro text-text-muted">
                Soft flag only — use Return if QR or academic year needs correction.
              </p>
            </div>
          </div>
          <div
            v-if="isCourseHistory && gradeSummary && gradeSummary.gradeMismatchCount > 0"
            class="mt-3 rounded-md border border-warning/40 bg-warning-soft p-3 text-xs text-warning"
            role="status"
          >
            Staff flag: Course History blank but Grade Slip has a grade for
            {{ gradeSummary.gradeMismatches.join(", ") || `${gradeSummary.gradeMismatchCount} course(s)` }}.
          </div>
          <div
            v-if="isGradeDocument && gradeSummary?.overLimit"
            class="mt-3 rounded-md border border-danger/40 bg-danger-soft p-3 text-sm text-danger"
            role="alert"
          >
            <p class="font-semibold">
              Does not pass retention
              <span v-if="item.student_name"> — {{ item.student_name }}</span>
            </p>
            <p class="mt-1 text-xs">
              {{
                gradeSummary.message ||
                `Retention count ${gradeSummary.retention} (failed ${gradeSummary.failed} + dropped ${gradeSummary.dropped})${
                  gradeSummary.maxFailed ? ` meets or exceeds max ${gradeSummary.maxFailed}` : ""
                }.`
              }}
            </p>
          </div>
          <div
            v-else-if="isGradeDocument && gradeSummary?.enrollmentSlipWarning"
            class="mt-3 rounded-md border border-warning/40 bg-warning-soft p-3 text-xs text-warning"
            role="status"
          >
            {{
              gradeSummary.message ||
              "This Grade Slip looks like a current-enrollment slip with no grades. Ask for the last graded Grade Slip."
            }}
          </div>
          <div
            v-else-if="isCourseHistory && gradeSummary?.termsMissing"
            class="mt-3 rounded-md border border-warning/40 bg-warning-soft p-3 text-xs text-warning"
            role="status"
          >
            Term headers not detected — blanks treated as pending; re-check the Course History PDF.
          </div>
          <div
            v-if="isGradeDocument && gradeSummary"
            class="mt-3 flex flex-wrap gap-2"
            aria-label="Grade summary indicators"
          >
            <span
              v-if="isCourseHistory"
              class="inline-flex items-center rounded-md px-2 py-1 text-xs"
              :class="
                gradeSummary.pending > 0
                  ? 'bg-warning-soft text-warning'
                  : 'bg-surface-muted text-text-muted'
              "
            >
              Pending: {{ gradeSummary.pending }}
            </span>
            <span
              v-if="!isCourseHistory || gradeSummary.blank > 0"
              class="inline-flex items-center rounded-md px-2 py-1 text-xs"
              :class="
                gradeSummary.blank > 0
                  ? 'bg-warning-soft text-warning'
                  : 'bg-surface-muted text-text-muted'
              "
            >
              Blank grades: {{ gradeSummary.blank }}
            </span>
            <span
              class="inline-flex items-center rounded-md px-2 py-1 text-xs"
              :class="
                gradeSummary.failed > 0
                  ? 'bg-danger-soft text-danger'
                  : 'bg-surface-muted text-text-muted'
              "
            >
              Failed: {{ gradeSummary.failed }}
            </span>
            <span
              class="inline-flex items-center rounded-md px-2 py-1 text-xs"
              :class="
                gradeSummary.dropped > 0
                  ? 'bg-danger-soft text-danger'
                  : 'bg-surface-muted text-text-muted'
              "
            >
              Dropped: {{ gradeSummary.dropped }}
            </span>
          </div>
          <p v-if="isGradeDocument && gradeSummary" class="mt-2 text-micro text-text-muted">
            {{ gradeBlankHelp }}
          </p>
          <div v-if="hasTermBlocks" class="mt-3 max-h-72 space-y-3 overflow-auto">
            <div
              v-for="(term, tIdx) in ocrTerms"
              :key="`${term.academic_term ?? 'term'}-${term.program_code ?? tIdx}`"
              class="overflow-hidden rounded border"
            >
              <div
                class="sticky top-0 z-[1] border-b bg-surface-muted px-2 py-1.5 text-xs font-semibold text-text"
              >
                {{ termHeaderLabel(term) }}
                <span
                  v-if="term.program_code"
                  class="ml-2 font-normal text-text-muted"
                >pass ≤ {{ term.pass_grade ?? "—" }}</span>
              </div>
              <table class="w-full min-w-[36rem] border-collapse text-left text-xs">
                <thead class="bg-surface-muted/70">
                  <tr>
                    <th class="border-b px-2 py-1.5 font-semibold">Code</th>
                    <th class="border-b px-2 py-1.5 font-semibold">Description</th>
                    <th class="border-b px-2 py-1.5 font-semibold">Units</th>
                    <th class="border-b px-2 py-1.5 font-semibold">Grade</th>
                    <th class="border-b px-2 py-1.5 font-semibold">Instructor</th>
                    <th class="border-b px-2 py-1.5 font-semibold">Remarks</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="(row, idx) in term.courses || []"
                    :key="`${term.academic_term}-${row.code ?? 'row'}-${idx}`"
                    class="odd:bg-surface even:bg-surface-muted/40"
                  >
                    <td class="border-b border-black/5 px-2 py-1 align-top whitespace-nowrap">{{ row.code || "—" }}</td>
                    <td class="border-b border-black/5 px-2 py-1 align-top">{{ row.description || "—" }}</td>
                    <td class="border-b border-black/5 px-2 py-1 align-top">{{ row.units || "—" }}</td>
                    <td
                      class="border-b border-black/5 px-2 py-1 align-top font-medium"
                      :class="!row.grade ? 'text-warning' : isFailedRow(row) ? 'text-danger' : ''"
                    >
                      {{ row.grade || "—" }}
                      <span
                        v-if="gradeCellBadge(row)"
                        class="ml-1 inline-flex rounded px-1 py-0.5 text-[10px] font-medium"
                        :class="gradeCellBadge(row)?.className"
                      >{{ gradeCellBadge(row)?.label }}</span>
                    </td>
                    <td class="border-b border-black/5 px-2 py-1 align-top">{{ row.instructor || "—" }}</td>
                    <td class="border-b border-black/5 px-2 py-1 align-top">{{ courseRemarkDisplay(row) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div v-else-if="ocrCourses.length > 0" class="mt-3 max-h-72 overflow-auto rounded border">
            <table class="w-full min-w-[36rem] border-collapse text-left text-xs">
              <thead class="sticky top-0 bg-surface-muted">
                <tr>
                  <th class="border-b px-2 py-1.5 font-semibold">Code</th>
                  <th class="border-b px-2 py-1.5 font-semibold">Description</th>
                  <th class="border-b px-2 py-1.5 font-semibold">Units</th>
                  <th class="border-b px-2 py-1.5 font-semibold">Grade</th>
                  <th class="border-b px-2 py-1.5 font-semibold">Instructor</th>
                  <th class="border-b px-2 py-1.5 font-semibold">Remarks</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="(row, idx) in ocrCourses"
                  :key="`${row.code ?? 'row'}-${idx}`"
                  class="odd:bg-surface even:bg-surface-muted/40"
                >
                  <td class="border-b border-black/5 px-2 py-1 align-top whitespace-nowrap">{{ row.code || "—" }}</td>
                  <td class="border-b border-black/5 px-2 py-1 align-top">{{ row.description || "—" }}</td>
                  <td class="border-b border-black/5 px-2 py-1 align-top">{{ row.units || "—" }}</td>
                  <td
                    class="border-b border-black/5 px-2 py-1 align-top font-medium"
                    :class="!row.grade ? 'text-warning' : isFailedRow(row) ? 'text-danger' : ''"
                  >
                    {{ row.grade || "—" }}
                    <span
                      v-if="gradeCellBadge(row)"
                      class="ml-1 inline-flex rounded px-1 py-0.5 text-[10px] font-medium"
                      :class="gradeCellBadge(row)?.className"
                    >{{ gradeCellBadge(row)?.label }}</span>
                  </td>
                  <td class="border-b border-black/5 px-2 py-1 align-top">{{ row.instructor || "—" }}</td>
                  <td class="border-b border-black/5 px-2 py-1 align-top">{{ courseRemarkDisplay(row) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <pre
            v-else-if="!isSchoolId"
            class="mt-3 max-h-72 overflow-auto whitespace-pre rounded bg-surface-muted p-3 font-mono text-xs"
            >{{ formattedOcrText }}</pre
          >
        </article>
        <article class="rounded-lg border bg-surface p-4">
          <h2 class="flex items-center gap-2 text-sm font-semibold">
            <IconShieldExclamation :size="17" />Risk and metadata
          </h2>
          <span
            class="mt-3 inline-block rounded-full bg-warning-soft px-2 py-1 text-xs text-warning"
            >{{ item.risk_level }} risk</span
          >
          <div class="mt-3 space-y-1 text-xs text-text-muted">
            <p v-if="item.slot_key">Slot: {{ item.slot_key.replaceAll("_", " ") }}</p>
            <p v-if="gradeSummary">
              {{ gradeCountsLine }}
            </p>
            <p v-if="item.face_quality_score !== null && item.face_quality_score !== undefined">
              ID face quality: {{ item.face_quality_score.toFixed(2) }}
            </p>
            <p v-if="item.identity_review_required" class="text-warning">
              Manual identity review: {{ item.identity_review_reason || "Required" }}
            </p>
          </div>
          <div v-if="pdfMetadataScan" class="mt-3 rounded-md border border-black/5 bg-surface-muted p-3">
            <p class="text-xs font-semibold text-text">
              PDF metadata
              <span class="ml-1 font-normal text-text-muted">({{ pdfMetadataScan.source }})</span>
            </p>
            <p
              v-if="pdfMetadataScan.suspicious"
              class="mt-1 text-xs text-warning"
            >
              Suspicious: {{ pdfMetadataScan.reasons.join(" · ") || "Flagged by analyzer" }}
            </p>
            <p v-else class="mt-1 text-xs text-text-muted">No tampering signals from creator/producer/dates.</p>
            <p
              v-if="pdfMetadataScan.notes.length"
              class="mt-1 text-xs text-text-muted"
            >
              {{ pdfMetadataScan.notes.join(" · ") }}
            </p>
            <dl v-if="pdfMetadataLines.length" class="mt-2 grid gap-1 text-micro text-text-muted">
              <div
                v-for="[label, value] in pdfMetadataLines"
                :key="label"
                class="grid grid-cols-[7rem_1fr] gap-2"
              >
                <dt class="font-medium text-text">{{ label }}</dt>
                <dd class="break-all">{{ value }}</dd>
              </div>
            </dl>
            <p v-else class="mt-2 text-micro text-text-muted">Metadata scanned; fields were empty.</p>
          </div>
          <p
            v-else-if="isGradeDocument"
            class="mt-3 text-xs text-text-muted"
          >
            PDF metadata not scanned yet — wait for the queue pipeline, or re-submit the package.
          </p>
          <pre class="mt-3 max-h-40 overflow-auto whitespace-pre-wrap text-micro text-text-muted">{{
            metadataDisplay
          }}</pre>
        </article>
        <article v-if="item.identity_check" class="rounded-lg border bg-surface p-4">
          <h2 class="text-sm font-semibold">Identity check</h2>
          <div class="mt-3 space-y-1 text-xs text-text-muted">
            <p class="capitalize">Result: {{ item.identity_check.result.replace("_", " ") }}</p>
            <p>Distance: {{ item.identity_check.distance.toFixed(4) }}</p>
            <p v-if="item.identity_check.confidence_score !== null">
              Confidence: {{ item.identity_check.confidence_score.toFixed(1) }}%
            </p>
            <p>
              Challenges:
              {{ item.identity_check.challenge_sequence.join(", ").replaceAll("_", " ") }}
            </p>
            <p v-if="item.identity_check.manual_review_required" class="text-warning">
              Manual review required
            </p>
          </div>
        </article>
        <article class="rounded-lg border bg-surface p-4">
          <h2 class="text-sm font-semibold">Staff decision</h2>
          <p class="mt-1 text-micro text-text-muted">
            Applies to the active tab: {{ activeTabDoc?.tab_label || item.document_type }}
          </p>
          <textarea
            v-model="notes"
            class="mt-3 min-h-20 w-full rounded-md border p-3 text-xs"
            placeholder="Validation notes"
          />
          <div class="mt-3 grid grid-cols-3 gap-2">
            <button
              :disabled="Boolean(pendingDecision)"
              class="rounded-md border px-2 py-2 text-xs"
              @click="decide('resubmission')"
            >
              Return
            </button>
            <button
              :disabled="Boolean(pendingDecision)"
              class="rounded-md border border-danger px-2 py-2 text-xs text-danger"
              @click="decide('rejected')"
            >
              Reject
            </button>
            <button
              :disabled="Boolean(pendingDecision)"
              class="rounded-md bg-primary px-2 py-2 text-xs text-white"
              @click="decide('approved')"
            >
              Approve
            </button>
          </div>
          <p class="mt-2 text-xs text-text-muted">
            Current status:
            <span :class="documentStatusClass(item.status)" class="ml-1">
              {{ item.status.replaceAll("_", " ") }}
            </span>
            <span v-if="pendingDecision" class="text-warning"> · pending commit…</span>
          </p>
        </article>
      </div>
    </section>
  </div>
  <div v-else-if="pageLoading" class="space-y-4">
    <CardSkeleton :lines="2" />
    <div class="flex gap-2 border-b pb-2">
      <CardSkeleton class="w-28" :lines="1" />
      <CardSkeleton class="w-28" :lines="1" />
      <CardSkeleton class="w-28" :lines="1" />
    </div>
    <div class="grid gap-4 xl:grid-cols-[1.4fr_1fr]">
      <CardSkeleton :lines="8" />
      <div class="space-y-4">
        <CardSkeleton :lines="4" />
        <CardSkeleton :lines="4" />
      </div>
    </div>
  </div>
  <EmptyState
    v-else-if="pageError"
    variant="error"
    title="Couldn't load submission package"
    :hint="
      pageError instanceof Error
        ? pageError.message
        : 'Unable to load submission package.'
    "
    @retry="
      () => {
        if (isPackageRoute) packageQuery.refetch();
        else detailQuery.refetch();
      }
    "
  />
</template>
