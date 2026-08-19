<script setup lang="ts">
/**
 * Public Form Page — no authentication required.
 * Mounted at /forms/public/:token — outside the authenticated app shell.
 */
import { ref, computed } from "vue";
import { useRoute } from "vue-router";
import Renderer from "./Renderer.vue";
import { usePublicForm, useSubmitPublicForm } from "@/composables/useForms";

const route = useRoute();
const token = computed(() => String(route.params.token));

const { data, isLoading, isError, error } = usePublicForm(token);
const schema = computed(() => data.value?.data ?? null);
const submitMutation = useSubmitPublicForm(token);

const rendererRef = ref<InstanceType<typeof Renderer> | null>(null);

const statusCode = computed(() => {
  if (!error.value) return null;
  const err = error.value as { status?: number };
  return err.status ?? null;
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
  <div class="min-h-screen bg-surface-muted/30 py-12 px-4">
    <div class="mx-auto w-full max-w-xl">
      <!-- Branding -->
      <div class="mb-8 text-center">
        <p class="text-xs font-semibold uppercase tracking-widest text-text-muted">TCC UniFAST</p>
      </div>

      <!-- Loading skeleton -->
      <div v-if="isLoading" class="rounded-xl border bg-surface p-6 space-y-4 animate-pulse">
        <div class="h-6 w-2/3 rounded bg-surface-muted"></div>
        <div class="h-4 w-full rounded bg-surface-muted"></div>
        <div class="h-10 w-full rounded bg-surface-muted"></div>
        <div class="h-10 w-full rounded bg-surface-muted"></div>
      </div>

      <!-- Error states -->
      <div v-else-if="isError" class="rounded-xl border bg-surface p-8 text-center space-y-2">
        <template v-if="statusCode === 410">
          <p class="text-2xl mb-2">🔒</p>
          <h1 class="text-lg font-semibold">This form is closed</h1>
          <p class="text-sm text-text-muted">This form is no longer accepting responses.</p>
        </template>
        <template v-else-if="statusCode === 400">
          <p class="text-2xl mb-2">⚠️</p>
          <h1 class="text-lg font-semibold">Invalid request</h1>
          <p class="text-sm text-text-muted">The link you used is not valid.</p>
        </template>
        <template v-else>
          <p class="text-2xl mb-2">❌</p>
          <h1 class="text-lg font-semibold">Form not found</h1>
          <p class="text-sm text-text-muted">This form may have been removed or the link is incorrect.</p>
        </template>
      </div>

      <!-- Form card -->
      <div v-else-if="schema" class="rounded-xl border bg-surface shadow-sm">
        <!-- Header -->
        <div class="border-b px-6 pt-6 pb-4">
          <h1 class="text-xl font-semibold">{{ schema.title }}</h1>
          <p v-if="schema.description" class="mt-2 text-sm text-text-muted whitespace-pre-line">{{ schema.description }}</p>
          <p v-if="schema.closes_at" class="mt-2 text-xs text-warning">
            Closes {{ new Date(schema.closes_at).toLocaleString() }}
          </p>
        </div>

        <!-- Renderer -->
        <div class="px-6 py-5">
          <Renderer ref="rendererRef" :schema="schema" @submit="onSubmit" />
        </div>

        <!-- Footer -->
        <div class="border-t px-6 py-3 text-center">
          <p class="text-micro text-text-muted">Powered by TCC UniFAST · Tagoloan Community College</p>
        </div>
      </div>
    </div>
  </div>
</template>
