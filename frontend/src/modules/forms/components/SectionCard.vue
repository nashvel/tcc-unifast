<script setup lang="ts">
import { ref } from 'vue';
import { 
  IconGripVertical, 
  IconChevronDown, 
  IconChevronRight, 
  IconSettings, 
  IconCopy, 
  IconTrash 
} from '@tabler/icons-vue';
import type { FormSection } from '@/api/types';

const props = defineProps<{
  section: FormSection;
  isExpanded: boolean;
}>();

const emit = defineEmits<{
  toggle: [];
  delete: [];
  duplicate: [];
  updateTitle: [title: string];
  updateDescription: [description: string];
}>();

const isEditingTitle = ref(false);
const titleInput = ref<HTMLInputElement | null>(null);

function startEditingTitle() {
  isEditingTitle.value = true;
  // Use setTimeout to allow DOM to render input before focusing
  setTimeout(() => titleInput.value?.focus(), 0);
}

function stopEditingTitle(event: Event) {
  isEditingTitle.value = false;
  const target = event.target as HTMLInputElement;
  if (target.value.trim() !== props.section.title) {
    emit('updateTitle', target.value.trim() || 'Untitled Section');
  }
}
</script>

<template>
  <div class="bg-surface rounded-lg border shadow-sm overflow-hidden mb-4">
    <!-- Header -->
    <div 
      class="flex items-center gap-2 p-3 bg-surface-muted border-b cursor-pointer group hover:bg-surface-active transition-colors"
      @click="emit('toggle')"
    >
      <div class="cursor-grab hover:text-primary p-1 text-text-muted" @click.stop>
        <IconGripVertical :size="18" />
      </div>

      <button class="text-text-muted hover:text-text transition-colors">
        <IconChevronDown v-if="isExpanded" :size="18" />
        <IconChevronRight v-else :size="18" />
      </button>

      <div class="flex-1 min-w-0" @click.stop>
        <input 
          v-if="isEditingTitle"
          ref="titleInput"
          type="text"
          class="w-full bg-surface border-none px-1 py-0.5 text-sm font-semibold focus:ring-1 focus:ring-primary rounded"
          :value="section.title"
          @blur="stopEditingTitle"
          @keyup.enter="stopEditingTitle"
        />
        <h3 
          v-else 
          class="text-sm font-semibold truncate hover:bg-surface px-1 py-0.5 rounded cursor-text"
          @click="startEditingTitle"
        >
          {{ section.title }}
        </h3>
      </div>

      <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity" @click.stop>
        <button 
          class="p-1.5 text-text-muted hover:text-primary hover:bg-primary/10 rounded-md transition-colors"
          title="Duplicate Section"
          @click="emit('duplicate')"
        >
          <IconCopy :size="16" />
        </button>
        <button 
          class="p-1.5 text-text-muted hover:text-danger hover:bg-danger/10 rounded-md transition-colors"
          title="Delete Section"
          @click="emit('delete')"
        >
          <IconTrash :size="16" />
        </button>
      </div>
    </div>

    <!-- Body (Fields) -->
    <div v-show="isExpanded" class="p-3 bg-surface">
      <slot></slot>
    </div>
  </div>
</template>
