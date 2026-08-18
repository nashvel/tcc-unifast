<script setup lang="ts">
import { computed } from 'vue';
import AppDialog from '@/components/dialogs/AppDialog.vue';
import { 
  IconCheck, 
  IconAlertTriangle, 
  IconSend, 
  IconLayoutList,
  IconAsterisk,
  IconUpload
} from '@tabler/icons-vue';
import type { FormDetail } from '@/api/types';

const props = defineProps<{
  show: boolean;
  form: FormDetail;
  isPublishing: boolean;
}>();

const emit = defineEmits<{
  close: [];
  publish: [];
  jumpToField: [fieldId: number];
}>();

const allFields = computed(() => {
  return props.form.sections.flatMap(s => s.fields);
});

const stats = computed(() => ({
  fields: allFields.value.length,
  required: allFields.value.filter(f => f.is_required).length,
  uploads: allFields.value.filter(f => f.field_type === 'file').length,
}));

// Pre-publish validation checks
const errors = computed(() => {
  const issues: { id: string, fieldId?: number, message: string }[] = [];

  if (!props.form.title) {
    issues.push({ id: 'no-title', message: 'Form requires a title.' });
  }

  if (allFields.value.length === 0) {
    issues.push({ id: 'no-fields', message: 'Form must have at least one field.' });
  }

  // Check choice fields for options
  allFields.value.forEach(f => {
    if (['select', 'radio', 'checkbox'].includes(f.field_type)) {
      if (!f.options || f.options.length < 2) {
        issues.push({ 
          id: `opt-${f.id}`, 
          fieldId: f.id, 
          message: `Field "${f.label || 'Untitled'}" needs at least 2 options.` 
        });
      }
    }
  });

  return issues;
});

const isReady = computed(() => errors.value.length === 0);

</script>

<template>
  <AppDialog 
    :model-value="show" 
    title="Publish Form?" 
    @update:model-value="emit('close')"
  >
    <div class="space-y-6">
      
      <p class="text-text-muted text-sm">
        Your form will become available to eligible grantees. Once published, you can still edit fields but responses may be affected.
      </p>

      <!-- Stats Summary -->
      <div class="grid grid-cols-3 gap-3">
        <div class="bg-surface-muted p-3 rounded-lg flex flex-col items-center justify-center gap-1 border">
          <IconLayoutList class="text-text-muted" :size="20" />
          <span class="font-semibold text-lg leading-none">{{ stats.fields }}</span>
          <span class="text-xs text-text-muted uppercase tracking-wider">Fields</span>
        </div>
        <div class="bg-surface-muted p-3 rounded-lg flex flex-col items-center justify-center gap-1 border">
          <IconAsterisk class="text-primary" :size="20" />
          <span class="font-semibold text-lg leading-none text-primary">{{ stats.required }}</span>
          <span class="text-xs text-text-muted uppercase tracking-wider">Required</span>
        </div>
        <div class="bg-surface-muted p-3 rounded-lg flex flex-col items-center justify-center gap-1 border">
          <IconUpload class="text-warning" :size="20" />
          <span class="font-semibold text-lg leading-none text-warning">{{ stats.uploads }}</span>
          <span class="text-xs text-text-muted uppercase tracking-wider">Uploads</span>
        </div>
      </div>

      <!-- Readiness Checklist -->
      <div class="space-y-3">
        <h4 class="font-semibold text-sm">Form Readiness</h4>
        
        <div v-if="isReady" class="flex items-center gap-2 p-3 bg-success/10 text-success rounded-lg border border-success/20">
          <IconCheck :size="20" />
          <span class="text-sm font-medium">All checks passed. Form is ready to publish!</span>
        </div>

        <div v-else class="space-y-2">
          <div class="flex items-center gap-2 p-3 bg-danger/10 text-danger rounded-lg border border-danger/20 mb-3">
            <IconAlertTriangle :size="20" />
            <span class="text-sm font-medium">Cannot be published. {{ errors.length }} issue(s) found:</span>
          </div>

          <button
            v-for="error in errors"
            :key="error.id"
            class="flex items-start gap-2 w-full text-left p-2.5 bg-surface-muted rounded border hover:border-danger/50 hover:bg-danger/5 transition-colors group"
            @click="error.fieldId && emit('jumpToField', error.fieldId)"
          >
            <IconAlertTriangle class="text-danger mt-0.5 shrink-0" :size="16" />
            <span class="text-sm text-text-muted group-hover:text-text transition-colors">
              {{ error.message }}
            </span>
          </button>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex justify-end gap-3 pt-4 border-t">
        <button 
          class="px-4 py-2 text-sm font-semibold rounded-lg hover:bg-surface-muted transition-colors"
          @click="emit('close')"
          :disabled="isPublishing"
        >
          Cancel
        </button>
        <button 
          class="flex items-center gap-2 px-4 py-2 text-sm font-semibold rounded-lg text-white bg-primary hover:bg-primary-dark disabled:opacity-50 disabled:cursor-not-allowed transition-all"
          :disabled="!isReady || isPublishing"
          @click="emit('publish')"
        >
          <IconSend v-if="!isPublishing" :size="18" />
          <span v-else class="h-4 w-4 rounded-full border-2 border-white/30 border-t-white animate-spin"></span>
          {{ isPublishing ? 'Publishing...' : 'Publish Form' }}
        </button>
      </div>

    </div>
  </AppDialog>
</template>
