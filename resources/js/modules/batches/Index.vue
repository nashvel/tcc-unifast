<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/vue-query";
import { IconCalendarDue, IconPlus, IconUsers } from "@tabler/icons-vue";
import AppDialog from "@/components/dialogs/AppDialog.vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import CardSkeleton from "@/components/ui/CardSkeleton.vue";
import EmptyState from "@/components/ui/EmptyState.vue";
import { apiFetch, type PaginatedResponse } from "@/lib/api";
import { queryKeys } from "@/lib/queryClient";
import { toast } from "@/composables/useToast";

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
const page = ref(1);
const form = ref({
  name: "",
  academic_year: "AY 2026-2027",
  semester: "1st Semester",
  submission_deadline: "",
});
const queryClient = useQueryClient();

const batchesQuery = useQuery({
  queryKey: computed(() => [...queryKeys.batches, { page: page.value }]),
  queryFn: () =>
    apiFetch<PaginatedResponse<Batch>>(`/api/batches?page=${page.value}&per_page=24`),
  placeholderData: keepPreviousData,
});

const batches = computed(() => batchesQuery.data.value?.data ?? []);
const meta = computed(() => batchesQuery.data.value?.meta);
const activeBatch = computed(() => batches.value.find((batch) => batch.window_status === "active"));

const createMutation = useMutation({
  mutationFn: () =>
    apiFetch<{ data: Batch }>("/api/batches", {
      method: "POST",
      body: JSON.stringify(form.value),
    }),
  onSuccess: (payload) => {
    queryClient.setQueryData<PaginatedResponse<Batch>>(
      [...queryKeys.batches, { page: 1 }],
      (current) => {
        if (!current) {
          return {
            data: [payload.data],
            meta: {
              current_page: 1,
              last_page: 1,
              per_page: 24,
              total: 1,
              from: 1,
              to: 1,
            },
          };
        }
        return { ...current, data: [payload.data, ...current.data] };
      },
    );
    void queryClient.invalidateQueries({ queryKey: queryKeys.batches });
    form.value = {
      name: "",
      academic_year: "AY 2026-2027",
      semester: "1st Semester",
      submission_deadline: "",
    };
    batchDialog.value = false;
    toast.success("Batch created");
  },
  onError: (error) => {
    toast.error(error instanceof Error ? error.message : "Unable to create batch.");
  },
});

watch(batchDialog, (open) => {
  if (!open) createMutation.reset();
});

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
        {{ activeBatch.name }} closes
        {{
          activeBatch.submission_deadline
            ? new Date(activeBatch.submission_deadline).toLocaleString()
            : "without a deadline"
        }}.
      </p>
    </section>

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
      <template v-if="batchesQuery.isLoading.value">
        <CardSkeleton v-for="i in 6" :key="i" :lines="4" />
      </template>
      <EmptyState
        v-else-if="batchesQuery.isError.value"
        variant="error"
        title="Couldn't load batches"
        :hint="
          batchesQuery.error.value instanceof Error
            ? batchesQuery.error.value.message
            : 'Unable to load batches.'
        "
        @retry="batchesQuery.refetch()"
      />
      <template v-else>
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
              :class="[
                'rounded-full px-2 py-1 text-micro font-semibold',
                statusClass(batch.window_status),
              ]"
            >
              {{ batch.window_status }}
            </span>
          </div>
          <h2 class="mt-5 text-sm font-semibold">{{ batch.name }}</h2>
          <p class="mt-1 text-xs text-text-muted">
            {{ batch.academic_year }} - {{ batch.semester }}
          </p>
          <p class="mt-3 flex items-center gap-1 text-xs text-text-muted">
            <IconCalendarDue :size="14" />
            {{
              batch.submission_deadline
                ? new Date(batch.submission_deadline).toLocaleString()
                : "No deadline set"
            }}
          </p>
          <div class="mt-4 flex justify-between text-xs text-text-muted">
            <span>{{ batch.grantees_count.toLocaleString() }} grantees</span>
            <span>{{ batch.is_active ? "Toggle on" : "Toggle off" }}</span>
          </div>
        </RouterLink>
        <EmptyState
          v-if="!batches.length"
          title="No batches yet"
          hint="Create a TES batch to open a submission window."
        />
      </template>
    </section>

    <div
      v-if="meta && meta.last_page > 1"
      class="mt-4 flex items-center justify-between text-xs text-text-muted"
    >
      <span>Page {{ meta.current_page }} of {{ meta.last_page }}</span>
      <div class="flex gap-2">
        <button
          class="rounded-md border px-2 py-1 disabled:opacity-40"
          :disabled="meta.current_page <= 1"
          @click="page = meta.current_page - 1"
        >
          Prev
        </button>
        <button
          class="rounded-md border px-2 py-1 disabled:opacity-40"
          :disabled="meta.current_page >= meta.last_page"
          @click="page = meta.current_page + 1"
        >
          Next
        </button>
      </div>
    </div>

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
          <input
            v-model="form.academic_year"
            class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm"
          />
        </label>
        <label class="text-xs font-medium">
          Semester
          <select
            v-model="form.semester"
            class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm"
          >
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
          :disabled="createMutation.isPending.value"
          @click="createMutation.mutate()"
        >
          {{ createMutation.isPending.value ? "Creating..." : "Create batch" }}
        </button>
      </template>
    </AppDialog>
  </div>
</template>
