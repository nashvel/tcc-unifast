<script setup lang="ts">
import { computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useQuery } from "@tanstack/vue-query";
import { IconArrowLeft, IconCalendar, IconSpeakerphone, IconUser, IconTag } from "@tabler/icons-vue";
import { apiFetch } from "@/api";
import { withLang } from "@/i18n/routeLang";
import { queryKeys } from "@/api/queryKeys";

type Announcement = {
  id: number;
  title: string;
  body: string;
  author?: string;
  audience?: string;
  created_at?: string;
};

// Static fallback data
const STATIC: Record<number, Announcement> = {
  1: {
    id: 1,
    title: "Scholarship orientation schedule",
    body: "Orientation for new TES grantees will be held at the TCC AVR on May 15, from 8:00 AM to 12:00 PM.\n\nAll grantees are required to attend. Please bring your student ID and one valid government-issued ID. Attendance will be checked and recorded by the scholarship office.\n\nFor questions and clarifications, please visit the UniFAST Office at the Administration Building during office hours (8:00 AM – 5:00 PM, Monday to Friday).",
    author: "UniFAST Office",
    audience: "All grantees",
    created_at: "May 12, 2025",
  },
  2: {
    id: 2,
    title: "TES application deadline",
    body: "Complete and upload all pending requirements before May 31 to keep your application active.\n\nThis includes your Certificate of Registration (COR), grades from the previous semester, and updated contact information. Applications with incomplete documents after the deadline will be placed on hold.\n\nIf you encounter any issues uploading your documents, please contact the Scholarship Services office immediately.",
    author: "Scholarship Services",
    audience: "All grantees",
    created_at: "May 8, 2025",
  },
  3: {
    id: 3,
    title: "Release schedule advisory",
    body: "The next subsidy disbursement window opens on June 15. Qualified grantees will receive a notification.\n\nPlease ensure your bank details or GCash information are up to date in your profile to avoid any delays in receiving your subsidy.\n\nGrantees who have not completed their profile verification will not be included in this disbursement cycle.",
    author: "UniFAST Office",
    audience: "All grantees",
    created_at: "May 2, 2025",
  },
};

const route = useRoute();
const router = useRouter();
const id = computed(() => Number(route.params.id));

const { data, isLoading } = useQuery({
  queryKey: queryKeys.studentAnnouncement(id.value),
  queryFn: async () => {
    try {
      return await apiFetch<{ data: Announcement }>(`/api/student/announcements/${id.value}`);
    } catch {
      return null; // Fall through to STATIC
    }
  },
  retry: false,
  staleTime: 30_000,
});

const item = computed<Announcement | null>(() => {
  if (data.value?.data) return data.value.data;
  return STATIC[id.value] ?? null;
});

function back() {
  router.push(withLang("/student/announcements", route.query.lang));
}

function formatDate(raw?: string) {
  if (!raw) return "";
  const d = new Date(raw);
  return isNaN(d.getTime())
    ? raw
    : d.toLocaleDateString("en-PH", { year: "numeric", month: "long", day: "numeric" });
}
</script>

<template>
  <!-- Full-width layout — no max-w constraint here, the shell provides padding -->
  <div class="-mx-4 -mt-4 sm:-mx-6 sm:-mt-6">

    <!-- ── Skeleton loading — only shown when no static data for this id ── -->
    <div v-if="isLoading && !item">
      <!-- Hero skeleton -->
      <div class="relative overflow-hidden bg-[#4a141d] px-6 pb-12 pt-10 sm:px-10">
        <div class="animate-pulse space-y-4 max-w-3xl">
          <div class="h-3 w-20 rounded-full bg-white/20" />
          <div class="h-8 w-3/4 rounded-lg bg-white/20" />
          <div class="flex gap-4 pt-1">
            <div class="h-3 w-28 rounded-full bg-white/15" />
            <div class="h-3 w-24 rounded-full bg-white/15" />
          </div>
        </div>
      </div>
      <!-- Body skeleton -->
      <div class="px-6 py-8 sm:px-10 animate-pulse space-y-3 max-w-3xl">
        <div class="h-4 w-full rounded bg-surface-muted" />
        <div class="h-4 w-5/6 rounded bg-surface-muted" />
        <div class="h-4 w-4/5 rounded bg-surface-muted" />
        <div class="h-4 w-full rounded bg-surface-muted mt-6" />
        <div class="h-4 w-3/4 rounded bg-surface-muted" />
      </div>
    </div>

    <!-- ── Content ──────────────────────────────────────────────────────── -->
    <div v-else-if="item">

      <!-- Hero banner — full bleed maroon gradient -->
      <div
        class="relative overflow-hidden px-6 pb-16 pt-10 sm:px-10"
        style="background: radial-gradient(ellipse at 20% 60%, rgba(126,31,44,0.95), transparent 60%),
               linear-gradient(135deg, #4a141d 0%, #6b1d2c 50%, #4a141d 100%);"
      >
        <!-- Dot-grid texture -->
        <div
          class="absolute inset-0 opacity-10"
          style="background-image: radial-gradient(rgba(255,255,255,0.6) 1px, transparent 1px); background-size: 22px 22px;"
        />

        <!-- Back button -->
        <button
          class="relative mb-8 inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1.5 text-xs font-medium text-white/80 backdrop-blur-sm transition hover:bg-white/20 hover:text-white"
          @click="back"
        >
          <IconArrowLeft :size="13" />
          Back to Announcements
        </button>

        <!-- Icon + Title -->
        <div class="relative flex items-start gap-4">
          <div class="hidden shrink-0 sm:grid h-14 w-14 place-items-center rounded-2xl bg-white/15 backdrop-blur-sm">
            <IconSpeakerphone :size="26" class="text-white" />
          </div>
          <div class="flex-1 min-w-0">
            <h1 class="text-2xl font-bold leading-tight text-white sm:text-3xl lg:text-4xl">
              {{ item.title }}
            </h1>

            <div class="mt-3 flex flex-wrap items-center gap-3">
              <span
                v-if="item.author"
                class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-medium text-white/90 backdrop-blur-sm"
              >
                <IconUser :size="12" />{{ item.author }}
              </span>
              <span
                v-if="item.created_at"
                class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-medium text-white/90 backdrop-blur-sm"
              >
                <IconCalendar :size="12" />{{ formatDate(item.created_at) }}
              </span>
              <span
                v-if="item.audience"
                class="inline-flex items-center gap-1.5 rounded-full bg-white/25 px-3 py-1 text-xs font-semibold text-white backdrop-blur-sm"
              >
                <IconTag :size="11" />{{ item.audience }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Article body — lifted card that overlaps the hero -->
      <div class="px-4 sm:px-8 lg:px-12">
        <div class="-mt-8 rounded-2xl border bg-surface shadow-lg">
          <div class="px-6 py-8 sm:px-10 sm:py-10">
            <div class="prose-custom max-w-none">
              <p
                v-for="(paragraph, i) in item.body.split('\n\n')"
                :key="i"
                :class="[
                  'text-[0.9375rem] leading-[1.8] text-text',
                  i > 0 && 'mt-5',
                ]"
              >
                {{ paragraph }}
              </p>
            </div>
          </div>

          <!-- Footer divider + back -->
          <div class="border-t px-6 py-4 sm:px-10">
            <button
              class="inline-flex items-center gap-1.5 text-sm font-medium text-primary transition hover:underline"
              @click="back"
            >
              <IconArrowLeft :size="14" />
              Back to Announcements
            </button>
          </div>
        </div>
      </div>

    </div>

    <!-- ── Not found ────────────────────────────────────────────────────── -->
    <div v-else class="flex min-h-[60vh] flex-col items-center justify-center text-center px-6">
      <div class="mb-4 grid h-16 w-16 place-items-center rounded-2xl bg-primary/10 text-primary">
        <IconSpeakerphone :size="32" />
      </div>
      <p class="text-base font-semibold text-text">Announcement not found</p>
      <p class="mt-1 text-sm text-text-muted">This announcement may have been removed or doesn't exist.</p>
      <button
        class="mt-5 inline-flex items-center gap-1.5 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white transition hover:bg-primary-hover"
        @click="back"
      >
        <IconArrowLeft :size="14" />
        Go back
      </button>
    </div>

  </div>
</template>
