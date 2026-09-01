<script setup lang="ts">
import { ref, computed } from "vue";
import { useRouter } from "vue-router";
import { IconFileText, IconCircleCheck, IconClock, IconArrowRight } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import CardSkeleton from "@/components/ui/CardSkeleton.vue";
import EmptyState from "@/components/ui/EmptyState.vue";
import { useAssignedForms } from "@/composables/useForms";
import type { AssignedForm } from "@/api/types";
import emptyFormsVideo from "@/assets/student-forms-empty.webm";

const router = useRouter();

const { data, isLoading, isError, refetch } = useAssignedForms();
const forms = computed(() => data.value?.data ?? []);

function statusLabel(f: AssignedForm) {
  if (f.already_submitted) return { text: "Submitted", cls: "bg-success-soft text-success" };
  if (f.is_closed) return { text: "Closed", cls: "bg-surface-muted text-text-muted" };
  return { text: "Open", cls: "bg-primary-soft text-primary" };
}

function deadline(f: AssignedForm) {
  if (!f.closes_at) return "No deadline";
  const d = new Date(f.closes_at);
  const now = new Date();
  const diff = Math.ceil((d.getTime() - now.getTime()) / 1000 / 60 / 60 / 24);
  if (diff < 0) return "Closed";
  if (diff === 0) return "Closes today";
  if (diff === 1) return "Closes tomorrow";
  return `Closes in ${diff} days`;
}
</script>

<template>
  <div>
    <PageHeader title="Forms" description="Complete forms assigned to you by the scholarship office." />

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
      <template v-if="isLoading">
        <CardSkeleton v-for="i in 4" :key="i" :lines="3" />
      </template>

      <EmptyState
        v-else-if="isError"
        variant="error"
        title="Couldn't load forms"
        hint="Please try again."
        @retry="refetch()"
      />

      <template v-else>
        <div
          v-if="!forms.length"
          class="flex min-h-[260px] flex-col items-center justify-center px-6 py-8 text-center md:col-span-2 xl:col-span-3"
        >
          <video
            class="h-80 w-80 max-w-full object-contain"
            :src="emptyFormsVideo"
            autoplay
            loop
            muted
            playsinline
            aria-hidden="true"
          />
          <p class="mt-4 text-sm font-medium">No forms assigned</p>
          <p class="mt-1 max-w-sm text-xs text-text-muted">No forms have been assigned to you yet.</p>
        </div>

        <button
          v-for="form in forms"
          :key="form.id"
          :id="`btn-open-form-${form.id}`"
          class="group text-left rounded-xl border bg-surface p-5 transition hover:border-primary/40 hover:shadow-sm disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="form.is_closed && !form.already_submitted"
          @click="!form.already_submitted && !form.is_closed && router.push(`/student/forms/${form.id}`)"
        >
          <div class="flex items-start justify-between mb-3">
            <span class="grid size-10 place-items-center rounded-lg bg-primary-soft text-primary">
              <IconFileText :size="18" />
            </span>
            <span :class="['rounded-full px-2 py-0.5 text-micro font-semibold', statusLabel(form).cls]">
              {{ statusLabel(form).text }}
            </span>
          </div>

          <h2 class="text-sm font-semibold">{{ form.title }}</h2>
          <p v-if="form.description" class="mt-1 line-clamp-2 text-xs text-text-muted">{{ form.description }}</p>

          <div class="mt-3 flex items-center justify-between">
            <span class="flex items-center gap-1 text-xs text-text-muted">
              <IconClock :size="12" />
              {{ deadline(form) }}
            </span>
            <span v-if="!form.already_submitted && !form.is_closed" class="inline-flex items-center gap-0.5 text-xs font-medium text-primary opacity-0 group-hover:opacity-100 transition">
              Fill out <IconArrowRight :size="12" />
            </span>
            <span v-else-if="form.already_submitted" class="flex items-center gap-0.5 text-xs text-success">
              <IconCircleCheck :size="12" /> Done
            </span>
          </div>
        </button>
      </template>
    </section>
  </div>
</template>
