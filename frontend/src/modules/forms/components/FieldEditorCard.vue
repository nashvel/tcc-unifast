<script setup lang="ts">
import { computed } from "vue";
import { 
  IconGripVertical, 
  IconCopy, 
  IconTrash, 
  IconAlignLeft, 
  IconHash, 
  IconMail, 
  IconList, 
  IconCircleDot, 
  IconCheckbox, 
  IconCalendar, 
  IconUpload,
  IconArrowUp,
  IconArrowDown
} from "@tabler/icons-vue";
import type { FormField } from "@/api/types";

const props = defineProps<{
  field: FormField;
  selected?: boolean;
}>();

const emit = defineEmits<{
  remove: [id: number];
  duplicate: [field: FormField];
  select: [id: number];
  moveUp: [id: number];
  moveDown: [id: number];
}>();

const fieldTypeIcon = computed(() => {
  switch (props.field.field_type) {
    case 'text': case 'textarea': return IconAlignLeft;
    case 'number': return IconHash;
    case 'email': return IconMail;
    case 'select': return IconList;
    case 'radio': return IconCircleDot;
    case 'checkbox': return IconCheckbox;
    case 'date': return IconCalendar;
    case 'file': return IconUpload;
    default: return IconAlignLeft;
  }
});

const fieldTypeLabel = computed(() => {
  const map: Record<string, string> = {
    text: "Short Text",
    textarea: "Paragraph",
    number: "Number",
    email: "Email",
    select: "Dropdown",
    radio: "Multiple Choice",
    checkbox: "Checkboxes",
    date: "Date",
    file: "File Upload",
  };
  return map[props.field.field_type] || props.field.field_type;
});
</script>

<template>
  <div 
    class="group flex items-center gap-3 rounded-lg border bg-surface p-3 shadow-sm hover:border-primary/40 focus-within:border-primary/40 transition cursor-pointer"
    :class="{ 'border-primary ring-1 ring-primary': selected }"
    @click="emit('select', field.id)"
    tabindex="0"
    @keydown.enter.prevent="emit('select', field.id)"
  >
    
    <!-- Drag Handle & Keyboard Move -->
    <div class="flex flex-col items-center">
      <button 
        class="sr-only focus:not-sr-only focus:p-1 text-primary"
        @click.stop="emit('moveUp', field.id)"
        title="Move Up"
      >
        <IconArrowUp :size="14" />
      </button>
      <div class="cursor-grab text-border hover:text-primary active:cursor-grabbing shrink-0 transition-colors">
        <IconGripVertical :size="18" />
      </div>
      <button 
        class="sr-only focus:not-sr-only focus:p-1 text-primary"
        @click.stop="emit('moveDown', field.id)"
        title="Move Down"
      >
        <IconArrowDown :size="14" />
      </button>
    </div>

    <!-- Icon -->
    <div class="flex items-center justify-center w-8 h-8 rounded bg-surface-muted shrink-0 text-text-muted" :class="{'text-primary bg-primary/10': selected}">
      <component :is="fieldTypeIcon" :size="16" />
    </div>

    <!-- Details -->
    <div class="flex-1 min-w-0">
      <div class="flex items-center gap-1.5">
        <span class="text-sm font-semibold truncate" :class="{'text-primary': selected}">
          {{ field.label || 'Untitled Question' }}
        </span>
        <span v-if="field.is_required" class="text-danger">*</span>
      </div>
      <div class="text-xs text-text-muted truncate mt-0.5">{{ fieldTypeLabel }}</div>
    </div>

    <!-- Actions -->
    <div class="flex items-center gap-2 shrink-0">
      <span v-if="field.is_required" class="hidden sm:inline-block px-2 py-0.5 bg-success/10 text-success text-[10px] font-bold rounded uppercase tracking-wider">Required</span>
      
      <div class="opacity-0 group-hover:opacity-100 focus-within:opacity-100 transition-opacity flex items-center gap-1 ml-2">
        <button @click.stop="emit('duplicate', field)" class="p-1.5 text-text-muted hover:text-primary rounded hover:bg-surface-muted transition-colors" title="Duplicate">
          <IconCopy :size="16" />
        </button>
        <button @click.stop="emit('remove', field.id)" :disabled="field.is_locked" class="p-1.5 text-text-muted hover:text-danger rounded hover:bg-danger/10 disabled:opacity-30 disabled:cursor-not-allowed transition-colors" title="Delete">
          <IconTrash :size="16" />
        </button>
      </div>
    </div>
  </div>
</template>
