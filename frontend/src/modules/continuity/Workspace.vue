<script setup lang="ts">
import { computed, ref } from "vue";
import { useMutation, useQuery, useQueryClient } from "@tanstack/vue-query";
import { useI18n } from "vue-i18n";
import { useOnline } from "@vueuse/core";
import { apiFetch } from "@/api/client";
import { authSession } from "@/auth/session";

const { t } = useI18n({
  useScope: "local",
  messages: {
    en: {
      title: "Google Workspace continuity",
      description: "Connect your school account and prepare private module workbooks.",
      restricted: "Only administrators can manage this integration.",
      credentials: "Enter the Google Workspace client ID and secret on the server before connecting.",
      connect: "Connect Google Workspace",
      connected: "Connected account",
      drive: "Shared Drive",
      loadDrives: "Load Shared Drives",
      select: "Select a Shared Drive",
      save: "Save Drive",
      provision: "Create module workbooks",
      confirmProvision: "Create private continuity workbooks in the selected Shared Drive.",
      setup: "Connection setup",
      resources: "Module workbooks",
      empty: "No workbooks have been created.",
      open: "Open workbook",
      refresh: "Refresh",
      pending: "Setup required",
      ready: "Workbook prepared",
      offline: "You are offline. Reconnect before changing integration settings.",
      loading: "Loading integration settings",
      runs: "Synchronization history",
      noRuns: "No synchronization runs yet.",
      sync: "Sync now",
      review: "Pending reviews",
      noReviews: "No pending identity or data reviews.",
      base: "Previously synchronized",
      system: "Current system",
      mirror: "Spreadsheet proposal",
      unavailable: "Full continuity activation is awaiting storage, intake, and permission verification. Connection setup is available.",
      disconnect: "Disconnect",
      confirmDisconnect: "Disconnect this Google account? Existing Drive files will remain.",
      failed: "Unable to complete this operation.",
    },
  },
});
type Connection = { status: string; email: string; drive_id: string | null; drive_name: string | null; enabled: boolean };
type Resource = { id: number; module: string; workbook_id: string | null; status: string };
type Status = { configured: boolean; connection: Connection | null; resources: Resource[] };
type Run = { id: string; status: string; created_at: string };
type Review = { id: string; module: string; kind: string; payload: { base: Record<string, string>; system: Record<string, string>; mirror: Record<string, string> } };
const allowed = computed(() => ["admin", "developer"].includes(authSession.user?.role ?? ""));
const online = useOnline();
const queryClient = useQueryClient();
const keys = ["continuity"] as const;
const status = useQuery({ queryKey: [...keys, "status"], queryFn: () => apiFetch<{ data: Status }>("/api/integrations/google-workspace/status").then(r => r.data), enabled: allowed, staleTime: 30_000, retry: false });
const runs = useQuery({ queryKey: [...keys, "runs"], queryFn: () => apiFetch<{ data: Run[] }>("/api/continuity/sync-runs").then(r => r.data), enabled: allowed, retry: false });
const reviews = useQuery({ queryKey: [...keys, "reviews"], queryFn: () => apiFetch<{ data: Review[] }>("/api/continuity/reviews").then(r => r.data), enabled: allowed, retry: false });
const drives = ref<{ id: string; name: string }[]>([]);
const selectedDrive = ref("");
const confirmed = ref(false);
const action = useMutation({
  mutationFn: async (operation: string) => {
    const base = "/api/integrations/google-workspace";
    if (operation === "connect") {
      const result = await apiFetch<{ data: { authorization_url: string } }>(base + "/oauth", { method: "POST" });
      window.location.assign(result.data.authorization_url);
    } else if (operation === "drives") {
      drives.value = (await apiFetch<{ data: { id: string; name: string }[] }>(base + "/drives")).data;
    } else if (operation === "save") {
      await apiFetch(base + "/resources", { method: "PUT", body: JSON.stringify({ drive_id: selectedDrive.value }) });
    } else if (operation === "provision") {
      await apiFetch(base + "/provision", { method: "POST", body: JSON.stringify({ confirm: confirmed.value }) });
    } else if (operation === "sync") {
      await apiFetch(base + "/sync", { method: "POST" });
    } else if (operation === "disconnect" && window.confirm(t("confirmDisconnect"))) {
      await apiFetch(base + "/connection", { method: "DELETE", body: JSON.stringify({ confirm: true }) });
    }
  },
  onSuccess: () => queryClient.invalidateQueries({ queryKey: keys }),
});
const busy = computed(() => action.isPending.value || !online.value);
</script>

<template>
  <main class="mx-auto max-w-5xl space-y-6">
    <header>
      <h1 class="text-2xl font-semibold">{{ t("title") }}</h1>
      <p class="mt-2 text-sm text-text-muted">{{ t("description") }}</p>
    </header>
    <p v-if="!allowed" role="alert">{{ t("restricted") }}</p>
    <template v-else>
      <p v-if="!online" role="status" class="rounded-lg border p-4">{{ t("offline") }}</p>
      <div v-if="status.isPending.value" class="h-48 animate-pulse rounded-lg bg-surface-muted" role="status" :aria-label="t('loading')" />
      <div v-if="status.error.value || action.error.value" role="alert" class="rounded-lg border border-danger p-4 text-danger">
        {{ status.error.value?.message || action.error.value?.message || t("failed") }}
      </div>
      <section v-if="status.data.value" class="space-y-4 rounded-xl border bg-surface p-5">
        <h2 class="text-lg font-semibold">{{ t("setup") }}</h2>
        <p v-if="!status.data.value.configured" class="text-sm">{{ t("credentials") }}</p>
        <p v-if="status.data.value.connection?.email">{{ t("connected") }}: {{ status.data.value.connection.email }}</p>
        <p v-if="status.data.value.connection?.drive_name">{{ t("drive") }}: {{ status.data.value.connection.drive_name }}</p>
        <div class="flex flex-wrap gap-3">
          <button class="rounded-md bg-primary px-4 py-2 text-white disabled:opacity-50" :disabled="busy || !status.data.value.configured" @click="action.mutate('connect')">{{ t("connect") }}</button>
          <button v-if="status.data.value.connection?.status === 'connected'" class="rounded-md border px-4 py-2 disabled:opacity-50" :disabled="busy" @click="action.mutate('drives')">{{ t("loadDrives") }}</button>
          <button class="rounded-md border px-4 py-2" :disabled="busy" @click="queryClient.invalidateQueries({ queryKey: keys })">{{ t("refresh") }}</button>
        </div>
        <form v-if="drives.length" class="flex flex-wrap items-end gap-3" @submit.prevent="action.mutate('save')">
          <label class="flex flex-col gap-2">{{ t("drive") }}
            <select v-model="selectedDrive" required class="rounded-md border bg-surface p-2">
              <option value="">{{ t("select") }}</option>
              <option v-for="drive in drives" :key="drive.id" :value="drive.id">{{ drive.name }}</option>
            </select>
          </label>
          <button class="rounded-md border px-4 py-2 disabled:opacity-50" :disabled="busy || !selectedDrive">{{ t("save") }}</button>
        </form>
        <div v-if="status.data.value.connection?.drive_id" class="space-y-3">
          <label class="flex items-start gap-2"><input v-model="confirmed" type="checkbox" class="mt-1" />{{ t("confirmProvision") }}</label>
          <button class="rounded-md border px-4 py-2 disabled:opacity-50" :disabled="busy || !confirmed" @click="action.mutate('provision')">{{ t("provision") }}</button>
        </div>
        <p class="rounded-md bg-surface-muted p-3 text-sm" role="status">{{ t("unavailable") }}</p>
        <button v-if="status.data.value.connection?.status === 'connected'" class="text-sm text-danger" :disabled="busy" @click="action.mutate('disconnect')">{{ t("disconnect") }}</button>
      </section>
      <section class="rounded-xl border bg-surface p-5">
        <h2 class="mb-3 text-lg font-semibold">{{ t("resources") }}</h2>
        <p v-if="!status.data.value?.resources.length">{{ t("empty") }}</p>
        <ul class="divide-y">
          <li v-for="resource in status.data.value?.resources" :key="resource.id" class="flex flex-wrap justify-between gap-3 py-3">
            <span class="capitalize">{{ resource.module }} — {{ resource.status === 'ready' ? t("ready") : t("pending") }}</span>
            <a v-if="resource.workbook_id" :href="'https://docs.google.com/spreadsheets/d/' + encodeURIComponent(resource.workbook_id) + '/edit'" target="_blank" rel="noopener noreferrer" class="text-primary underline">{{ t("open") }}</a>
          </li>
        </ul>
      </section>
      <section class="space-y-3 rounded-xl border bg-surface p-5">
        <div class="flex justify-between"><h2 class="text-lg font-semibold">{{ t("runs") }}</h2>
          <button class="rounded-md border px-3 py-2 disabled:opacity-50" :disabled="busy || !status.data.value?.connection?.enabled" @click="action.mutate('sync')">{{ t("sync") }}</button>
        </div>
        <p v-if="runs.error.value" role="alert">{{ runs.error.value.message }}</p>
        <p v-else-if="!runs.data.value?.length">{{ t("noRuns") }}</p>
        <div v-for="run in runs.data.value" :key="run.id" class="flex justify-between border-t py-2 text-sm"><span>{{ run.created_at }}</span><span>{{ run.status }}</span></div>
      </section>
      <section class="space-y-4 rounded-xl border bg-surface p-5">
        <h2 class="text-lg font-semibold">{{ t("review") }}</h2>
        <p v-if="reviews.error.value" role="alert">{{ reviews.error.value.message }}</p>
        <p v-else-if="!reviews.data.value?.length">{{ t("noReviews") }}</p>
        <details v-for="review in reviews.data.value" :key="review.id" class="rounded-md border p-3">
          <summary class="cursor-pointer">{{ review.module }} · {{ review.kind }}</summary>
          <div class="mt-3 grid gap-4 md:grid-cols-3">
            <div v-for="side in (['base', 'system', 'mirror'] as const)" :key="side">
              <h3 class="font-medium">{{ t(side) }}</h3>
              <dl v-for="(value, field) in review.payload[side]" :key="field" class="mt-2 text-sm"><dt class="text-text-muted">{{ field }}</dt><dd class="break-words">{{ value }}</dd></dl>
            </div>
          </div>
        </details>
      </section>
    </template>
  </main>
</template>
