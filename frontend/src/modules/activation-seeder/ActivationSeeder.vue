<script setup lang="ts">
/**
 * Activation Seeder â€” Admin UI
 *
 * Allows admins to create activation-ready grantees without CLI.
 * Redesigned as a full data table with persistent history and service manager.
 */
import { ref, computed, reactive } from "vue";
import { useQuery, useMutation, useQueryClient } from "@tanstack/vue-query";
import {
  IconSeedling,
  IconLink,
  IconCopy,
  IconCheck,
  IconAlertCircle,
  IconRefresh,
  IconUserPlus,
  IconChevronDown,
  IconActivity,
  IconX
} from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import { apiFetch } from "@/api";

const queryClient = useQueryClient();

// â”€â”€ Types â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

type BatchOption = {
  id: number;
  name: string;
  academic_year: string;
  semester: string;
  status: string;
  is_active: boolean;
};

type HistoryRecord = {
  id: number;
  student_id: string;
  full_name: string;
  email: string;
  program: string;
  year_level: string;
  created_at: string;
  token_status: "Active" | "Expired" | "Used" | "Unknown";
  token_expires_at?: string;
};

// â”€â”€ State â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const showModal = ref(false);
const batchMode = ref<"existing" | "new">("existing");
const copiedIds = ref<Record<number, boolean>>({});

const form = reactive({
  batch_id: null as number | null,
  batch_name: "",
  academic_year: "",
  semester: "1st Semester",
  student_id: "",
  first_name: "",
  last_name: "",
  middle_name: "",
  email: "",
  program: "BSIT",
  year_level: "1st Year",
  reset_kyc: false,
});

const errors = reactive<Record<string, string>>({});

// â”€â”€ Persist generated links across reloads (sessionStorage) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const SESSION_KEY = 'seeder_generated_links';

function loadGeneratedLinks(): Record<number, string> {
  try {
    const raw = sessionStorage.getItem(SESSION_KEY);
    return raw ? JSON.parse(raw) : {};
  } catch {
    return {};
  }
}

function saveGeneratedLinks(links: Record<number, string>) {
  try {
    sessionStorage.setItem(SESSION_KEY, JSON.stringify(links));
  } catch { /* quota exceeded - ignore */ }
}

const newlyGenerated = ref<Record<number, string>>(loadGeneratedLinks()); // Maps grantee_id -> activation_url

// â”€â”€ Queries â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

const batchesQuery = useQuery({
  queryKey: ["activation-seeder-batches"],
  queryFn: () => apiFetch<{ data: BatchOption[] }>("/api/activation-seeder/batches"),
});

const historyQuery = useQuery({
  queryKey: ["activation-seeder-history"],
  queryFn: () => apiFetch<{ data: HistoryRecord[] }>("/api/activation-seeder/history"),
});

const servicesQuery = useQuery({
  queryKey: ["developer-services-status"],
  queryFn: () => apiFetch<{ data: { cloudflare: boolean; ocr: boolean; activation_base: string } }>("/api/services/status"),
  refetchInterval: 10000,
  retry: false,
});

// Track the live tunnel URL (updated after cloudflare starts)
const tunnelUrl = ref<string | null>(null);

// Safe accessor â€” prevents template from crashing if services API fails
const services = computed(() => servicesQuery.data.value?.data ?? { cloudflare: false, ocr: false, activation_base: '' });

const batchOptions = computed(() => batchesQuery.data.value?.data ?? []);
const history = computed(() => historyQuery.data.value?.data ?? []);

// â”€â”€ Mutations â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

const seedMutation = useMutation({
  mutationFn: (payload: Record<string, unknown>) =>
    apiFetch<{ data: any }>("/api/activation-seeder", {
      method: "POST",
      body: JSON.stringify(payload),
    }),
  onSuccess: (res) => {
    showModal.value = false;
    newlyGenerated.value[res.data.grantee_id] = res.data.activation_url;
    saveGeneratedLinks(newlyGenerated.value);
    queryClient.invalidateQueries({ queryKey: ["activation-seeder-history"] });
    
    // reset form fields
    form.student_id = "";
    form.first_name = "";
    form.last_name = "";
    form.middle_name = "";
    form.email = "";
    form.reset_kyc = false;
    Object.keys(errors).forEach((k) => delete (errors as Record<string, string>)[k]);
  },
  onError: (err: any) => {
    const detail = err?.response?.data?.errors ?? {};
    Object.keys(errors).forEach((k) => delete (errors as Record<string, string>)[k]);
    Object.assign(errors, detail);
  },
});

const regenerateMutation = useMutation({
  mutationFn: (granteeId: number) =>
    apiFetch<{ data: any }>(`/api/activation-seeder/regenerate/${granteeId}`, {
      method: "POST",
    }),
  onSuccess: (res) => {
    newlyGenerated.value[res.data.grantee_id] = res.data.activation_url;
    saveGeneratedLinks(newlyGenerated.value);
    queryClient.invalidateQueries({ queryKey: ["activation-seeder-history"] });
  }
});

const startCloudflareMutation = useMutation({
  mutationFn: () => apiFetch<{ data?: { tunnel_url?: string }; message: string }>("/api/services/start-cloudflare", { method: "POST" }),
  onSuccess: (res) => {
    // Capture and display the new tunnel URL
    if (res.data?.tunnel_url) {
      tunnelUrl.value = res.data.tunnel_url;
    }
    queryClient.invalidateQueries({ queryKey: ["developer-services-status"] });
  }
});

const stopCloudflareMutation = useMutation({
  mutationFn: () => apiFetch("/api/services/stop-cloudflare", { method: "POST" }),
  onSuccess: () => {
    tunnelUrl.value = null;
    queryClient.invalidateQueries({ queryKey: ["developer-services-status"] });
  }
});

const startOcrMutation = useMutation({
  mutationFn: () => apiFetch("/api/services/start-ocr", { method: "POST" }),
  onSuccess: () => queryClient.invalidateQueries({ queryKey: ["developer-services-status"] })
});

const stopOcrMutation = useMutation({
  mutationFn: () => apiFetch("/api/services/stop-ocr", { method: "POST" }),
  onSuccess: () => queryClient.invalidateQueries({ queryKey: ["developer-services-status"] })
});


// â”€â”€ Actions â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

function validate(): boolean {
  Object.keys(errors).forEach((k) => delete (errors as Record<string, string>)[k]);

  if (batchMode.value === "existing" && !form.batch_id) {
    errors.batch_id = "Select an existing batch.";
  }
  if (batchMode.value === "new") {
    if (!form.batch_name.trim()) errors.batch_name = "Batch name is required.";
    if (!form.academic_year.trim()) errors.academic_year = "Academic year is required.";
    if (!form.semester.trim()) errors.semester = "Semester is required.";
  }
  if (!form.student_id.trim()) errors.student_id = "Student ID is required.";
  if (!form.first_name.trim()) errors.first_name = "First name is required.";
  if (!form.last_name.trim()) errors.last_name = "Last name is required.";
  if (!form.email.trim()) errors.email = "Email is required.";
  else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) errors.email = "Must be a valid email.";
  if (!form.program.trim()) errors.program = "Program is required.";

  return Object.keys(errors).length === 0;
}

function onSubmit() {
  if (!validate()) return;

  const payload: Record<string, unknown> = {
    student_id:     form.student_id.trim(),
    first_name:     form.first_name.trim(),
    last_name:      form.last_name.trim(),
    middle_name:    form.middle_name.trim(),
    email:          form.email.trim().toLowerCase(),
    program:        form.program.trim(),
    year_level:     form.year_level || "1st Year",
    reset_kyc:      form.reset_kyc,
  };

  if (batchMode.value === "existing") {
    payload.batch_id = form.batch_id;
  } else {
    payload.batch_name    = form.batch_name.trim();
    payload.academic_year = form.academic_year.trim();
    payload.semester      = form.semester.trim();
  }

  seedMutation.mutate(payload);
}

function getEffectiveActivationUrl(rawUrlOrToken: string): string {
  if (!rawUrlOrToken) return "";

  // Current active base: Cloudflare URL if tunnel is active, otherwise fallback to activation_base or current window origin
  const activeBase = (services.value.cloudflare && (tunnelUrl.value || services.value.activation_base))
    ? (tunnelUrl.value || services.value.activation_base).replace(/\/+$/, '')
    : (services.value.activation_base || window.location.origin).replace(/\/+$/, '');

  // If item is a full URL, replace its domain/origin with the activeBase
  if (rawUrlOrToken.startsWith('http://') || rawUrlOrToken.startsWith('https://')) {
    try {
      const parsed = new URL(rawUrlOrToken);
      return `${activeBase}${parsed.pathname}${parsed.search}`;
    } catch {
      const match = rawUrlOrToken.match(/(\/activate\/[^?#]+(?:\?[^#]*)?)/);
      return match ? `${activeBase}${match[1]}` : rawUrlOrToken;
    }
  }

  // If it's a relative path or plain token
  if (rawUrlOrToken.startsWith('/')) {
    return `${activeBase}${rawUrlOrToken}`;
  }
  return `${activeBase}/activate/${rawUrlOrToken}?lang=en`;
}

async function copyUrl(url: string, granteeId: number) {
  const effective = getEffectiveActivationUrl(url);
  await navigator.clipboard.writeText(effective);
  copiedIds.value[granteeId] = true;
  setTimeout(() => (copiedIds.value[granteeId] = false), 2000);
}

const tunnelCopied = ref(false);
async function copyTunnelUrl() {
  const url = tunnelUrl.value || services.value.activation_base || '';
  if (!url) return;
  await navigator.clipboard.writeText(url);
  tunnelCopied.value = true;
  setTimeout(() => (tunnelCopied.value = false), 2000);
}

function formatExpiry(iso: string): string {
  return new Date(iso).toLocaleDateString("en-PH", {
    year: "numeric", month: "short", day: "numeric",
  });
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
      <PageHeader
        title="Activation Link Seeder"
        description="Manage activation-ready grantees and background services."
      />

      <!-- Services Widget -->
      <div class="flex flex-wrap items-center gap-2 rounded-xl border bg-surface p-2 shadow-sm text-sm">
        <div class="flex items-center gap-1.5 px-2 border-r">
          <IconActivity :size="16" class="text-text-muted" />
          <span class="font-medium">Services:</span>
          <button
            type="button"
            @click="servicesQuery.refetch()"
            :disabled="servicesQuery.isFetching.value"
            title="Refresh service status"
            class="inline-flex h-6 w-6 items-center justify-center rounded-md text-text-muted transition hover:bg-surface-muted hover:text-text disabled:opacity-40"
          >
            <IconRefresh :size="13" :class="{ 'animate-spin': servicesQuery.isFetching.value }" />
          </button>
        </div>

        <!-- Cloudflare -->
        <div class="flex items-center gap-2 px-2">
          <span class="text-xs text-text-muted">Cloudflare</span>
          <div v-if="services.cloudflare" class="flex items-center gap-1.5">
            <span class="inline-flex items-center gap-1 rounded-full border border-success/30 bg-success/10 px-2 py-0.5 text-2xs font-medium text-success">
              <span class="h-1.5 w-1.5 rounded-full bg-success" />
              Running
            </span>
            <button
              type="button"
              @click="startCloudflareMutation.mutate()"
              :disabled="startCloudflareMutation.isPending.value || stopCloudflareMutation.isPending.value"
              title="Restart Cloudflare tunnel"
              class="inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-2xs font-medium text-text-muted transition hover:bg-surface-muted hover:text-text disabled:opacity-50"
            >
              <IconRefresh :size="11" :class="{ 'animate-spin': startCloudflareMutation.isPending.value }" />
              {{ startCloudflareMutation.isPending.value ? 'Restarting…' : 'Restart' }}
            </button>
            <button
              type="button"
              @click="stopCloudflareMutation.mutate()"
              :disabled="startCloudflareMutation.isPending.value || stopCloudflareMutation.isPending.value"
              title="Stop Cloudflare tunnel"
              class="inline-flex items-center gap-1 rounded-md border border-danger/30 text-danger px-2 py-0.5 text-2xs font-medium transition hover:bg-danger/10 disabled:opacity-50"
            >
              <IconX :size="11" />
              {{ stopCloudflareMutation.isPending.value ? 'Stopping…' : 'Stop' }}
            </button>
          </div>
          <button
            v-else
            type="button"
            @click="startCloudflareMutation.mutate()"
            :disabled="startCloudflareMutation.isPending.value"
            class="flex items-center gap-1.5 rounded-full px-2.5 py-1 text-2xs font-medium border transition disabled:cursor-not-allowed hover:bg-surface-muted"
            :class="{
              'bg-warning/10 border-warning/30 text-warning': startCloudflareMutation.isPending.value,
            }"
          >
            <div
              class="h-1.5 w-1.5 rounded-full"
              :class="{
                'bg-warning animate-pulse': startCloudflareMutation.isPending.value,
                'bg-danger': !startCloudflareMutation.isPending.value,
              }"
            />
            <IconRefresh v-if="startCloudflareMutation.isPending.value" :size="11" class="animate-spin" />
            <span>
              {{ startCloudflareMutation.isPending.value ? 'Starting…' : 'Start' }}
            </span>
          </button>
        </div>

        <!-- OCR Engine -->
        <div class="flex items-center gap-2 px-2 border-l">
          <span class="text-xs text-text-muted">OCR Engine</span>
          <div v-if="services.ocr" class="flex items-center gap-1.5">
            <span class="inline-flex items-center gap-1 rounded-full border border-success/30 bg-success/10 px-2 py-0.5 text-2xs font-medium text-success">
              <span class="h-1.5 w-1.5 rounded-full bg-success" />
              Running
            </span>
            <button
              type="button"
              @click="startOcrMutation.mutate()"
              :disabled="startOcrMutation.isPending.value || stopOcrMutation.isPending.value"
              title="Restart OCR engine"
              class="inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-2xs font-medium text-text-muted transition hover:bg-surface-muted hover:text-text disabled:opacity-50"
            >
              <IconRefresh :size="11" :class="{ 'animate-spin': startOcrMutation.isPending.value }" />
              {{ startOcrMutation.isPending.value ? 'Restarting…' : 'Restart' }}
            </button>
            <button
              type="button"
              @click="stopOcrMutation.mutate()"
              :disabled="startOcrMutation.isPending.value || stopOcrMutation.isPending.value"
              title="Stop OCR engine"
              class="inline-flex items-center gap-1 rounded-md border border-danger/30 text-danger px-2 py-0.5 text-2xs font-medium transition hover:bg-danger/10 disabled:opacity-50"
            >
              <IconX :size="11" />
              {{ stopOcrMutation.isPending.value ? 'Stopping…' : 'Stop' }}
            </button>
          </div>
          <button
            v-else
            type="button"
            @click="startOcrMutation.mutate()"
            :disabled="startOcrMutation.isPending.value"
            class="flex items-center gap-1.5 rounded-full px-2.5 py-1 text-2xs font-medium border transition disabled:cursor-not-allowed hover:bg-surface-muted"
            :class="{
              'bg-warning/10 border-warning/30 text-warning': startOcrMutation.isPending.value,
            }"
          >
            <div
              class="h-1.5 w-1.5 rounded-full"
              :class="{
                'bg-warning animate-pulse': startOcrMutation.isPending.value,
                'bg-danger': !startOcrMutation.isPending.value,
              }"
            />
            <IconRefresh v-if="startOcrMutation.isPending.value" :size="11" class="animate-spin" />
            <span>
              {{ startOcrMutation.isPending.value ? 'Starting…' : 'Start' }}
            </span>
          </button>
        </div>
      </div>
    </div>

    <!-- Tunnel URL Banner: shown when cloudflare is running and we have a URL -->
    <div
      v-if="services.cloudflare && (tunnelUrl || services.activation_base)"
      class="flex items-center gap-3 rounded-xl border border-primary/20 bg-primary/5 px-4 py-3 text-sm"
    >
      <IconLink :size="16" class="shrink-0 text-primary" />
      <div class="flex-1 min-w-0">
        <p class="text-xs font-medium text-text mb-0.5">Active Cloudflare Tunnel (used for activation links)</p>
        <code class="text-xs text-primary truncate block">
          {{ tunnelUrl || services.activation_base }}
        </code>
      </div>
      <button
        @click="copyTunnelUrl()"
        class="shrink-0 inline-flex items-center gap-1.5 rounded-lg border border-primary/20 bg-primary/10 px-3 py-1.5 text-xs font-medium text-primary transition hover:bg-primary/20"
        :class="tunnelCopied ? 'bg-success/10 border-success/30 text-success' : ''"
      >
        <IconCheck v-if="tunnelCopied" :size="13" />
        <IconCopy v-else :size="13" />
        {{ tunnelCopied ? 'Copied!' : 'Copy URL' }}
      </button>
    </div>

    <!-- â”€â”€ Data Table â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
    <div class="rounded-xl border bg-surface shadow-sm overflow-hidden">
      <div class="flex items-center justify-between p-4 border-b">
        <h2 class="text-sm font-semibold text-text">Seeded Grantees</h2>
        <button
          @click="showModal = true"
          class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-xs font-semibold text-white shadow hover:bg-primary-hover transition"
        >
          <IconSeedling :size="14" />
          Seed New Account
        </button>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="bg-surface-muted text-xs uppercase text-text-muted">
            <tr>
              <th class="px-4 py-3 font-medium">Student ID</th>
              <th class="px-4 py-3 font-medium">Name & Email</th>
              <th class="px-4 py-3 font-medium">Program</th>
              <th class="px-4 py-3 font-medium">Status</th>
              <th class="px-4 py-3 font-medium text-right">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y border-t">
            <tr v-if="historyQuery.isLoading.value">
              <td colspan="5" class="p-8 text-center text-text-muted">Loading history...</td>
            </tr>
            <tr v-else-if="history.length === 0">
              <td colspan="5" class="p-8 text-center text-text-muted">No seeded grantees yet.</td>
            </tr>
            <tr v-for="record in history" :key="record.id" class="hover:bg-surface-muted/50 transition">
              <td class="px-4 py-3 font-mono text-xs">{{ record.student_id }}</td>
              <td class="px-4 py-3">
                <div class="font-medium text-text">{{ record.full_name }}</div>
                <div class="text-xs text-text-muted">{{ record.email }}</div>
              </td>
              <td class="px-4 py-3 text-xs text-text-muted">{{ record.program }} - {{ record.year_level }}</td>
              <td class="px-4 py-3">
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-2xs font-medium border"
                  :class="{
                    'bg-success/10 text-success border-success/30': record.token_status === 'Used',
                    'bg-primary/10 text-primary border-primary/30': record.token_status === 'Active',
                    'bg-danger/10 text-danger border-danger/30': record.token_status === 'Expired',
                    'bg-surface-muted text-text-muted border-border': record.token_status === 'Unknown'
                  }">
                  {{ record.token_status }}
                </span>
                <div v-if="record.token_expires_at && record.token_status === 'Active'" class="text-2xs text-text-muted mt-1">
                  Exp: {{ formatExpiry(record.token_expires_at) }}
                </div>
              </td>
              <td class="px-4 py-3 text-right">
                <div class="flex items-center justify-end gap-2">
                  <!-- Copy Link: visible if we have the link in session -->
                  <button
                    v-if="newlyGenerated[record.id]"
                    @click="copyUrl(newlyGenerated[record.id], record.id)"
                    class="inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium transition hover:bg-surface-muted"
                    :class="copiedIds[record.id] ? 'text-success border-success/40 bg-success/5' : 'text-primary border-primary/20 bg-primary/5'"
                  >
                    <IconCheck v-if="copiedIds[record.id]" :size="13" />
                    <IconCopy v-else :size="13" />
                    {{ copiedIds[record.id] ? "Copied!" : "Copy Link" }}
                  </button>

                  <!-- Regenerate: visible for any non-Used row -->
                  <button
                    v-if="record.token_status !== 'Used'"
                    @click="regenerateMutation.mutate(record.id)"
                    :disabled="regenerateMutation.isPending.value"
                    class="inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium transition hover:bg-surface-muted text-text-muted disabled:opacity-50"
                    :title="newlyGenerated[record.id] ? 'Get a fresh link (e.g. after Cloudflare restarts)' : 'Generate activation link'"
                  >
                    <IconRefresh :size="13" :class="{'animate-spin': regenerateMutation.isPending.value}" />
                    {{ newlyGenerated[record.id] ? 'Refresh' : 'Get Link' }}
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- â”€â”€ New Seed Modal â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
  <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
    <div class="w-full max-w-2xl rounded-xl bg-surface shadow-2xl overflow-hidden max-h-[90vh] flex flex-col">
      <div class="flex items-center justify-between border-b px-6 py-4 bg-surface-muted/30">
        <div class="flex items-center gap-3">
          <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10">
            <IconSeedling class="text-primary" :size="18" />
          </div>
          <div>
            <h2 class="text-sm font-semibold text-text">New Grantee Activation</h2>
            <p class="text-xs text-text-muted">All fields required unless marked optional</p>
          </div>
        </div>
        <button @click="showModal = false" class="text-text-muted hover:text-text rounded-lg p-1 hover:bg-surface-muted transition">
          <IconX :size="20" />
        </button>
      </div>

      <div class="flex-1 overflow-y-auto p-6">
        <form id="activation-seeder-form" @submit.prevent="onSubmit" class="space-y-6">
          <!-- â”€â”€ Batch Section â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
          <fieldset class="space-y-3">
            <legend class="text-xs font-semibold uppercase tracking-wide text-text-muted">Batch</legend>

            <!-- Mode toggle -->
            <div class="flex overflow-hidden rounded-lg border text-sm">
              <button
                type="button"
                class="flex-1 py-2 text-center transition"
                :class="batchMode === 'existing'
                  ? 'bg-primary text-white font-medium'
                  : 'bg-surface text-text-muted hover:bg-surface-muted'"
                @click="batchMode = 'existing'"
              >
                Use Existing Batch
              </button>
              <button
                type="button"
                class="flex-1 py-2 text-center transition"
                :class="batchMode === 'new'
                  ? 'bg-primary text-white font-medium'
                  : 'bg-surface text-text-muted hover:bg-surface-muted'"
                @click="batchMode = 'new'"
              >
                Create New Batch
              </button>
            </div>

            <!-- Existing batch picker -->
            <div v-if="batchMode === 'existing'">
              <label class="mb-1 block text-xs font-medium text-text">Batch</label>
              <div class="relative">
                <select
                  id="batch-select"
                  v-model="form.batch_id"
                  class="h-10 w-full appearance-none rounded-lg border bg-surface px-3 pr-8 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20"
                  :class="errors.batch_id ? 'border-danger' : ''"
                >
                  <option :value="null" disabled>â€” Select batch â€”</option>
                  <option
                    v-for="b in batchOptions"
                    :key="b.id"
                    :value="b.id"
                  >
                    {{ b.name }} ({{ b.academic_year }} {{ b.semester }})
                    {{ b.is_active ? "âœ“ Active" : "" }}
                  </option>
                </select>
                <IconChevronDown class="pointer-events-none absolute right-2.5 top-2.5 text-text-muted" :size="16" />
              </div>
              <p v-if="errors.batch_id" class="mt-1 text-xs text-danger">{{ errors.batch_id }}</p>
              <p v-if="batchesQuery.isLoading.value" class="mt-1 text-xs text-text-muted">Loading batchesâ€¦</p>
            </div>

            <!-- New batch fields -->
            <div v-else class="grid gap-3 sm:grid-cols-3">
              <div class="sm:col-span-3">
                <label class="mb-1 block text-xs font-medium text-text">Batch Name</label>
                <input
                  v-model="form.batch_name"
                  type="text"
                  class="h-10 w-full rounded-lg border bg-surface px-3 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20"
                  :class="errors.batch_name ? 'border-danger' : ''"
                  placeholder="TES AY 2026-2027 1st Semester"
                />
                <p v-if="errors.batch_name" class="mt-1 text-xs text-danger">{{ errors.batch_name }}</p>
              </div>
              <div class="sm:col-span-1">
                <label class="mb-1 block text-xs font-medium text-text">Academic Year</label>
                <input
                  v-model="form.academic_year"
                  type="text"
                  class="h-10 w-full rounded-lg border bg-surface px-3 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20"
                  :class="errors.academic_year ? 'border-danger' : ''"
                  placeholder="2026-2027"
                />
                <p v-if="errors.academic_year" class="mt-1 text-xs text-danger">{{ errors.academic_year }}</p>
              </div>
              <div class="sm:col-span-2">
                <label class="mb-1 block text-xs font-medium text-text">Semester</label>
                <div class="relative">
                  <select
                    v-model="form.semester"
                    class="h-10 w-full appearance-none rounded-lg border bg-surface px-3 pr-8 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20"
                  >
                    <option>1st Semester</option>
                    <option>2nd Semester</option>
                    <option>Summer</option>
                  </select>
                  <IconChevronDown class="pointer-events-none absolute right-2.5 top-2.5 text-text-muted" :size="16" />
                </div>
              </div>
            </div>
          </fieldset>

          <div class="border-t" />

          <!-- â”€â”€ Grantee Fields â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
          <fieldset class="space-y-3">
            <legend class="text-xs font-semibold uppercase tracking-wide text-text-muted">Grantee Info</legend>

            <div class="grid gap-3 sm:grid-cols-2">
              <div class="sm:col-span-2">
                <label class="mb-1 block text-xs font-medium text-text">Student ID</label>
                <input
                  v-model="form.student_id"
                  type="text"
                  class="h-10 w-full rounded-lg border bg-surface px-3 text-sm font-mono focus:border-primary focus:ring-2 focus:ring-primary/20"
                  :class="errors.student_id ? 'border-danger' : ''"
                  placeholder="20232131"
                />
                <p v-if="errors.student_id" class="mt-1 text-xs text-danger">{{ errors.student_id }}</p>
              </div>

              <div class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                  <label class="mb-1 block text-xs font-medium text-text">First Name</label>
                  <input
                    v-model="form.first_name"
                    type="text"
                    class="h-10 w-full rounded-lg border bg-surface px-3 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20"
                    :class="errors.first_name ? 'border-danger' : ''"
                    placeholder="Juan"
                  />
                  <p v-if="errors.first_name" class="mt-1 text-xs text-danger">{{ errors.first_name }}</p>
                </div>
                <div>
                  <label class="mb-1 block text-xs font-medium text-text">Middle Name <span class="text-text-muted font-normal">(Optional)</span></label>
                  <input
                    v-model="form.middle_name"
                    type="text"
                    class="h-10 w-full rounded-lg border bg-surface px-3 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20"
                    placeholder="Protacio"
                  />
                </div>
                <div>
                  <label class="mb-1 block text-xs font-medium text-text">Last Name</label>
                  <input
                    v-model="form.last_name"
                    type="text"
                    class="h-10 w-full rounded-lg border bg-surface px-3 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20"
                    :class="errors.last_name ? 'border-danger' : ''"
                    placeholder="Dela Cruz"
                  />
                  <p v-if="errors.last_name" class="mt-1 text-xs text-danger">{{ errors.last_name }}</p>
                </div>
              </div>

              <div class="sm:col-span-2">
                <label class="mb-1 block text-xs font-medium text-text">Email</label>
                <input
                  v-model="form.email"
                  type="email"
                  class="h-10 w-full rounded-lg border bg-surface px-3 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20"
                  :class="errors.email ? 'border-danger' : ''"
                  placeholder="juan.delacruz@tcc.edu.ph"
                />
                <p v-if="errors.email" class="mt-1 text-xs text-danger">{{ errors.email }}</p>
              </div>

              <div>
                <label class="mb-1 block text-xs font-medium text-text">Program</label>
                <div class="relative">
                  <select
                    v-model="form.program"
                    class="h-10 w-full appearance-none rounded-lg border bg-surface px-3 pr-8 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20"
                  >
                    <option>BSIT</option>
                    <option>BSCS</option>
                    <option>BSED</option>
                    <option>BSBA</option>
                    <option>BSCRIM</option>
                    <option>BSN</option>
                    <option>BSHM</option>
                    <option>BSMT</option>
                    <option>BSECE</option>
                  </select>
                  <IconChevronDown class="pointer-events-none absolute right-2.5 top-2.5 text-text-muted" :size="16" />
                </div>
              </div>

              <div>
                <label class="mb-1 block text-xs font-medium text-text">Year Level</label>
                <div class="relative">
                  <select
                    v-model="form.year_level"
                    class="h-10 w-full appearance-none rounded-lg border bg-surface px-3 pr-8 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20"
                  >
                    <option value="1st Year">1st Year</option>
                    <option value="2nd Year">2nd Year</option>
                    <option value="3rd Year">3rd Year</option>
                    <option value="4th Year">4th Year</option>
                  </select>
                  <IconChevronDown class="pointer-events-none absolute right-2.5 top-2.5 text-text-muted" :size="16" />
                </div>
              </div>
            </div>
          </fieldset>

          <div class="border-t" />

          <!-- â”€â”€ Options â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
          <div class="flex items-start gap-3">
            <input
              id="reset-kyc"
              v-model="form.reset_kyc"
              type="checkbox"
              class="mt-0.5 h-4 w-4 rounded border-border accent-primary"
            />
            <label for="reset-kyc" class="text-sm text-text">
              <span class="font-medium">Reset KYC &amp; identity data</span>
              <span class="block text-xs text-text-muted">
                Wipes existing KYC profile and identity scan so the grantee restarts onboarding from scratch.
              </span>
            </label>
          </div>

          <!-- Error summary -->
          <div
            v-if="seedMutation.isError.value"
            class="flex items-start gap-2 rounded-lg border border-danger/30 bg-danger-soft px-4 py-3 text-sm text-danger"
          >
            <IconAlertCircle :size="16" class="mt-0.5 shrink-0" />
            <div>
              <p class="font-medium">Seeding failed</p>
              <p class="text-xs">
                {{ (seedMutation.error.value as any)?.response?.data?.message ?? "Check the fields above and try again." }}
              </p>
            </div>
          </div>
        </form>
      </div>
      
      <div class="border-t p-4 bg-surface flex justify-end gap-3">
        <button
          type="button"
          @click="showModal = false"
          class="rounded-lg border px-5 py-2.5 text-sm font-medium hover:bg-surface-muted transition"
        >
          Cancel
        </button>
        <button
          form="activation-seeder-form"
          type="submit"
          class="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow hover:bg-primary-hover disabled:opacity-50 transition"
          :disabled="seedMutation.isPending.value"
        >
          <IconRefresh v-if="seedMutation.isPending.value" :size="16" class="animate-spin" />
          <IconUserPlus v-else :size="16" />
          {{ seedMutation.isPending.value ? "Seedingâ€¦" : "Generate Activation Link" }}
        </button>
      </div>
    </div>
  </div>
</template>

