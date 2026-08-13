<script setup lang="ts">
import { ref, computed } from "vue";
import { useRoute } from "vue-router";
import { IconDownload, IconEye, IconX, IconChevronLeft, IconChevronRight } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import TableSkeleton from "@/components/ui/TableSkeleton.vue";
import EmptyState from "@/components/ui/EmptyState.vue";
import { useFormResponses } from "@/composables/useForms";
import { exportFormResponses, getFormResponse } from "@/api/forms";
import { toast } from "@/composables/useToast";
import type { FormResponse, FormResponseDetail } from "@/api/types";

const route = useRoute();
const formId = computed(() => String(route.params.id));

const page = ref(1);
const authFilter = ref("all");
const dateFrom = ref("");
const dateTo = ref("");
const batchId = ref("");

const params = computed(() => ({
  page: page.value,
  authenticated: authFilter.value !== "all" ? authFilter.value : undefined,
  date_from: dateFrom.value || undefined,
  date_to: dateTo.value || undefined,
  batch_id: batchId.value || undefined,
}));

const { data, isLoading, isError, refetch } = useFormResponses(formId, params);
const responses = computed(() => data.value?.data ?? []);
const meta = computed(() => data.value?.meta);

// Detail modal
const showDetail = ref(false);
const detailLoading = ref(false);
const detail = ref<FormResponseDetail | null>(null);

async function openDetail(r: FormResponse) {
  showDetail.value = true;
  detailLoading.value = true;
  try {
    detail.value = await getFormResponse(formId.value, r.id);
  } catch {
    toast.error("Failed to load response.");
    showDetail.value = false;
  } finally {
    detailLoading.value = false;
  }
}

// CSV export
const exporting = ref(false);
async function doExport() {
  exporting.value = true;
  try {
    const blob = await exportFormResponses(formId.value);
    if (!blob.ok) throw new Error("Export failed.");
    const url = URL.createObjectURL(await blob.blob());
    const a = document.createElement("a");
    a.href = url;
    a.download = `form-${formId.value}-responses.csv`;
    a.click();
    URL.revokeObjectURL(url);
  } catch {
    toast.error("Failed to export responses.");
  } finally {
    exporting.value = false;
  }
}

function formatDate(iso: string | null) {
  if (!iso) return "—";
  return new Date(iso).toLocaleString();
}
</script>

<template>
  <div>
    <PageHeader title="Form Responses" description="View and export submissions for this form.">
      <template #actions>
        <button
          id="btn-export-responses"
          class="inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-xs transition hover:bg-surface-muted disabled:opacity-60"
          :disabled="exporting"
          @click="doExport"
        >
          <IconDownload :size="14" />
          {{ exporting ? "Exporting…" : "Export CSV" }}
        </button>
      </template>
    </PageHeader>

    <!-- Filters -->
    <div class="mb-4 flex flex-wrap gap-2">
      <select v-model="authFilter" class="h-9 rounded-md border bg-surface px-3 text-sm">
        <option value="all">All submissions</option>
        <option value="true">Authenticated</option>
        <option value="false">Anonymous</option>
      </select>
      <input v-model="dateFrom" type="date" class="h-9 rounded-md border bg-surface px-3 text-sm" placeholder="From" />
      <input v-model="dateTo" type="date" class="h-9 rounded-md border bg-surface px-3 text-sm" placeholder="To" />
    </div>

    <!-- Table -->
    <div class="rounded-lg border bg-surface overflow-hidden">
      <TableSkeleton v-if="isLoading" :cols="6" :rows="10" />
      <EmptyState v-else-if="isError" variant="error" title="Could not load responses" @retry="refetch()" />
      <template v-else>
        <table class="w-full text-sm">
          <thead class="border-b bg-surface-muted text-xs text-text-muted">
            <tr>
              <th class="px-4 py-3 text-left font-medium">Grantee</th>
              <th class="px-4 py-3 text-left font-medium">Student ID</th>
              <th class="px-4 py-3 text-left font-medium">Batch</th>
              <th class="px-4 py-3 text-left font-medium">Submitted</th>
              <th class="px-4 py-3 text-left font-medium">Auth</th>
              <th class="px-4 py-3 text-right font-medium">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr v-if="!responses.length">
              <td colspan="6" class="py-12 text-center text-sm text-text-muted">No responses yet.</td>
            </tr>
            <tr v-for="r in responses" :key="r.id" class="hover:bg-surface-muted/40 transition">
              <td class="px-4 py-3 font-medium">{{ r.grantee_name }}</td>
              <td class="px-4 py-3 text-text-muted">{{ r.student_id ?? '—' }}</td>
              <td class="px-4 py-3 text-text-muted">{{ r.batch_name ?? '—' }}</td>
              <td class="px-4 py-3 text-text-muted text-xs">{{ formatDate(r.submitted_at) }}</td>
              <td class="px-4 py-3">
                <span :class="['rounded-full px-2 py-0.5 text-micro font-semibold', r.is_authenticated ? 'bg-success-soft text-success' : 'bg-surface-muted text-text-muted']">
                  {{ r.is_authenticated ? 'Authenticated' : 'Anonymous' }}
                </span>
              </td>
              <td class="px-4 py-3 text-right">
                <button
                  :id="`btn-view-response-${r.id}`"
                  class="inline-flex h-7 items-center gap-1 rounded border px-2 text-xs hover:bg-surface-muted"
                  @click="openDetail(r)"
                >
                  <IconEye :size="12" /> View
                </button>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div v-if="meta && meta.last_page > 1" class="flex items-center justify-between border-t px-4 py-3 text-xs text-text-muted">
          <span>Page {{ meta.current_page }} of {{ meta.last_page }} ({{ meta.total }})</span>
          <div class="flex gap-2">
            <button class="rounded border px-2 py-1 disabled:opacity-40" :disabled="meta.current_page <= 1" @click="page--">
              <IconChevronLeft :size="12" />
            </button>
            <button class="rounded border px-2 py-1 disabled:opacity-40" :disabled="meta.current_page >= meta.last_page" @click="page++">
              <IconChevronRight :size="12" />
            </button>
          </div>
        </div>
      </template>
    </div>

    <!-- Detail modal -->
    <div v-if="showDetail" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="showDetail = false">
      <div class="w-full max-w-lg rounded-xl border bg-surface shadow-xl">
        <div class="flex items-center justify-between border-b px-5 py-4">
          <h2 class="text-sm font-semibold">Response Detail</h2>
          <button class="grid size-7 place-items-center rounded hover:bg-surface-muted" @click="showDetail = false">
            <IconX :size="14" />
          </button>
        </div>
        <div class="max-h-[70vh] overflow-y-auto px-5 py-4">
          <div v-if="detailLoading" class="py-8 text-center text-sm text-text-muted">Loading…</div>
          <template v-else-if="detail">
            <!-- Metadata -->
            <div class="mb-4 rounded-lg bg-surface-muted/50 p-3 text-xs space-y-1">
              <p><span class="font-medium">Submitted:</span> {{ formatDate(detail.submitted_at) }}</p>
              <p><span class="font-medium">IP:</span> {{ detail.submitter_ip ?? '—' }}</p>
              <p><span class="font-medium">Authenticated:</span> {{ detail.is_authenticated ? 'Yes' : 'No (public)' }}</p>
              <p v-if="detail.honeypot_triggered" class="text-danger font-semibold">⚠ Honeypot was triggered</p>
            </div>

            <!-- Responses -->
            <div class="space-y-3">
              <div
                v-for="(value, key) in detail.responses"
                :key="String(key)"
                class="rounded-lg border p-3"
              >
                <p class="text-xs font-medium text-text-muted mb-1">{{ String(key) }}</p>
                <!-- Text interpolation only — never v-html -->
                <p class="text-sm">
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
