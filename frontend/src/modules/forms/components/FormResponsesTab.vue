<script setup lang="ts">
import { ref } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import { listFormResponses, exportFormResponses } from '@/api/forms';
import { apiFetch } from '@/api/client';
import AppDialog from '@/components/dialogs/AppDialog.vue';
import type { FormDetail, FormResponse, FormResponseDetail } from '@/api/types';
import { 
  IconDownload, 
  IconEye, 
  IconInbox, 
  IconCheck, 
  IconClock,
  IconPhoto
} from '@tabler/icons-vue';

const props = defineProps<{
  form: FormDetail;
}>();

const page = ref(1);

const { data: responseData, isLoading } = useQuery({
  queryKey: ['form-responses', props.form.id, page],
  queryFn: () => listFormResponses(props.form.id, { page: page.value, per_page: 15 }),
});

const isExporting = ref(false);
const selectedResponse = ref<FormResponseDetail | null>(null);
const detailLoading = ref(false);
const detailDialogOpen = ref(false);
const detailError = ref("");

async function showResponseDetail(responseId: number) {
  detailDialogOpen.value = true;
  detailLoading.value = true;
  selectedResponse.value = null;
  detailError.value = "";
  try {
    const result = await apiFetch<{ data: FormResponseDetail }>(`/api/forms/${props.form.id}/responses/${responseId}`);
    selectedResponse.value = result.data;
  } catch (error) {
    detailError.value = error instanceof Error ? error.message : "Unable to load this response.";
  } finally {
    detailLoading.value = false;
  }
}

async function handleExport() {
  isExporting.value = true;
  try {
    const response = await exportFormResponses(props.form.id);
    const blob = await response.blob();
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    const contentDisposition = response.headers.get('Content-Disposition');
    let filename = `form_${props.form.id}_responses.csv`;
    if (contentDisposition) {
      const match = contentDisposition.match(/filename="?([^"]+)"?/);
      if (match && match[1]) {
        filename = match[1];
      }
    }
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    window.URL.revokeObjectURL(url);
    document.body.removeChild(a);
  } catch (error) {
    console.error('Export failed:', error);
    alert('Failed to export responses. Please try again.');
  } finally {
    isExporting.value = false;
  }
}

function formatDate(dateStr: string | null) {
  if (!dateStr) return 'N/A';
  return new Date(dateStr).toLocaleString();
}

function isFormUpload(val: unknown): boolean {
  return typeof val === 'string' && val.startsWith('form-uploads/');
}

function isImageUpload(val: unknown): boolean {
  if (!isFormUpload(val)) return false;
  const str = (val as string).toLowerCase();
  return str.endsWith('.png') || str.endsWith('.jpg') || str.endsWith('.jpeg') || str.endsWith('.webp');
}

function fileUrl(fieldName: string): string {
  if (!selectedResponse.value) return '#';
  return `/api/forms/${props.form.id}/responses/${selectedResponse.value.id}/files/${encodeURIComponent(fieldName)}`;
}
</script>

<template>
  <div class="h-full flex flex-col bg-surface">
    <!-- Header -->
    <div class="p-6 border-b flex justify-between items-center bg-surface-muted/30">
      <div>
        <h2 class="text-xl font-bold">Responses</h2>
        <p class="text-text-muted text-sm mt-1">
          {{ props.form.responses_count }} total submission{{ props.form.responses_count !== 1 ? 's' : '' }}
        </p>
      </div>
      <button 
        @click="handleExport" 
        :disabled="isExporting || props.form.responses_count === 0"
        class="flex items-center gap-2 bg-primary hover:bg-primary-dark text-white px-4 py-2 rounded-lg font-medium text-sm transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
      >
        <IconDownload :size="18" />
        {{ isExporting ? 'Exporting...' : 'Export CSV' }}
      </button>
    </div>

    <!-- Content -->
    <div class="flex-1 overflow-auto p-6">
      <div v-if="isLoading" class="flex justify-center items-center h-48">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
      </div>

      <div v-else-if="!responseData?.data || responseData.data.length === 0" class="flex flex-col items-center justify-center h-64 text-center">
        <div class="w-12 h-12 rounded-full bg-surface-muted flex items-center justify-center text-text-muted mb-3">
          <IconInbox :size="24" />
        </div>
        <h3 class="font-semibold text-lg text-text">No responses yet</h3>
        <p class="text-text-muted text-sm max-w-sm mt-1">
          Responses will appear here once users start submitting the form.
        </p>
      </div>

      <div v-else class="border rounded-xl bg-surface overflow-hidden shadow-sm">
        <table class="w-full text-left border-collapse text-sm">
          <thead>
            <tr class="border-b bg-surface-muted/50 text-text-muted font-medium">
              <th class="px-6 py-3.5">Respondent</th>
              <th class="px-6 py-3.5">Status</th>
              <th class="px-6 py-3.5">Submitted At</th>
              <th class="px-6 py-3.5 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr 
              v-for="res in responseData.data" 
              :key="res.id"
              class="hover:bg-surface-muted/20 transition-colors"
            >
              <td class="px-6 py-4">
                <div class="font-medium text-text">
                  {{ res.grantee_name || 'Anonymous' }}
                </div>
                <div v-if="res.student_id" class="text-xs text-text-muted">
                  {{ res.student_id }}
                </div>
              </td>
              <td class="px-6 py-4">
                <span 
                  class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium"
                  :class="res.is_authenticated ? 'bg-success/10 text-success' : 'bg-surface-muted text-text-muted'"
                >
                  <IconCheck v-if="res.is_authenticated" :size="12" />
                  {{ res.is_authenticated ? 'Authenticated' : 'Public' }}
                </span>
              </td>
              <td class="px-6 py-4 text-text-muted flex items-center gap-1.5 mt-1">
                <IconClock :size="14" class="opacity-50" />
                {{ formatDate(res.submitted_at) }}
              </td>
              <td class="px-6 py-4 text-right">
                <button 
                  class="text-primary hover:text-primary-dark hover:bg-primary-soft p-1.5 rounded transition-colors inline-flex"
                  title="View Details"
                  @click="showResponseDetail(res.id)"
                >
                  <IconEye :size="18" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div v-if="responseData.meta && responseData.meta.last_page > 1" class="p-4 border-t bg-surface-muted/30 flex justify-between items-center text-sm">
          <span class="text-text-muted">
            Showing {{ responseData.meta.from }} to {{ responseData.meta.to }} of {{ responseData.meta.total }} entries
          </span>
          <div class="flex gap-2">
            <button 
              @click="page--" 
              :disabled="page === 1"
              class="px-3 py-1 border rounded bg-surface hover:bg-surface-muted disabled:opacity-50 transition-colors"
            >
              Previous
            </button>
            <button 
              @click="page++" 
              :disabled="page === responseData.meta.last_page"
              class="px-3 py-1 border rounded bg-surface hover:bg-surface-muted disabled:opacity-50 transition-colors"
            >
              Next
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Response Details Dialog -->
    <AppDialog :model-value="detailDialogOpen" title="Response details" @update:model-value="detailDialogOpen = false">
      <p v-if="detailLoading" class="text-sm text-text-muted">Loading response…</p>
      <p v-else-if="detailError" class="text-sm text-danger">{{ detailError }}</p>
      <div v-else-if="selectedResponse" class="space-y-4 text-sm max-h-[70vh] overflow-y-auto pr-1">
        <!-- Metadata -->
        <div class="rounded-lg bg-surface-muted/50 p-3 text-xs space-y-1">
          <p><span class="font-medium">Submitted:</span> {{ formatDate(selectedResponse.submitted_at) }}</p>
          <p><span class="font-medium">Respondent:</span> {{ selectedResponse.grantee_name || 'Anonymous' }} <span v-if="selectedResponse.student_id" class="font-mono text-text-muted">({{ selectedResponse.student_id }})</span></p>
          <p><span class="font-medium">Authenticated:</span> {{ selectedResponse.is_authenticated ? 'Yes' : 'No (public)' }}</p>
          <p v-if="selectedResponse.honeypot_triggered" class="text-danger font-semibold">⚠ Honeypot was triggered</p>
        </div>

        <dl class="space-y-3 text-sm">
          <div v-for="(value, key) in selectedResponse.responses" :key="key" class="border-b pb-3">
            <dt class="font-medium text-text text-xs mb-1">{{ key }}</dt>
            <dd class="text-text-muted">
              <!-- Uploaded file / image handling -->
              <div v-if="isFormUpload(value)" class="mt-1">
                <div v-if="isImageUpload(value)" class="space-y-2">
                  <img
                    :src="fileUrl(String(key))"
                    alt="Uploaded image attachment"
                    class="max-h-52 max-w-full rounded-md border object-contain bg-surface-muted/30 p-1"
                    loading="lazy"
                  />
                  <div>
                    <a
                      :href="fileUrl(String(key))"
                      target="_blank"
                      rel="noopener noreferrer"
                      class="inline-flex items-center gap-1 text-xs text-primary font-medium hover:underline"
                    >
                      <IconPhoto :size="13" /> Open full image
                    </a>
                  </div>
                </div>
                <div v-else>
                  <a
                    :href="fileUrl(String(key))"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1.5 text-xs text-primary font-medium hover:bg-surface-muted transition"
                  >
                    <IconDownload :size="13" /> Download attached file
                  </a>
                </div>
              </div>
              <span v-else class="whitespace-pre-wrap">{{ Array.isArray(value) ? value.join(', ') : String(value ?? '—') }}</span>
            </dd>
          </div>
        </dl>
      </div>
    </AppDialog>
  </div>
</template>
