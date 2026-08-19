<script setup lang="ts">
import { ref, watch } from 'vue';
import type { FormDetail } from '@/api/types';
import { IconSettings, IconDeviceFloppy } from '@tabler/icons-vue';

// Define a type for the local form data
type LocalFormData = {
  title: string;
  description: string;
  visibility: 'public' | 'private';
  target_role: 'grantee' | 'staff' | 'all';
  closes_at: string;
  max_submissions: number;
};

const props = defineProps<{
  form: FormDetail | null;
  isSaving: boolean;
}>();

const emit = defineEmits<{
  save: [payload: LocalFormData];
}>();

const formData = ref<LocalFormData>({
  title: '',
  description: '',
  visibility: 'private',
  target_role: 'grantee',
  closes_at: '',
  max_submissions: 1,
});

// Initialize form data if editing
watch(() => props.form, (newForm) => {
  if (newForm) {
    formData.value = {
      title: newForm.title || '',
      description: newForm.description || '',
      visibility: newForm.visibility || 'private',
      target_role: newForm.target_role || 'grantee',
      closes_at: newForm.closes_at ? newForm.closes_at.slice(0, 16) : '',
      max_submissions: newForm.max_submissions || 1,
    };
  }
}, { immediate: true });

function handleSubmit() {
  emit('save', formData.value);
}
</script>

<template>
  <div class="h-full overflow-y-auto p-6 bg-surface-muted">
    <div class="max-w-3xl mx-auto space-y-6">
      
      <div>
        <h2 class="text-xl font-bold">Form Settings</h2>
        <p class="text-text-muted text-sm">Manage the overall configuration and access rules for this form.</p>
      </div>

      <form @submit.prevent="handleSubmit" class="bg-surface border rounded-xl shadow-sm p-6 space-y-6">
        
        <!-- Basic Info -->
        <div class="space-y-4">
          <h3 class="text-sm font-semibold border-b pb-2">Basic Details</h3>
          
          <label class="block">
            <span class="text-sm font-medium">Form Title <span class="text-danger">*</span></span>
            <input 
              v-model="formData.title" 
              type="text" 
              required 
              placeholder="e.g., Annual Scholar Feedback Form"
              class="mt-1 w-full rounded-lg border px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none"
            />
          </label>

          <label class="block">
            <span class="text-sm font-medium">Description</span>
            <textarea 
              v-model="formData.description" 
              rows="3"
              placeholder="Provide context or instructions for respondents..."
              class="mt-1 w-full rounded-lg border px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none"
            ></textarea>
          </label>
        </div>

        <!-- Access Rules -->
        <div class="space-y-4">
          <h3 class="text-sm font-semibold border-b pb-2">Access & Permissions</h3>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <label class="block">
              <span class="text-sm font-medium">Visibility</span>
              <select 
                v-model="formData.visibility"
                class="mt-1 w-full rounded-lg border px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none"
              >
                <option value="private">Private (Requires Login)</option>
                <option value="public">Public (Anyone with link)</option>
              </select>
              <p class="text-xs text-text-muted mt-1">Public forms will generate a shareable link.</p>
            </label>

            <label class="block">
              <span class="text-sm font-medium">Target Role</span>
              <select 
                v-model="formData.target_role"
                :disabled="formData.visibility === 'public'"
                class="mt-1 w-full rounded-lg border px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none disabled:bg-surface-muted disabled:opacity-50"
              >
                <option value="grantee">Grantees Only</option>
                <option value="staff">Staff Only</option>
                <option value="all">Everyone</option>
              </select>
            </label>
          </div>
        </div>

        <!-- Restrictions -->
        <div class="space-y-4">
          <h3 class="text-sm font-semibold border-b pb-2">Restrictions</h3>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <label class="block">
              <span class="text-sm font-medium">Closing Date (Optional)</span>
              <input 
                v-model="formData.closes_at" 
                type="datetime-local" 
                class="mt-1 w-full rounded-lg border px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none"
              />
              <p class="text-xs text-text-muted mt-1">Form will stop accepting responses after this date.</p>
            </label>

            <label class="block">
              <span class="text-sm font-medium">Max Submissions per User</span>
              <input 
                v-model.number="formData.max_submissions" 
                type="number" 
                min="1"
                class="mt-1 w-full rounded-lg border px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none"
              />
              <p class="text-xs text-text-muted mt-1">Leave empty or 1 for single submission.</p>
            </label>
          </div>
        </div>

        <div class="pt-4 flex justify-end">
          <button 
            type="submit" 
            :disabled="isSaving"
            class="flex items-center gap-2 px-6 py-2.5 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-primary-dark transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <IconDeviceFloppy v-if="!isSaving" :size="18" />
            <span v-else class="h-4 w-4 rounded-full border-2 border-white/30 border-t-white animate-spin"></span>
            {{ isSaving ? 'Saving...' : (form ? 'Save Settings' : 'Create Form') }}
          </button>
        </div>

      </form>
    </div>
  </div>
</template>
