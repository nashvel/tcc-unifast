<script setup lang="ts">
import { ref, computed, watch } from "vue";
import { IconX } from "@tabler/icons-vue";
import { addField, updateField } from "@/api/forms";
import { toast } from "@/composables/useToast";
import type { FormField, FieldType } from "@/api/types";

const props = defineProps<{
  formId: number;
  editing: FormField | null;
  existingFieldNames: string[];
}>();

const emit = defineEmits<{
  saved: [field: FormField];
  close: [];
}>();

const isEdit = computed(() => !!props.editing?.id);
const saving = ref(false);

const form = ref({
  label: "",
  field_name: "",
  field_type: "text" as FieldType,
  placeholder: "",
  options: [] as string[],
  is_required: true,
  min_value: "",
  max_value: "",
  min_length: null as number | null,
  max_length: null as number | null,
  accepted_types: "",
  max_file_size: null as number | null,
});

const newOption = ref("");

// Sync editing field into local state
watch(() => props.editing, (f) => {
  if (!f) return;
  form.value = {
    label: f.label,
    field_name: f.field_name,
    field_type: f.field_type,
    placeholder: f.placeholder ?? "",
    options: f.options ? [...f.options] : [],
    is_required: f.is_required,
    min_value: f.min_value ?? "",
    max_value: f.max_value ?? "",
    min_length: f.min_length,
    max_length: f.max_length,
    accepted_types: f.accepted_types ?? "",
    max_file_size: f.max_file_size,
  };
}, { immediate: true });

// Auto-generate field_name from label (only on create)
watch(() => form.value.label, (label) => {
  if (isEdit.value) return;
  form.value.field_name = label
    .toLowerCase()
    .replace(/[^a-z0-9\s]/g, "")
    .trim()
    .replace(/\s+/g, "_")
    .replace(/^[^a-z]+/, "")
    .slice(0, 100);
});

// Whether the current field type uses options
const isChoiceType = computed(() => ["select", "radio", "checkbox"].includes(form.value.field_type));
const hasMinMax = computed(() => ["text", "textarea", "number", "date"].includes(form.value.field_type));
const hasPlaceholder = computed(() => ["text", "email", "textarea", "number"].includes(form.value.field_type));
const isFileType = computed(() => form.value.field_type === "file");

function addOption() {
  const opt = newOption.value.trim();
  if (!opt || form.value.options.includes(opt)) return;
  form.value.options.push(opt);
  newOption.value = "";
}

function removeOption(idx: number) {
  form.value.options.splice(idx, 1);
}

const fieldNameError = computed(() => {
  const name = form.value.field_name;
  if (!name) return "Field name is required.";
  if (!/^[a-z][a-z0-9_]*$/.test(name)) return "Must start with a letter, only lowercase letters, numbers, underscores.";
  if (props.existingFieldNames.includes(name)) return "This field name is already used in this form.";
  return null;
});

async function save() {
  if (!form.value.label.trim()) { toast.error("Label is required."); return; }
  if (fieldNameError.value) { toast.error(fieldNameError.value); return; }
  if (isChoiceType.value && form.value.options.length < 2) {
    toast.error("Add at least 2 options for this field type.");
    return;
  }

  saving.value = true;
  try {
    const payload = {
      label: form.value.label,
      field_name: form.value.field_name,
      field_type: form.value.field_type,
      placeholder: form.value.placeholder || null,
      options: isChoiceType.value ? form.value.options : null,
      is_required: form.value.is_required,
      min_value: hasMinMax.value ? form.value.min_value || null : null,
      max_value: hasMinMax.value ? form.value.max_value || null : null,
      min_length: form.value.field_type === "text" || form.value.field_type === "textarea" ? form.value.min_length : null,
      max_length: form.value.field_type === "text" || form.value.field_type === "textarea" ? form.value.max_length : null,
      accepted_types: isFileType.value ? form.value.accepted_types || null : null,
      max_file_size: isFileType.value ? form.value.max_file_size : null,
    };

    let saved: FormField;
    if (isEdit.value && props.editing) {
      const res = await updateField(props.formId, props.editing.id, payload);
      saved = res.data;
    } else {
      const res = await addField(props.formId, payload);
      saved = res.data;
    }

    emit("saved", saved);
  } catch (e) {
    toast.error(e instanceof Error ? e.message : "Failed to save field.");
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="emit('close')">
    <div class="w-full max-w-lg rounded-xl border bg-surface shadow-xl">
      <!-- Header -->
      <div class="flex items-center justify-between border-b px-5 py-4">
        <h2 class="text-sm font-semibold">{{ isEdit ? "Edit field" : "Add field" }}</h2>
        <button class="grid size-7 place-items-center rounded hover:bg-surface-muted" @click="emit('close')">
          <IconX :size="14" />
        </button>
      </div>

      <!-- Body -->
      <div class="max-h-[70vh] overflow-y-auto px-5 py-4 space-y-4">
        <!-- Label -->
        <label class="block text-xs font-medium">
          Label <span class="text-danger">*</span>
          <input v-model="form.label" class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm" placeholder="e.g. Full Name" />
        </label>

        <!-- Field name -->
        <label class="block text-xs font-medium">
          Field name (unique key)
          <input
            v-model="form.field_name"
            class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm font-mono"
            placeholder="e.g. full_name"
            :disabled="isEdit && !!props.editing?.is_locked"
          />
          <p v-if="fieldNameError" class="mt-1 text-micro text-danger">{{ fieldNameError }}</p>
        </label>

        <!-- Field type -->
        <label class="block text-xs font-medium">
          Field type
          <select
            v-model="form.field_type"
            class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm"
            :disabled="isEdit && !!props.editing?.is_locked"
          >
            <option value="text">Text</option>
            <option value="number">Number</option>
            <option value="email">Email</option>
            <option value="textarea">Textarea</option>
            <option value="date">Date</option>
            <option value="select">Select (dropdown)</option>
            <option value="radio">Radio buttons</option>
            <option value="checkbox">Checkboxes (multi-select)</option>
            <option value="file">File upload</option>
          </select>
        </label>

        <!-- Placeholder -->
        <label v-if="hasPlaceholder" class="block text-xs font-medium">
          Placeholder text
          <input v-model="form.placeholder" class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm" placeholder="Optional hint text" />
        </label>

        <!-- Required toggle -->
        <label class="flex items-center gap-2 text-xs font-medium cursor-pointer">
          <input type="checkbox" v-model="form.is_required" class="rounded" />
          Required field
        </label>

        <!-- Text / Textarea: min/max length -->
        <div v-if="form.field_type === 'text' || form.field_type === 'textarea'" class="grid grid-cols-2 gap-3">
          <label class="text-xs font-medium">
            Min length
            <input v-model.number="form.min_length" type="number" min="0" class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm" placeholder="—" />
          </label>
          <label class="text-xs font-medium">
            Max length
            <input v-model.number="form.max_length" type="number" min="1" class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm" placeholder="—" />
          </label>
        </div>

        <!-- Number / Date: min/max value -->
        <div v-if="form.field_type === 'number' || form.field_type === 'date'" class="grid grid-cols-2 gap-3">
          <label class="text-xs font-medium">
            Min {{ form.field_type === 'date' ? 'date' : 'value' }}
            <input v-model="form.min_value" :type="form.field_type === 'date' ? 'date' : 'number'" class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm" placeholder="—" />
          </label>
          <label class="text-xs font-medium">
            Max {{ form.field_type === 'date' ? 'date' : 'value' }}
            <input v-model="form.max_value" :type="form.field_type === 'date' ? 'date' : 'number'" class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm" placeholder="—" />
          </label>
        </div>

        <!-- Choice field options builder -->
        <div v-if="isChoiceType" class="space-y-2">
          <p class="text-xs font-medium">Options <span class="text-danger">*</span> <span class="text-text-muted">(min 2)</span></p>
          <div class="flex flex-wrap gap-2">
            <span
              v-for="(opt, i) in form.options"
              :key="i"
              class="inline-flex items-center gap-1 rounded-full border bg-surface-muted px-2.5 py-1 text-xs"
            >
              {{ opt }}
              <button
                type="button"
                class="ml-0.5 text-text-muted hover:text-danger"
                :disabled="isEdit && !!props.editing?.is_locked"
                @click="removeOption(i)"
              >
                <IconX :size="10" />
              </button>
            </span>
          </div>
          <div v-if="!(isEdit && props.editing?.is_locked)" class="flex gap-2">
            <input
              v-model="newOption"
              class="h-9 flex-1 rounded-md border px-3 text-sm"
              placeholder="Type option and press Enter"
              @keydown.enter.prevent="addOption"
            />
            <button type="button" class="h-9 rounded-md border px-3 text-xs hover:bg-surface-muted" @click="addOption">Add</button>
          </div>
        </div>

        <!-- File field config -->
        <div v-if="isFileType" class="grid grid-cols-2 gap-3">
          <label class="col-span-2 text-xs font-medium">
            Accepted types (comma-separated MIME)
            <input
              v-model="form.accepted_types"
              class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm"
              placeholder="application/pdf, image/jpeg, image/png"
            />
          </label>
          <label class="col-span-2 text-xs font-medium">
            Max file size (KB)
            <input v-model.number="form.max_file_size" type="number" min="1" class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm" placeholder="5120 (= 5MB)" />
          </label>
        </div>
      </div>

      <!-- Footer -->
      <div class="flex items-center justify-end gap-2 border-t px-5 py-4">
        <button class="rounded-md border px-4 py-2 text-xs" @click="emit('close')">Cancel</button>
        <button
          id="btn-save-field"
          class="rounded-md bg-primary px-4 py-2 text-xs font-medium text-white disabled:opacity-60"
          :disabled="saving"
          @click="save"
        >
          {{ saving ? "Saving…" : "Save field" }}
        </button>
      </div>
    </div>
  </div>
</template>
