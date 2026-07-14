<script setup lang="ts">
import {
  IconArrowLeft,
  IconBellRinging,
  IconChecklist,
  IconSchool,
  IconSend,
} from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";

const terms = [
  ["1st Semester AY 2025-2026", "2.76", "18 units", "Probation"],
  ["2nd Semester AY 2024-2025", "2.58", "21 units", "Watchlist"],
  ["1st Semester AY 2024-2025", "2.22", "21 units", "Clear"],
];

const notices = [
  ["July 11, 2026", "Risk notice drafted", "Pending student acknowledgement"],
  ["July 4, 2026", "Academic reminder sent", "Delivered by portal notification"],
];
</script>

<template>
  <div>
    <RouterLink
      to="/app/academic"
      class="mb-3 inline-flex items-center gap-1 text-xs text-text-muted"
    >
      <IconArrowLeft :size="14" />Academic records
    </RouterLink>

    <PageHeader
      title="Christian Dela Cruz"
      description="2024-00231 · BS Criminology · Academic risk monitoring"
    />

    <section class="grid gap-4 lg:grid-cols-[2fr_1fr]">
      <div class="space-y-4">
        <article class="rounded-lg border bg-surface">
          <header class="border-b p-4">
            <h2 class="font-semibold">Term history</h2>
            <p class="mt-1 text-xs text-text-muted">
              Used to identify students who need guidance or academic follow-up.
            </p>
          </header>
          <div
            v-for="term in terms"
            :key="term[0]"
            class="flex flex-wrap items-center justify-between gap-3 border-b p-4 last:border-0"
          >
            <div>
              <p class="text-sm font-medium">{{ term[0] }}</p>
              <p class="mt-1 text-xs text-text-muted">{{ term[2] }}</p>
            </div>
            <div class="text-right">
              <p class="text-lg font-semibold">{{ term[1] }} GWA</p>
              <span
                class="text-xs"
                :class="term[3] === 'Probation' ? 'text-danger' : 'text-text-muted'"
              >
                {{ term[3] }}
              </span>
            </div>
          </div>
        </article>

        <article class="rounded-lg border bg-surface">
          <header class="border-b p-4">
            <h2 class="font-semibold">Notification history</h2>
          </header>
          <div
            v-for="notice in notices"
            :key="notice[0]"
            class="flex flex-wrap items-center justify-between gap-3 border-b p-4 last:border-0"
          >
            <div>
              <p class="text-sm font-medium">{{ notice[1] }}</p>
              <p class="mt-1 text-xs text-text-muted">{{ notice[2] }}</p>
            </div>
            <p class="text-xs text-text-muted">{{ notice[0] }}</p>
          </div>
        </article>
      </div>

      <aside class="space-y-4">
        <article class="rounded-lg border bg-danger-soft p-4">
          <IconBellRinging :size="20" class="text-danger" />
          <h2 class="mt-3 text-sm font-semibold text-danger">Student is at risk</h2>
          <p class="mt-2 text-xs leading-5 text-text-muted">
            This record should trigger a student notice and adviser follow-up, not an eligibility
            evaluation.
          </p>
          <button
            class="mt-4 inline-flex h-9 items-center gap-2 rounded-md bg-primary px-3 text-xs font-medium text-white"
          >
            <IconSend :size="14" /> Send mocked notice
          </button>
        </article>

        <article class="rounded-lg border bg-surface p-4">
          <IconSchool :size="20" class="text-primary" />
          <p class="mt-3 text-xs text-text-muted">Current standing</p>
          <p class="mt-1 text-xl font-semibold">Probation</p>
        </article>

        <article class="rounded-lg border bg-surface p-4">
          <IconChecklist :size="20" class="text-primary" />
          <h2 class="mt-3 text-sm font-semibold">Recommended next steps</h2>
          <ul class="mt-2 space-y-2 text-xs leading-5 text-text-muted">
            <li>Notify the student through portal notification.</li>
            <li>Ask the student to visit the scholarship office.</li>
            <li>Record acknowledgement once the student responds.</li>
          </ul>
        </article>
      </aside>
    </section>
  </div>
</template>
