<script setup lang="ts">
/**
 * StudentFormRenderer — grantee fills out a private form by ID.
 * Mounted at /student/forms/:id
 */
import { ref, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { IconArrowLeft } from "@tabler/icons-vue";
import Renderer from "./Renderer.vue";
import { useFormSchema, useSubmitFormResponse } from "@/composables/useForms";

const route  = useRoute();
const router = useRouter();

const formId = computed(() => String(route.params.id));

const { data, isLoading, isError, error } = useFormSchema(formId);
const schema = computed(() => data.value?.data ?? null);

const submitMutation = useSubmitFormResponse(formId);
const rendererRef = ref<InstanceType<typeof Renderer> | null>(null);

const statusCode = computed(() => {
  const err = error.value as { status?: number } | null;
  return err?.status ?? null;
});

async function onSubmit(payload: Record<string, unknown>) {
  try {
    const hasFile = Object.values(payload).some((v) => v instanceof File);
    let body: FormData | Record<string, unknown> = payload;

    if (hasFile) {
      const fd = new FormData();
      for (const [k, v] of Object.entries(payload)) {
        if (v instanceof File) fd.append(k, v);
        else if (Array.isArray(v)) v.forEach((item: string) => fd.append(`${k}[]`, item));
        else if (v !== null && v !== undefined) fd.append(k, String(v));
      }
      body = fd;
    }

    await submitMutation.mutateAsync(body);
    rendererRef.value?.onSuccess();
  } catch (e) {
    const err = e as { status?: number; message?: string };
    rendererRef.value?.onError(err.status ?? 0, err.message ?? "Submission failed.");
  }
}
</script>

<template>
  <div class="mx-auto max-w-2xl">
    <div class="mb-4">
      <button
        class="inline-flex h-8 items-center gap-1.5 rounded-md border px-3 text-xs hover:bg-surface-muted"
        @click="router.back()"
      >
        <IconArrowLeft :size="13" /> Back
      </button>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="rounded-xl border bg-surface p-6 space-y-4 animate-pulse">
      <div class="h-6 w-2/3 rounded bg-surface-muted"></div>
      <div class="h-4 w-full rounded bg-surface-muted"></div>
      <div class="h-10 w-full rounded bg-surface-muted"></div>
    </div>

    <!-- Error -->
    <div v-else-if="isError" class="rounded-xl border bg-surface p-8 text-center space-y-2">
      <p class="text-2xl mb-2">🔒</p>
      <h1 class="text-lg font-semibold">
        {{ statusCode === 410 ? 'This form is closed' : 'Access denied' }}
      </h1>
      <p class="text-sm text-text-muted">
        {{ statusCode === 410 ? 'This form is no longer accepting responses.' : 'You do not have access to this form.' }}
      </p>
    </div>

    <!-- Form -->
    <div v-else-if="schema" class="rounded-xl border bg-surface shadow-sm">
      <div class="border-b px-6 pt-6 pb-4">
        <h1 class="text-xl font-semibold">{{ schema.title }}</h1>
        <p v-if="schema.description" class="mt-2 text-sm text-text-muted whitespace-pre-line">{{ schema.description }}</p>
        <p v-if="schema.closes_at" class="mt-2 text-xs text-warning">
          Closes {{ new Date(schema.closes_at).toLocaleString() }}
        </p>
      </div>
      <div class="px-6 py-5">
        <Renderer ref="rendererRef" :schema="schema" @submit="onSubmit" />
      </div>
    </div>
  </div>
</template>
