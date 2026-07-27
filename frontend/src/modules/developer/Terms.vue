<script setup lang="ts">
import { onMounted, ref } from "vue";
import { IconFileText, IconCheck, IconHistory, IconDeviceFloppy, IconLoader, IconRefresh } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import { apiFetch, isMockMode } from "@/api/client";
import { toast } from "@/composables/useToast";

type TermsDoc = {
  id?: number;
  title?: string;
  version: string;
  effectiveDate?: string;
  content: string;
};

const defaultTerms: TermsDoc = {
  id: 1,
  title: "TERMS AND CONDITIONS FOR TCC-UNIFAST TES PORTAL",
  version: "v2.1.0",
  effectiveDate: "July 1, 2026",
  content: `TERMS AND CONDITIONS FOR TCC-UNIFAST TES PORTAL

1. ACCEPTANCE OF TERMS
By accessing and utilizing the Tagoloan Community College (TCC) UniFAST Tertiary Education Subsidy (TES) Portal, students and administrators agree to adhere to all terms, policies, and regulations governed by UniFAST guidelines.

2. ACCURACY OF SUBMITTED DOCUMENTS
All documents uploaded (Certificate of Indigency, Transcript of Records, Student IDs, and Proof of Income) must be authentic. Falsification of documents will lead to immediate disqualification and legal escalation under RA 10931.

3. DATA PRIVACY COMPLIANCE
In compliance with Republic Act 10173 (Data Privacy Act of 2012), all student records collected through this portal will be processed exclusively for subsidy qualification verification and reporting.`,
};

const termsDoc = ref<TermsDoc>(defaultTerms);
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
      if (doc && doc.content) {
        termsDoc.value = doc;
      } else {
        termsDoc.value = defaultTerms;
      }
    } else {
      termsDoc.value = defaultTerms;
    }
  } catch (err: any) {
    if (isMockMode) {
      termsDoc.value = defaultTerms;
    } else {
      errorMessage.value = err?.message || "Failed to connect to backend server. Using default template.";
      termsDoc.value = defaultTerms;
    }
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
    if (isMockMode) {
      toast.success("Terms & Conditions published (Mock mode).");
    } else {
      toast.error(err?.message || "Failed to save Terms & Conditions on server.");
    }
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

    <div v-else class="space-y-4">
      <div class="grid gap-3 sm:grid-cols-2">
        <label class="block text-xs font-medium text-text">
          Document Version
          <input
            v-model="termsDoc.version"
            class="mt-1 h-9 w-full rounded-md border border-border px-3 text-xs bg-surface text-text"
            placeholder="v2.1.0"
          />
        </label>
        <label class="block text-xs font-medium text-text">
          Effective Date
          <input
            v-model="termsDoc.effectiveDate"
            class="mt-1 h-9 w-full rounded-md border border-border px-3 text-xs bg-surface text-text"
            placeholder="July 1, 2026"
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
  </div>
</template>
