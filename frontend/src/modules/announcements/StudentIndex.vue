<script setup lang="ts">
import { computed } from "vue";
import { useQuery } from "@tanstack/vue-query";
import { IconSpeakerphone, IconChevronRight } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import { apiFetch } from "@/api";
import { queryKeys } from "@/api/queryKeys";
import { useRouter, useRoute } from "vue-router";
import { withLang } from "@/i18n/routeLang";

type Announcement = {
  id: number;
  title: string;
  body: string;
  author?: string;
  audience?: string;
  created_at?: string;
};

const router = useRouter();
const route = useRoute();

// Try to fetch real announcements — silently falls back to static if API not ready
const { data, isLoading } = useQuery({
  queryKey: queryKeys.studentAnnouncements,
  queryFn: async () => {
    try {
      return await apiFetch<{ data: Announcement[] }>("/api/student/announcements");
    } catch {
      return null;
    }
  },
  retry: false,
  staleTime: 30_000,
});

const items = computed<Announcement[]>(() => {
  if (data.value?.data?.length) return data.value.data;
  // Fallback static items while the backend endpoint is not yet built
  return [
    {
      id: 1,
      title: "Scholarship orientation schedule",
      body: "Orientation for new TES grantees will be held at the TCC AVR on May 15, from 8:00 AM to 12:00 PM.",
      author: "UniFAST Office",
      created_at: "May 12, 2025",
    },
    {
      id: 2,
      title: "TES application deadline",
      body: "Complete and upload all pending requirements before May 31 to keep your application active.",
      author: "Scholarship Services",
      created_at: "May 8, 2025",
    },
    {
      id: 3,
      title: "Release schedule advisory",
      body: "The next subsidy disbursement window opens on June 15. Qualified grantees will receive a notification.",
      author: "UniFAST Office",
      created_at: "May 2, 2025",
    },
  ];
});

function openAnnouncement(id: number) {
  router.push(withLang(`/student/announcements/${id}`, route.query.lang));
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
  <div>
    <PageHeader title="Announcements" description="Updates from the UniFAST Office." />

    <!-- ── Skeleton loading — 3 cards matching the real card shape ──────── -->
    <ul v-if="isLoading && items.length === 0" class="space-y-2">
      <li
        v-for="n in 3"
        :key="n"
        class="rounded-xl border bg-surface p-4 animate-pulse"
      >
        <div class="flex gap-3">
          <!-- Icon placeholder -->
          <div class="h-9 w-9 shrink-0 rounded-lg bg-surface-muted" />
          <div class="flex-1 min-w-0 space-y-2.5">
            <!-- Title row -->
            <div class="flex items-start justify-between gap-2">
              <div class="h-4 w-1/2 rounded-md bg-surface-muted" />
              <div class="h-4 w-4 shrink-0 rounded bg-surface-muted" />
            </div>
            <!-- Meta row -->
            <div class="h-3 w-1/3 rounded bg-surface-muted" />
            <!-- Body lines -->
            <div class="space-y-1.5 pt-0.5">
              <div class="h-3 w-full rounded bg-surface-muted" />
              <div class="h-3 w-4/5 rounded bg-surface-muted" />
            </div>
          </div>
        </div>
      </li>
    </ul>

    <!-- ── Announcement cards ─────────────────────────────────────────── -->
    <ul v-if="items.length > 0" class="space-y-2">
      <li
        v-for="item in items"
        :key="item.id"
        class="group cursor-pointer rounded-xl border bg-surface p-4 transition hover:border-primary/40 hover:shadow-sm"
        @click="openAnnouncement(item.id)"
      >
        <div class="flex gap-3">
          <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-primary-soft text-primary transition group-hover:bg-primary group-hover:text-white">
            <IconSpeakerphone :size="17" />
          </span>
          <div class="min-w-0 flex-1">
            <div class="flex items-start justify-between gap-2">
              <p class="text-sm font-semibold leading-snug text-text group-hover:text-primary">
                {{ item.title }}
              </p>
              <IconChevronRight :size="15" class="mt-0.5 shrink-0 text-text-muted transition group-hover:text-primary" />
            </div>
            <p class="mt-0.5 text-xs text-text-muted">
              {{ formatDate(item.created_at) }}
              <span v-if="item.author"> · {{ item.author }}</span>
            </p>
            <p class="mt-2 line-clamp-2 text-sm leading-relaxed text-text-muted">{{ item.body }}</p>
          </div>
        </div>
      </li>
    </ul>
  </div>
</template>

