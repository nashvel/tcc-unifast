<script setup lang="ts">
import { ref, watch } from "vue";
import { useRoute } from "vue-router";
import { useQueryClient } from "@tanstack/vue-query";
import { IconArrowLeft, IconFile, IconScan, IconShieldExclamation } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import CardSkeleton from "@/components/ui/CardSkeleton.vue";
import EmptyState from "@/components/ui/EmptyState.vue";
import { useDocumentDetail } from "@/composables/useDocuments";
import { toast } from "@/composables/useToast";
import { scheduleUndo } from "@/composables/useUndo";

const route = useRoute();
const notes = ref("");
const pendingDecision = ref<string | null>(null);

const { item, query: detailQuery, reviewMutation } = useDocumentDetail(
  String(route.params.id),
);
const queryClient = useQueryClient();

watch(
  () => detailQuery.data.value,
  (data) => {
    if (data) notes.value = data.review_notes || "";
  },
);

async function decide(decision: string) {
  if (!item.value || pendingDecision.value) return;
  const previous = { ...item.value };
  const label =
    decision === "approved" ? "Approve" : decision === "rejected" ? "Reject" : "Return";

  pendingDecision.value = decision;
  await scheduleUndo(`document-decide-${item.value.id}`, {
    message: `${label} scheduled`,
    description: "Undo within 5 seconds to cancel this decision.",
    optimistic: () => {
      queryClient.setQueryData(
        ["document-submissions", String(route.params.id)],
        {
          ...previous,
          status: decision,
          review_notes: notes.value,
        },
      );
      return () => {
        queryClient.setQueryData(
          ["document-submissions", String(route.params.id)],
          previous,
        );
      };
    },
    commit: async () => {
      const payload = await reviewMutation.mutateAsync(decision);
      return payload.data;
    },
    onUndo: () => {
      toast.info("Decision cancelled");
    },
    onError: (error: unknown) => {
      toast.error(error instanceof Error ? error.message : "Decision failed.");
    },
  });
  pendingDecision.value = null;
}
</script>

<template>
  <div v-if="item">
    <RouterLink
      to="/app/documents"
      class="mb-3 inline-flex items-center gap-1 text-xs text-text-muted"
    >
      <IconArrowLeft :size="14" />Validation queue
    </RouterLink>
    <PageHeader
      :title="item.document_type"
      :description="`From ${item.student_name} (${item.student_id})`"
    />
    <section class="grid gap-4 xl:grid-cols-[1.4fr_1fr]">
      <div class="rounded-lg border bg-surface p-4">
        <h2 class="flex items-center gap-2 text-sm font-semibold">
          <IconFile :size="17" />File preview
        </h2>
        <div class="mt-4 grid gap-3" :class="item.secondary_file_url ? 'lg:grid-cols-2' : ''">
          <figure>
            <figcaption class="mb-2 text-xs font-semibold text-text-muted">
              {{ item.slot_key === "school_id" ? "Front" : item.original_name }}
            </figcaption>
            <iframe
              v-if="item.mime_type === 'application/pdf'"
              :src="item.file_url"
              class="h-[34rem] w-full rounded-md border"
            />
            <img
              v-else
              :src="item.file_url"
              :alt="item.original_name"
              class="max-h-[34rem] w-full rounded-md bg-surface-muted object-contain"
            />
          </figure>
          <figure v-if="item.secondary_file_url">
            <figcaption class="mb-2 text-xs font-semibold text-text-muted">Back</figcaption>
            <img
              :src="item.secondary_file_url"
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
          <p v-if="item.ocr_confidence !== null" class="mt-3 text-xs text-text-muted">
            Average confidence: {{ item.ocr_confidence.toFixed(1) }}%
          </p>
          <pre
            class="mt-3 max-h-72 overflow-auto whitespace-pre-wrap rounded bg-surface-muted p-3 text-xs"
            >{{ item.extracted_text || "No readable text detected." }}</pre
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
            <p v-if="item.face_quality_score !== null">
              ID face quality: {{ item.face_quality_score.toFixed(2) }}
            </p>
            <p v-if="item.identity_review_required" class="text-warning">
              Manual identity review: {{ item.identity_review_reason || "Required" }}
            </p>
          </div>
          <pre class="mt-3 max-h-40 overflow-auto whitespace-pre-wrap text-micro text-text-muted">{{
            JSON.stringify(item.metadata_payload, null, 2) || "No metadata recorded."
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
            Current status: {{ item.status.replaceAll("_", " ") }}
            <span v-if="pendingDecision" class="text-warning"> · pending commit\u2026</span>
          </p>
        </article>
      </div>
    </section>
  </div>
  <div v-else-if="detailQuery.isLoading.value" class="space-y-4">
    <CardSkeleton :lines="2" />
    <div class="grid gap-4 xl:grid-cols-[1.4fr_1fr]">
      <CardSkeleton :lines="8" />
      <div class="space-y-4">
        <CardSkeleton :lines="4" />
        <CardSkeleton :lines="4" />
      </div>
    </div>
  </div>
  <EmptyState
    v-else-if="detailQuery.isError.value"
    variant="error"
    title="Couldn't load submission"
    :hint="
      detailQuery.error.value instanceof Error
        ? detailQuery.error.value.message
        : 'Unable to load submission.'
    "
    @retry="detailQuery.refetch()"
  />
</template>
