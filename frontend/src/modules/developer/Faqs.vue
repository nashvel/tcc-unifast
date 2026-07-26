<script setup lang="ts">
import { onMounted, ref } from "vue";
import { IconEdit, IconPlus, IconTrash, IconCheck, IconX, IconGripVertical } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import { apiFetch } from "@/api/client";
import { toast } from "@/composables/useToast";

type Faq = {
  id: number;
  question: string;
  answer: string;
  category: string;
  sort_order: number;
  is_active: boolean;
  created_at: string;
};

const faqs = ref<Faq[]>([]);
const loading = ref(false);
const editDialog = ref(false);
const editingFaq = ref<Partial<Faq>>({});
const expandedId = ref<number | null>(null);

async function loadFaqs() {
  loading.value = true;
  try {
    const payload = await apiFetch<{ data: Faq[] }>("/api/faqs/all");
    faqs.value = payload.data;
  } catch (e) {
    toast.error("Failed to load FAQs.");
  } finally {
    loading.value = false;
  }
}

function openCreate() {
  editingFaq.value = { question: "", answer: "", category: "general", sort_order: faqs.value.length, is_active: true };
  editDialog.value = true;
}

function openEdit(faq: Faq) {
  editingFaq.value = { ...faq };
  editDialog.value = true;
}

async function save() {
  try {
    if (editingFaq.value.id) {
      await apiFetch(`/api/faqs/${editingFaq.value.id}`, {
        method: "PUT",
        body: JSON.stringify(editingFaq.value),
      });
      toast.success("FAQ updated.");
    } else {
      await apiFetch("/api/faqs", {
        method: "POST",
        body: JSON.stringify(editingFaq.value),
      });
      toast.success("FAQ created.");
    }
    editDialog.value = false;
    loadFaqs();
  } catch (e) {
    toast.error(e instanceof Error ? e.message : "Failed to save.");
  }
}

async function deleteFaq(id: number) {
  if (!confirm("Delete this FAQ?")) return;
  try {
    await apiFetch(`/api/faqs/${id}`, { method: "DELETE" });
    toast.success("FAQ deleted.");
    loadFaqs();
  } catch (e) {
    toast.error("Failed to delete.");
  }
}

async function toggleActive(faq: Faq) {
  try {
    await apiFetch(`/api/faqs/${faq.id}`, {
      method: "PUT",
      body: JSON.stringify({ is_active: !faq.is_active }),
    });
    loadFaqs();
  } catch (e) {
    toast.error("Failed to update.");
  }
}

function toggleExpand(id: number) {
  expandedId.value = expandedId.value === id ? null : id;
}

const categoryColors: Record<string, string> = {
  general: "bg-[var(--info-soft)] text-[var(--info)]",
  account: "bg-[var(--success-soft)] text-[var(--success)]",
  documents: "bg-[var(--warning-soft)] text-[var(--warning)]",
  verification: "bg-[var(--danger-soft)] text-[var(--danger)]",
};

onMounted(loadFaqs);
</script>

<template>
  <div>
    <PageHeader title="FAQ Management" description="Manage frequently asked questions displayed on the login page.">
      <template #actions>
        <button class="inline-flex h-8 items-center gap-1.5 rounded-md bg-[var(--primary)] px-3 text-xs text-black" @click="openCreate">
          <IconPlus :size="14" /> New FAQ
        </button>
      </template>
    </PageHeader>

    <div class="space-y-2">
      <div v-for="faq in faqs" :key="faq.id" class="rounded-lg border border-[var(--border)] bg-[var(--surface)]">
        <div class="flex items-center gap-3 p-4 cursor-pointer" @click="toggleExpand(faq.id)">
          <IconGripVertical :size="14" class="text-[var(--text-soft)]" />
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
              <span :class="['rounded-full px-2 py-0.5 text-2xs font-medium', categoryColors[faq.category] || 'bg-[var(--surface-muted)] text-[var(--text-muted)]']">
                {{ faq.category }}
              </span>
              <span v-if="!faq.is_active" class="rounded-full bg-[var(--surface-muted)] px-2 py-0.5 text-2xs text-[var(--text-muted)]">Inactive</span>
            </div>
            <p class="mt-1 text-sm text-[var(--text)]">{{ faq.question }}</p>
          </div>
          <div class="flex gap-1" @click.stop>
            <button class="rounded p-1.5 hover:bg-[var(--surface-muted)]" @click="toggleActive(faq)">
              <IconCheck v-if="faq.is_active" :size="14" class="text-[var(--success)]" />
              <IconX v-else :size="14" class="text-[var(--text-soft)]" />
            </button>
            <button class="rounded p-1.5 hover:bg-[var(--surface-muted)]" @click="openEdit(faq)">
              <IconEdit :size="14" class="text-[var(--text-muted)]" />
            </button>
            <button class="rounded p-1.5 hover:bg-[var(--surface-muted)]" @click="deleteFaq(faq.id)">
              <IconTrash :size="14" class="text-[var(--danger)]" />
            </button>
          </div>
        </div>
        <div v-if="expandedId === faq.id" class="border-t border-[var(--border)] px-4 py-3">
          <p class="text-xs text-[var(--text-muted)] whitespace-pre-wrap">{{ faq.answer }}</p>
        </div>
      </div>
    </div>

    <!-- Edit Dialog -->
    <div v-if="editDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="editDialog = false">
      <div class="w-full max-w-2xl rounded-lg bg-[var(--surface)] p-6 shadow-xl">
        <h2 class="text-sm font-semibold text-[var(--text)]">{{ editingFaq.id ? "Edit" : "Create" }} FAQ</h2>
        <div class="mt-4 space-y-3">
          <label class="block text-xs font-medium text-[var(--text-muted)]">
            Question
            <input v-model="editingFaq.question" class="mt-1 h-9 w-full rounded-md border border-[var(--border)] bg-[var(--bg)] px-3 text-sm text-[var(--text)]" />
          </label>
          <label class="block text-xs font-medium text-[var(--text-muted)]">
            Answer
            <textarea v-model="editingFaq.answer" rows="6" class="mt-1 w-full rounded-md border border-[var(--border)] bg-[var(--bg)] p-3 text-xs text-[var(--text)]" />
          </label>
          <div class="grid grid-cols-2 gap-3">
            <label class="block text-xs font-medium text-[var(--text-muted)]">
              Category
              <select v-model="editingFaq.category" class="mt-1 h-9 w-full rounded-md border border-[var(--border)] bg-[var(--bg)] px-3 text-sm text-[var(--text)]">
                <option value="general">General</option>
                <option value="account">Account</option>
                <option value="documents">Documents</option>
                <option value="verification">Verification</option>
              </select>
            </label>
            <label class="block text-xs font-medium text-[var(--text-muted)]">
              Sort Order
              <input v-model.number="editingFaq.sort_order" type="number" class="mt-1 h-9 w-full rounded-md border border-[var(--border)] bg-[var(--bg)] px-3 text-sm text-[var(--text)]" />
            </label>
          </div>
        </div>
        <div class="mt-4 flex justify-end gap-2">
          <button class="rounded-md border border-[var(--border)] px-4 py-2 text-xs text-[var(--text-muted)]" @click="editDialog = false">Cancel</button>
          <button class="rounded-md bg-[var(--primary)] px-4 py-2 text-xs text-black" @click="save">Save</button>
        </div>
      </div>
    </div>
  </div>
</template>
