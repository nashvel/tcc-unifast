<script setup lang="ts">
import { ref } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import { listFormResponses, exportFormResponses } from '@/api/forms';
import type { FormDetail, FormResponse } from '@/api/types';
import { 
  IconDownload, 
  IconEye, 
  IconInbox, 
  IconCheck, 
  IconClock 
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

async function handleExport() {
  isExporting.value = true;
  try {
    const response = await exportFormResponses(props.form.id);
    const blob = await response.blob();
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    // Extract filename from Content-Disposition header if possible, else default
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

    <!-- Table Area -->
    <div class="flex-1 overflow-auto p-6">
      <div v-if="isLoading" class="space-y-4 animate-pulse">
        <div class="h-12 bg-surface-muted rounded-lg" v-for="i in 5" :key="i"></div>
      </div>

      <div v-else-if="!responseData?.data?.length" class="flex flex-col items-center justify-center h-64 text-text-muted border-2 border-dashed rounded-xl border-border">
        <IconInbox :size="48" class="opacity-20 mb-4" />
        <p class="font-medium text-lg text-text">No responses yet</p>
        <p class="text-sm">Once users submit the form, their answers will appear here.</p>
      </div>

      <div v-else class="bg-surface border rounded-xl overflow-hidden shadow-sm">
        <table class="w-full text-left text-sm whitespace-nowrap">
          <thead class="bg-surface-muted border-b text-xs uppercase text-text-muted font-semibold">
            <tr>
              <th class="px-6 py-4">ID</th>
              <th class="px-6 py-4">Respondent</th>
              <th class="px-6 py-4">Student ID</th>
              <th class="px-6 py-4">Batch</th>
              <th class="px-6 py-4">Submitted At</th>
              <th class="px-6 py-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr v-for="res in responseData.data" :key="res.id" class="hover:bg-surface-muted/50 transition-colors">
              <td class="px-6 py-4 text-text-muted">#{{ res.id }}</td>
              <td class="px-6 py-4">
                <div class="font-medium text-text flex items-center gap-2">
                  {{ res.grantee_name || 'Anonymous' }}
                  <span v-if="res.is_authenticated" class="bg-success-soft text-success text-[10px] px-1.5 py-0.5 rounded font-bold" title="Authenticated User">AUTH</span>
                </div>
              </td>
              <td class="px-6 py-4 text-text-muted">{{ res.student_id || '—' }}</td>
              <td class="px-6 py-4 text-text-muted">{{ res.batch_name || '—' }}</td>
              <td class="px-6 py-4 text-text-muted flex items-center gap-1.5">
                <IconClock :size="14" class="opacity-50" />
                {{ formatDate(res.submitted_at) }}
              </td>
              <td class="px-6 py-4 text-right">
                <button 
                  class="text-primary hover:text-primary-dark hover:bg-primary-soft p-1.5 rounded transition-colors inline-flex"
                  title="View Details"
                  @click="alert('Detailed response view coming soon!')"
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
  </div>
</template>
