<script setup lang="ts">
import { computed, ref, watch, onMounted, onBeforeUnmount } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useQuery, useQueryClient } from "@tanstack/vue-query";
import { IconArrowLeft, IconSearch, IconId, IconEye, IconX } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import DataTable from "@/components/tables/DataTable.vue";
import TablePagination from "@/components/tables/TablePagination.vue";
import TableStates from "@/components/ui/TableStates.vue";
import AppDialog from "@/components/dialogs/AppDialog.vue";
import { apiFetch, apiFetchBlob, apiUrl, ApiError } from "@/api/client";
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
  id_front_frame_url?: string | null;
  id_back_frame_url?: string | null;
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

const isConfirmOpen = computed({
  get: () => confirmAction.value !== null,
  set: (open: boolean) => {
    if (!open) confirmAction.value = null;
  },
});

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
  return apiUrl(path);
}

/** Prefer cookie credentials via blob fetch for private photos. */
const idRefSrc = ref<string | null>(null);
const idFrontSrc = ref<string | null>(null);
const idBackSrc = ref<string | null>(null);
const selfieSrc = ref<string | null>(null);
const challenge1Src = ref<string | null>(null);
const challenge2Src = ref<string | null>(null);

const idSide = ref<"front" | "back">("front");
const zoomImage = ref<{ src: string; title: string } | null>(null);

function openZoom(src: string | null, title: string) {
  if (src) {
    zoomImage.value = { src, title };
  }
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === "Escape" && zoomImage.value) {
    zoomImage.value = null;
  }
}

onMounted(() => window.addEventListener("keydown", onKeydown));
onBeforeUnmount(() => window.removeEventListener("keydown", onKeydown));

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
      detail.value?.id_front_frame_url,
      detail.value?.id_back_frame_url,
      detail.value?.onboarding_selfie_url,
      detail.value?.liveness_challenge_1_url,
      detail.value?.liveness_challenge_2_url,
      selectedId.value,
    ] as const,
  async ([idUrl, frontUrl, backUrl, selfieUrl, c1Url, c2Url]) => {
    revokePhoto(idRefSrc);
    revokePhoto(idFrontSrc);
    revokePhoto(idBackSrc);
    revokePhoto(selfieSrc);
    revokePhoto(challenge1Src);
    revokePhoto(challenge2Src);
    if (!idUrl && !frontUrl && !backUrl && !selfieUrl && !c1Url && !c2Url) return;
    idRefSrc.value = await loadAuthImage(idUrl);
    idFrontSrc.value = await loadAuthImage(frontUrl);
    idBackSrc.value = await loadAuthImage(backUrl);
    selfieSrc.value = await loadAuthImage(selfieUrl);
    challenge1Src.value = await loadAuthImage(c1Url);
    challenge2Src.value = await loadAuthImage(c2Url);
  },
  { immediate: true },
);

function revokePhoto(target: { value: string | null }) {
  if (target.value?.startsWith("blob:")) URL.revokeObjectURL(target.value);
  target.value = null;
}

async function loadAuthImage(path: string | null | undefined): Promise<string | null> {
  if (!path) return null;
  try {
    const response = await apiFetchBlob(path, {
      headers: { Accept: "image/*" },
    });
    if (!response.ok) return null;
    const blob = await response.blob();
    return URL.createObjectURL(blob);
  } catch {
    return authPhotoUrl(path);
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
      action === "approve"
        ? "Approved — account activated"
        : "Rejected — retry link sent to student",
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
        description="Compare the School ID reference, onboarding selfie, and two liveness challenge stills, then approve or request a retry."
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

        <!-- Full Physical School ID Card -->
        <section class="mb-4 rounded-lg border bg-surface p-4">
          <div class="mb-3 flex flex-wrap items-center justify-between gap-2 border-b pb-3">
            <div class="flex items-center gap-2">
              <span class="grid h-8 w-8 place-items-center rounded-lg bg-primary/10 text-primary">
                <IconId :size="18" />
              </span>
              <div>
                <h3 class="text-sm font-semibold text-text">Physical School ID Card</h3>
                <p class="text-xs text-text-muted">
                  Inspect the student's full name, student ID number, and photo printed on the card.
                </p>
              </div>
            </div>

            <!-- Front / Back Toggle & Enlarge -->
            <div class="flex items-center gap-2">
              <div v-if="idBackSrc" class="inline-flex rounded-lg border bg-surface-muted p-0.5 text-xs font-medium">
                <button
                  type="button"
                  class="rounded-md px-3 py-1 transition"
                  :class="idSide === 'front' ? 'bg-surface text-text shadow-sm' : 'text-text-muted hover:text-text'"
                  @click="idSide = 'front'"
                >
                  Front
                </button>
                <button
                  type="button"
                  class="rounded-md px-3 py-1 transition"
                  :class="idSide === 'back' ? 'bg-surface text-text shadow-sm' : 'text-text-muted hover:text-text'"
                  @click="idSide = 'back'"
                >
                  Back
                </button>
              </div>
              <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-md border px-3 py-1 text-xs font-medium text-text hover:bg-surface-muted transition"
                @click="openZoom(idSide === 'front' ? (idFrontSrc || idRefSrc) : (idBackSrc || idFrontSrc), `School ID (${idSide})`)"
              >
                <IconEye :size="14" /> Enlarge
              </button>
            </div>
          </div>

          <!-- Card image container -->
          <div class="relative flex min-h-64 items-center justify-center rounded-lg bg-surface-muted/40 p-4">
            <div
              v-if="idSide === 'front' ? (idFrontSrc || idRefSrc) : idBackSrc"
              class="group relative max-h-96 cursor-zoom-in overflow-hidden rounded-lg border shadow-sm transition hover:shadow-md"
              @click="openZoom(idSide === 'front' ? (idFrontSrc || idRefSrc) : idBackSrc, `School ID (${idSide})`)"
            >
              <img
                :src="(idSide === 'front' ? (idFrontSrc || idRefSrc) : idBackSrc)!"
                :alt="`School ID ${idSide}`"
                class="max-h-96 w-auto object-contain transition group-hover:scale-[1.01]"
              />
              <div class="absolute inset-0 flex items-center justify-center bg-black/20 opacity-0 backdrop-blur-[2px] transition group-hover:opacity-100">
                <span class="inline-flex items-center gap-1.5 rounded-md bg-black/75 px-3 py-1.5 text-xs font-medium text-white shadow-lg">
                  <IconEye :size="14" /> Click to view full size
                </span>
              </div>
            </div>
            <p v-else class="py-12 text-xs text-text-muted">Full ID card image unavailable</p>
          </div>
        </section>

        <!-- Face Comparison and Liveness Challenge Stills -->
        <div class="mb-4 grid gap-4 md:grid-cols-3">
          <!-- Face Match: ID Photo vs Live Selfie -->
          <figure class="rounded-lg border bg-surface p-3">
            <figcaption class="mb-2 flex items-center justify-between text-xs font-semibold">
              <span>Face Comparison</span>
              <span class="rounded bg-surface-muted px-1.5 py-0.5 text-[10px] font-normal text-text-muted">
                Dist: {{ detail.onboarding_face_distance != null ? Number(detail.onboarding_face_distance).toFixed(4) : "—" }}
              </span>
            </figcaption>
            <div class="grid grid-cols-2 gap-2">
              <div class="overflow-hidden rounded border bg-surface-muted/30 p-1 text-center">
                <img
                  v-if="idRefSrc"
                  :src="idRefSrc"
                  alt="Cropped ID reference face"
                  class="mx-auto h-36 w-full cursor-zoom-in rounded object-contain transition hover:scale-105"
                  @click="openZoom(idRefSrc, 'ID Reference Face (Cropped)')"
                />
                <p v-else class="py-8 text-[11px] text-text-muted">—</p>
                <p class="mt-1 text-[10px] font-medium text-text-muted">ID Photo</p>
              </div>
              <div class="overflow-hidden rounded border bg-surface-muted/30 p-1 text-center">
                <img
                  v-if="selfieSrc"
                  :src="selfieSrc"
                  alt="Onboarding liveness selfie"
                  class="mx-auto h-36 w-full cursor-zoom-in rounded object-contain transition hover:scale-105"
                  @click="openZoom(selfieSrc, 'Live Onboarding Selfie')"
                />
                <p v-else class="py-8 text-[11px] text-text-muted">—</p>
                <p class="mt-1 text-[10px] font-medium text-text-muted">Live Selfie</p>
              </div>
            </div>
          </figure>

          <!-- Challenge Still 1 -->
          <figure class="rounded-lg border bg-surface p-3">
            <figcaption class="mb-2 text-xs font-semibold">
              Challenge 1 — {{ challengeLabel(0) }}
            </figcaption>
            <div class="overflow-hidden rounded border bg-surface-muted/30 p-1 text-center">
              <img
                v-if="challenge1Src"
                :src="challenge1Src"
                :alt="`Liveness challenge: ${challengeLabel(0)}`"
                class="mx-auto h-36 w-full cursor-zoom-in rounded object-contain transition hover:scale-105"
                @click="openZoom(challenge1Src, `Challenge 1 — ${challengeLabel(0)}`)"
              />
              <p v-else class="py-12 text-xs text-text-muted">Still unavailable</p>
            </div>
          </figure>

          <!-- Challenge Still 2 -->
          <figure class="rounded-lg border bg-surface p-3">
            <figcaption class="mb-2 text-xs font-semibold">
              Challenge 2 — {{ challengeLabel(1) }}
            </figcaption>
            <div class="overflow-hidden rounded border bg-surface-muted/30 p-1 text-center">
              <img
                v-if="challenge2Src"
                :src="challenge2Src"
                :alt="`Liveness challenge: ${challengeLabel(1)}`"
                class="mx-auto h-36 w-full cursor-zoom-in rounded object-contain transition hover:scale-105"
                @click="openZoom(challenge2Src, `Challenge 2 — ${challengeLabel(1)}`)"
              />
              <p v-else class="py-12 text-xs text-text-muted">Still unavailable</p>
            </div>
          </figure>
        </div>

        <section class="rounded-lg border bg-surface p-4">
          <label class="mb-3 block text-xs font-medium"
            >Reject reason (optional)
            <input
              v-model="rejectReason"
              class="mt-1.5 h-9 w-full max-w-xl rounded-md border px-3 text-sm"
              placeholder="Notes for audit log & student retry email (e.g. lighting too dark, blurry ID)"
              :disabled="Boolean(busy)"
            />
          </label>

          <div class="flex flex-wrap gap-2">
            <button
              class="inline-flex h-9 items-center rounded-md bg-primary px-4 text-xs font-medium text-white transition hover:bg-primary/90 disabled:opacity-60"
              type="button"
              :disabled="Boolean(busy)"
              @click="requestDecide('approve')"
            >
              Approve & activate
            </button>
            <button
              class="inline-flex h-9 items-center rounded-md border border-danger/40 bg-danger-soft px-4 text-xs font-medium text-danger transition hover:bg-danger-soft/80 disabled:opacity-60"
              type="button"
              :disabled="Boolean(busy)"
              @click="requestDecide('reject')"
            >
              Reject & request retry
            </button>
          </div>
        </section>

        <!-- Decision Confirmation Modal -->
        <AppDialog
          v-model="isConfirmOpen"
          :title="confirmAction === 'approve' ? 'Approve & activate account' : 'Reject & request retry'"
          :description="
            confirmAction === 'approve'
              ? 'Confirm identity verification and activate this student account.'
              : 'Reset the current verification attempt and send a new link to the student.'
          "
          size="md"
          :closeable="!busy"
        >
          <div v-if="confirmAction === 'approve'" class="space-y-3 text-xs">
            <div class="rounded-lg border border-border/60 bg-surface-muted/40 p-3.5 leading-relaxed">
              <p class="font-medium text-text">
                Grantee: <span class="font-semibold text-text">{{ detailQuery.data.value?.student_name || "Student" }}</span>
                <span v-if="detailQuery.data.value?.student_id" class="font-mono text-text-muted ml-1">({{ detailQuery.data.value.student_id }})</span>
              </p>
              <p class="mt-1 text-text-muted">
                Face match distance:
                <strong class="font-mono text-text">
                  {{ detailQuery.data.value?.onboarding_face_distance != null ? Number(detailQuery.data.value.onboarding_face_distance).toFixed(4) : "—" }}
                </strong>
                <span class="ml-1 text-text-muted">({{ zoneLabel(detailQuery.data.value?.face_zone) }})</span>
              </p>
            </div>
            <p class="text-text-muted leading-relaxed">
              By confirming, this grantee will be marked as identity-verified. Any onboarding holds will be cleared, and their student account will be activated immediately.
            </p>
          </div>

          <div v-else-if="confirmAction === 'reject'" class="space-y-3 text-xs">
            <div class="rounded-lg border border-amber-200/60 bg-amber-500/10 p-3.5 text-amber-900 dark:text-amber-200 leading-relaxed">
              <p class="font-semibold">Recoverable review retry</p>
              <p class="mt-1 text-text-muted">
                The grantee’s account is <strong>not</strong> permanently closed or banned. Any current session will be revoked, and a fresh verification link will be emailed to:
              </p>
              <p class="mt-1 font-mono font-medium text-text">{{ detailQuery.data.value?.email || "the student’s address of record" }}</p>
            </div>

            <div v-if="rejectReason.trim()" class="rounded-lg border border-border/60 bg-surface-muted/40 p-3">
              <span class="font-medium text-text">Note included in retry email:</span>
              <p class="mt-1 italic text-text-muted">"{{ rejectReason.trim() }}"</p>
            </div>
            <div v-else class="rounded-lg border border-border/40 bg-surface-muted/20 p-3 text-text-muted italic">
              No specific note provided. The student will receive standard instructions to re-scan their ID and retake their selfie.
            </div>
          </div>

          <template #footer="{ close }">
            <button
              class="inline-flex h-9 items-center rounded-md border px-4 text-xs font-medium text-text-muted hover:bg-surface-muted hover:text-text transition disabled:opacity-60"
              type="button"
              :disabled="Boolean(busy)"
              @click="close"
            >
              Cancel
            </button>
            <button
              :class="[
                'inline-flex h-9 items-center rounded-md px-4 text-xs font-medium text-white shadow-sm transition disabled:opacity-60',
                confirmAction === 'approve'
                  ? 'bg-primary hover:bg-primary/90'
                  : 'bg-danger hover:bg-danger/90',
              ]"
              type="button"
              :disabled="Boolean(busy)"
              @click="confirmAction && decide(confirmAction)"
            >
              <template v-if="busy">
                {{ confirmAction === 'approve' ? 'Approving…' : 'Processing…' }}
              </template>
              <template v-else>
                {{ confirmAction === 'approve' ? 'Yes, approve & activate' : 'Yes, reject & send retry' }}
              </template>
            </button>
          </template>
        </AppDialog>
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

    <!-- Lightbox Zoom Modal (Teleported to body to cover top header & sidebar) -->
    <Teleport to="body">
      <div
        v-if="zoomImage"
        class="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm"
        role="dialog"
        aria-modal="true"
        @click.self="zoomImage = null"
      >
        <div class="relative flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl border border-border/60 bg-surface shadow-2xl">
          <div class="flex shrink-0 items-center justify-between border-b px-5 py-3.5 bg-surface-muted/30">
            <div class="flex items-center gap-2">
              <IconId class="text-primary" :size="18" />
              <h4 class="text-sm font-semibold text-text">{{ zoomImage.title }}</h4>
            </div>
            <button
              type="button"
              class="rounded-lg p-1.5 text-text-muted hover:bg-surface-muted hover:text-text transition"
              aria-label="Close preview"
              @click="zoomImage = null"
            >
              <IconX :size="20" />
            </button>
          </div>
          <div class="flex flex-1 items-center justify-center overflow-auto bg-black/5 p-4 sm:p-6">
            <img
              :src="zoomImage.src"
              :alt="zoomImage.title"
              class="max-h-[78vh] w-auto max-w-full rounded-lg object-contain shadow-lg"
            />
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
