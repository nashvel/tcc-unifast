import { ref, computed } from 'vue';
import type { FormSection } from '@/api/types';

export function useBuilderState() {
  const activeTab = ref<'builder' | 'settings' | 'responses' | 'analytics'>('builder');
  const activeRightPanel = ref<'library' | 'field-settings'>('library');
  const selectedFieldId = ref<number | null>(null);
  const selectedSectionId = ref<number | null>(null);
  const previewDevice = ref<'desktop' | 'tablet' | 'mobile'>('desktop');
  const isDragging = ref(false);
  const saveStatus = ref<'idle' | 'saving' | 'saved' | 'error'>('idle');
  
  // Local reactive copy of sections for optimistic drag and drop
  const sections = ref<FormSection[]>([]);

  const isDirty = computed(() => saveStatus.value === 'saving' || saveStatus.value === 'error');

  function openFieldSettings(fieldId: number) {
    selectedFieldId.value = fieldId;
    activeRightPanel.value = 'field-settings';
  }

  function closeFieldSettings() {
    selectedFieldId.value = null;
    activeRightPanel.value = 'library';
  }

  return {
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
    closeFieldSettings,
  };
}
