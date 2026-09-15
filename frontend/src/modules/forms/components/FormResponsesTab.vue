<script setup lang="ts">
import { ref } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import { listFormResponses, exportFormResponses, getFormResponse } from '@/api/forms';
import type { FormDetail, FormResponse, FormResponseDetail } from '@/api/types';
import { 
  IconDownload, 
  IconEye, 
  IconInbox, 
  IconCheck, 
  IconClock,
  IconX,
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

const showDetail = ref(false);
const detailLoading = ref(false);
const detail = ref<FormResponseDetail | null>(null);

async function openDetail(resId: number) {
  showDetail.value = true;
  detailLoading.value = true;
  try {
    detail.value = await getFormResponse(props.form.id, resId);
  } catch (error) {
    console.error('Failed to load response detail:', error);
    alert('Failed to load response detail.');
    showDetail.value = false;
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
  if (!detail.value) return '#';
  return `/api/forms/${props.form.id}/responses/${detail.value.id}/files/${encodeURIComponent(fieldName)}`;
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
                  @click="openDetail(res.id)"
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

    <!-- Detail Modal -->
    <div v-if="showDetail" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="showDetail = false">
      <div class="w-full max-w-lg rounded-xl border bg-surface shadow-xl flex flex-col max-h-[85vh]">
        <div class="flex items-center justify-between border-b px-5 py-4">
          <h2 class="text-sm font-semibold">Response Detail</h2>
          <button class="grid size-7 place-items-center rounded hover:bg-surface-muted transition" @click="showDetail = false">
            <IconX :size="15" />
          </button>
        </div>

        <div class="flex-1 overflow-y-auto px-5 py-4">
          <div v-if="detailLoading" class="py-12 text-center text-sm text-text-muted">
            Loading response…
          </div>
          <template v-else-if="detail">
            <!-- Metadata -->
            <div class="mb-4 rounded-lg bg-surface-muted/50 p-3 text-xs space-y-1">
              <p><span class="font-medium">Submitted:</span> {{ formatDate(detail.submitted_at) }}</p>
              <p><span class="font-medium">Respondent:</span> {{ detail.grantee_name || 'Anonymous' }} <span v-if="detail.student_id" class="font-mono text-text-muted">({{ detail.student_id }})</span></p>
              <p><span class="font-medium">Authenticated:</span> {{ detail.is_authenticated ? 'Yes' : 'No (public)' }}</p>
              <p v-if="detail.honeypot_triggered" class="text-danger font-semibold">⚠ Honeypot was triggered</p>
            </div>

            <!-- Answers -->
            <div class="space-y-3">
              <div
                v-for="(value, key) in detail.responses"
                :key="String(key)"
                class="rounded-lg border p-3"
              >
                <p class="text-xs font-medium text-text-muted mb-1.5">{{ String(key) }}</p>
                
                <!-- Uploaded file / image handling -->
                <div v-if="isFormUpload(value)" class="mt-1">
                  <div v-if="isImageUpload(value)" class="space-y-2">
                    <img
                      :src="fileUrl(String(key))"
                      alt="Uploaded image attachment"
                      class="max-h-56 max-w-full rounded-md border object-contain bg-surface-muted/30 p-1"
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

                <!-- Text interpolation only -->
                <p v-else class="text-sm font-normal text-text">
                  {{ Array.isArray(value) ? value.join(', ') : String(value ?? '—') }}
                </p>
              </div>
            </div>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>
