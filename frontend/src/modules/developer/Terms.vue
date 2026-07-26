<script setup lang="ts">
import { onMounted, ref } from "vue";
import { IconEdit, IconPlus, IconTrash, IconCheck, IconX } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import { apiFetch } from "@/api/client";
import { toast } from "@/composables/useToast";

type Term = {
  id: number;
  title: string;
  content: string;
  version: string;
  is_active: boolean;
  created_at: string;
  updated_at: string;
};

const terms = ref<Term[]>([]);
const loading = ref(false);
const editDialog = ref(false);
const editingTerm = ref<Partial<Term>>({});

async function loadTerms() {
  loading.value = true;
  try {
    const payload = await apiFetch<{ data: Term[] }>("/api/terms");
    terms.value = payload.data;
  } catch (e) {
    toast.error("Failed to load terms.");
  } finally {
    loading.value = false;
  }
}

function openCreate() {
  editingTerm.value = { title: "", content: "", version: "1.0", is_active: true };
  editDialog.value = true;
}

function openEdit(term: Term) {
  editingTerm.value = { ...term };
  editDialog.value = true;
}

async function save() {
  try {
    if (editingTerm.value.id) {
      await apiFetch(`/api/terms/${editingTerm.value.id}`, {
        method: "PUT",
        body: JSON.stringify(editingTerm.value),
      });
      toast.success("Term updated.");
    } else {
      await apiFetch("/api/terms", {
        method: "POST",
        body: JSON.stringify(editingTerm.value),
      });
      toast.success("Term created.");
    }
    editDialog.value = false;
    loadTerms();
  } catch (e) {
    toast.error(e instanceof Error ? e.message : "Failed to save.");
  }
}

async function deleteTerm(id: number) {
  if (!confirm("Delete this term?")) return;
  try {
    await apiFetch(`/api/terms/${id}`, { method: "DELETE" });
    toast.success("Term deleted.");
    loadTerms();
  } catch (e) {
    toast.error("Failed to delete.");
  }
}

async function toggleActive(term: Term) {
  try {
    await apiFetch(`/api/terms/${term.id}`, {
      method: "PUT",
      body: JSON.stringify({ is_active: !term.is_active }),
    });
    loadTerms();
  } catch (e) {
    toast.error("Failed to update.");
  }
}

onMounted(loadTerms);
</script>

<template>
  <div>
    <PageHeader title="Terms & Conditions" description="Manage the terms and conditions displayed on the login page.">
      <template #actions>
        <button class="inline-flex h-8 items-center gap-1.5 rounded-md bg-[var(--primary)] px-3 text-xs text-black" @click="openCreate">
          <IconPlus :size="14" /> New Term
        </button>
      </template>
    </PageHeader>

    <div class="space-y-2">
      <div v-for="term in terms" :key="term.id" class="flex items-center justify-between rounded-lg border border-[var(--border)] bg-[var(--surface)] px-4 py-3">
        <div class="flex items-center gap-3">
          <h3 class="text-sm font-medium text-[var(--text)]">{{ term.title }}</h3>
          <span class="rounded bg-[var(--surface-muted)] px-1.5 py-0.5 font-mono text-2xs text-[var(--text-muted)]">v{{ term.version }}</span>
          <span :class="['rounded-full px-2 py-0.5 text-2xs font-medium', term.is_active ? 'bg-[var(--success-soft)] text-[var(--success)]' : 'bg-[var(--surface-muted)] text-[var(--text-muted)]']">
            {{ term.is_active ? "Active" : "Inactive" }}
          </span>
          <span class="text-2xs text-[var(--text-soft)]">Updated {{ new Date(term.updated_at).toLocaleDateString() }}</span>
        </div>
        <div class="flex gap-1">
          <button class="rounded p-1.5 hover:bg-[var(--surface-muted)]" @click="toggleActive(term)">
            <IconCheck v-if="term.is_active" :size="14" class="text-[var(--success)]" />
            <IconX v-else :size="14" class="text-[var(--text-soft)]" />
          </button>
          <button class="rounded p-1.5 hover:bg-[var(--surface-muted)]" @click="openEdit(term)">
            <IconEdit :size="14" class="text-[var(--text-muted)]" />
          </button>
          <button class="rounded p-1.5 hover:bg-[var(--surface-muted)]" @click="deleteTerm(term.id)">
            <IconTrash :size="14" class="text-[var(--danger)]" />
          </button>
        </div>
      </div>
    </div>

    <!-- Edit Dialog -->
    <div v-if="editDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="editDialog = false">
      <div class="w-full max-w-2xl rounded-lg bg-[var(--surface)] p-6 shadow-xl">
        <h2 class="text-sm font-semibold text-[var(--text)]">{{ editingTerm.id ? "Edit" : "Create" }} Terms & Conditions</h2>
        <div class="mt-4 space-y-3">
          <label class="block text-xs font-medium text-[var(--text-muted)]">
            Title
            <input v-model="editingTerm.title" class="mt-1 h-9 w-full rounded-md border border-[var(--border)] bg-[var(--bg)] px-3 text-sm text-[var(--text)]" />
          </label>
          <label class="block text-xs font-medium text-[var(--text-muted)]">
            Version
            <input v-model="editingTerm.version" class="mt-1 h-9 w-full rounded-md border border-[var(--border)] bg-[var(--bg)] px-3 text-sm text-[var(--text)]" />
          </label>
          <label class="block text-xs font-medium text-[var(--text-muted)]">
            Content (HTML)
            <textarea v-model="editingTerm.content" rows="12" class="mt-1 w-full rounded-md border border-[var(--border)] bg-[var(--bg)] p-3 font-mono text-xs text-[var(--text)]" />
          </label>
        </div>
        <div class="mt-4 flex justify-end gap-2">
          <button class="rounded-md border border-[var(--border)] px-4 py-2 text-xs text-[var(--text-muted)]" @click="editDialog = false">Cancel</button>
          <button class="rounded-md bg-[var(--primary)] px-4 py-2 text-xs text-black" @click="save">Save</button>
        </div>
      </div>
    </div>
  </div>
</template>
