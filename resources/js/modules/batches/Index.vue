<script setup lang="ts">
import { computed, onMounted, reactive, ref } from "vue";
import { IconCalendarDue, IconPlus, IconUsers } from "@tabler/icons-vue";
import AppDialog from "@/components/dialogs/AppDialog.vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import { csrfToken } from "@/auth/session";

type Batch = {
  id: number;
  name: string;
  academic_year: string;
  semester: string;
  submission_deadline: string | null;
  is_active: boolean;
  window_status: "draft" | "active" | "closed" | "expired";
  grantees_count: number;
};

const batchDialog = ref(false);
const batches = ref<Batch[]>([]);
const loading = ref(true);
const busy = ref(false);
const error = ref("");
const form = reactive({
  name: "",
  academic_year: "AY 2026-2027",
  semester: "1st Semester",
  submission_deadline: "",
});

const activeBatch = computed(() => batches.value.find((batch) => batch.window_status === "active"));

onMounted(loadBatches);

async function loadBatches() {
  loading.value = true;
  error.value = "";
  try {
    const response = await fetch("/api/batches", { headers: { Accept: "application/json" } });
    const payload = await response.json();
    if (!response.ok) throw new Error(payload.message || "Unable to load batches.");
    batches.value = payload.data || [];
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "Unable to load batches.";
  } finally {
    loading.value = false;
  }
}

async function createBatch(close: () => void) {
  busy.value = true;
  error.value = "";
  try {
    const response = await fetch("/api/batches", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": csrfToken(),
        Accept: "application/json",
      },
      body: JSON.stringify(form),
    });
    const payload = await response.json();
    if (!response.ok) {
      const validation = payload.errors ? Object.values(payload.errors).flat().join(" ") : "";
      throw new Error(validation || payload.message || "Unable to create batch.");
    }
    batches.value.unshift(payload.data);
    form.name = "";
    close();
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "Unable to create batch.";
  } finally {
    busy.value = false;
  }
}

function statusClass(status: Batch["window_status"]) {
  if (status === "active") return "bg-success-soft text-success";
  if (status === "expired") return "bg-danger-soft text-danger";
  if (status === "closed") return "bg-warning-soft text-warning";
  return "bg-surface-muted text-text-muted";
}
</script>

<template>
  <div>
    <PageHeader title="Batches" description="Manage TES submission windows by academic period.">
      <template #actions>
        <button
          class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-xs text-white"
          @click="batchDialog = true"
        >
          <IconPlus :size="14" />New batch
        </button>
      </template>
    </PageHeader>

    <section v-if="activeBatch" class="mb-4 rounded-lg border border-success/30 bg-success-soft p-4">
      <p class="text-sm font-semibold text-success">Active submission window</p>
      <p class="mt-1 text-xs text-text-muted">
        {{ activeBatch.name }} closes {{ activeBatch.submission_deadline ? new Date(activeBatch.submission_deadline).toLocaleString() : "without a deadline" }}.
      </p>
    </section>
    <p v-if="error" class="mb-4 rounded-md border border-danger/30 bg-danger-soft p-3 text-xs text-danger">
      {{ error }}
    </p>

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
      <article v-if="loading" class="rounded-lg border bg-surface p-5 text-sm text-text-muted">
        Loading batches...
      </article>
      <RouterLink
        v-for="batch in batches"
        :key="batch.id"
        :to="`/app/batches/${batch.id}`"
        class="rounded-lg border bg-surface p-5 transition hover:border-primary/40 hover:shadow-sm"
      >
        <div class="flex items-start justify-between">
          <span class="grid size-10 place-items-center rounded-md bg-primary-soft text-primary">
            <IconUsers :size="19" />
          </span>
          <span :class="['rounded-full px-2 py-1 text-micro font-semibold', statusClass(batch.window_status)]">
            {{ batch.window_status }}
          </span>
        </div>
        <h2 class="mt-5 text-sm font-semibold">{{ batch.name }}</h2>
        <p class="mt-1 text-xs text-text-muted">{{ batch.academic_year }} - {{ batch.semester }}</p>
        <p class="mt-3 flex items-center gap-1 text-xs text-text-muted">
          <IconCalendarDue :size="14" />
          {{ batch.submission_deadline ? new Date(batch.submission_deadline).toLocaleString() : "No deadline set" }}
        </p>
        <div class="mt-4 flex justify-between text-xs text-text-muted">
          <span>{{ batch.grantees_count.toLocaleString() }} grantees</span>
          <span>{{ batch.is_active ? "Toggle on" : "Toggle off" }}</span>
        </div>
      </RouterLink>
      <article
        v-if="!loading && !batches.length"
        class="rounded-lg border bg-surface p-5 text-sm text-text-muted"
      >
        No batches yet.
      </article>
    </section>

    <AppDialog
      v-model="batchDialog"
      title="Create TES batch"
      description="Set the academic period and submission deadline."
      size="lg"
    >
      <div class="grid gap-4 sm:grid-cols-2">
        <label class="text-xs font-medium sm:col-span-2">
          Batch name
          <input
            v-model="form.name"
            class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm"
            placeholder="TES 2026 - Batch 01"
          />
        </label>
        <label class="text-xs font-medium">
          Academic year
          <input v-model="form.academic_year" class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm" />
        </label>
        <label class="text-xs font-medium">
          Semester
          <select v-model="form.semester" class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm">
            <option>1st Semester</option>
            <option>2nd Semester</option>
            <option>Summer</option>
          </select>
        </label>
        <label class="text-xs font-medium sm:col-span-2">
          Submission deadline
          <input
            v-model="form.submission_deadline"
            type="datetime-local"
            class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm"
          />
        </label>
      </div>
      <template #footer="{ close }">
        <button class="rounded-md border px-4 py-2 text-xs" @click="close">Cancel</button>
        <button
          class="rounded-md bg-primary px-4 py-2 text-xs text-white disabled:opacity-60"
          :disabled="busy"
          @click="createBatch(close)"
        >
          {{ busy ? "Creating..." : "Create batch" }}
        </button>
      </template>
    </AppDialog>
  </div>
</template>
