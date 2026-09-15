<script setup lang="ts">
import { onMounted, ref } from "vue";
import { IconDeviceFloppy, IconLoader } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import { apiFetch } from "@/api/client";
import { toast } from "@/composables/useToast";

type TermsDoc = {
  id?: number;
  title?: string;
  version: string;
  content: string;
};

const termsDoc = ref<TermsDoc | null>(null);
const loading = ref(false);
const saving = ref(false);
const errorMessage = ref("");

async function loadTerms() {
  loading.value = true;
  errorMessage.value = "";
  try {
    const res = await apiFetch<{ data: TermsDoc | TermsDoc[] }>("/api/terms");
    if (res.data) {
      const doc = Array.isArray(res.data) ? res.data[0] : res.data;
      termsDoc.value = doc?.content ? doc : null;
    }
  } catch (err: any) {
    errorMessage.value = err?.message || "Failed to load Terms & Conditions from the backend server.";
    termsDoc.value = null;
  } finally {
    loading.value = false;
  }
}

async function saveTerms() {
  if (!termsDoc.value || !termsDoc.value.content.trim()) {
    toast.error("Terms content cannot be empty.");
    return;
  }
  saving.value = true;

  try {
    const method = termsDoc.value.id ? "PUT" : "POST";
    const endpoint = termsDoc.value.id ? `/api/terms/${termsDoc.value.id}` : "/api/terms";
    
    const res = await apiFetch<{ data: TermsDoc }>(endpoint, {
      method,
      body: JSON.stringify({
        title: termsDoc.value.title || "Terms and Conditions",
        content: termsDoc.value.content,
        version: termsDoc.value.version || "v2.1.0",
        is_active: true,
      }),
    });
    if (res.data) termsDoc.value = res.data;
    toast.success("Terms & Conditions published to system.");
  } catch (err: any) {
    toast.error(err?.message || "Failed to save Terms & Conditions on server.");
  } finally {
    saving.value = false;
  }
}

onMounted(loadTerms);
</script>

<template>
  <div>
    <PageHeader
      title="Terms & Conditions Manager"
      description="Manage the legal terms, disclaimers, and guidelines presented to students."
    >
      <template #actions>
        <button
          class="inline-flex h-9 items-center gap-1.5 rounded-md bg-white text-black font-medium px-3 text-xs hover:bg-neutral-200 disabled:opacity-50 transition-colors shadow-sm"
          :disabled="saving || !termsDoc"
          @click="saveTerms"
        >
          <IconDeviceFloppy :size="14" /> {{ saving ? "Publishing..." : "Publish Terms" }}
        </button>
      </template>
    </PageHeader>

    <div v-if="errorMessage" class="mb-4 rounded-lg border border-amber-500/30 bg-amber-950/40 p-4 text-xs text-amber-200">
      {{ errorMessage }}
    </div>

    <div v-if="loading" class="flex items-center justify-center p-12">
      <IconLoader :size="24" class="animate-spin text-text-muted" />
    </div>

    <div v-else-if="termsDoc" class="space-y-4">
      <div>
        <label class="block text-xs font-medium text-text">
          Document Version
          <input
            v-model="termsDoc.version"
            class="mt-1 h-9 w-full rounded-md border border-border px-3 text-xs bg-surface text-text"
            placeholder="v2.1.0"
          />
        </label>
      </div>

      <div>
        <label class="block text-xs font-medium text-text mb-1">Terms & Conditions Content (Markdown / Plain Text)</label>
        <textarea
          v-model="termsDoc.content"
          rows="16"
          class="w-full rounded-lg border border-border bg-surface p-4 font-mono text-xs text-text focus:outline-none focus:ring-1 focus:ring-primary"
        />
      </div>
    </div>

    <div v-else class="rounded-lg border border-dashed bg-surface p-12 text-center text-xs text-text-muted">
      {{ errorMessage ? "Terms could not be loaded from the backend server." : "No Terms & Conditions document is available yet." }}
    </div>
  </div>
</template>
