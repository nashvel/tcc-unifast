<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useQuery, useQueryClient } from "@tanstack/vue-query";
import { IconArrowLeft, IconSearch } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import DataTable from "@/components/tables/DataTable.vue";
import TablePagination from "@/components/tables/TablePagination.vue";
import TableStates from "@/components/ui/TableStates.vue";
import { apiFetch, apiUrl, ApiError } from "@/api/client";
import { getAuthToken } from "@/auth/session";
import { toast } from "@/composables/useToast";
import { useOnline } from "@/composables/useOnline";
import { queryKeys } from "@/api/queryKeys";

type FaceReview = {
  id: number;
  status: string;
  grantee_id: number;
  student_name: string | null;
  student_id: string | null;
  student_number: string | null;
  email: string | null;
  batch_name: string | null;
  onboarding_face_distance: number | null;
  face_zone: string | null;
  pass_max?: number;
  review_max?: number;
  id_reference_face_url: string | null;
  onboarding_selfie_url: string | null;
  liveness_challenge_1_url: string | null;
  liveness_challenge_2_url: string | null;
  liveness_challenge_labels: string[];
  account_status: string | null;
  updated_at: string | null;
};

type ListResponse = {
  data: FaceReview[];
  meta: { current_page: number; last_page: number; per_page: number; total: number };
};

const route = useRoute();
const router = useRouter();
const queryClient = useQueryClient();
const { online } = useOnline();

const search = ref("");
const debouncedSearch = ref("");
const page = ref(1);
const busy = ref("");
const rejectReason = ref("");
const confirmAction = ref<"approve" | "reject" | null>(null);

const selectedId = computed(() => {
  const raw = route.params.id;
  const value = Array.isArray(raw) ? raw[0] : raw;
  const n = Number(value);
  return Number.isFinite(n) && n > 0 ? n : null;
});

let searchTimer: ReturnType<typeof setTimeout> | undefined;
watch(search, (value) => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    debouncedSearch.value = value;
    page.value = 1;
  }, 250);
});

const listQuery = useQuery({
  queryKey: computed(() => [...queryKeys.faceReviews, { page: page.value, search: debouncedSearch.value }]),
  queryFn: () => {
    const params = new URLSearchParams({
      page: String(page.value),
      per_page: "20",
    });
    if (debouncedSearch.value.trim()) params.set("search", debouncedSearch.value.trim());
    return apiFetch<ListResponse>(`/api/face-reviews?${params}`);
  },
});

const detailQuery = useQuery({
  queryKey: computed(() => [...queryKeys.faceReviews, "detail", selectedId.value]),
  queryFn: () => apiFetch<{ data: FaceReview }>(`/api/face-reviews/${selectedId.value}`),
  enabled: computed(() => selectedId.value !== null),
});

const rows = computed(() => listQuery.data.value?.data ?? []);
const meta = computed(() => listQuery.data.value?.meta ?? null);
const detail = computed(() => detailQuery.data.value?.data ?? null);

function zoneLabel(zone: string | null | undefined): string {
  if (zone === "uncertain") return "Uncertain";
  if (zone === "confident") return "Confident";
  if (zone === "mismatch") return "Mismatch";
  return zone || "—";
}

function authPhotoUrl(path: string | null): string | null {
  if (!path) return null;
  const token = getAuthToken();
  const url = apiUrl(path);
  return token ? `${url}${url.includes("?") ? "&" : "?"}token=${encodeURIComponent(token)}` : url;
}

/** Prefer Authorization header via blob fetch for private photos. */
const idRefSrc = ref<string | null>(null);
const selfieSrc = ref<string | null>(null);
const challenge1Src = ref<string | null>(null);
const challenge2Src = ref<string | null>(null);

const challengeLabel = (index: 0 | 1): string => {
  const raw = detail.value?.liveness_challenge_labels?.[index];
  if (raw === "blink") return "Blink";
  if (raw === "turn_left") return "Turn left";
  if (raw === "turn_right") return "Turn right";
  return `Challenge ${index + 1}`;
};

watch(
  () =>
    [
      detail.value?.id_reference_face_url,
      detail.value?.onboarding_selfie_url,
      detail.value?.liveness_challenge_1_url,
      detail.value?.liveness_challenge_2_url,
      selectedId.value,
    ] as const,
  async ([idUrl, selfieUrl, c1Url, c2Url]) => {
    revokePhoto(idRefSrc);
    revokePhoto(selfieSrc);
    revokePhoto(challenge1Src);
    revokePhoto(challenge2Src);
    if (!idUrl && !selfieUrl && !c1Url && !c2Url) return;
    idRefSrc.value = await loadAuthImage(idUrl);
    selfieSrc.value = await loadAuthImage(selfieUrl);
    challenge1Src.value = await loadAuthImage(c1Url);
    challenge2Src.value = await loadAuthImage(c2Url);
  },
  { immediate: true },
);

function revokePhoto(target: typeof idRefSrc) {
  if (target.value?.startsWith("blob:")) URL.revokeObjectURL(target.value);
  target.value = null;
}

async function loadAuthImage(path: string | null | undefined): Promise<string | null> {
  if (!path) return null;
  const token = getAuthToken();
  if (!token) return authPhotoUrl(path);
  try {
    const response = await fetch(apiUrl(path), {
      headers: { Authorization: `Bearer ${token}`, Accept: "image/*" },
    });
    if (!response.ok) return null;
    const blob = await response.blob();
    return URL.createObjectURL(blob);
  } catch {
    return null;
  }
}

function requestDecide(action: "approve" | "reject") {
  if (!selectedId.value || busy.value) return;
  confirmAction.value = action;
}

async function decide(action: "approve" | "reject") {
  if (!selectedId.value || busy.value) return;
  busy.value = action;
  confirmAction.value = null;
  try {
    await apiFetch(`/api/face-reviews/${selectedId.value}/${action}`, {
      method: "POST",
      body: action === "reject" ? JSON.stringify({ reason: rejectReason.value || null }) : undefined,
    });
    toast.success(
      action === "approve" ? "Approved — account activated" : "Rejected — account blocked",
    );
    await queryClient.invalidateQueries({ queryKey: queryKeys.faceReviews });
    rejectReason.value = "";
    await router.push("/app/face-reviews");
  } catch (error) {
    toast.error(error instanceof ApiError ? error.message : `Unable to ${action} face review.`);
  } finally {
    busy.value = "";
  }
}
</script>

<template>
  <div>
    <template v-if="selectedId">
      <PageHeader
        title="Face match review"
        description="Compare the School ID reference, onboarding selfie, and two liveness challenge stills, then approve or block."
      >
        <template #actions>
          <RouterLink
            to="/app/face-reviews"
            class="inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-xs"
          >
            <IconArrowLeft :size="14" /> Back to queue
          </RouterLink>
        </template>
      </PageHeader>

      <div
        v-if="detailQuery.isLoading.value"
        class="rounded-lg border bg-surface p-8 text-center text-sm text-text-muted"
      >
        Loading review…
      </div>
      <div
        v-else-if="detailQuery.isError.value"
        class="rounded-lg border border-danger/30 bg-danger-soft p-4 text-sm text-danger"
      >
        <p>{{ detailQuery.error.value instanceof Error ? detailQuery.error.value.message : "Unable to load review." }}</p>
        <button
          class="mt-3 inline-flex h-8 items-center rounded-md border px-3 text-xs"
          type="button"
          @click="detailQuery.refetch()"
        >
          Retry
        </button>
      </div>
      <template v-else-if="detail">
        <section class="mb-4 rounded-lg border bg-surface p-4 text-sm">
          <p class="font-semibold">{{ detail.student_name || "—" }}</p>
          <p class="mt-1 text-xs text-text-muted">
            {{ detail.student_id }} · {{ detail.email || "—" }} ·
            {{ detail.batch_name || "No batch" }}
          </p>
          <p class="mt-2 flex flex-wrap gap-3 text-xs">
            <span class="rounded-md bg-surface-muted px-2 py-1 font-medium">
              Distance
              {{
                detail.onboarding_face_distance != null
                  ? Number(detail.onboarding_face_distance).toFixed(4)
                  : "—"
              }}
            </span>
            <span class="rounded-md bg-warning-soft px-2 py-1 font-medium text-warning">
              Zone: {{ zoneLabel(detail.face_zone) }}
            </span>
            <span class="text-text-muted">
              pass &lt; {{ detail.pass_max ?? 0.45 }} · review &lt; {{ detail.review_max ?? 0.6 }}
            </span>
          </p>
        </section>

        <div class="mb-4 grid gap-4 md:grid-cols-2">
          <figure class="rounded-lg border bg-surface p-3">
            <figcaption class="mb-2 text-xs font-semibold">ID reference face</figcaption>
            <img
              v-if="idRefSrc"
              :src="idRefSrc"
              alt="School ID reference face"
              class="mx-auto max-h-80 rounded-md object-contain"
            />
            <p v-else class="py-10 text-center text-xs text-text-muted">Reference photo unavailable</p>
          </figure>
          <figure class="rounded-lg border bg-surface p-3">
            <figcaption class="mb-2 text-xs font-semibold">Onboarding selfie</figcaption>
            <img
              v-if="selfieSrc"
              :src="selfieSrc"
              alt="Onboarding liveness selfie"
              class="mx-auto max-h-80 rounded-md object-contain"
            />
            <p v-else class="py-10 text-center text-xs text-text-muted">Selfie unavailable</p>
          </figure>
          <figure class="rounded-lg border bg-surface p-3">
            <figcaption class="mb-2 text-xs font-semibold">
              Challenge still 1 — {{ challengeLabel(0) }}
            </figcaption>
            <img
              v-if="challenge1Src"
              :src="challenge1Src"
              :alt="`Liveness challenge: ${challengeLabel(0)}`"
              class="mx-auto max-h-80 rounded-md object-contain"
            />
            <p v-else class="py-10 text-center text-xs text-text-muted">Challenge still unavailable</p>
          </figure>
          <figure class="rounded-lg border bg-surface p-3">
            <figcaption class="mb-2 text-xs font-semibold">
              Challenge still 2 — {{ challengeLabel(1) }}
            </figcaption>
            <img
              v-if="challenge2Src"
              :src="challenge2Src"
              :alt="`Liveness challenge: ${challengeLabel(1)}`"
              class="mx-auto max-h-80 rounded-md object-contain"
            />
            <p v-else class="py-10 text-center text-xs text-text-muted">Challenge still unavailable</p>
          </figure>
        </div>

        <section class="rounded-lg border bg-surface p-4">
          <label class="mb-3 block text-xs font-medium"
            >Reject reason (optional)
            <input
              v-model="rejectReason"
              class="mt-1.5 h-9 w-full max-w-xl rounded-md border px-3 text-sm"
              placeholder="Notes for audit log"
              :disabled="Boolean(busy)"
            />
          </label>

          <div
            v-if="confirmAction"
            class="mb-3 rounded-md border border-amber-400/40 bg-amber-500/10 px-3 py-3 text-xs"
            role="alertdialog"
          >
            <p class="font-semibold">
              {{
                confirmAction === "approve"
                  ? "Confirm approve & activate this account?"
                  : "Confirm reject & block this account?"
              }}
            </p>
            <div class="mt-2 flex flex-wrap gap-2">
              <button
                class="inline-flex h-8 items-center rounded-md bg-primary px-3 text-xs font-medium text-white disabled:opacity-60"
                type="button"
                :disabled="Boolean(busy)"
                @click="decide(confirmAction)"
              >
                {{ busy ? "Working…" : "Yes, confirm" }}
              </button>
              <button
                class="inline-flex h-8 items-center rounded-md border px-3 text-xs font-medium disabled:opacity-60"
                type="button"
                :disabled="Boolean(busy)"
                @click="confirmAction = null"
              >
                Cancel
              </button>
            </div>
          </div>

          <div class="flex flex-wrap gap-2">
            <button
              class="inline-flex h-9 items-center rounded-md bg-primary px-4 text-xs font-medium text-white disabled:opacity-60"
              type="button"
              :disabled="Boolean(busy) || confirmAction !== null"
              @click="requestDecide('approve')"
            >
              {{ busy === "approve" ? "Approving…" : "Approve & activate" }}
            </button>
            <button
              class="inline-flex h-9 items-center rounded-md border border-danger/40 bg-danger-soft px-4 text-xs font-medium text-danger disabled:opacity-60"
              type="button"
              :disabled="Boolean(busy) || confirmAction !== null"
              @click="requestDecide('reject')"
            >
              {{ busy === "reject" ? "Blocking…" : "Reject & block" }}
            </button>
          </div>
        </section>
      </template>
    </template>

    <template v-else>
      <PageHeader
        title="Face match reviews"
        description="Uncertain onboarding face matches waiting for staff side-by-side review (uncertain ≠ blocked)."
      />

      <div class="relative mb-3 max-w-xl">
        <IconSearch :size="14" class="absolute left-3 top-1/2 -translate-y-1/2 text-text-soft" />
        <input
          v-model="search"
          class="h-9 w-full rounded-md border pl-9 pr-3 text-xs"
          placeholder="Search name, student ID, or email"
        />
      </div>

      <DataTable :headings="['Grantee', 'Student ID', 'Batch', 'Distance / zone', 'Updated', '']">
        <TableStates
          v-if="
            listQuery.isLoading.value ||
            listQuery.isError.value ||
            (!online && !rows.length) ||
            (!listQuery.isLoading.value && !rows.length)
          "
          :col-span="6"
          :is-loading="listQuery.isLoading.value"
          :is-fetching="listQuery.isFetching.value"
          :is-error="listQuery.isError.value"
          :error="listQuery.error.value"
          :is-offline="!online && !rows.length"
          :is-empty="!listQuery.isLoading.value && !listQuery.isError.value && !rows.length"
          empty-title="No pending face reviews"
          empty-hint="Uncertain liveness matches will appear here for staff decision."
          @retry="listQuery.refetch()"
        />
        <template v-else>
          <tr v-for="row in rows" :key="row.id">
            <td class="px-3 py-3 font-medium">{{ row.student_name || "—" }}</td>
            <td class="px-3 py-3 font-mono">{{ row.student_id || "—" }}</td>
            <td class="px-3 py-3 text-text-muted">{{ row.batch_name || "—" }}</td>
            <td class="px-3 py-3 tabular-nums">
              {{
                row.onboarding_face_distance != null
                  ? Number(row.onboarding_face_distance).toFixed(4)
                  : "—"
              }}
              <span class="ml-1 text-xs text-text-muted">({{ zoneLabel(row.face_zone) }})</span>
            </td>
            <td class="px-3 py-3 text-text-muted">
              {{ row.updated_at ? new Date(row.updated_at).toLocaleString() : "—" }}
            </td>
            <td class="px-3 py-3 text-right">
              <RouterLink :to="`/app/face-reviews/${row.id}`" class="text-primary">Review</RouterLink>
            </td>
          </tr>
        </template>
        <template v-if="meta" #footer>
          <TablePagination
            :meta="meta"
            :busy="listQuery.isFetching.value"
            @update:page="page = $event"
          />
        </template>
      </DataTable>
    </template>
  </div>
</template>
