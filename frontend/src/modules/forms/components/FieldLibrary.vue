<script setup lang="ts">
import { ref, computed } from "vue";
import {
  IconTypography,
  IconAlignLeft,
  IconNumber123,
  IconAt,
  IconPhone,
  IconCalendarEvent,
  IconClock,
  IconList,
  IconChecklist,
  IconCheckbox,
  IconToggleLeft,
  IconFileUpload,
  IconFiles,
  IconHeading,
  IconMinus,
  IconCode,
  IconId,
  IconUser,
  IconBook,
  IconSchool,
  IconBuilding,
  IconMapPin,
  IconCoin,
  IconUsers,
  IconSearch
} from "@tabler/icons-vue";
import type { FieldType } from "@/api/types";

const emit = defineEmits<{
  add: [type: FieldType, preset?: Record<string, any>];
  dragstart: [event: DragEvent, type: FieldType, preset?: Record<string, any>];
}>();

const searchQuery = ref("");

// Define the field definitions
const fieldGroups = [
  {
    name: "Basic Fields",
    fields: [
      { type: "text" as FieldType, icon: IconTypography, label: "Short Text" },
      { type: "textarea" as FieldType, icon: IconAlignLeft, label: "Paragraph" },
      { type: "number" as FieldType, icon: IconNumber123, label: "Number" },
      { type: "email" as FieldType, icon: IconAt, label: "Email" },
      { type: "text" as FieldType, icon: IconPhone, label: "Phone", preset: { placeholder: "+63" } },
      { type: "date" as FieldType, icon: IconCalendarEvent, label: "Date" },
      // { type: "time" as FieldType, icon: IconClock, label: "Time" }, // Requires Time field support
    ],
  },
  {
    name: "Choice Fields",
    fields: [
      { type: "select" as FieldType, icon: IconList, label: "Dropdown", preset: { options: ["Option 1", "Option 2"] } },
      { type: "radio" as FieldType, icon: IconChecklist, label: "Multiple Choice", preset: { options: ["Option 1", "Option 2"] } },
      { type: "checkbox" as FieldType, icon: IconCheckbox, label: "Checkboxes", preset: { options: ["Option 1", "Option 2"] } },
      { type: "radio" as FieldType, icon: IconToggleLeft, label: "Yes / No", preset: { options: ["Yes", "No"] } },
    ],
  },
  {
    name: "File Fields",
    fields: [
      { type: "file" as FieldType, icon: IconFileUpload, label: "File Upload" },
      // { type: "file" as FieldType, icon: IconFiles, label: "Multiple Files", preset: { max_files: 5 } }, // Requires multiple file support
    ],
  },
  /* Layout fields might need dedicated types in the backend to render properly, 
     skipping for now unless we add 'heading', 'divider', 'html' to FieldType enum
  {
    name: "Layout",
    fields: [
      { type: "heading", icon: IconHeading, label: "Heading" },
      { type: "divider", icon: IconMinus, label: "Divider" },
      { type: "html", icon: IconCode, label: "HTML Block" },
    ],
  },
  */
  {
    name: "Academic Presets",
    fields: [
      { type: "text" as FieldType, icon: IconId, label: "Student ID", preset: { is_required: true, placeholder: "e.g. 2026-00001" } },
      { type: "text" as FieldType, icon: IconUser, label: "Full Name", preset: { is_required: true } },
      { type: "select" as FieldType, icon: IconBook, label: "Program", preset: { is_required: true, options: ["BSIT", "BSCS", "BSIS"] } },
      { type: "select" as FieldType, icon: IconSchool, label: "Year Level", preset: { is_required: true, options: ["1st Year", "2nd Year", "3rd Year", "4th Year"] } },
      { type: "select" as FieldType, icon: IconBuilding, label: "Campus", preset: { options: ["Main Campus", "Satellite Campus"] } },
      { type: "textarea" as FieldType, icon: IconMapPin, label: "Address", preset: { is_required: true } },
      { type: "number" as FieldType, icon: IconCoin, label: "Household Income", preset: { placeholder: "0.00" } },
      { type: "number" as FieldType, icon: IconUsers, label: "Number of Dependents", preset: { min_value: "0" } },
    ],
  },
];

const filteredGroups = computed(() => {
  if (!searchQuery.value) return fieldGroups;
  const lowerQuery = searchQuery.value.toLowerCase();
  
  return fieldGroups.map(group => ({
    name: group.name,
    fields: group.fields.filter(f => f.label.toLowerCase().includes(lowerQuery))
  })).filter(group => group.fields.length > 0);
});

function handleDragStart(event: DragEvent, type: FieldType, preset?: Record<string, any>) {
  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed = 'copy';
    event.dataTransfer.setData('application/json', JSON.stringify({ type, preset }));
  }
  emit('dragstart', event, type, preset);
}

</script>

<template>
  <div class="flex flex-col h-full bg-surface border-l">
    <div class="p-4 border-b space-y-3">
      <h3 class="font-semibold text-text">Add Fields</h3>
      
      <div class="relative">
        <IconSearch class="absolute left-2.5 top-1/2 -translate-y-1/2 text-text-muted" :size="16" />
        <input 
          v-model="searchQuery"
          type="text" 
          placeholder="Search fields..." 
          class="w-full pl-8 pr-3 py-1.5 text-sm bg-surface-muted border border-border rounded-md focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
        />
      </div>
    </div>

    <div class="flex-1 overflow-y-auto p-4 space-y-6">
      <div v-if="filteredGroups.length === 0" class="text-center py-8 text-text-muted text-sm">
        No fields found matching "{{ searchQuery }}"
      </div>

      <div v-for="group in filteredGroups" :key="group.name" class="space-y-2">
        <h4 class="text-xs font-semibold text-text-muted uppercase tracking-wider">{{ group.name }}</h4>
        
        <div class="grid grid-cols-2 gap-2">
          <button
            v-for="field in group.fields"
            :key="field.label"
            class="flex flex-col items-center justify-center p-3 gap-2 bg-surface border border-border rounded-lg hover:border-primary/50 hover:bg-primary/5 hover:text-primary transition-all group cursor-grab active:cursor-grabbing"
            draggable="true"
            @dragstart="handleDragStart($event, field.type, field.preset)"
            @click="emit('add', field.type, field.preset)"
          >
            <component 
              :is="field.icon" 
              :size="20" 
              class="text-text-muted group-hover:text-primary transition-colors" 
            />
            <span class="text-xs font-medium text-text group-hover:text-primary transition-colors text-center leading-tight">
              {{ field.label }}
            </span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
