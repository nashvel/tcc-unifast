<script setup lang="ts">
import { ref, computed, watch, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import {
  IconSettings, IconLayoutList, IconMessageCircle, IconChartLine, 
  IconDeviceDesktop, IconCheck, IconAlertTriangle, IconSend, IconArrowLeft, IconEye
} from "@tabler/icons-vue";

import PageHeader from "@/components/ui/PageHeader.vue";
import { toast } from "@/composables/useToast";
import { 
  useFormDetail, 
  useCreateForm,
  useUpdateForm, 
  useCreateSection, 
  useUpdateSection, 
  useDeleteSection,
  useReorderSections,
  useAddField,
  useUpdateField,
  useDeleteField,
  useReorderFields,
  usePublishForm,
  useCloseForm
} from "@/composables/useForms";
import { useBuilderState } from "@/composables/useBuilderState";
import type { FormField, FormSection, FieldType } from "@/api/types";

// Components
import FormStatusBadge from "./components/FormStatusBadge.vue";
import AutosaveIndicator from "./components/AutosaveIndicator.vue";
import PreviewDeviceToggle from "./components/PreviewDeviceToggle.vue";
import SectionCard from "./components/SectionCard.vue";
import FieldLibrary from "./components/FieldLibrary.vue";
import FieldEditorCard from "./components/FieldEditorCard.vue";
import FieldSettingsPanel from "./components/FieldSettingsPanel.vue";
import PublishModal from "./components/PublishModal.vue";
import FormAnalyticsTab from "./components/FormAnalyticsTab.vue";
import FormSettingsTab from "./components/FormSettingsTab.vue";
import FormResponsesTab from "./components/FormResponsesTab.vue";
import Renderer from "./Renderer.vue";

const route = useRoute();
const router = useRouter();
const formId = computed(() => Number(route.params.id) || 0);

// Global Builder State
const {
  activeTab,
  activeRightPanel,
  selectedFieldId,
  selectedSectionId,
  previewDevice,
  isDragging,
  saveStatus,
  sections,
  isDirty,
  openFieldSettings,
  closeFieldSettings
} = useBuilderState();

const showPublishModal = ref(false);
const isPublishing = ref(false);

const isViewMode = computed(() => route.path.endsWith('/responses'));

onMounted(() => {
  if (route.query.tab) {
    const tab = route.query.tab as string;
    if (['settings', 'builder', 'responses', 'analytics'].includes(tab)) {
      activeTab.value = tab as any;
    }
  } else if (isViewMode.value) {
    activeTab.value = 'responses';
  } else if (formId.value === 0) {
    activeTab.value = 'settings';
  }
});

// Fetch data
const { data: formData, isLoading } = useFormDetail(formId);

// Mutations
const createFormMutation = useCreateForm();
const updateFormMutation = useUpdateForm(formId);
const createSectionMutation = useCreateSection(formId);
const updateSectionMutation = useUpdateSection(formId);
const deleteSectionMutation = useDeleteSection(formId);
const reorderSectionsMutation = useReorderSections(formId);

const addFieldMutation = useAddField(formId);
const updateFieldMutation = useUpdateField(formId);
const deleteFieldMutation = useDeleteField(formId);
const reorderFieldsMutation = useReorderFields(formId);

const publishMutation = usePublishForm();
const closeMutation = useCloseForm();

// Sync data
watch(formData, (newData) => {
  if (newData?.sections) {
    // Clone to local state for optimistic UI updates
    sections.value = JSON.parse(JSON.stringify(newData.sections));
  }
}, { immediate: true });

// Computed
const allFields = computed(() => sections.value.flatMap(s => s.fields));
const existingFieldNames = computed(() => allFields.value.map(f => f.field_name));
const selectedField = computed(() => allFields.value.find(f => f.id === selectedFieldId.value));

// ─────────────────────────────────────────────────────────────
// Handlers: Form Actions
// ─────────────────────────────────────────────────────────────

async function handleSaveSettings(payload: any) {
  saveStatus.value = "saving";
  try {
    if (formId.value === 0) {
      // Create new form
      const res = await createFormMutation.mutateAsync(payload);
      toast.success("Form created! You can now add fields.");
      router.push(`/app/forms/${res.data.id}/edit`);
    } else {
      // Update existing
      await updateFormMutation.mutateAsync(payload);
      toast.success("Settings saved.");
      saveStatus.value = "saved";
    }
  } catch (err: any) {
    toast.error(err.message || "Failed to save settings.");
    saveStatus.value = "error";
  }
}

async function handlePublish() {
  isPublishing.value = true;
  try {
    await publishMutation.mutateAsync(formId.value);
    showPublishModal.value = false;
    toast.success("Form published successfully!");
  } catch (err: any) {
    toast.error(err.message || "Failed to publish form.");
  } finally {
    isPublishing.value = false;
  }
}

async function handleCloseForm() {
  try {
    await closeMutation.mutateAsync(formId.value);
    toast.success("Form closed to new submissions.");
  } catch (err: any) {
    toast.error(err.message || "Failed to close form.");
  }
}

function handlePreview() {
  if (formData.value?.public_token) {
    window.open(`/forms/public/${formData.value.public_token}`, '_blank');
  } else {
    toast.info("Publish the form to view the live version.");
  }
}

// ─────────────────────────────────────────────────────────────
// Handlers: Sections
// ─────────────────────────────────────────────────────────────

async function handleAddSection() {
  saveStatus.value = "saving";
  try {
    await createSectionMutation.mutateAsync({ title: `Section ${sections.value.length + 1}` });
    saveStatus.value = "saved";
  } catch {
    saveStatus.value = "error";
  }
}

let sectionUpdateTimeout: ReturnType<typeof setTimeout> | null = null;

async function handleUpdateSection(sectionId: number, title: string) {
  const section = sections.value.find(s => s.id === sectionId);
  if (!section || section.title === title) return;
  
  // Optimistic local update
  section.title = title;
  
  // Debounced API save
  saveStatus.value = "saving";
  if (sectionUpdateTimeout) clearTimeout(sectionUpdateTimeout);
  
  sectionUpdateTimeout = setTimeout(async () => {
    try {
      await updateSectionMutation.mutateAsync({ sectionId, data: { title } });
      saveStatus.value = "saved";
    } catch {
      saveStatus.value = "error";
    }
  }, 800);
}

async function handleDeleteSection(sectionId: number) {
  saveStatus.value = "saving";
  try {
    await deleteSectionMutation.mutateAsync(sectionId);
    saveStatus.value = "saved";
  } catch {
    saveStatus.value = "error";
  }
}

// ─────────────────────────────────────────────────────────────
// Handlers: Fields
// ─────────────────────────────────────────────────────────────

const activeSectionIdForLibrary = ref<number | null>(null);

function prepareAddField(sectionId: number) {
  activeSectionIdForLibrary.value = sectionId;
  closeFieldSettings();
}

async function handleAddField(type: FieldType, preset?: Record<string, any>, targetSectionId?: number) {
  const sectionId = targetSectionId || activeSectionIdForLibrary.value || sections.value[sections.value.length - 1]?.id;
  if (!sectionId) return;

  saveStatus.value = "saving";
  try {
    const res = await addFieldMutation.mutateAsync({
      section_id: sectionId,
      field_type: type,
      field_name: `field_${Date.now()}`,
      label: "New Field",
      ...preset
    });
    
    // Automatically select the new field and open settings
    openFieldSettings(res.data.id);
    activeSectionIdForLibrary.value = null;
    saveStatus.value = "saved";
  } catch {
    saveStatus.value = "error";
  }
}

let fieldUpdateTimeout: ReturnType<typeof setTimeout> | null = null;

async function handleUpdateField(updatedField: FormField) {
  // 1. Immediate optimistic local update for real-time preview
  for (const section of sections.value) {
    const idx = section.fields.findIndex(f => f.id === updatedField.id);
    if (idx !== -1) {
      section.fields[idx] = { ...updatedField };
      break;
    }
  }

  // 2. Debounced API save to prevent spamming backend
  saveStatus.value = "saving";
  if (fieldUpdateTimeout) clearTimeout(fieldUpdateTimeout);
  
  fieldUpdateTimeout = setTimeout(async () => {
    try {
      await updateFieldMutation.mutateAsync({ 
        fieldId: updatedField.id, 
        data: updatedField 
      });
      saveStatus.value = "saved";
    } catch {
      saveStatus.value = "error";
    }
  }, 800);
}

async function handleDeleteField(fieldId: number) {
  saveStatus.value = "saving";
  try {
    await deleteFieldMutation.mutateAsync(fieldId);
    if (selectedFieldId.value === fieldId) {
      closeFieldSettings();
    }
    saveStatus.value = "saved";
  } catch {
    saveStatus.value = "error";
  }
}

async function handleDuplicateField(field: FormField) {
  const duplicate = { ...field };
  delete (duplicate as any).id;
  duplicate.field_name = `${field.field_name}_copy`;
  duplicate.label = `${field.label} (Copy)`;
  
  saveStatus.value = "saving";
  try {
    await addFieldMutation.mutateAsync(duplicate as any);
    saveStatus.value = "saved";
  } catch {
    saveStatus.value = "error";
  }
}

// Very basic move up/down implementation for keyboard accessibility
async function handleMoveField(fieldId: number, direction: 'up' | 'down') {
  const section = sections.value.find(s => s.fields.some(f => f.id === fieldId));
  if (!section) return;

  const idx = section.fields.findIndex(f => f.id === fieldId);
  if (direction === 'up' && idx > 0) {
    const temp = section.fields[idx];
    section.fields[idx] = section.fields[idx - 1];
    section.fields[idx - 1] = temp;
  } else if (direction === 'down' && idx < section.fields.length - 1) {
    const temp = section.fields[idx];
    section.fields[idx] = section.fields[idx + 1];
    section.fields[idx + 1] = temp;
  } else {
    return;
  }

  saveStatus.value = "saving";
  try {
    await reorderFieldsMutation.mutateAsync(section.fields.map(f => f.id));
    saveStatus.value = "saved";
  } catch {
    saveStatus.value = "error";
  }
}

// ─────────────────────────────────────────────────────────────
// Drag & Drop Handling (HTML5 Native)
// ─────────────────────────────────────────────────────────────

const draggedFieldType = ref<FieldType | null>(null);
const draggedPreset = ref<any>(null);

function onDragStartFromLibrary(e: DragEvent, type: FieldType, preset?: Record<string, any>) {
  isDragging.value = true;
  draggedFieldType.value = type;
  draggedPreset.value = preset;
}

function onDragOver(e: DragEvent) {
  e.preventDefault();
  if (e.dataTransfer) e.dataTransfer.dropEffect = 'copy';
}

async function onDropToSection(e: DragEvent, sectionId: number) {
  e.preventDefault();
  isDragging.value = false;
  if (!draggedFieldType.value) return;

  await handleAddField(draggedFieldType.value, draggedPreset.value, sectionId);
  draggedFieldType.value = null;
  draggedPreset.value = null;
}

function onDragEnd() {
  isDragging.value = false;
  draggedFieldType.value = null;
  draggedPreset.value = null;
}

// Force settings tab if creating a new form
onMounted(() => {
  if (formId.value === 0) {
    activeTab.value = 'settings';
  }
});
</script>

<template>
  <div class="h-[calc(100vh-theme(spacing.16))] flex flex-col bg-surface-muted overflow-hidden">
    
    <!-- Header -->
    <header class="bg-surface border-b px-6 py-3 flex items-center justify-between shrink-0">
      <div class="flex items-center gap-4">
        <button @click="router.push('/app/forms')" class="text-text-muted hover:text-text p-1.5 -ml-1.5 rounded-md hover:bg-surface-muted transition-colors">
          <IconArrowLeft :size="20" />
        </button>
        <div v-if="formData">
          <h1 class="font-bold text-lg leading-tight">{{ formData.title }}</h1>
          <div class="flex items-center gap-3 mt-1">
            <FormStatusBadge :status="formData.status" />
            <AutosaveIndicator :status="saveStatus" />
          </div>
        </div>
        <div v-else-if="formId === 0">
          <h1 class="font-bold text-lg leading-tight">Create New Form</h1>
        </div>
      </div>

      <div class="flex items-center gap-3">
        <button 
          v-if="formData?.status === 'draft' || formData?.status === 'closed'"
          class="flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-primary-dark transition-colors"
          @click="showPublishModal = true"
        >
          <IconSend :size="16" /> Publish
        </button>
        
        <button 
          v-if="formData?.status === 'published'"
          class="flex items-center gap-2 px-4 py-2 bg-warning text-white text-sm font-semibold rounded-lg hover:bg-warning-dark transition-colors"
          @click="handleCloseForm"
        >
          <IconAlertTriangle :size="16" /> Close Form
        </button>
      </div>
    </header>

    <!-- Top Tabs -->
    <div class="bg-surface border-b flex justify-center shrink-0">
      <nav class="flex gap-8">
        <button 
          v-if="!isViewMode"
          class="px-4 py-3 text-sm font-medium border-b-2 transition-colors flex items-center gap-2"
          :class="activeTab === 'settings' ? 'border-primary text-primary' : 'border-transparent text-text-muted hover:text-text'"
          @click="activeTab = 'settings'"
        >
          <IconSettings :size="18" /> Settings
        </button>
        <button 
          v-if="!isViewMode"
          class="px-4 py-3 text-sm font-medium border-b-2 transition-colors flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
          :class="activeTab === 'builder' ? 'border-primary text-primary' : 'border-transparent text-text-muted hover:text-text'"
          :disabled="formId === 0"
          @click="activeTab = 'builder'"
        >
          <IconLayoutList :size="18" /> Builder
        </button>
        <button 
          v-if="isViewMode"
          class="px-4 py-3 text-sm font-medium border-b-2 transition-colors flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
          :class="activeTab === 'responses' ? 'border-primary text-primary' : 'border-transparent text-text-muted hover:text-text'"
          :disabled="formId === 0"
          @click="activeTab = 'responses'"
        >
          <IconMessageCircle :size="18" /> Responses
        </button>
        <button 
          v-if="isViewMode"
          class="px-4 py-3 text-sm font-medium border-b-2 transition-colors flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
          :class="activeTab === 'analytics' ? 'border-primary text-primary' : 'border-transparent text-text-muted hover:text-text'"
          :disabled="formId === 0"
          @click="activeTab = 'analytics'"
        >
          <IconChartLine :size="18" /> Analytics
        </button>
      </nav>
    </div>

    <!-- Main Workspace (3 Columns when activeTab === 'builder') -->
    <div v-if="activeTab === 'builder'" class="flex-1 flex overflow-hidden">
      
      <!-- Left Column: Schema Overview & Sections -->
      <div class="w-80 bg-surface border-r flex flex-col shrink-0">
        <div class="p-4 border-b flex items-center justify-between">
          <h2 class="font-semibold text-sm">Form Structure</h2>
          <button 
            @click="handleAddSection" 
            :disabled="saveStatus === 'saving'"
            class="text-primary hover:text-primary-dark text-xs font-semibold hover:underline disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:no-underline"
          >
            {{ saveStatus === 'saving' ? 'Adding...' : '+ Add Section' }}
          </button>
        </div>
        
        <div class="flex-1 overflow-y-auto p-4">
          <div v-if="isLoading" class="space-y-4 animate-pulse">
            <div class="h-20 bg-surface-muted rounded-lg" v-for="i in 3" :key="i"></div>
          </div>
          
          <div v-else class="space-y-6">
            <SectionCard 
              v-for="section in sections" 
              :key="section.id"
              :section="section"
              :is-expanded="true"
              @update-title="t => handleUpdateSection(section.id, t)"
              @delete="handleDeleteSection(section.id)"
              @dragover="onDragOver"
              @drop="onDropToSection($event, section.id)"
              class="border-2 border-transparent transition-colors"
              :class="{ 'border-primary border-dashed bg-primary/5': isDragging }"
            >
              <!-- Fields List -->
              <div class="space-y-2 min-h-[40px]">
                <FieldEditorCard 
                  v-for="field in section.fields"
                  :key="field.id"
                  :field="field"
                  :selected="selectedFieldId === field.id"
                  @select="openFieldSettings"
                  @remove="handleDeleteField"
                  @duplicate="handleDuplicateField"
                  @move-up="handleMoveField($event, 'up')"
                  @move-down="handleMoveField($event, 'down')"
                />
                
                <button 
                  class="w-full py-2 mt-2 text-center border-2 border-dashed rounded-lg text-text-muted hover:text-primary hover:border-primary/50 text-xs font-semibold transition-colors flex items-center justify-center gap-1.5"
                  :class="activeSectionIdForLibrary === section.id ? 'border-primary/50 text-primary bg-primary/5' : 'border-border'"
                  @click.stop="prepareAddField(section.id)"
                >
                  <IconPlus :size="14" /> Add Field Here
                </button>
              </div>
            </SectionCard>
          </div>
        </div>
      </div>

      <!-- Center Column: Live Preview Area -->
      <div class="flex-1 bg-surface-muted overflow-y-auto flex flex-col relative" @click="closeFieldSettings">
        
        <!-- Preview Tools Overlay -->
        <div class="sticky top-0 z-10 p-4 flex justify-center pointer-events-none">
          <div class="pointer-events-auto shadow-sm">
            <PreviewDeviceToggle v-model="previewDevice" />
          </div>
        </div>

        <div class="flex-1 p-6 pb-20 flex justify-center items-start">
          <div 
            class="transition-all duration-300"
            :class="{
              'w-full max-w-3xl': previewDevice === 'desktop',
              'w-full max-w-xl': previewDevice === 'tablet',
              'w-full max-w-[375px]': previewDevice === 'mobile'
            }"
            @click.stop
          >
            <!-- Form Renderer -->
            <div class="bg-surface rounded-xl shadow-sm border p-0 overflow-hidden min-h-[400px]">
              <!-- Mock Header -->
              <div class="bg-primary h-3"></div>
              
              <div class="p-6 md:p-8">
                <h1 class="text-2xl font-bold mb-2">{{ formData?.title }}</h1>
                <p v-if="formData?.description" class="text-text-muted mb-8 whitespace-pre-wrap">{{ formData.description }}</p>
                
                <Renderer v-if="formData" :schema="{ ...formData, fields: allFields }" :readonly="true" />
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Column: Settings / Library Panel -->
      <div class="w-80 bg-surface shrink-0 z-20 shadow-[-4px_0_15px_-3px_rgba(0,0,0,0.05)] border-l flex flex-col">
        <!-- Close Settings Button (if editing field) -->
        <div v-if="activeRightPanel === 'field-settings'" class="p-3 border-b flex items-center gap-2 bg-surface-muted/30">
          <button @click="closeFieldSettings" class="text-text-muted hover:text-text p-1 rounded transition-colors">
            <IconArrowLeft :size="16" />
          </button>
          <span class="font-semibold text-sm">Field Settings</span>
        </div>

        <div class="flex-1 overflow-y-auto overflow-x-hidden">
          <FieldLibrary 
            v-if="activeRightPanel === 'library'" 
            @add="(t, p) => handleAddField(t, p)" 
            @dragstart="onDragStartFromLibrary"
            @dragend="onDragEnd"
          />
          
          <FieldSettingsPanel 
            v-else-if="activeRightPanel === 'field-settings' && selectedField"
            :field="selectedField"
            :existing-field-names="existingFieldNames"
            :all-fields="allFields"
            @update="handleUpdateField"
          />
        </div>
      </div>

    </div>

    <!-- Other Tabs -->
    <div v-else-if="activeTab === 'settings'" class="flex-1 overflow-hidden">
      <FormSettingsTab 
        :form="formData || null" 
        :is-saving="saveStatus === 'saving'" 
        @save="handleSaveSettings" 
      />
    </div>

    <div v-else-if="activeTab === 'analytics'" class="flex-1 overflow-hidden">
      <FormAnalyticsTab v-if="formData" :form="formData" />
    </div>

    <div v-else-if="activeTab === 'responses'" class="flex-1 overflow-hidden">
      <FormResponsesTab v-if="formData" :form="formData" />
    </div>

    <div v-else class="flex-1 flex items-center justify-center bg-surface-muted text-text-muted">
      <!-- Fallback just in case -->
      <div class="text-center space-y-2">
        <p class="font-medium text-lg text-text">Unknown Tab</p>
      </div>
    </div>

    <!-- Modals -->
    <PublishModal 
      v-if="formData"
      :show="showPublishModal" 
      :form="formData" 
      :is-publishing="isPublishing"
      @close="showPublishModal = false" 
      @publish="handlePublish" 
      @jump-to-field="openFieldSettings"
    />

  </div>
</template>
