<script setup lang="ts">
import { ref, computed, watch } from "vue";
import { IconX, IconLock, IconGripVertical, IconPlus, IconSettings, IconShieldCheck, IconLogicAnd } from "@tabler/icons-vue";
import type { FormField, FormFieldCondition } from "@/api/types";

const props = defineProps<{
  field: FormField;
  existingFieldNames: string[];
  allFields: FormField[];
}>();

const emit = defineEmits<{
  update: [field: FormField];
}>();

const activeTab = ref<'basic' | 'validation' | 'logic'>('basic');

// Local copy for two-way binding
const form = ref<FormField>(JSON.parse(JSON.stringify(props.field)));

watch(() => props.field, (newField) => {
  form.value = JSON.parse(JSON.stringify(newField));
}, { deep: true });

watch(form, (newVal) => {
  emit("update", newVal);
}, { deep: true });

// Auto-generate field_name
watch(() => form.value.label, (label) => {
  if (props.field.id > 0) return;
  form.value.field_name = label
    .toLowerCase()
    .replace(/[^a-z0-9\s]/g, "")
    .trim()
    .replace(/\s+/g, "_")
    .replace(/^[^a-z]+/, "")
    .slice(0, 100);
});

const isChoiceType = computed(() => ["select", "radio", "checkbox"].includes(form.value.field_type));
const hasMinMaxValue = computed(() => ["number", "date"].includes(form.value.field_type));
const hasMinMaxLength = computed(() => ["text", "textarea"].includes(form.value.field_type));
const hasPlaceholder = computed(() => ["text", "email", "textarea", "number"].includes(form.value.field_type));
const isFileType = computed(() => form.value.field_type === "file");

const fieldTypes = [
  { value: "text", label: "Short Text" },
  { value: "textarea", label: "Paragraph" },
  { value: "number", label: "Number" },
  { value: "email", label: "Email" },
  { value: "select", label: "Dropdown" },
  { value: "radio", label: "Multiple Choice" },
  { value: "checkbox", label: "Checkboxes" },
  { value: "date", label: "Date" },
  { value: "file", label: "File Upload" },
];

const newOption = ref("");
function addOption() {
  const opt = newOption.value.trim();
  if (!opt || form.value.options?.includes(opt)) return;
  if (!form.value.options) form.value.options = [];
  form.value.options.push(opt);
  newOption.value = "";
}

function removeOption(idx: number) {
  if (form.value.options) form.value.options.splice(idx, 1);
}

const fieldNameError = computed(() => {
  const name = form.value.field_name;
  if (!name) return "Required";
  if (!/^[a-z][a-z0-9_]*$/.test(name)) return "Invalid format";
  const duplicate = props.existingFieldNames.filter(n => n === name).length;
  if (duplicate > 1) return "Duplicate name";
  return null;
});

// Logic Condition Helpers
const availableSourceFields = computed(() => {
  // Only fields that appear BEFORE this field in the form can be used as logic sources (simplified approach)
  // Or just all choice fields except this one
  return props.allFields.filter(f => f.id !== form.value.id && ['select', 'radio', 'checkbox'].includes(f.field_type));
});

function addCondition() {
  if (!form.value.conditions) form.value.conditions = [];
  form.value.conditions.push({
    id: Date.now(), // temp id
    source_field_id: availableSourceFields.value[0]?.id || 0,
    operator: 'equals',
    condition_value: ''
  });
}

function removeCondition(idx: number) {
  form.value.conditions.splice(idx, 1);
}
</script>

<template>
  <div class="flex flex-col h-full bg-surface border-l">
    
    <!-- Header Tabs -->
    <div class="flex border-b">
      <button 
        class="flex-1 py-3 text-sm font-medium border-b-2 transition-colors flex justify-center items-center gap-1.5"
        :class="activeTab === 'basic' ? 'border-primary text-primary' : 'border-transparent text-text-muted hover:text-text hover:bg-surface-muted'"
        @click="activeTab = 'basic'"
      >
        <IconSettings :size="16" /> <span class="hidden sm:inline">Basic</span>
      </button>
      <button 
        class="flex-1 py-3 text-sm font-medium border-b-2 transition-colors flex justify-center items-center gap-1.5"
        :class="activeTab === 'validation' ? 'border-primary text-primary' : 'border-transparent text-text-muted hover:text-text hover:bg-surface-muted'"
        @click="activeTab = 'validation'"
      >
        <IconShieldCheck :size="16" /> <span class="hidden sm:inline">Validation</span>
      </button>
      <button 
        class="flex-1 py-3 text-sm font-medium border-b-2 transition-colors flex justify-center items-center gap-1.5"
        :class="activeTab === 'logic' ? 'border-primary text-primary' : 'border-transparent text-text-muted hover:text-text hover:bg-surface-muted'"
        @click="activeTab = 'logic'"
      >
        <IconLogicAnd :size="16" /> <span class="hidden sm:inline">Logic</span>
      </button>
    </div>

    <!-- Scrollable Content -->
    <div class="flex-1 overflow-y-auto p-5 space-y-6">
      
      <!-- ==================== BASIC TAB ==================== -->
      <template v-if="activeTab === 'basic'">
        <div class="space-y-4">
          <label class="block text-sm font-semibold">
            Field Label
            <input
              v-model="form.label"
              class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm font-normal focus:border-primary focus:ring-1 focus:ring-primary"
              placeholder="Type your question here..."
            />
          </label>

          <div class="space-y-4">
            <label class="block text-xs font-semibold">
              Field Type
              <select
                v-model="form.field_type"
                :disabled="form.is_locked"
                class="mt-1.5 h-9 w-full rounded-md border px-2 text-xs font-medium focus:border-primary focus:ring-1 focus:ring-primary disabled:bg-surface-muted disabled:text-text-muted"
              >
                <option v-for="t in fieldTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
              </select>
            </label>
          </div>

          <label class="flex items-center gap-2 text-sm font-semibold cursor-pointer">
            <input type="checkbox" v-model="form.is_required" class="rounded border-border text-primary focus:ring-primary h-4 w-4" />
            Required Field
          </label>
        </div>

        <hr v-if="isChoiceType" class="border-border" />

        <!-- Options for Select/Radio/Checkbox -->
        <div v-if="isChoiceType" class="space-y-3">
          <h3 class="text-sm font-semibold">Options</h3>
          <div class="space-y-2">
            <div v-for="(opt, idx) in (form.options || [])" :key="idx" class="flex items-center gap-2 bg-surface-muted/50 p-1.5 rounded-md border">
              <IconGripVertical class="text-text-muted cursor-grab" :size="14" />
              <input
                v-model="form.options![idx]"
                class="flex-1 bg-transparent text-sm focus:outline-none"
                placeholder="Option text"
              />
              <button @click="removeOption(idx)" class="text-text-muted hover:text-danger p-1 rounded hover:bg-surface-muted transition-colors">
                <IconX :size="14" />
              </button>
            </div>
            
            <div class="flex items-center gap-2 mt-2">
               <input
                 v-model="newOption"
                 @keyup.enter="addOption"
                 class="flex-1 h-9 rounded-md border px-3 text-sm focus:border-primary focus:ring-1 focus:ring-primary"
                 placeholder="Add new option..."
               />
               <button @click="addOption" class="h-9 px-3 bg-surface-muted hover:bg-border rounded-md text-sm font-medium transition-colors">Add</button>
            </div>
          </div>
        </div>

        <label v-if="hasPlaceholder" class="block text-xs font-semibold">
          Placeholder Text
          <input v-model="form.placeholder" class="mt-1.5 h-9 w-full rounded border px-3 text-sm font-normal" placeholder="Optional placeholder" />
        </label>
      </template>

      <!-- ==================== VALIDATION TAB ==================== -->
      <template v-if="activeTab === 'validation'">
        <div class="space-y-4">
          <p class="text-sm text-text-muted mb-4">Set limits on the answers (e.g., minimum length, max value).</p>

          <div v-if="hasMinMaxValue" class="grid grid-cols-2 gap-4">
            <label class="block text-xs font-semibold">
              Min value/date
              <input v-model="form.min_value" :type="form.field_type === 'date' ? 'date' : 'text'" class="w-full mt-1 p-2 border rounded-lg text-sm bg-surface-muted" />
            </label>
            <label class="block text-xs font-semibold">
              Max value/date
              <input v-model="form.max_value" :type="form.field_type === 'date' ? 'date' : 'text'" class="w-full mt-1 p-2 border rounded-lg text-sm bg-surface-muted" />
            </label>
          </div>

          <div v-if="hasMinMaxLength" class="grid grid-cols-2 gap-4">
            <label class="block text-xs font-semibold">
              Min length (chars)
              <input v-model.number="form.min_length" type="number" class="w-full mt-1 p-2 border rounded-lg text-sm bg-surface-muted" />
            </label>
            <label class="block text-xs font-semibold">
              Max length (chars)
              <input v-model.number="form.max_length" type="number" class="w-full mt-1 p-2 border rounded-lg text-sm bg-surface-muted" />
            </label>
          </div>
          
          <div v-if="isFileType" class="space-y-4">
            <label class="block text-xs font-semibold">
              Accepted file types
              <input v-model="form.accepted_types" class="mt-1.5 h-9 w-full rounded border px-3 text-sm font-normal" placeholder="e.g. .pdf,.png" />
              <span class="text-[10px] text-text-muted mt-1 block">Comma separated extensions.</span>
            </label>
            <label class="block text-xs font-semibold">
              Max file size (KB)
              <input v-model.number="form.max_file_size" type="number" min="1" class="mt-1.5 h-9 w-full rounded border px-3 text-sm font-normal" />
            </label>
          </div>

          <div v-if="!hasMinMaxValue && !hasMinMaxLength && !isFileType && form.field_type !== 'text' && form.field_type !== 'textarea'" class="text-sm text-text-muted italic">
            No specific validation rules apply to this field type.
          </div>
        </div>
      </template>

      <!-- ==================== LOGIC TAB ==================== -->
      <template v-if="activeTab === 'logic'">
        <div class="space-y-4">
          <div class="flex items-center justify-between mb-2">
            <p class="text-sm font-semibold">Only show this field when...</p>
            <button 
              @click="addCondition" 
              class="text-xs text-primary hover:underline flex items-center gap-1 font-medium disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:no-underline"
              :disabled="availableSourceFields.length === 0"
            >
              <IconPlus :size="14" /> Add Condition
            </button>
          </div>

          <p class="text-xs text-text-muted mb-4">Add conditions to dynamically show or hide this field based on previous answers.</p>
          
          <div v-if="availableSourceFields.length === 0" class="text-sm text-warning bg-warning-soft p-4 rounded-lg text-center border border-warning/30">
            No fields available for logic. You must add a Dropdown, Multiple Choice, or Checkbox field to the form first to use it as a condition.
          </div>

          <div v-else-if="!form.conditions?.length" class="text-sm text-text-muted bg-surface-muted p-4 rounded-lg text-center border border-dashed">
            Always shown. Add a condition to hide this field conditionally.
          </div>

          <div class="space-y-3">
            <div 
              v-for="(cond, idx) in form.conditions" 
              :key="cond.id"
              class="p-3 bg-surface-muted border rounded-lg space-y-2 relative group"
            >
              <button @click="removeCondition(idx)" class="absolute top-2 right-2 text-text-muted hover:text-danger opacity-0 group-hover:opacity-100 transition-opacity">
                <IconX :size="14" />
              </button>

              <div class="text-[10px] uppercase font-bold text-text-muted tracking-wider mb-1">
                {{ idx === 0 ? 'When' : 'And' }}
              </div>

              <select v-model="cond.source_field_id" class="w-full text-xs border rounded px-2 py-1.5 focus:ring-1 focus:ring-primary outline-none">
                <option :value="0" disabled>Select field...</option>
                <option v-for="f in availableSourceFields" :key="f.id" :value="f.id">{{ f.label }}</option>
              </select>

              <div class="flex gap-2">
                <select v-model="cond.operator" class="w-1/3 text-xs border rounded px-2 py-1.5 focus:ring-1 focus:ring-primary outline-none">
                  <option value="equals">is</option>
                  <option value="not_equals">is not</option>
                  <option value="contains">contains</option>
                  <option value="is_answered">is answered</option>
                  <option value="is_not_answered">is empty</option>
                </select>

                <input 
                  v-if="!['is_answered', 'is_not_answered'].includes(cond.operator)"
                  v-model="cond.condition_value"
                  type="text" 
                  class="flex-1 text-xs border rounded px-2 py-1.5 focus:ring-1 focus:ring-primary outline-none"
                  placeholder="Value"
                />
              </div>
            </div>
          </div>

        </div>
      </template>

    </div>
  </div>
</template>
