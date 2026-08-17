<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useQuery, useMutation, useQueryClient } from "@tanstack/vue-query";
import {
  IconMail,
  IconCheck,
  IconSend,
  IconUsers,
  IconAlertTriangle,
} from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import DataTable from "@/components/tables/DataTable.vue";
import { apiFetch, apiUrl } from "@/api/client";
import type { PaginatedResponse } from "@/api/types";
import { queryKeys } from "@/api/queryKeys";
import { toast } from "@/composables/useToast";

type Batch = {
  id: number;
  name: string;
  academic_year: string;
  semester: string;
  window_status: "draft" | "active" | "closed" | "expired";
};

type OnboardingStats = {
  total: number;
  invited: number;
  uninvited: number;
  active: number;
  pending_face_review: number;
};

type GranteeRow = {
  id: number;
  student_id: string;
  full_name: string;
  email: string;
  program: string;
  is_invited: boolean;
  account_status: string;
};

const queryClient = useQueryClient();
const selectedBatchId = ref<number | null>(null);
const page = ref(1);
const searchQuery = ref("");
const statusFilter = ref("");

watch([selectedBatchId, searchQuery, statusFilter], () => {
  page.value = 1;
});

const batchesQuery = useQuery({
  queryKey: computed(() => [...queryKeys.batches, { page: 1, onboarding: true }]),
  queryFn: () => apiFetch<PaginatedResponse<Batch>>("/api/batches?page=1&per_page=50"),
});

const batches = computed(() => batchesQuery.data.value?.data ?? []);

// Stats Query
const statsQuery = useQuery({
  queryKey: computed(() => ["onboarding_stats", selectedBatchId.value]),
  queryFn: () => apiFetch<{ data: OnboardingStats }>(`/api/onboarding-center/batches/${selectedBatchId.value}/stats`),
  enabled: computed(() => selectedBatchId.value !== null),
});

const stats = computed(() => statsQuery.data.value?.data);

// Grantees Query
const granteesQuery = useQuery({
  queryKey: computed(() => ["onboarding_grantees", selectedBatchId.value, page.value, searchQuery.value, statusFilter.value]),
  queryFn: () => {
    const params = new URLSearchParams({ page: page.value.toString() });
    if (searchQuery.value) params.append("search", searchQuery.value);
    if (statusFilter.value) params.append("status", statusFilter.value);
    return apiFetch<PaginatedResponse<GranteeRow>>(`/api/onboarding-center/batches/${selectedBatchId.value}/grantees?${params.toString()}`);
  },
  enabled: computed(() => selectedBatchId.value !== null),
});

const grantees = computed(() => granteesQuery.data.value?.data ?? []);
const granteesMeta = computed(() => granteesQuery.data.value?.meta);

// Blast Invites Mutation
const blastInvitesMutation = useMutation({
  mutationFn: async () => {
    return apiFetch(`/api/onboarding-center/batches/${selectedBatchId.value}/blast-invites`, {
      method: "POST",
    });
  },
  onSuccess: (data: any) => {
    toast.success(data.message || "Invites blasted successfully!");
    queryClient.invalidateQueries({ queryKey: ["onboarding_stats"] });
    queryClient.invalidateQueries({ queryKey: ["onboarding_grantees"] });
  },
  onError: (error: any) => {
    toast.error(error.message || "Failed to blast invites.");
  },
});

// Resend Invite Mutation
const resendInviteMutation = useMutation({
  mutationFn: async (granteeId: number) => {
    return apiFetch(`/api/onboarding-center/grantees/${granteeId}/resend-invite`, {
      method: "POST",
    });
  },
  onSuccess: () => {
    toast.success("Invite resent successfully!");
    queryClient.invalidateQueries({ queryKey: ["onboarding_grantees"] });
  },
  onError: (error: any) => {
    toast.error(error.message || "Failed to resend invite.");
  },
});
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      title="Onboarding Center"
      description="Manage the rollout of the onboarding process. Track which students have received invitations and send activation emails to the batch."
    />

    <!-- Batch Selection Card -->
    <div class="rounded-xl border bg-surface p-5 shadow-sm">
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h2 class="text-sm font-semibold text-text">Select Target Batch</h2>
          <p class="text-xs text-text-muted">Choose the batch you want to manage invitations for.</p>
        </div>
        <div class="w-full md:w-72">
          <select
            v-model="selectedBatchId"
            class="h-10 w-full rounded-md border bg-surface px-3 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
          >
            <option :value="null">-- Select a Batch --</option>
            <option v-for="batch in batches" :key="batch.id" :value="batch.id">
              {{ batch.name }} ({{ batch.academic_year }})
            </option>
          </select>
        </div>
      </div>
    </div>

    <template v-if="selectedBatchId">
      <!-- Stats Dashboard -->
      <div class="grid gap-4 md:grid-cols-4">
        <div class="rounded-xl border bg-surface p-4 shadow-sm">
          <p class="text-xs font-semibold text-text-muted uppercase tracking-wider mb-1">Total Grantees</p>
          <div class="flex items-center gap-2">
            <IconUsers :size="24" class="text-text-muted" />
            <span class="text-2xl font-bold text-text">{{ stats?.total || 0 }}</span>
          </div>
        </div>
        <div class="rounded-xl border bg-surface p-4 shadow-sm">
          <p class="text-xs font-semibold text-text-muted uppercase tracking-wider mb-1">Invites Sent</p>
          <div class="flex items-center gap-2">
            <IconMail :size="24" class="text-primary" />
            <span class="text-2xl font-bold text-text">{{ stats?.invited || 0 }}</span>
          </div>
        </div>
        <div class="rounded-xl border bg-surface p-4 shadow-sm">
          <p class="text-xs font-semibold text-text-muted uppercase tracking-wider mb-1">Active Accounts</p>
          <div class="flex items-center gap-2">
            <IconCheck :size="24" class="text-success" />
            <span class="text-2xl font-bold text-text">{{ stats?.active || 0 }}</span>
          </div>
        </div>
        <div class="rounded-xl border bg-surface p-4 shadow-sm flex flex-col justify-center">
          <button
            class="w-full inline-flex h-12 items-center justify-center gap-2 rounded-lg bg-primary px-4 font-semibold text-white shadow hover:bg-primary-600 disabled:opacity-60 transition"
            :disabled="!stats || stats.uninvited === 0 || blastInvitesMutation.isPending.value"
            @click="blastInvitesMutation.mutate()"
          >
            <IconSend :size="18" />
            <span v-if="blastInvitesMutation.isPending.value">Sending...</span>
            <span v-else-if="stats?.uninvited === 0">All Invited</span>
            <span v-else>Blast {{ stats?.uninvited }} Invites</span>
          </button>
        </div>
      </div>

      <!-- Grantees List -->
      <div class="rounded-xl border bg-surface shadow-sm overflow-hidden">
        <div class="border-b px-5 py-4 flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div>
            <h2 class="font-semibold text-text flex items-center gap-2">
              Batch Grantees
              <span v-if="selectedBatchId" class="inline-flex items-center rounded-full bg-surface-muted px-2.5 py-0.5 text-xs font-medium text-text-muted border">
                {{ batches.find(b => b.id === selectedBatchId)?.name }}
              </span>
            </h2>
            <p class="text-xs text-text-muted mt-1">Manage individual invitations for this batch.</p>
          </div>
          <div class="flex items-center gap-2">
            <input
              v-model="searchQuery"
              type="search"
              placeholder="Search ID, name, email..."
              class="h-9 w-64 rounded-md border bg-surface px-3 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
            />
            <select
              v-model="statusFilter"
              class="h-9 rounded-md border bg-surface px-3 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
            >
              <option value="">All Statuses</option>
              <option value="unverified">Unverified</option>
              <option value="pending_face_review">Pending Face Review</option>
              <option value="active">Active</option>
            </select>
          </div>
        </div>
        <DataTable
          :headings="['Student ID', 'Name', 'Email', 'Account Status', 'Invitation', 'Actions']"
        >
          <template v-if="granteesQuery.isPending.value">
            <tr v-for="i in 5" :key="i" class="border-t">
              <td class="px-5 py-4"><div class="h-4 w-24 animate-pulse rounded bg-surface-muted"></div></td>
              <td class="px-5 py-4"><div class="h-4 w-40 animate-pulse rounded bg-surface-muted"></div></td>
              <td class="px-5 py-4"><div class="h-4 w-48 animate-pulse rounded bg-surface-muted"></div></td>
              <td class="px-5 py-4"><div class="h-5 w-24 animate-pulse rounded-full bg-surface-muted"></div></td>
              <td class="px-5 py-4"><div class="h-4 w-16 animate-pulse rounded bg-surface-muted"></div></td>
              <td class="px-5 py-4 text-right"><div class="ml-auto h-4 w-20 animate-pulse rounded bg-surface-muted"></div></td>
            </tr>
          </template>
          <tr v-else-if="grantees.length === 0">
            <td colspan="6" class="px-5 py-8 text-center text-sm text-text-muted">No grantees found in this batch. (Did you upload the Masterlist?)</td>
          </tr>
          <tr v-for="grantee in grantees" :key="grantee.id" class="border-t hover:bg-surface-muted transition">
            <td class="px-5 py-3 text-sm font-mono text-text-muted">{{ grantee.student_id }}</td>
            <td class="px-5 py-3 text-sm font-medium text-text">{{ grantee.full_name }}</td>
            <td class="px-5 py-3 text-sm text-text-muted">{{ grantee.email }}</td>
            <td class="px-5 py-3 text-sm">
              <span
                :class="[
                  'inline-flex items-center rounded-full px-2 py-0.5 text-2xs font-semibold uppercase tracking-wider',
                  grantee.account_status === 'active'
                    ? 'bg-success/10 text-success'
                    : grantee.account_status === 'pending_face_review'
                      ? 'bg-warning/10 text-warning'
                      : 'bg-surface-muted text-text-muted',
                ]"
              >
                {{ grantee.account_status.replace(/_/g, ' ') }}
              </span>
            </td>
            <td class="px-5 py-3 text-sm">
              <span v-if="grantee.is_invited" class="inline-flex items-center gap-1 text-xs font-semibold text-success">
                <IconCheck :size="14" /> Sent
              </span>
              <span v-else class="text-xs text-text-muted">Uninvited</span>
            </td>
            <td class="px-5 py-3 text-right">
              <button
                v-if="grantee.account_status !== 'active'"
                class="text-xs font-semibold text-primary hover:underline disabled:opacity-50"
                :disabled="resendInviteMutation.isPending.value"
                @click="resendInviteMutation.mutate(grantee.id)"
              >
                Resend Invite
              </button>
            </td>
          </tr>
        </DataTable>
        
        <!-- Pagination Controls -->
        <div v-if="granteesMeta && granteesMeta.last_page > 1" class="flex items-center justify-between border-t px-5 py-3 bg-surface">
          <p class="text-xs text-text-muted">
            Showing <span class="font-medium text-text">{{ granteesMeta.from || 0 }}</span> to <span class="font-medium text-text">{{ granteesMeta.to || 0 }}</span> of <span class="font-medium text-text">{{ granteesMeta.total }}</span> grantees
          </p>
          <div class="flex items-center gap-2">
            <button
              class="rounded-md border px-3 py-1.5 text-xs font-medium disabled:opacity-50 transition-colors"
              :class="page === 1 ? 'bg-surface-muted text-text-soft' : 'bg-surface text-text hover:bg-surface-muted'"
              :disabled="page === 1"
              @click="page--"
            >
              Previous
            </button>
            <span class="text-xs text-text-muted px-2">Page {{ granteesMeta.current_page }} of {{ granteesMeta.last_page }}</span>
            <button
              class="rounded-md border px-3 py-1.5 text-xs font-medium disabled:opacity-50 transition-colors"
              :class="page === granteesMeta.last_page ? 'bg-surface-muted text-text-soft' : 'bg-surface text-text hover:bg-surface-muted'"
              :disabled="page === granteesMeta.last_page"
              @click="page++"
            >
              Next
            </button>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
