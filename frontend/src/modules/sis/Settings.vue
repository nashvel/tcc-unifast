<script setup lang="ts">
import { computed, reactive, ref, watch } from "vue";
import { useQuery, useMutation, useQueryClient } from "@tanstack/vue-query";
import { useI18n } from "vue-i18n";
import { useOnline } from "@vueuse/core";
import { apiFetch } from "@/api/client";
import { authSession } from "@/auth/session";
import { sisKeys, sisMutation, type SisConnection, type PilotStudent, type IdentityReview, type Page } from "./api";
import { sisMessages } from "./messages";

const { t, d } = useI18n({ useScope: "local", messages: sisMessages });
const allowed = computed(() => ["admin", "developer"].includes(authSession.user?.role ?? ""));
const online = useOnline();
const client = useQueryClient();
const base = "/api/integrations/sis-sso";
const status = useQuery({ queryKey: [...sisKeys.all, "status"], queryFn: () => apiFetch<{ data: SisConnection }>(base).then(r => r.data), enabled: allowed, staleTime: 30_000, retry: false });
const form = reactive({ display_name: "", issuer: "", client_id: "", client_secret: "", student_id_claim: "student_id", confirm: false });
const mode = ref("disabled");
const confirmRollout = ref(false);
const sessionPolicy = ref("revoke");
const searchInput = ref("");
const search = ref("");
const pilotPage = ref(1);
const reviewPage = ref(1);
const configured = computed(() => allowed.value && !!status.data.value?.id);
const students = useQuery({ queryKey: computed(() => [...sisKeys.all, "pilots", search.value, pilotPage.value]), queryFn: () => apiFetch<Page<PilotStudent>>(`${base}/pilots?search=${encodeURIComponent(search.value)}&page=${pilotPage.value}`), enabled: configured, retry: false });
const reviews = useQuery({ queryKey: computed(() => [...sisKeys.all, "reviews", reviewPage.value]), queryFn: () => apiFetch<Page<IdentityReview>>(`${base}/reviews?page=${reviewPage.value}`), enabled: configured, retry: false });
const invitation = ref<{ url: string; expires_at: string } | null>(null);
const notes = reactive<Record<number, string>>({});
const decisionsConfirmed = reactive<Record<number, boolean>>({});
const notice = ref("");
watch(status.data, value => {
  if (!value) return;
  form.display_name = value.display_name ?? "";
  form.issuer = value.issuer ?? "";
  form.client_id = value.client_id ?? "";
  form.student_id_claim = value.student_id_claim ?? "student_id";
  mode.value = ["pilot", "all_students"].includes(value.state) ? value.state : "disabled";
}, { immediate: true });
const action = useMutation({
  mutationFn: async ({ path, method, body }: { path: string; method: string; body?: object }) => sisMutation<{ data?: { url: string; expires_at: string } }>(base + path, method, body),
  onSuccess: async (response) => {
    notice.value = t("saved");
    if (response.data?.url) invitation.value = response.data;
    form.client_secret = "";
    form.confirm = false;
    confirmRollout.value = false;
    await Promise.all([client.invalidateQueries({ queryKey: sisKeys.all }), client.invalidateQueries({ queryKey: sisKeys.capabilities })]);
  },
});
const busy = computed(() => action.isPending.value || !online.value);
function save() {
  const { client_secret, ...rest } = form;
  action.mutate({ path: "", method: "PUT", body: { ...rest, ...(client_secret ? { client_secret } : {}) } });
}
function rollout() {
  action.mutate({ path: "/rollout", method: "PUT", body: { mode: mode.value, confirm: confirmRollout.value, session_policy: sessionPolicy.value } });
}
function decide(review: IdentityReview, decision: string) {
  action.mutate({ path: `/reviews/${review.id}/decide`, method: "POST", body: { decision, notes: notes[review.id], confirm: decisionsConfirmed[review.id] } });
}
async function copy(value: string) {
  try { await navigator.clipboard.writeText(value); notice.value = t("copied"); }
  catch { notice.value = t("failed"); }
}
const date = (value?: string | null) => value ? d(new Date(value), { dateStyle: "medium", timeStyle: "short" }) : t("none");
const fields = ["student_id", "name", "email"] as const;
const fieldLabels = { student_id: "studentId", name: "name", email: "email" };
</script>

<template>
  <div class="mx-auto max-w-5xl space-y-6">
    <header><h1 class="text-2xl font-semibold">{{ t("title") }}</h1><p class="mt-2 text-sm text-text-muted">{{ t("description") }}</p></header>
    <p v-if="!allowed" role="alert">{{ t("restricted") }}</p>
    <template v-else>
      <p v-if="!online" role="status" class="rounded-lg border p-4">{{ t("offline") }}</p>
      <p v-if="status.isError.value" role="alert" class="text-danger">{{ t("failed") }}</p>
      <div v-if="status.isPending.value" :aria-label="t('loading')" aria-busy="true" class="space-y-3 rounded-xl border p-6"><div v-for="i in 4" :key="i" class="h-10 animate-pulse rounded bg-surface-muted" /></div>
      <template v-else-if="status.data.value">
        <p v-if="action.isError.value" role="alert" class="rounded-lg border border-danger p-3 text-sm text-danger">{{ t("failed") }}</p>
        <p v-if="notice" role="status" class="text-sm">{{ notice }}</p>
        <section class="rounded-xl border bg-surface p-5">
          <h2 class="font-semibold">{{ t("state") }}: {{ t(`states.${status.data.value.state}`) }}</h2>
          <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
            <div><dt class="text-text-muted">{{ t("validated") }}</dt><dd>{{ date(status.data.value.validated_at) }}</dd></div>
            <div><dt class="text-text-muted">{{ t("keys") }}</dt><dd>{{ date(status.data.value.jwks_refreshed_at) }}</dd></div>
            <div><dt class="text-text-muted">{{ t("pending") }}</dt><dd>{{ status.data.value.pending_reviews }}</dd></div>
            <div><dt class="text-text-muted">{{ t("pilotSuccess") }}</dt><dd>{{ status.data.value.pilot_successes ?? 0 }}</dd></div>
            <div><dt class="text-text-muted">{{ t("pilotFailure") }}</dt><dd>{{ status.data.value.pilot_failures ?? 0 }}</dd></div>
          </dl>
        </section>
        <form class="space-y-4 rounded-xl border bg-surface p-5" @submit.prevent="save">
          <h2 class="font-semibold">{{ t("configuration") }}</h2>
          <div class="grid gap-4 sm:grid-cols-2">
            <label class="space-y-1 text-sm"><span>{{ t("displayName") }}</span><input v-model="form.display_name" required maxlength="100" class="w-full rounded-md border bg-surface p-2" /></label>
            <label class="space-y-1 text-sm"><span>{{ t("issuer") }}</span><input v-model="form.issuer" required type="url" maxlength="500" placeholder="https://sis.example.edu" class="w-full rounded-md border bg-surface p-2" /></label>
            <label class="space-y-1 text-sm"><span>{{ t("clientId") }}</span><input v-model="form.client_id" required maxlength="255" autocomplete="off" class="w-full rounded-md border bg-surface p-2" /></label>
            <label class="space-y-1 text-sm"><span>{{ t("secret") }}</span><input v-model="form.client_secret" :required="!status.data.value.has_client_secret" type="password" maxlength="4096" autocomplete="new-password" class="w-full rounded-md border bg-surface p-2" /><span class="block text-xs text-text-muted">{{ t("secretHint") }}</span></label>
            <label class="space-y-1 text-sm sm:col-span-2"><span>{{ t("claim") }}</span><input v-model="form.student_id_claim" required maxlength="255" class="w-full rounded-md border bg-surface p-2" /></label>
          </div>
          <div class="space-y-2 text-sm"><p>{{ t("callback") }}</p><div class="flex flex-wrap items-center gap-3"><code class="break-all rounded bg-surface-muted p-2">{{ status.data.value.callback_uri }}</code><button type="button" class="rounded border px-3 py-2" @click="copy(status.data.value.callback_uri)">{{ t("copy") }}</button></div></div>
          <label v-if="status.data.value.id" class="flex items-start gap-2 text-sm"><input v-model="form.confirm" type="checkbox" class="mt-1" />{{ t("confirmReplace") }}</label>
          <div class="flex flex-wrap gap-3"><button :disabled="busy" class="rounded-lg bg-primary px-4 py-2 text-sm text-white disabled:opacity-50">{{ t("save") }}</button><button type="button" :disabled="busy || !configured" class="rounded-lg border px-4 py-2 text-sm disabled:opacity-50" @click="action.mutate({ path: '/validate', method: 'POST' })">{{ t("validate") }}</button></div>
        </form>
        <form v-if="configured" class="space-y-4 rounded-xl border bg-surface p-5" @submit.prevent="rollout">
          <h2 class="font-semibold">{{ t("rollout") }}</h2>
          <label class="block space-y-1 text-sm"><span>{{ t("mode") }}</span><select v-model="mode" class="block w-full rounded border bg-surface p-2"><option value="disabled">{{ t("disabled") }}</option><option value="pilot">{{ t("pilot") }}</option><option value="all_students">{{ t("allStudents") }}</option></select></label>
          <label v-if="mode === 'disabled'" class="block space-y-1 text-sm"><span>{{ t("sessionPolicy") }}</span><select v-model="sessionPolicy" class="block w-full rounded border bg-surface p-2"><option value="revoke">{{ t("revoke") }}</option><option value="keep">{{ t("keep") }}</option></select></label>
          <label class="flex items-start gap-2 text-sm"><input v-model="confirmRollout" type="checkbox" required class="mt-1" />{{ t("confirmRollout") }}</label>
          <p class="text-xs text-text-muted">{{ t("rollback") }}</p>
          <button :disabled="busy || !confirmRollout" class="rounded-lg bg-primary px-4 py-2 text-sm text-white disabled:opacity-50">{{ t("apply") }}</button>
        </form>
        <section v-if="configured" class="space-y-4 rounded-xl border bg-surface p-5">
          <h2 class="font-semibold">{{ t("students") }}</h2>
          <form class="flex gap-2" @submit.prevent="search = searchInput; pilotPage = 1"><input v-model="searchInput" :aria-label="t('search')" :placeholder="t('search')" maxlength="100" class="min-w-0 flex-1 rounded border bg-surface p-2 text-sm" /><button class="rounded border px-3 py-2 text-sm">{{ t("searchAction") }}</button></form>
          <div v-if="students.isPending.value" aria-busy="true" class="h-24 animate-pulse rounded bg-surface-muted" />
          <p v-else-if="students.isError.value" role="alert">{{ t("failed") }}</p>
          <p v-else-if="!students.data.value?.data.length" class="text-sm text-text-muted">{{ t("emptyStudents") }}</p>
          <ul v-else class="divide-y">
            <li v-for="student in students.data.value?.data" :key="student.id" class="flex flex-wrap items-center justify-between gap-3 py-3">
              <div><p class="text-sm font-medium">{{ student.name }}</p><p class="text-xs text-text-muted">{{ student.student_id }} · {{ student.email }}</p><p v-if="student.pilot_id" class="text-xs text-text-muted">{{ t("pilotSuccess") }}: {{ student.successes ?? 0 }} · {{ t("pilotFailure") }}: {{ student.failures ?? 0 }}</p></div>
              <div class="flex flex-wrap gap-2">
                <button v-if="!student.pilot_id" :disabled="busy" class="rounded border px-3 py-2 text-xs disabled:opacity-50" @click="action.mutate({ path: '/pilots', method: 'POST', body: { user_id: student.id } })">{{ t("select") }}</button>
                <template v-else><button :disabled="busy || status.data.value.state !== 'pilot'" class="rounded border px-3 py-2 text-xs disabled:opacity-50" @click="action.mutate({ path: `/pilots/${student.id}/invite`, method: 'POST' })">{{ t("invite") }}</button><button :disabled="busy" class="rounded border px-3 py-2 text-xs disabled:opacity-50" @click="action.mutate({ path: `/pilots/${student.id}`, method: 'DELETE' })">{{ t("remove") }}</button></template>
              </div>
            </li>
          </ul>
          <div v-if="students.data.value" class="flex items-center justify-between gap-2 text-xs"><button :disabled="pilotPage <= 1" class="rounded border p-2 disabled:opacity-40" @click="pilotPage--">{{ t("previous") }}</button><span>{{ t("page", { current: pilotPage, last: students.data.value.meta.last_page }) }}</span><button :disabled="pilotPage >= students.data.value.meta.last_page" class="rounded border p-2 disabled:opacity-40" @click="pilotPage++">{{ t("next") }}</button></div>
          <div v-if="invitation" class="space-y-2 rounded-lg border p-4"><p class="text-sm font-medium">{{ t("invitation") }}</p><input :value="invitation.url" readonly :aria-label="t('invitation')" class="w-full rounded border bg-surface p-2 text-sm" /><p class="text-xs">{{ t("expires", { date: date(invitation.expires_at) }) }}</p><button class="rounded border px-3 py-2 text-sm" @click="copy(invitation.url)">{{ t("copy") }}</button></div>
        </section>
        <section v-if="configured" class="space-y-4 rounded-xl border bg-surface p-5">
          <h2 class="font-semibold">{{ t("reviews") }}</h2>
          <div v-if="reviews.isPending.value" aria-busy="true" class="h-24 animate-pulse rounded bg-surface-muted" />
          <p v-else-if="reviews.isError.value" role="alert">{{ t("failed") }}</p>
          <p v-else-if="!reviews.data.value?.data.length" class="text-sm text-text-muted">{{ t("emptyReviews") }}</p>
          <article v-for="review in reviews.data.value?.data" :key="review.id" class="space-y-3 rounded-lg border p-4">
            <h3 class="font-medium">{{ t(`reasons.${review.reason}`) }} · {{ t(`reviewStates.${review.status}`) }}</h3>
            <p class="text-xs text-text-muted">{{ date(review.created_at) }}</p>
            <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr><th class="p-2">{{ t("name") }}</th><th class="p-2">{{ t("local") }}</th><th class="p-2">{{ t("provider") }}</th></tr></thead><tbody><tr v-for="field in fields" :key="field" class="border-t"><th scope="row" class="p-2 font-normal text-text-muted">{{ t(fieldLabels[field]) }}</th><td class="break-all p-2">{{ review.local_claims[field] }}</td><td class="break-all p-2">{{ review.provider_claims[field] }}</td></tr></tbody></table></div>
            <template v-if="review.status === 'pending'">
              <label class="block text-sm">{{ t("notes") }}<textarea v-model="notes[review.id]" required maxlength="2000" class="mt-1 block w-full rounded border bg-surface p-2" /></label>
              <label class="flex items-start gap-2 text-sm"><input v-model="decisionsConfirmed[review.id]" type="checkbox" class="mt-1" />{{ t("confirmReview") }}</label>
              <div class="flex gap-2"><button v-if="review.reason === 'identity_data_changed'" :disabled="busy || !decisionsConfirmed[review.id] || !notes[review.id]?.trim()" class="rounded-lg bg-primary px-4 py-2 text-sm text-white disabled:opacity-50" @click="decide(review, 'approve')">{{ t("approve") }}</button><button :disabled="busy || !decisionsConfirmed[review.id] || !notes[review.id]?.trim()" class="rounded-lg border px-4 py-2 text-sm disabled:opacity-50" @click="decide(review, 'reject')">{{ t("reject") }}</button></div>
            </template>
            <p v-else class="text-sm text-text-muted">{{ date(review.reviewed_at) }} · {{ review.decision_notes }}</p>
          </article>
          <div v-if="reviews.data.value" class="flex items-center justify-between gap-2 text-xs"><button :disabled="reviewPage <= 1" class="rounded border p-2 disabled:opacity-40" @click="reviewPage--">{{ t("previous") }}</button><span>{{ t("page", { current: reviewPage, last: reviews.data.value.meta.last_page }) }}</span><button :disabled="reviewPage >= reviews.data.value.meta.last_page" class="rounded border p-2 disabled:opacity-40" @click="reviewPage++">{{ t("next") }}</button></div>
        </section>
      </template>
    </template>
  </div>
</template>
