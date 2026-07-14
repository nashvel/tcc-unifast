<script setup lang="ts">
import { onMounted, ref } from "vue";
import { useRoute } from "vue-router";
import { IconArrowLeft, IconFile, IconScan, IconShieldExclamation } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import { csrfToken } from "@/auth/session";
type Submission = {
  id: number;
  student_name: string;
  student_id: string;
  document_type: string;
  original_name: string;
  file_url: string;
  mime_type: string;
  status: string;
  risk_level: string;
  extracted_text: string | null;
  ocr_confidence: number | null;
  metadata_payload: Record<string, unknown> | null;
  review_notes: string | null;
};
const route = useRoute();
const item = ref<Submission | null>(null);
const notes = ref("");
const busy = ref(false);
const message = ref("");
async function load() {
  const r = await fetch(`/api/document-submissions/${route.params.id}`);
  item.value = (await r.json()).data;
  notes.value = item.value?.review_notes || "";
}
async function decide(decision: string) {
  busy.value = true;
  message.value = "";
  try {
    const r = await fetch(`/api/document-submissions/${route.params.id}/review`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": csrfToken(),
        Accept: "application/json",
      },
      body: JSON.stringify({ decision, notes: notes.value }),
    });
    const p = await r.json();
    if (!r.ok) throw new Error(p.message || "Decision failed.");
    item.value = p.data;
    message.value = `Submission marked ${decision}.`;
  } catch (e) {
    message.value = e instanceof Error ? e.message : "Decision failed.";
  } finally {
    busy.value = false;
  }
}
onMounted(load);
</script>
<template>
  <div v-if="item">
    <RouterLink
      to="/app/documents"
      class="mb-3 inline-flex items-center gap-1 text-xs text-text-muted"
      ><IconArrowLeft :size="14" />Validation queue</RouterLink
    ><PageHeader
      :title="item.document_type"
      :description="`From ${item.student_name} (${item.student_id})`"
    />
    <section class="grid gap-4 xl:grid-cols-[1.4fr_1fr]">
      <div class="rounded-lg border bg-surface p-4">
        <h2 class="flex items-center gap-2 text-sm font-semibold">
          <IconFile :size="17" />File preview
        </h2>
        <iframe
          v-if="item.mime_type === 'application/pdf'"
          :src="item.file_url"
          class="mt-4 h-[34rem] w-full rounded-md border"
        /><img
          v-else
          :src="item.file_url"
          :alt="item.original_name"
          class="mt-4 max-h-[34rem] w-full rounded-md bg-surface-muted object-contain"
        />
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
            >{{ item.extracted_text || "No readable text detected." }}</pre>
        </article>
        <article class="rounded-lg border bg-surface p-4">
          <h2 class="flex items-center gap-2 text-sm font-semibold">
            <IconShieldExclamation :size="17" />Risk and metadata
          </h2>
          <span
            class="mt-3 inline-block rounded-full bg-warning-soft px-2 py-1 text-xs text-warning"
            >{{ item.risk_level }} risk</span
          >
          <pre class="mt-3 max-h-40 overflow-auto whitespace-pre-wrap text-micro text-text-muted">{{
            JSON.stringify(item.metadata_payload, null, 2) || "No metadata recorded."
          }}</pre>
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
              :disabled="busy"
              class="rounded-md border px-2 py-2 text-xs"
              @click="decide('resubmission')"
            >
              Return</button
            ><button
              :disabled="busy"
              class="rounded-md border border-danger px-2 py-2 text-xs text-danger"
              @click="decide('rejected')"
            >
              Reject</button
            ><button
              :disabled="busy"
              class="rounded-md bg-primary px-2 py-2 text-xs text-white"
              @click="decide('approved')"
            >
              Approve
            </button>
          </div>
          <p v-if="message" class="mt-3 text-xs text-primary">{{ message }}</p>
          <p class="mt-2 text-xs text-text-muted">
            Current status: {{ item.status.replaceAll("_", " ") }}
          </p>
        </article>
      </div>
    </section>
  </div>
  <p v-else class="p-8 text-sm text-text-muted">Loading submission…</p>
</template>
