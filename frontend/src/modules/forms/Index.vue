<script setup lang="ts">
import { ref, computed } from "vue";
import { useRouter } from "vue-router";
import {
  IconPlus, IconPencil, IconArchive, IconToggleLeft, IconToggleRight,
  IconLink, IconRefresh, IconEye, IconShield, IconChevronLeft, IconChevronRight, IconDatabase
} from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import TableSkeleton from "@/components/ui/TableSkeleton.vue";
import EmptyState from "@/components/ui/EmptyState.vue";
import AppDialog from "@/components/dialogs/AppDialog.vue";
import { useFormList, useDeleteForm, useToggleForm, useRegenerateToken } from "@/composables/useForms";
import { toast } from "@/composables/useToast";
import type { Form } from "@/api/types";

const router = useRouter();
const page = ref(1);
const search = ref("");
const visibility = ref("all");
const status = ref("all");
const isArchivedView = ref(false);

const params = computed(() => ({
  page: page.value,
  search: search.value || undefined,
  visibility: visibility.value !== "all" ? visibility.value : undefined,
  status: status.value !== "all" ? status.value : undefined,
  archived: isArchivedView.value || undefined,
}));

const { data, isLoading, isError, refetch } = useFormList(params);
const forms = computed(() => data.value?.data ?? []);
const meta = computed(() => data.value?.meta);

const deleteMutation = useDeleteForm();
const toggleMutation = useToggleForm();
const regenMutation = useRegenerateToken();

// Archive confirmation
const archiveTarget = ref<Form | null>(null);
const confirmArchive = (form: Form) => { archiveTarget.value = form; };
const doArchive = async () => {
  if (!archiveTarget.value) return;
  try {
    // Note: It's still using `deleteMutation` under the hood which maps to the destroy endpoint, 
    // but the backend now archives it.
    await deleteMutation.mutateAsync(archiveTarget.value.id);
    toast.success("Form archived.");
    archiveTarget.value = null;
  } catch (e) {
    toast.error(e instanceof Error ? e.message : "Cannot archive form.");
    archiveTarget.value = null;
  }
};

// Regenerate token confirmation
const regenTarget = ref<Form | null>(null);
const confirmRegen = (form: Form) => { regenTarget.value = form; };
const doRegen = async () => {
  if (!regenTarget.value) return;
  try {
    await regenMutation.mutateAsync(regenTarget.value.id);
    toast.success("Public token regenerated. Old link is now invalid.");
    regenTarget.value = null;
  } catch (e) {
    toast.error(e instanceof Error ? e.message : "Failed to regenerate token.");
    regenTarget.value = null;
  }
};

const doToggle = async (form: Form) => {
  try {
    await toggleMutation.mutateAsync(form.id);
    toast.success(`Form ${form.is_active ? "deactivated" : "activated"}.`);
  } catch (e) {
    toast.error(e instanceof Error ? e.message : "Failed to toggle form.");
  }
};

const copyLink = (token: string) => {
  const url = `${window.location.origin}/forms/public/${token}`;
  navigator.clipboard.writeText(url).then(() => toast.success("Link copied!"));
};

const handlePreview = (form: Form) => {
  if (form.public_token) {
    window.open(`/forms/public/${form.public_token}`, '_blank');
  } else {
    toast.info("Publish the form to view the live version.");
  }
};

function visibilityBadge(v: string) {
  return v === "public"
    ? "bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400"
    : "bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400";
}

function statusBadge(active: boolean) {
  return active
    ? "bg-success-soft text-success"
    : "bg-surface-muted text-text-muted";
}
</script>

<template>
  <div>
    <PageHeader :title="isArchivedView ? 'Archived Forms' : 'Form Builder'" description="Create and manage dynamic forms for grantees and staff.">
      <template #actions>
        <button
          @click="isArchivedView = !isArchivedView"
          class="inline-flex h-9 items-center gap-1.5 rounded-md border bg-surface px-3 text-xs font-medium transition hover:bg-surface-muted"
        >
          <IconArchive :size="14" /> {{ isArchivedView ? 'Back to Forms' : 'Archives' }}
        </button>
        <button
          id="btn-create-form"
          class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-xs font-medium text-white transition hover:bg-primary/90"
          @click="router.push('/app/forms/new')"
        >
          <IconPlus :size="14" /> New form
        </button>
      </template>
    </PageHeader>

    <!-- Filters -->
    <div class="mb-4 flex flex-wrap gap-2">
      <input
        v-model="search"
        class="h-9 rounded-md border bg-surface px-3 text-sm w-48"
        placeholder="Search forms…"
      />
      <select v-model="visibility" class="h-9 rounded-md border bg-surface px-3 text-sm">
        <option value="all">All visibility</option>
        <option value="public">Public</option>
        <option value="private">Private</option>
      </select>
      <select v-model="status" class="h-9 rounded-md border bg-surface px-3 text-sm">
        <option value="all">All status</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
      </select>
    </div>

    <!-- Table -->
    <div class="rounded-lg border bg-surface overflow-hidden">
      <TableSkeleton v-if="isLoading" :cols="7" :rows="8" />
      <EmptyState
        v-else-if="isError"
        variant="error"
        title="Could not load forms"
        hint="Please try again."
        @retry="refetch()"
      />
      <template v-else>
        <table class="w-full text-sm">
          <thead class="border-b bg-surface-muted text-xs text-text-muted">
            <tr>
              <th class="px-4 py-3 text-left font-medium">Title</th>
              <th class="px-4 py-3 text-left font-medium">Visibility</th>
              <th class="px-4 py-3 text-left font-medium">Target</th>
              <th class="px-4 py-3 text-left font-medium">Batch</th>
              <th class="px-4 py-3 text-left font-medium">Status</th>
              <th class="px-4 py-3 text-left font-medium">Responses</th>
              <th class="px-4 py-3 text-left font-medium">Closes</th>
              <th class="px-4 py-3 text-center font-medium">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr v-if="!forms.length">
              <td colspan="8" class="py-12 text-center text-sm text-text-muted">No forms found.</td>
            </tr>
            <tr
              v-for="form in forms"
              :key="form.id"
              class="group hover:bg-surface-muted/40 transition"
            >
              <td class="px-4 py-3 font-medium">{{ form.title }}</td>
              <td class="px-4 py-3">
                <span :class="['rounded-full px-2 py-0.5 text-micro font-semibold capitalize', visibilityBadge(form.visibility)]">
                  {{ form.visibility }}
                </span>
              </td>
              <td class="px-4 py-3 capitalize text-text-muted">{{ form.target_role }}</td>
              <td class="px-4 py-3 text-text-muted">{{ form.batch_name ?? '—' }}</td>
              <td class="px-4 py-3">
                <span :class="['rounded-full px-2 py-0.5 text-micro font-semibold', statusBadge(form.is_active)]">
                  {{ form.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td class="px-4 py-3 text-text-muted">{{ form.responses_count.toLocaleString() }}</td>
              <td class="px-4 py-3 text-text-muted text-xs">
                {{ form.closes_at ? new Date(form.closes_at).toLocaleDateString() : '—' }}
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center justify-center gap-1 opacity-0 group-hover:opacity-100 transition">
                  <!-- Edit -->
                  <button
                    :id="`btn-edit-form-${form.id}`"
                    class="grid size-9 place-items-center rounded hover:bg-surface-muted"
                    title="Edit form"
                    @click="router.push(`/app/forms/${form.id}/edit`)"
                  >
                    <IconPencil :size="18" />
                  </button>
                  <!-- Responses -->
                  <button
                    :id="`btn-responses-form-${form.id}`"
                    class="grid size-9 place-items-center rounded hover:bg-surface-muted"
                    title="View data"
                    @click="router.push(`/app/forms/${form.id}/responses`)"
                  >
                    <IconEye :size="18" />
                  </button>
                  <!-- Security logs -->
                  <button
                    :id="`btn-security-form-${form.id}`"
                    class="grid size-9 place-items-center rounded hover:bg-surface-muted"
                    title="Security log"
                    @click="router.push(`/app/forms/${form.id}/security`)"
                  >
                    <IconShield :size="18" />
                  </button>
                  <!-- Toggle active -->
                  <button
                    :id="`btn-toggle-form-${form.id}`"
                    class="grid size-9 place-items-center rounded hover:bg-surface-muted"
                    :title="form.is_active ? 'Deactivate' : 'Activate'"
                    @click="doToggle(form)"
                  >
                    <IconToggleRight v-if="form.is_active" :size="18" class="text-success" />
                    <IconToggleLeft v-else :size="18" class="text-text-muted" />
                  </button>
                  <!-- Copy public link -->
                  <button
                    v-if="form.visibility === 'public' && form.public_token"
                    :id="`btn-copy-link-form-${form.id}`"
                    class="grid size-9 place-items-center rounded hover:bg-surface-muted"
                    title="Copy public link"
                    @click="copyLink(form.public_token!)"
                  >
                    <IconLink :size="18" />
                  </button>
                  <!-- Regen token -->
                  <button
                    v-if="form.visibility === 'public'"
                    :id="`btn-regen-form-${form.id}`"
                    class="grid size-9 place-items-center rounded hover:bg-surface-muted"
                    title="Regenerate public token"
                    @click="confirmRegen(form)"
                  >
                    <IconRefresh :size="18" />
                  </button>
                  <!-- Archive -->
                  <button
                    v-if="form.status !== 'archived'"
                    :id="`btn-archive-form-${form.id}`"
                    class="grid size-9 place-items-center rounded hover:bg-surface-muted text-text-muted transition"
                    title="Archive form"
                    @click="confirmArchive(form)"
                  >
                    <IconArchive :size="18" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div
          v-if="meta && meta.last_page > 1"
          class="flex items-center justify-between border-t px-4 py-3 text-xs text-text-muted"
        >
          <span>Page {{ meta.current_page }} of {{ meta.last_page }} ({{ meta.total }} forms)</span>
          <div class="flex gap-2">
            <button
              class="rounded border px-2 py-1 disabled:opacity-40"
              :disabled="meta.current_page <= 1"
              @click="page--"
            >
              <IconChevronLeft :size="12" />
            </button>
            <button
              class="rounded border px-2 py-1 disabled:opacity-40"
              :disabled="meta.current_page >= meta.last_page"
              @click="page++"
            >
              <IconChevronRight :size="12" />
            </button>
          </div>
        </div>
      </template>
    </div>

    <!-- Archive confirm modal -->
    <AppDialog :model-value="!!archiveTarget" title="Archive form" @update:model-value="archiveTarget = null">
      <p class="text-sm text-text-muted">
        Are you sure you want to archive <strong>{{ archiveTarget?.title }}</strong>? Archiving will hide it from the active list but preserve its responses.
      </p>
      <template #footer="{ close }">
        <button class="rounded border px-4 py-2 text-xs" @click="close">Cancel</button>
        <button
          class="rounded bg-primary px-4 py-2 text-xs text-white disabled:opacity-60"
          :disabled="deleteMutation.isPending.value"
          @click="doArchive"
        >
          {{ deleteMutation.isPending.value ? "Archiving…" : "Archive" }}
        </button>
      </template>
    </AppDialog>

    <!-- Regen token confirm modal -->
    <AppDialog :model-value="!!regenTarget" title="Regenerate public link" @update:model-value="regenTarget = null">
      <p class="text-sm text-text-muted">
        Regenerating the link for <strong>{{ regenTarget?.title }}</strong> will immediately invalidate the current public URL.
        Anyone with the old link will lose access.
      </p>
      <template #footer="{ close }">
        <button class="rounded border px-4 py-2 text-xs" @click="close">Cancel</button>
        <button
          class="rounded bg-warning px-4 py-2 text-xs text-white disabled:opacity-60"
          :disabled="regenMutation.isPending.value"
          @click="doRegen"
        >
          {{ regenMutation.isPending.value ? "Regenerating…" : "Regenerate" }}
        </button>
      </template>
    </AppDialog>
  </div>
</template>
