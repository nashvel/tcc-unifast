<script setup lang="ts">
import { ref, computed } from "vue";
import { useQuery, useMutation, useQueryClient } from "@tanstack/vue-query";
import { IconEdit, IconPlus, IconTrash } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import DataTable from "@/components/tables/DataTable.vue";
import AppDialog from "@/components/dialogs/AppDialog.vue";
import { apiFetch } from "@/api/client";
import { toast } from "@/composables/useToast";

type AcademicProgram = {
  id: number;
  code: string;
  name: string;
  pass_grade: number;
  pass_grade_display: string;
  is_active: boolean;
};

const queryClient = useQueryClient();

const programsQuery = useQuery({
  queryKey: ["academic_programs"],
  queryFn: () => apiFetch<{ data: AcademicProgram[] }>("/api/academic-programs"),
});
const programs = computed(() => programsQuery.data.value?.data ?? []);

const dialogOpen = ref(false);
const editingProgram = ref<AcademicProgram | null>(null);
const form = ref({
  code: "",
  name: "",
  pass_grade: 3.0,
  is_active: true,
});

function openNewDialog() {
  editingProgram.value = null;
  form.value = { code: "", name: "", pass_grade: 3.0, is_active: true };
  dialogOpen.value = true;
}

function openEditDialog(program: AcademicProgram) {
  editingProgram.value = program;
  form.value = { 
    code: program.code, 
    name: program.name, 
    pass_grade: program.pass_grade, 
    is_active: program.is_active 
  };
  dialogOpen.value = true;
}

const deleteDialogOpen = ref(false);
const deletingProgram = ref<AcademicProgram | null>(null);

function openDeleteDialog(program: AcademicProgram) {
  deletingProgram.value = program;
  deleteDialogOpen.value = true;
}

const busy = ref(false);
const error = ref("");

async function saveProgram() {
  busy.value = true;
  error.value = "";
  try {
    if (editingProgram.value) {
      await apiFetch(`/api/academic-programs/${editingProgram.value.id}`, {
        method: "PATCH",
        body: JSON.stringify(form.value),
      });
      toast.success("Program updated successfully");
    } else {
      await apiFetch("/api/academic-programs", {
        method: "POST",
        body: JSON.stringify(form.value),
      });
      toast.success("Program created successfully");
    }
    await queryClient.invalidateQueries({ queryKey: ["academic_programs"] });
    dialogOpen.value = false;
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "Unable to save program.";
    toast.error(error.value);
  } finally {
    busy.value = false;
  }
}

async function deleteProgram() {
  if (!deletingProgram.value) return;
  busy.value = true;
  try {
    await apiFetch(`/api/academic-programs/${deletingProgram.value.id}`, { method: "DELETE" });
    toast.success("Program deleted successfully");
    await queryClient.invalidateQueries({ queryKey: ["academic_programs"] });
    deleteDialogOpen.value = false;
  } catch (exception) {
    toast.error(exception instanceof Error ? exception.message : "Unable to delete program.");
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <div>
    <PageHeader title="Academic Programs" description="Manage the list of valid programs for masterlist validation.">
      <template #actions>
        <button
          class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-xs text-white"
          @click="openNewDialog"
        >
          <IconPlus :size="14" />New program
        </button>
      </template>
    </PageHeader>

    <div class="rounded-lg border bg-surface">
      <DataTable
        :headings="['Code', 'Name', 'Passing Grade', 'Status', 'Actions']"
      >
        <tr v-if="programsQuery.isLoading.value">
          <td colspan="5" class="p-4 text-center text-xs text-text-muted">Loading programs...</td>
        </tr>
        <tr v-else-if="!programs.length">
          <td colspan="5" class="p-4 text-center text-xs text-text-muted">No academic programs found.</td>
        </tr>
        <tr v-for="program in programs" :key="program.id" class="group border-t bg-surface hover:bg-surface-muted transition-colors">
          <td class="px-4 py-3 text-sm font-semibold text-text">{{ program.code }}</td>
          <td class="px-4 py-3 text-sm text-text-muted">{{ program.name }}</td>
          <td class="px-4 py-3 text-sm text-text-muted">{{ program.pass_grade_display }}</td>
          <td class="px-4 py-3 text-sm">
            <span v-if="program.is_active" class="inline-flex rounded-full bg-success-soft px-2 py-0.5 text-xs font-semibold text-success">Active</span>
            <span v-else class="inline-flex rounded-full bg-surface-muted px-2 py-0.5 text-xs font-semibold text-text-muted">Inactive</span>
          </td>
          <td class="px-4 py-3 text-sm">
            <div class="flex items-center gap-2">
              <button class="text-text-muted hover:text-primary" @click="openEditDialog(program)" title="Edit">
                <IconEdit :size="16" />
              </button>
              <button class="text-text-muted hover:text-danger" @click="openDeleteDialog(program)" title="Delete">
                <IconTrash :size="16" />
              </button>
            </div>
          </td>
        </tr>
      </DataTable>
    </div>

    <AppDialog
      v-model="dialogOpen"
      :title="editingProgram ? 'Edit program' : 'New program'"
      :description="editingProgram ? 'Update academic program details.' : 'Add a new academic program to the registry.'"
    >
      <form @submit.prevent="saveProgram" class="space-y-4">
        <div>
          <label class="mb-1 block text-xs font-semibold">Program Code</label>
          <input
            v-model="form.code"
            type="text"
            required
            class="w-full rounded-md border bg-surface px-3 py-2 text-sm focus:border-primary focus:outline-none"
            placeholder="e.g. BSIT"
          />
        </div>
        <div>
          <label class="mb-1 block text-xs font-semibold">Program Name</label>
          <input
            v-model="form.name"
            type="text"
            required
            class="w-full rounded-md border bg-surface px-3 py-2 text-sm focus:border-primary focus:outline-none"
            placeholder="e.g. Bachelor of Science in Information Technology"
          />
        </div>
        <div>
          <label class="mb-1 block text-xs font-semibold">Passing Grade</label>
          <input
            v-model="form.pass_grade"
            type="number"
            step="0.1"
            min="1.0"
            max="5.0"
            required
            class="w-full rounded-md border bg-surface px-3 py-2 text-sm focus:border-primary focus:outline-none"
          />
        </div>
        <div class="flex items-center gap-2">
          <input type="checkbox" id="isActiveCheck" v-model="form.is_active" class="rounded border-gray-300 text-primary focus:ring-primary" />
          <label for="isActiveCheck" class="text-sm font-medium">Active Program</label>
        </div>

        <div v-if="error" class="text-xs text-danger">{{ error }}</div>

        <div class="flex justify-end gap-2 pt-2">
          <button
            type="button"
            class="rounded-md border px-4 py-2 text-xs font-medium hover:bg-surface-muted"
            @click="dialogOpen = false"
          >
            Cancel
          </button>
          <button
            type="submit"
            class="rounded-md bg-primary px-4 py-2 text-xs font-medium text-white disabled:opacity-50"
            :disabled="busy"
          >
            {{ busy ? "Saving..." : "Save program" }}
          </button>
        </div>
      </form>
    </AppDialog>

    <AppDialog
      v-model="deleteDialogOpen"
      title="Delete program"
      description="Are you sure you want to delete this academic program? This action cannot be undone."
    >
      <div class="flex justify-end gap-2 pt-4">
        <button
          type="button"
          class="rounded-md border px-4 py-2 text-xs font-medium hover:bg-surface-muted"
          @click="deleteDialogOpen = false"
        >
          Cancel
        </button>
        <button
          type="button"
          class="rounded-md bg-danger px-4 py-2 text-xs font-medium text-white disabled:opacity-50"
          :disabled="busy"
          @click="deleteProgram"
        >
          {{ busy ? "Deleting..." : "Delete program" }}
        </button>
      </div>
    </AppDialog>
  </div>
</template>
