<script setup lang="ts">
import { onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { IconFile, IconLock, IconUpload, IconX } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import { useSubmissionWindow } from "@/composables/useSubmissionWindow";
import { submitOcrDocument } from "@/api/student/submissions";

const router = useRouter();
const route = useRoute();
const documentTypes = ["Course History", "COR"];
const initialType =
  typeof route.query.type === "string" && documentTypes.includes(route.query.type)
    ? route.query.type
    : documentTypes[0];
const documentType = ref(initialType);
const file = ref<File | null>(null);
const error = ref("");
const busy = ref(false);
const result = ref<{ text: string; confidence: number | null } | null>(null);
const { windowState, loadingWindow, windowError, loadWindow } = useSubmissionWindow();

onMounted(loadWindow);

function choose(event: Event) {
  file.value = (event.target as HTMLInputElement).files?.[0] ?? null;
  error.value = "";
  result.value = null;
}

async function submit() {
  if (result.value) {
    await router.push("/student/documents");
    return;
  }
  if (!file.value) {
    error.value = "Please choose a file first.";
    return;
  }

  busy.value = true;
  error.value = "";
  const body = new FormData();
  body.append("document_type", documentType.value);
  body.append("file", file.value);

  try {
    const payload = await submitOcrDocument(body);
    const ocr = payload.ocr;
    result.value = {
      text:
        (ocr.document_type === "pdf" ? ocr.result.combined_text : ocr.result.cleaned_text) ||
        "No readable text was detected.",
      confidence: ocr.document_type === "image" ? ocr.result.average_confidence ?? null : null,
    };
  } catch (exception) {
    error.value =
      exception instanceof Error ? exception.message : "Unable to process the document.";
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <div>
    <PageHeader
      title="Upload Document"
      description="Submit your Course History or COR for OCR-assisted validation."
    />
    <section
      v-if="loadingWindow || windowError || !windowState?.open"
      class="rounded-2xl border bg-surface p-6 shadow-sm"
    >
      <span class="inline-flex items-center gap-2 rounded-full bg-warning-soft px-3 py-1 text-xs font-semibold text-warning">
        <IconLock :size="14" /> Locked vault
      </span>
      <h2 class="mt-4 text-2xl font-semibold tracking-tight">Submission window is closed</h2>
      <p class="mt-2 max-w-2xl text-sm text-text-muted">
        {{ loadingWindow ? "Checking your batch submission window..." : windowError || windowState?.message }}
      </p>
    </section>
    <div v-else class="grid gap-4 lg:grid-cols-3">
      <section class="space-y-4 rounded-lg border bg-surface p-4 lg:col-span-2">
        <div class="rounded-lg border bg-surface-muted px-3 py-2.5">
          <label class="block text-xs font-medium text-text-muted" for="document_type"
            >Document type</label
          >
          <select
            id="document_type"
            v-model="documentType"
            class="mt-1 h-9 w-full rounded-md border bg-surface px-3 text-sm font-semibold"
          >
            <option v-for="item in documentTypes" :key="item" :value="item">{{ item }}</option>
          </select>
        </div>
        <label class="block">
          <span class="mb-1.5 block text-xs font-medium">File</span>
          <span
            class="relative grid min-h-40 cursor-pointer place-items-center rounded-lg border-2 border-dashed border-border-strong p-5 text-center hover:border-primary"
          >
            <input
              type="file"
              accept=".pdf,.jpg,.jpeg,.png,.webp"
              class="absolute inset-0 cursor-pointer opacity-0"
              @change="choose"
            />
            <span v-if="!file">
              <IconUpload :size="28" class="mx-auto text-primary" />
              <b class="mt-2 block text-sm">Choose a file or drag it here</b>
              <span class="mt-1 block text-xs text-text-muted"
                >PDF up to 20 MB; images up to 10 MB</span
              >
            </span>
            <span v-else class="flex items-center gap-3">
              <IconFile :size="26" class="text-primary" />
              <span class="text-left"
                ><b class="block text-sm">{{ file.name }}</b
                ><span class="text-xs text-text-muted"
                  >{{ (file.size / 1024).toFixed(1) }} KB</span
                ></span
              >
              <button
                type="button"
                class="rounded p-1 hover:bg-surface-muted"
                @click.prevent="
                  file = null;
                  result = null;
                "
              >
                <IconX :size="16" />
              </button>
            </span>
          </span>
        </label>
        <p v-if="error" class="text-xs text-danger">{{ error }}</p>
        <div v-if="result" class="rounded-lg border border-success/30 bg-success-soft p-3">
          <p class="text-xs font-semibold text-success">OCR completed</p>
          <p v-if="result.confidence !== null" class="mt-1 text-xs text-text-muted">
            Average confidence: {{ result.confidence.toFixed(1) }}%
          </p>
          <p class="mt-2 max-h-40 overflow-auto whitespace-pre-wrap text-xs text-text-muted">
            {{ result.text }}
          </p>
        </div>
        <div class="flex justify-end gap-2">
          <button
            class="h-9 rounded-md border px-3 text-xs"
            @click="router.push('/student/documents')"
          >
            Cancel
          </button>
          <button
            class="h-9 rounded-md bg-primary px-3 text-xs font-medium text-white disabled:opacity-50"
            :disabled="busy"
            @click="submit"
          >
            {{
              busy ? "Processing OCR..." : result ? "Back to documents" : "Submit for validation"
            }}
          </button>
        </div>
      </section>
      <aside class="h-fit rounded-lg border bg-surface p-4">
        <h2 class="text-sm font-semibold">Tips for accepted documents</h2>
        <ul class="mt-2 list-inside list-disc space-y-1.5 text-xs text-text-muted">
          <li>Upload the official Course History or COR PDF when available.</li>
          <li>Use a clear scan or photo if the document is printed.</li>
          <li>Ensure student details, subjects, and semester labels are legible.</li>
          <li>OCR assists review but does not determine authenticity.</li>
        </ul>
      </aside>
    </div>
  </div>
</template>
