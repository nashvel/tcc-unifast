<script setup lang="ts">
import { onMounted, ref } from "vue";
import { IconHelpCircle, IconPlus, IconEdit, IconTrash, IconLoader, IconAlertTriangle } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import { apiFetch, isMockMode } from "@/api/client";
import { toast } from "@/composables/useToast";

type FaqItem = {
  id: number;
  question: string;
  answer: string;
  category: string;
  is_active?: boolean;
};

const initialFaqs: FaqItem[] = [
  { id: 1, question: "Who is eligible for the Tertiary Education Subsidy (TES)?", answer: "Filipino students currently enrolled in accredited State Universities and Colleges (SUCs) or Local Universities and Colleges (LUCs) like Tagoloan Community College with valid Listahanan 2.0 or 3.0 registration.", category: "Grants & Eligibility", is_active: true },
  { id: 2, question: "What documents are required for initial verification?", answer: "Enrolled students must submit a Certificate of Indigency, Transcript of Records or Grade Slip, Official Student ID, and Proof of Family Income.", category: "Document Vault", is_active: true },
  { id: 3, question: "How will subsidy disbursements be distributed?", answer: "Subsidy disbursements are directly deposited to verified Landbank cash cards or issued via official TCC institutional payroll checks under UniFAST supervision.", category: "Disbursement", is_active: true },
];

const faqs = ref<FaqItem[]>([]);
const loading = ref(false);
const dialog = ref(false);
const confirmDeleteDialog = ref(false);
const selectedFaq = ref<FaqItem | null>(null);
const errorMessage = ref("");
const form = ref<FaqItem>({ id: 0, question: "", answer: "", category: "Grants & Eligibility", is_active: true });

async function loadFaqs() {
  loading.value = true;
  errorMessage.value = "";
  try {
    const res = await apiFetch<{ data: FaqItem[] }>("/api/faqs/all");
    if (res.data && res.data.length > 0) {
      faqs.value = res.data;
    } else {
      faqs.value = isMockMode ? initialFaqs : [];
    }
  } catch (err: any) {
    if (isMockMode) {
      faqs.value = initialFaqs;
    } else {
      errorMessage.value = err?.message || "Failed to load FAQs from backend server.";
      toast.error(errorMessage.value);
      faqs.value = [];
    }
  } finally {
    loading.value = false;
  }
}

async function saveFaq() {
  if (!form.value.question.trim() || !form.value.answer.trim()) {
    toast.error("Question and Answer are required.");
    return;
  }

  const isEdit = form.value.id > 0;
  const endpoint = isEdit ? `/api/faqs/${form.value.id}` : "/api/faqs";
  const method = isEdit ? "PUT" : "POST";

  try {
    const res = await apiFetch<{ data: FaqItem }>(endpoint, {
      method,
      body: JSON.stringify(form.value),
    });
    if (isEdit) {
      const idx = faqs.value.findIndex((f) => f.id === form.value.id);
      if (idx !== -1) faqs.value[idx] = res.data;
    } else {
      faqs.value.push(res.data);
    }
    toast.success(isEdit ? "FAQ updated" : "FAQ created");
    dialog.value = false;
  } catch (err: any) {
    if (isMockMode) {
      if (isEdit) {
        const idx = faqs.value.findIndex((f) => f.id === form.value.id);
        if (idx !== -1) faqs.value[idx] = { ...form.value };
      } else {
        faqs.value.push({ ...form.value, id: Date.now(), is_active: true });
      }
      toast.success(isEdit ? "FAQ updated (Mock mode)" : "FAQ created (Mock mode)");
      dialog.value = false;
    } else {
      toast.error(err?.message || "Failed to save FAQ on server.");
    }
  }
}

function promptDelete(faq: FaqItem) {
  selectedFaq.value = faq;
  confirmDeleteDialog.value = true;
}

async function confirmDelete() {
  if (!selectedFaq.value) return;
  const faq = selectedFaq.value;

  try {
    await apiFetch(`/api/faqs/${faq.id}`, { method: "DELETE" });
    const target = faqs.value.find((f) => f.id === faq.id);
    if (target) {
      target.is_active = false;
    }
    toast.success("FAQ deactivated (soft deleted).");
  } catch (err: any) {
    if (isMockMode) {
      const target = faqs.value.find((f) => f.id === faq.id);
      if (target) {
        target.is_active = false;
      }
      toast.success("FAQ deactivated (Mock mode)");
    } else {
      toast.error(err?.message || "Failed to deactivate FAQ on server.");
    }
  } finally {
    confirmDeleteDialog.value = false;
    selectedFaq.value = null;
  }
}

function openCreate() {
  form.value = { id: 0, question: "", answer: "", category: "Grants & Eligibility", is_active: true };
  dialog.value = true;
}

function openEdit(faq: FaqItem) {
  form.value = { ...faq };
  dialog.value = true;
}

onMounted(loadFaqs);
</script>

<template>
  <div>
    <PageHeader
      title="FAQ Knowledge Base Manager"
      description="Create and update frequently asked questions shown to students and applicants."
    >
      <template #actions>
        <button
          class="inline-flex h-9 items-center gap-1.5 rounded-md bg-white text-black font-medium px-3 text-xs hover:bg-neutral-200 transition-colors shadow-sm"
          @click="openCreate"
        >
          <IconPlus :size="14" /> Add New FAQ
        </button>
      </template>
    </PageHeader>

    <div v-if="errorMessage" class="mb-4 rounded-lg border border-red-500/30 bg-red-950/40 p-4 text-xs text-red-200">
      {{ errorMessage }}
    </div>

    <div class="space-y-4">
      <div v-if="loading" class="p-12 text-center text-text-muted">
        <IconLoader :size="20" class="animate-spin inline mr-2" /> Loading FAQ items...
      </div>

      <div v-else-if="faqs.length === 0" class="rounded-lg border border-dashed bg-surface p-12 text-center text-xs text-text-muted">
        {{ errorMessage ? "Unable to load FAQs from backend server." : "No FAQ items created yet." }}
      </div>

      <div v-for="faq in faqs" :key="faq.id" class="rounded-lg border bg-surface p-4">
        <div class="flex items-start justify-between gap-4">
          <div>
            <div class="flex items-center gap-2">
              <span class="rounded bg-surface-muted px-2 py-0.5 text-2xs font-medium text-text-muted">
                {{ faq.category }}
              </span>
              <span
                :class="[
                  'rounded-full px-2 py-0.5 text-2xs font-semibold',
                  faq.is_active !== false ? 'bg-success-soft text-success' : 'bg-danger-soft text-danger',
                ]"
              >
                {{ faq.is_active !== false ? 'Active' : 'Inactive (Soft Deleted)' }}
              </span>
            </div>
            <h3 class="mt-2 text-sm font-semibold text-text">{{ faq.question }}</h3>
            <p class="mt-1 text-xs text-text-muted leading-relaxed">{{ faq.answer }}</p>
          </div>

          <div class="flex items-center gap-1 shrink-0">
            <button class="rounded p-1.5 text-text-muted hover:text-text hover:bg-surface-muted" title="Edit FAQ" @click="openEdit(faq)">
              <IconEdit :size="14" />
            </button>
            <button
              v-if="faq.is_active !== false"
              class="rounded p-1.5 text-text-muted hover:text-danger hover:bg-surface-muted"
              title="Deactivate FAQ (soft delete)"
              @click="promptDelete(faq)"
            >
              <IconTrash :size="14" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Confirm Delete Modal -->
    <div
      v-if="confirmDeleteDialog && selectedFaq"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
      @click.self="confirmDeleteDialog = false"
    >
      <div class="w-full max-w-sm rounded-lg bg-surface p-5 shadow-2xl border border-border">
        <div class="flex items-center gap-3">
          <div class="grid size-9 place-items-center rounded-full bg-danger-soft text-danger shrink-0">
            <IconAlertTriangle :size="18" />
          </div>
          <div>
            <h3 class="text-sm font-bold text-text">Deactivate FAQ Item?</h3>
            <p class="text-2xs text-text-muted mt-0.5">
              Confirm setting <span class="font-semibold text-text">"{{ selectedFaq.question }}"</span> to inactive.
            </p>
          </div>
        </div>

        <div class="mt-5 flex justify-end gap-2">
          <button class="rounded-md border border-border px-3 py-1.5 text-xs text-text hover:bg-surface-muted" @click="confirmDeleteDialog = false">Cancel</button>
          <button class="rounded-md bg-danger px-3 py-1.5 text-xs font-medium text-white hover:bg-danger/90" @click="confirmDelete">
            Confirm Deactivate
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Dialog -->
    <div v-if="dialog" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="dialog = false">
      <div class="w-full max-w-md rounded-lg bg-surface p-6 shadow-xl border border-border">
        <h2 class="text-sm font-semibold text-text">{{ form.id ? "Edit FAQ" : "Add FAQ" }}</h2>
        <div class="mt-4 space-y-3">
          <label class="block text-xs font-medium text-text">
            Category
            <select v-model="form.category" class="mt-1 h-9 w-full rounded-md border border-border bg-surface px-3 text-xs text-text">
              <option value="Grants & Eligibility">Grants & Eligibility</option>
              <option value="Document Vault">Document Vault</option>
              <option value="Disbursement">Disbursement</option>
              <option value="General Support">General Support</option>
            </select>
          </label>
          <label class="block text-xs font-medium text-text">
            Question *
            <input v-model="form.question" class="mt-1 h-9 w-full rounded-md border border-border bg-surface px-3 text-xs text-text" />
          </label>
          <label class="block text-xs font-medium text-text">
            Answer *
            <textarea v-model="form.answer" rows="4" class="mt-1 w-full rounded-md border border-border bg-surface p-3 text-xs text-text" />
          </label>
        </div>

        <div class="mt-6 flex justify-end gap-2">
          <button class="rounded-md border border-border px-3 py-2 text-xs text-text hover:bg-surface-muted" @click="dialog = false">Cancel</button>
          <button class="rounded-md bg-white text-black font-medium px-3 py-2 text-xs hover:bg-neutral-200" @click="saveFaq">Save FAQ</button>
        </div>
      </div>
    </div>
  </div>
</template>
