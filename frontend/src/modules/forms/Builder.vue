<script setup lang="ts">
import { ref, computed, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import {
  IconPlus, IconGripVertical, IconPencil, IconTrash,
  IconLock, IconEye, IconArrowLeft, IconLink, IconRefresh, IconCopy,
} from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import AppDialog from "@/components/dialogs/AppDialog.vue";
import FieldConfigModal from "./FieldConfigModal.vue";
import Renderer from "./Renderer.vue";
import {
  useFormDetail, useCreateForm, useUpdateForm,
  useDeleteField, useReorderFields, useRegenerateToken,
} from "@/composables/useForms";
import { toast } from "@/composables/useToast";
import type { FormDetail, FormField } from "@/api/types";
import { addField } from "@/api/forms";

const route  = useRoute();
const router = useRouter();

const isEdit = computed(() => !!route.params.id);
const formId = computed(() => String(route.params.id || ""));

// Load existing form in edit mode
const { data: formData, isLoading } = useFormDetail(formId);

// Local form state
const meta = ref({
  title: "",
  description: "",
  visibility: "private" as "public" | "private",
  target_role: "grantee" as "grantee" | "staff" | "all",
  batch_id: null as number | null,
  is_active: false,
  max_submissions: 1,
  closes_at: "",
});

const fields = ref<FormField[]>([]);

// Sync loaded form into local state
watch(formData, (d) => {
  if (!d) return;
  meta.value = {
    title: d.title,
    description: d.description ?? "",
    visibility: d.visibility,
    target_role: d.target_role,
    batch_id: d.batch_id,
    is_active: d.is_active,
    max_submissions: d.max_submissions ?? 1,
    closes_at: d.closes_at ? d.closes_at.slice(0, 16) : "",
  };
  fields.value = [...d.fields];
}, { immediate: true });

const createMutation = useCreateForm();
const updateMutation = useUpdateForm(formId);
const deleteFieldMutation = useDeleteField(formId);
const reorderMutation = useReorderFields(formId);
const regenMutation = useRegenerateToken();

const saving = ref(false);

async function saveForm() {
  if (!meta.value.title.trim()) {
    toast.error("Title is required.");
    return;
  }
  saving.value = true;
  try {
    const payload = {
      ...meta.value,
      closes_at: meta.value.closes_at || null,
    };

    if (isEdit.value) {
      await updateMutation.mutateAsync(payload);
      toast.success("Form saved.");
    } else {
      const res = await createMutation.mutateAsync(payload);
      toast.success("Form created.");
      router.push(`/app/forms/${res.data.id}/edit`);
    }
  } catch (e) {
    toast.error(e instanceof Error ? e.message : "Failed to save form.");
  } finally {
    saving.value = false;
  }
}

// Public link helpers
const publicLink = computed(() => {
  const token = formData.value?.public_token;
  return token ? `${window.location.origin}/forms/public/${token}` : null;
});

function copyLink() {
  if (publicLink.value) {
    navigator.clipboard.writeText(publicLink.value).then(() => toast.success("Link copied!"));
  }
}

const showRegenConfirm = ref(false);
async function doRegen() {
  try {
    await regenMutation.mutateAsync(formId.value);
    showRegenConfirm.value = false;
    toast.success("Token regenerated. Old link is now invalid.");
  } catch {
    toast.error("Failed to regenerate token.");
  }
}

// Visibility change warning
const showVisibilityWarning = ref(false);
const pendingVisibility = ref<"public" | "private" | null>(null);

function onVisibilityChange(val: string) {
  const v = val as "public" | "private";
  if (v === "public" && meta.value.visibility === "private") {
    pendingVisibility.value = v;
    showVisibilityWarning.value = true;
  } else if (v === "private" && meta.value.visibility === "public") {
    pendingVisibility.value = v;
    showVisibilityWarning.value = true;
  } else {
    meta.value.visibility = v;
  }
}

function confirmVisibilityChange() {
  if (pendingVisibility.value) meta.value.visibility = pendingVisibility.value;
  showVisibilityWarning.value = false;
}

// Field modal
const showFieldModal = ref(false);
const editingField = ref<FormField | null>(null);

function openAddField() {
  editingField.value = null;
  showFieldModal.value = true;
}

function openEditField(f: FormField) {
  editingField.value = { ...f };
  showFieldModal.value = true;
}

async function onFieldSaved(saved: FormField) {
  showFieldModal.value = false;

  if (!isEdit.value) {
    toast.error("Save the form first before adding fields.");
    return;
  }

  // Optimistically add to local list if new
  if (!saved.id) return;

  const idx = fields.value.findIndex((f) => f.id === saved.id);
  if (idx >= 0) {
    fields.value[idx] = saved;
  } else {
    fields.value.push(saved);
  }
}

async function removeField(f: FormField) {
  if (f.is_locked) {
    toast.error("This field is locked because the form has responses.");
    return;
  }
  try {
    await deleteFieldMutation.mutateAsync(f.id);
    fields.value = fields.value.filter((x) => x.id !== f.id);
    toast.success("Field removed.");
  } catch (e) {
    toast.error(e instanceof Error ? e.message : "Failed to remove field.");
  }
}

// Drag-to-reorder (pure CSS + mouse events, no library)
const dragIdx = ref<number | null>(null);

function onDragStart(idx: number) { dragIdx.value = idx; }
function onDragOver(idx: number) {
  if (dragIdx.value === null || dragIdx.value === idx) return;
  const reordered = [...fields.value];
  const [moved] = reordered.splice(dragIdx.value, 1);
  reordered.splice(idx, 0, moved);
  fields.value = reordered;
  dragIdx.value = idx;
}
async function onDragEnd() {
  dragIdx.value = null;
  if (!isEdit.value) return;
  const order: Record<number, number> = {};
  fields.value.forEach((f, i) => { order[f.id] = i; });
  try {
    await reorderMutation.mutateAsync(order);
  } catch {
    toast.error("Failed to save new field order.");
  }
}

// Preview
const showPreview = ref(false);
const previewSchema = computed(() => ({
  id: formData.value?.id ?? 0,
  title: meta.value.title,
  description: meta.value.description || null,
  closes_at: meta.value.closes_at || null,
  fields: fields.value,
}));
</script>

<template>
  <div class="mx-auto max-w-4xl space-y-6">
    <PageHeader
      :title="isEdit ? 'Edit Form' : 'New Form'"
      description="Configure the form settings and add fields."
    >
      <template #actions>
        <button class="inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-xs" @click="router.back()">
          <IconArrowLeft :size="14" /> Back
        </button>
        <button
          class="inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-xs"
          :disabled="!isEdit"
          @click="showPreview = true"
        >
          <IconEye :size="14" /> Preview
        </button>
        <button
          id="btn-save-form"
          class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-4 text-xs font-medium text-white disabled:opacity-60"
          :disabled="saving"
          @click="saveForm"
        >
          {{ saving ? "Saving…" : "Save form" }}
        </button>
      </template>
    </PageHeader>

    <!-- Metadata panel -->
    <section class="rounded-lg border bg-surface p-5 space-y-4">
      <h2 class="text-sm font-semibold">Form settings</h2>
      <div class="grid gap-4 sm:grid-cols-2">
        <label class="text-xs font-medium sm:col-span-2">
          Title <span class="text-danger">*</span>
          <input v-model="meta.title" class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm" placeholder="Form title" />
        </label>
        <label class="text-xs font-medium sm:col-span-2">
          Description
          <textarea v-model="meta.description" rows="3" class="mt-1.5 w-full rounded-md border px-3 py-2 text-sm" placeholder="Optional description" />
        </label>

        <!-- Visibility toggle -->
        <label class="text-xs font-medium">
          Visibility
          <select
            :value="meta.visibility"
            class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm"
            @change="onVisibilityChange(($event.target as HTMLSelectElement).value)"
          >
            <option value="private">Private (grantees only)</option>
            <option value="public">Public (no login required)</option>
          </select>
        </label>

        <label class="text-xs font-medium">
          Target role
          <select v-model="meta.target_role" class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm">
            <option value="grantee">Grantee</option>
            <option value="staff">Staff</option>
            <option value="all">All</option>
          </select>
        </label>

        <label class="text-xs font-medium">
          Max submissions per grantee
          <input v-model.number="meta.max_submissions" type="number" min="1" class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm" />
        </label>

        <label class="text-xs font-medium">
          Closes at (optional)
          <input v-model="meta.closes_at" type="datetime-local" class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm" />
        </label>

        <label class="flex items-center gap-2 text-xs font-medium sm:col-span-2 cursor-pointer">
          <input type="checkbox" v-model="meta.is_active" class="rounded" />
          Form is active
        </label>
      </div>

      <!-- Public link display -->
      <div v-if="meta.visibility === 'public' && publicLink" class="rounded-lg border border-emerald-200 bg-emerald-50 dark:bg-emerald-900/20 p-3">
        <p class="text-xs font-semibold text-emerald-700 dark:text-emerald-400 mb-1 flex items-center gap-1">
          <IconLink :size="12" /> Public URL
        </p>
        <div class="flex items-center gap-2">
          <code class="flex-1 rounded bg-surface px-2 py-1 text-xs break-all">{{ publicLink }}</code>
          <button class="grid size-7 place-items-center rounded hover:bg-surface-muted" @click="copyLink">
            <IconCopy :size="14" />
          </button>
          <button class="grid size-7 place-items-center rounded hover:bg-surface-muted text-warning" title="Regenerate token" @click="showRegenConfirm = true">
            <IconRefresh :size="14" />
          </button>
        </div>
        <p class="mt-1.5 text-micro text-warning">⚠ This form is accessible to anyone with the link — no login required.</p>
      </div>
    </section>

    <!-- Fields panel -->
    <section class="rounded-lg border bg-surface p-5 space-y-4">
      <div class="flex items-center justify-between">
        <h2 class="text-sm font-semibold">Fields <span class="ml-1 text-text-muted">({{ fields.length }})</span></h2>
        <button
          id="btn-add-field"
          class="inline-flex h-8 items-center gap-1.5 rounded-md bg-primary-soft text-primary px-3 text-xs font-medium transition hover:bg-primary hover:text-white"
          :disabled="!isEdit"
          :title="!isEdit ? 'Save the form first' : undefined"
          @click="openAddField"
        >
          <IconPlus :size="13" /> Add field
        </button>
      </div>

      <div v-if="fields.length === 0" class="py-8 text-center text-sm text-text-muted">
        No fields yet. Click "Add field" to start.
      </div>

      <div class="space-y-2">
        <div
          v-for="(field, idx) in fields"
          :key="field.id"
          :class="[
            'group relative flex items-center gap-3 rounded-lg border bg-surface-muted/30 px-3 py-2.5 transition',
            dragIdx === idx ? 'opacity-50 ring-2 ring-primary' : 'hover:bg-surface-muted/50',
          ]"
          draggable="true"
          @dragstart="onDragStart(idx)"
          @dragover.prevent="onDragOver(idx)"
          @dragend="onDragEnd"
        >
          <!-- Drag handle -->
          <span class="cursor-grab text-text-muted opacity-0 group-hover:opacity-100 transition">
            <IconGripVertical :size="14" />
          </span>

          <!-- Field info -->
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium truncate">{{ field.label }}</p>
            <p class="text-micro text-text-muted">{{ field.field_name }} · {{ field.field_type }}</p>
          </div>

          <!-- Badges -->
          <div class="flex items-center gap-1.5">
            <span v-if="field.is_required" class="rounded-full bg-danger-soft px-1.5 py-0.5 text-micro font-semibold text-danger">Required</span>
            <span v-if="field.is_locked" class="flex items-center gap-0.5 rounded-full bg-surface-muted px-1.5 py-0.5 text-micro text-text-muted">
              <IconLock :size="10" /> Locked
            </span>
          </div>

          <!-- Actions -->
          <div class="flex items-center gap-1">
            <button
              :id="`btn-edit-field-${field.id}`"
              class="grid size-7 place-items-center rounded hover:bg-surface-muted"
              @click="openEditField(field)"
            >
              <IconPencil :size="13" />
            </button>
            <button
              :id="`btn-delete-field-${field.id}`"
              class="grid size-7 place-items-center rounded hover:bg-danger-soft text-danger"
              :class="{ 'opacity-30 cursor-not-allowed': field.is_locked }"
              :disabled="field.is_locked"
              :title="field.is_locked ? 'Locked — form has responses' : 'Remove field'"
              @click="!field.is_locked && removeField(field)"
            >
              <IconTrash :size="13" />
            </button>
          </div>
        </div>
      </div>
    </section>

    <!-- Field config modal -->
    <FieldConfigModal
      v-if="showFieldModal"
      :form-id="Number(formId)"
      :editing="editingField"
      :existing-field-names="fields.filter(f => f.id !== editingField?.id).map(f => f.field_name)"
      @saved="onFieldSaved"
      @close="showFieldModal = false"
    />

    <!-- Preview modal -->
    <AppDialog v-model="showPreview" title="Form preview" size="lg">
      <Renderer :schema="previewSchema" :preview-mode="true" />
    </AppDialog>

    <!-- Visibility change warning -->
    <AppDialog v-model="showVisibilityWarning" :title="pendingVisibility === 'public' ? 'Make form public?' : 'Make form private?'">
      <p class="text-sm text-text-muted">
        <template v-if="pendingVisibility === 'public'">
          This form will become accessible to <strong>anyone with the link</strong>, without requiring a login. A public URL will be generated.
        </template>
        <template v-else>
          The current public link will be <strong>immediately invalidated</strong>. Anyone using the old link will lose access.
        </template>
      </p>
      <template #footer="{ close }">
        <button class="rounded border px-4 py-2 text-xs" @click="close">Cancel</button>
        <button class="rounded bg-primary px-4 py-2 text-xs text-white" @click="confirmVisibilityChange">Confirm</button>
      </template>
    </AppDialog>

    <!-- Regen token warning -->
    <AppDialog v-model="showRegenConfirm" title="Regenerate public link?">
      <p class="text-sm text-text-muted">
        The current public URL will be permanently invalidated. Anyone using the old link will no longer be able to access this form.
      </p>
      <template #footer="{ close }">
        <button class="rounded border px-4 py-2 text-xs" @click="close">Cancel</button>
        <button
          class="rounded bg-warning px-4 py-2 text-xs text-white disabled:opacity-60"
          :disabled="regenMutation.isPending.value"
          @click="doRegen"
        >
          {{ regenMutation.isPending.value ? "Regenerating…" : "Regenerate" }}
        </button>
      </template>
    </AppDialog>
  </div>
</template>
