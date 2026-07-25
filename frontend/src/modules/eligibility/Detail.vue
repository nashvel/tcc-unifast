<script setup lang="ts">
import { IconArrowLeft, IconBellRinging, IconCheck, IconSend, IconX } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";

const criteria = [
  ["Enrolled at an eligible institution", true, "Verified by current enrollment record."],
  ["Filipino citizen", true, "Matched against submitted profile."],
  ["Meets household income threshold", true, "Income record is on file."],
  ["No other government scholarship", true, "No duplicate assistance flag found."],
  ["Academic retention requirement", false, "Student needs academic follow-up notice."],
];

const notices = [
  ["July 11, 2026", "Eligibility update notice drafted", "Pending student acknowledgement"],
  ["July 8, 2026", "Reminder sent", "Delivered by portal notification"],
];
</script>

<template>
  <div>
    <RouterLink
      to="/app/eligibility"
      class="mb-3 inline-flex items-center gap-1 text-xs text-text-muted"
    >
      <IconArrowLeft :size="14" />Eligibility status
    </RouterLink>

    <PageHeader
      title="Eligibility Status — Christian Dela Cruz"
      description="2024-00231 · Notify student about missing or failed requirements"
    />

    <section class="grid gap-4 lg:grid-cols-[2fr_1fr]">
      <div class="space-y-4">
        <article class="rounded-lg border bg-surface">
          <header class="border-b p-4">
            <h2 class="font-semibold">Eligibility checklist</h2>
            <p class="mt-1 text-xs text-text-muted">
              This checklist identifies what the student needs to update. It is not a staff decision
              screen.
            </p>
          </header>
          <div
            v-for="criterion in criteria"
            :key="String(criterion[0])"
            class="flex items-start justify-between gap-4 border-b p-4 last:border-0"
          >
            <div>
              <p class="text-sm font-medium">{{ criterion[0] }}</p>
              <p class="mt-1 text-xs text-text-muted">{{ criterion[2] }}</p>
            </div>
            <span :class="criterion[1] ? 'text-success' : 'text-danger'">
              <component :is="criterion[1] ? IconCheck : IconX" :size="18" />
            </span>
          </div>
        </article>

        <article class="rounded-lg border bg-surface">
          <header class="border-b p-4">
            <h2 class="font-semibold">Notice history</h2>
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
        <article class="rounded-lg border bg-warning-soft p-4">
          <IconBellRinging :size="20" class="text-warning" />
          <h2 class="mt-3 text-sm font-semibold text-warning">
            Student needs an eligibility notice
          </h2>
          <p class="mt-2 text-xs leading-5 text-text-muted">
            Notify the student about missing or failed eligibility requirements so they can update
            records before the deadline.
          </p>
          <button
            class="mt-4 inline-flex h-9 items-center gap-2 rounded-md bg-primary px-3 text-xs font-medium text-white"
          >
            <IconSend :size="14" /> Send mocked notice
          </button>
        </article>

        <article class="rounded-lg border bg-surface p-4">
          <p class="text-xs text-text-muted">Current eligibility status</p>
          <p class="mt-1 text-xl font-semibold">Needs update</p>
          <p class="mt-2 text-xs leading-5 text-text-muted">
            The student is not being manually evaluated here. This screen only explains what needs
            attention.
          </p>
        </article>
      </aside>
    </section>
  </div>
</template>
