<script setup lang="ts">
import { ref } from "vue";
import { IconPlus, IconUsers } from "@tabler/icons-vue";
import AppDialog from "@/components/dialogs/AppDialog.vue";
import PageHeader from "@/components/ui/PageHeader.vue";

const batchDialog = ref(false);
const batches = [
  {
    id: 1,
    name: "TES 2025 — Batch 1",
    year: "AY 2025–2026",
    semester: "1st Semester",
    count: 1248,
    status: "Inactive accounts",
    progress: 0,
  },
  {
    id: 3,
    name: "TES 2025 — Batch 03",
    year: "AY 2024–2025",
    semester: "2nd Semester",
    count: 1106,
    status: "Released",
    progress: 100,
  },
  {
    id: 2,
    name: "TES 2025 — Batch 02",
    year: "AY 2024–2025",
    semester: "1st Semester",
    count: 984,
    status: "Completed",
    progress: 100,
  },
];
</script>

<template>
  <div>
    <PageHeader title="Batches" description="Manage TES grantee batches per academic period.">
      <template #actions>
        <button
          class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-xs text-white"
          @click="batchDialog = true"
        >
          <IconPlus :size="14" />New batch
        </button>
      </template>
    </PageHeader>

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
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
          <span
            class="rounded-full bg-warning-soft px-2 py-1 text-micro font-semibold text-warning"
          >
            {{ batch.status }}
          </span>
        </div>
        <h2 class="mt-5 text-sm font-semibold">{{ batch.name }}</h2>
        <p class="mt-1 text-xs text-text-muted">{{ batch.year }} · {{ batch.semester }}</p>
        <div class="mt-5 h-1.5 overflow-hidden rounded-full bg-primary-soft">
          <div class="h-full bg-primary" :style="{ width: `${batch.progress}%` }" />
        </div>
        <div class="mt-3 flex justify-between text-xs text-text-muted">
          <span>{{ batch.count.toLocaleString() }} grantees</span>
          <span>{{ batch.progress }}% activated</span>
        </div>
      </RouterLink>
    </section>

    <AppDialog
      v-model="batchDialog"
      title="Create TES batch"
      description="Set the academic period and initial batch configuration."
      size="lg"
    >
      <div class="grid gap-4 sm:grid-cols-2">
        <label class="text-xs font-medium sm:col-span-2">
          Batch name
          <input
            class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm"
            placeholder="TES 2026 — Batch 01"
          />
        </label>
        <label class="text-xs font-medium">
          Academic year
          <select class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm">
            <option>AY 2026–2027</option>
            <option>AY 2025–2026</option>
          </select>
        </label>
        <label class="text-xs font-medium">
          Semester
          <select class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm">
            <option>1st Semester</option>
            <option>2nd Semester</option>
            <option>Summer</option>
          </select>
        </label>
        <label class="text-xs font-medium">
          Grant amount
          <input class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm" placeholder="₱20,000" />
        </label>
        <label class="text-xs font-medium">
          Initial status
          <select class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm">
            <option>Inactive accounts</option>
            <option>Activation notified</option>
          </select>
        </label>
      </div>
      <template #footer="{ close }">
        <button class="rounded-md border px-4 py-2 text-xs" @click="close">Cancel</button>
        <button class="rounded-md bg-primary px-4 py-2 text-xs text-white" @click="close">
          Create batch
        </button>
      </template>
    </AppDialog>
  </div>
</template>
