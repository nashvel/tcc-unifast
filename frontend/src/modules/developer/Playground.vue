<script setup lang="ts">
import { ref } from "vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import { apiFetch } from "@/api/client";
import {
  IconTestPipe,
  IconCheck,
  IconX,
  IconLoader,
  IconTrash,
  IconRefresh,
  IconPlayerPlay,
} from "@tabler/icons-vue";
import { toast } from "@/composables/useToast";

const endpoint = ref("/api/academic-programs");
const createPayload = ref('{\n  "code": "TEST-101",\n  "name": "Playground Test Program",\n  "pass_grade": 3.0\n}');
const updatePayload = ref('{\n  "name": "Playground Test Program (Updated)"\n}');

type TestStep = {
  name: string;
  status: "idle" | "running" | "success" | "error";
  response?: any;
  error?: string;
  duration?: number;
};

const steps = ref<TestStep[]>([
  { name: "Create (POST)", status: "idle" },
  { name: "Read (GET)", status: "idle" },
  { name: "Update (PUT)", status: "idle" },
  { name: "Delete (DELETE)", status: "idle" },
]);

const isRunning = ref(false);
const createdId = ref<string | number | null>(null);

async function runTests() {
  if (isRunning.value) return;
  isRunning.value = true;
  createdId.value = null;

  // Reset steps
  steps.value.forEach(s => {
    s.status = "idle";
    s.response = undefined;
    s.error = undefined;
    s.duration = undefined;
  });

  let payloadObj: any;
  let updateObj: any;
  try {
    payloadObj = JSON.parse(createPayload.value);
    updateObj = JSON.parse(updatePayload.value);
  } catch (e) {
    toast.error("Invalid JSON payload");
    isRunning.value = false;
    return;
  }

  // 1. Create (POST)
  let step = steps.value[0];
  step.status = "running";
  let start = performance.now();
  try {
    const res = await apiFetch<any>(endpoint.value, {
      method: "POST",
      body: JSON.stringify(payloadObj),
    });
    step.duration = Math.round(performance.now() - start);
    step.response = res;
    step.status = "success";
    
    // Extract ID from response (could be id, data.id, etc)
    createdId.value = res.id || res?.data?.id;
  } catch (err: any) {
    step.duration = Math.round(performance.now() - start);
    step.error = err?.message || "POST Failed";
    step.status = "error";
    isRunning.value = false;
    return;
  }

  if (!createdId.value) {
    steps.value[1].status = "error";
    steps.value[1].error = "Could not extract ID from POST response to continue tests.";
    isRunning.value = false;
    return;
  }

  const resourceUrl = `${endpoint.value}/${createdId.value}`;

  // 2. Read (GET)
  step = steps.value[1];
  step.status = "running";
  start = performance.now();
  try {
    const res = await apiFetch<any>(resourceUrl, {
      method: "GET",
    });
    step.duration = Math.round(performance.now() - start);
    step.response = res;
    step.status = "success";
  } catch (err: any) {
    step.duration = Math.round(performance.now() - start);
    step.error = err?.message || "GET Failed";
    step.status = "error";
  }

  // 3. Update (PUT)
  step = steps.value[2];
  step.status = "running";
  start = performance.now();
  try {
    const res = await apiFetch<any>(resourceUrl, {
      method: "PUT",
      body: JSON.stringify(updateObj),
    });
    step.duration = Math.round(performance.now() - start);
    step.response = res;
    step.status = "success";
  } catch (err: any) {
    step.duration = Math.round(performance.now() - start);
    step.error = err?.message || "PUT Failed";
    step.status = "error";
  }

  // 4. Delete (DELETE)
  step = steps.value[3];
  step.status = "running";
  start = performance.now();
  try {
    const res = await apiFetch<any>(resourceUrl, {
      method: "DELETE",
    });
    step.duration = Math.round(performance.now() - start);
    step.response = res;
    step.status = "success";
  } catch (err: any) {
    step.duration = Math.round(performance.now() - start);
    step.error = err?.message || "DELETE Failed";
    step.status = "error";
  }

  isRunning.value = false;
  
  if (steps.value.every(s => s.status === "success")) {
    toast.success("All CRUD tests passed and data was cleaned up!");
  } else {
    toast.error("Some CRUD tests failed. Check the logs.");
  }
}

function resetPlayground() {
  steps.value.forEach(s => {
    s.status = "idle";
    s.response = undefined;
    s.error = undefined;
    s.duration = undefined;
  });
  createdId.value = null;
}
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      title="Developer Playground"
      description="Test CRUD operations systematically on any endpoint. Acts like a Pest test to create, read, update, and finally delete to leave no trace."
    />

    <div class="grid gap-6 lg:grid-cols-[1fr_400px]">
      <section class="space-y-4">
        <!-- Configuration -->
        <div class="rounded-lg border bg-surface p-5 shadow-sm space-y-4">
          <div class="flex items-center gap-2 border-b border-border pb-3">
            <IconTestPipe :size="18" class="text-primary" />
            <h2 class="text-sm font-bold text-text">Test Configuration</h2>
          </div>
          
          <div class="space-y-3">
            <div>
              <label class="mb-1 block text-xs font-medium text-text-muted">API Endpoint (Base Resource URL)</label>
              <input
                v-model="endpoint"
                type="text"
                class="w-full rounded-md border border-border bg-surface-muted px-3 py-2 text-sm text-text focus:border-primary focus:outline-hidden"
                placeholder="/api/announcements"
              />
              <p class="mt-1 text-3xs text-text-muted">ID will be appended for Read, Update, and Delete operations.</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
              <div>
                <label class="mb-1 block text-xs font-medium text-text-muted">Create Payload (JSON)</label>
                <textarea
                  v-model="createPayload"
                  rows="6"
                  class="w-full rounded-md border border-border bg-neutral-950 px-3 py-2 font-mono text-xs text-text focus:border-primary focus:outline-hidden"
                  spellcheck="false"
                ></textarea>
              </div>
              <div>
                <label class="mb-1 block text-xs font-medium text-text-muted">Update Payload (JSON)</label>
                <textarea
                  v-model="updatePayload"
                  rows="6"
                  class="w-full rounded-md border border-border bg-neutral-950 px-3 py-2 font-mono text-xs text-text focus:border-primary focus:outline-hidden"
                  spellcheck="false"
                ></textarea>
              </div>
            </div>
          </div>

          <div class="flex items-center gap-3 pt-2">
            <button
              @click="runTests"
              :disabled="isRunning"
              class="flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-xs font-medium text-white transition-colors hover:bg-primary/90 disabled:opacity-50"
            >
              <IconLoader v-if="isRunning" :size="14" class="animate-spin" />
              <IconPlayerPlay v-else :size="14" />
              Run CRUD Tests
            </button>
            <button
              @click="resetPlayground"
              :disabled="isRunning"
              class="flex items-center gap-2 rounded-md border border-border bg-surface-muted px-4 py-2 text-xs font-medium text-text transition-colors hover:bg-neutral-800 disabled:opacity-50"
            >
              <IconRefresh :size="14" />
              Reset State
            </button>
          </div>
        </div>
      </section>

      <!-- Test Execution Timeline -->
      <section class="space-y-4">
        <div class="rounded-lg border bg-surface p-5 shadow-sm space-y-4">
          <div class="flex items-center gap-2 border-b border-border pb-3">
            <IconTestPipe :size="18" class="text-text-muted" />
            <h2 class="text-sm font-bold text-text">Execution Steps</h2>
          </div>

          <div class="space-y-4">
            <div v-for="(step, idx) in steps" :key="idx" class="relative pl-6">
              <!-- Timeline connector -->
              <div v-if="idx !== steps.length - 1" class="absolute left-2.5 top-5 h-full w-px bg-border"></div>
              
              <!-- Status Icon -->
              <div class="absolute left-0 top-0.5 flex size-5 items-center justify-center rounded-full bg-surface">
                <IconLoader v-if="step.status === 'running'" :size="14" class="animate-spin text-primary" />
                <IconCheck v-else-if="step.status === 'success'" :size="14" class="text-green-500" />
                <IconX v-else-if="step.status === 'error'" :size="14" class="text-red-500" />
                <div v-else class="size-2 rounded-full bg-border"></div>
              </div>

              <div class="pb-4">
                <div class="flex items-center justify-between">
                  <h3 :class="['text-sm font-medium', step.status === 'idle' ? 'text-text-muted' : 'text-text']">
                    {{ step.name }}
                  </h3>
                  <span v-if="step.duration" class="text-3xs font-mono text-text-muted">{{ step.duration }}ms</span>
                </div>
                
                <div v-if="step.error" class="mt-2 rounded bg-red-950/30 p-2 text-xs text-red-400 font-mono break-all">
                  {{ step.error }}
                </div>
                
                <details v-else-if="step.response" class="mt-2 text-xs">
                  <summary class="cursor-pointer text-text-muted hover:text-text font-medium select-none">View Response</summary>
                  <pre class="mt-2 overflow-x-auto rounded bg-neutral-950 p-2 font-mono text-2xs text-text-muted">{{ JSON.stringify(step.response, null, 2) }}</pre>
                </details>
              </div>
            </div>
          </div>
          
          <div v-if="createdId" class="mt-4 flex items-center justify-between rounded border border-dashed border-border bg-surface-muted/50 p-3">
            <span class="text-xs text-text-muted">Temporary Resource ID:</span>
            <span class="font-mono text-xs font-bold text-primary">{{ createdId }}</span>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>
