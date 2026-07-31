<script setup lang="ts">
import { computed, ref } from "vue";
import { useRoute } from "vue-router";
import { useQuery } from "@tanstack/vue-query";
import { IconArrowLeft, IconBellRinging, IconCheck, IconSend, IconX } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import { apiFetch, ApiError } from "@/api";
import { queryKeys } from "@/api/queryKeys";
import { toast } from "vue-sonner";

type Criterion = { label: string; passed: boolean; note: string };
type Notice = { date: string; title: string; status: string };

type EligibilityDetail = {
  id: number;
  studentNo: string;
  name: string;
  batch: string;
  status: "Eligible" | "Needs update" | "Not eligible";
  passed: string;
  missing: string;
  max_failed_subjects_per_semester: number;
  criteria: Criterion[];
  notices: Notice[];
  risk_score: number | null;
  risk_badge: string | null;
  eligibility: Record<string, unknown>;
};

const route = useRoute();
const id = computed(() => String(route.params.id));
const sending = ref(false);

const detailQuery = useQuery({
  queryKey: computed(() => queryKeys.eligibilityDetail(id.value)),
  queryFn: () => apiFetch<{ data: EligibilityDetail }>(`/api/eligibility/${id.value}`),
});

const detail = computed(() => detailQuery.data.value?.data ?? null);

async function sendNotice() {
  if (!detail.value) return;
  sending.value = true;
  try {
    await apiFetch(`/api/eligibility/${detail.value.id}/notify`, {
      method: "POST",
      body: JSON.stringify({
        message: `Hello ${detail.value.name}, your TES batch submission needs attention: ${detail.value.missing} Please update your requirements or visit the scholarship office for assistance.`,
      }),
    });
    toast.success("Notice sent to student portal");
    await detailQuery.refetch();
  } catch (error) {
    toast.error(error instanceof ApiError ? error.message : "Unable to send notice.");
  } finally {
    sending.value = false;
  }
}
</script>

<template>
  <div>
    <RouterLink
      to="/app/eligibility"
      class="mb-3 inline-flex items-center gap-1 text-xs text-text-muted"
    >
      <IconArrowLeft :size="14" />Submission eligibility
    </RouterLink>

    <p v-if="detailQuery.isLoading.value" class="text-xs text-text-muted">Loading…</p>
    <p v-else-if="detailQuery.isError.value" class="text-xs text-danger">
      Unable to load eligibility detail.
      <button class="ml-2 text-primary underline" @click="detailQuery.refetch()">Retry</button>
    </p>

    <template v-else-if="detail">
      <PageHeader
        :title="`Submission eligibility — ${detail.name}`"
        :description="`${detail.studentNo} · ${detail.batch} — review batch submissions against retention rules`"
      />

      <section class="grid gap-4 lg:grid-cols-[2fr_1fr]">
        <div class="space-y-4">
          <article class="rounded-lg border bg-surface">
            <header class="border-b p-4">
              <h2 class="font-semibold">Submission &amp; retention checklist</h2>
              <p class="mt-1 text-xs text-text-muted">
                CHED already confirmed this student as a TES grantee. This checklist only checks
                what they submitted for the active batch and whether retention rules from Settings
                are met.
              </p>
            </header>
            <div
              v-for="criterion in detail.criteria"
              :key="criterion.label"
              class="flex items-start justify-between gap-4 border-b p-4 last:border-0"
            >
              <div>
                <p class="text-sm font-medium">{{ criterion.label }}</p>
                <p class="mt-1 text-xs text-text-muted">{{ criterion.note }}</p>
              </div>
              <span :class="criterion.passed ? 'text-success' : 'text-danger'">
                <component :is="criterion.passed ? IconCheck : IconX" :size="18" />
              </span>
            </div>
          </article>

          <article class="rounded-lg border bg-surface">
            <header class="border-b p-4">
              <h2 class="font-semibold">Notice history</h2>
            </header>
            <div
              v-if="detail.notices.length === 0"
              class="px-4 py-6 text-xs text-text-muted"
            >
              No eligibility notices sent yet.
            </div>
            <div
              v-for="notice in detail.notices"
              :key="`${notice.date}-${notice.title}`"
              class="flex flex-wrap items-center justify-between gap-3 border-b p-4 last:border-0"
            >
              <div>
                <p class="text-sm font-medium">{{ notice.title }}</p>
                <p class="mt-1 text-xs text-text-muted">{{ notice.status }}</p>
              </div>
              <p class="text-xs text-text-muted">{{ notice.date }}</p>
            </div>
          </article>
        </div>

        <aside class="space-y-4">
          <article
            v-if="detail.status !== 'Eligible'"
            class="rounded-lg border bg-warning-soft p-4"
          >
            <IconBellRinging :size="20" class="text-warning" />
            <h2 class="mt-3 text-sm font-semibold text-warning">
              Grantee needs a retention notice
            </h2>
            <p class="mt-2 text-xs leading-5 text-text-muted">
              {{ detail.missing }}
            </p>
            <button
              class="mt-4 inline-flex h-9 items-center gap-2 rounded-md bg-primary px-3 text-xs font-medium text-white disabled:opacity-60"
              :disabled="sending"
              @click="sendNotice"
            >
              <IconSend :size="14" /> {{ sending ? "Sending…" : "Send notice" }}
            </button>
          </article>

          <article class="rounded-lg border bg-surface p-4">
            <p class="text-xs text-text-muted">Current submission status</p>
            <p class="mt-1 text-xl font-semibold">{{ detail.status }}</p>
            <p
              v-if="detail.status === 'Not eligible'"
              class="mt-2 rounded-md bg-danger-soft px-2 py-1 text-xs text-danger"
            >
              {{
                (detail.eligibility?.note as string) ||
                detail.missing ||
                "Not eligible under retention rules."
              }}
            </p>
            <p class="mt-2 text-xs leading-5 text-text-muted">
              Checks passed: {{ detail.passed }}. Max failed+dropped from Settings:
              {{ detail.max_failed_subjects_per_semester }}. Retention uses Course History when
              available (all terms/programs); Grade Slip is supplemental. Citizenship, income,
              and other scholarships are not re-evaluated here.
            </p>
            <div
              v-if="Array.isArray(detail.eligibility?.terms) && (detail.eligibility.terms as unknown[]).length"
              class="mt-3 space-y-1 rounded-md border bg-surface-muted/40 p-2 text-xs text-text-muted"
            >
              <p class="font-medium text-text">Course History programs</p>
              <p
                v-for="(term, idx) in (detail.eligibility.terms as Array<Record<string, unknown>>)"
                :key="idx"
              >
                {{ term.academic_term || "Term" }}
                · {{ term.program_raw || term.program_code || "Program" }}
                <span v-if="term.year_level"> · {{ term.year_level }}</span>
                — {{ Number(term.failed_count ?? 0) }} failed /
                {{ Number(term.dropped_count ?? 0) }} dropped
              </p>
            </div>
            <p
              v-if="detail.risk_badge"
              class="mt-3 text-xs text-text-muted"
            >
              Pipeline risk: <span class="font-medium capitalize">{{ detail.risk_badge }}</span>
              <span v-if="detail.risk_score !== null"> ({{ detail.risk_score }})</span>
            </p>
          </article>
        </aside>
      </section>
    </template>
  </div>
</template>
