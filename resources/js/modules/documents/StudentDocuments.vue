<script setup lang="ts">
import { IconBook2, IconChevronRight, IconUpload } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";

const documents = [
  {
    name: "Course History",
    status: "missing",
    label: "Upload",
    updated: "Not yet submitted",
    icon: IconBook2,
  },
  {
    name: "COR",
    status: "missing",
    label: "Upload",
    updated: "Not yet submitted",
    icon: IconBook2,
  },
];
const groups = [
  {
    status: "missing",
    title: "Required documents",
    hint: "Upload your Course History and COR to complete your submission.",
    tone: "text-text-muted",
  },
  {
    status: "pending",
    title: "Under review",
    hint: "Our team is verifying these submissions.",
    tone: "text-info",
  },
  { status: "approved", title: "Approved", hint: "Verified and accepted.", tone: "text-success" },
];
const statusClasses: Record<string, string> = {
  missing: "bg-primary-soft text-primary",
  pending: "bg-info-soft text-info",
  approved: "bg-success-soft text-success",
};
</script>

<template>
  <div class="space-y-5 sm:space-y-6">
    <PageHeader
      title="Required Documents"
      description="Track your Course History and COR submissions."
    />
    <section class="rounded-2xl border bg-surface p-4 shadow-sm sm:p-5">
      <div class="flex items-start justify-between">
        <div>
          <p class="text-xs font-medium uppercase tracking-wide text-text-muted">
            Submission progress
          </p>
          <p class="mt-1 text-3xl font-semibold">
            0 <span class="text-xl font-normal text-text-muted">/ 2</span>
          </p>
          <p class="text-xs text-text-muted">documents submitted</p>
        </div>
        <span class="rounded-full border px-3 py-1.5 text-xs font-semibold text-primary">0%</span>
      </div>
      <div class="mt-4 h-2 overflow-hidden rounded-full bg-surface-muted">
        <div class="h-full w-0 bg-primary" />
      </div>
      <div class="mt-4 grid grid-cols-3 gap-2 text-center">
        <div
          v-for="stat in [
            ['Approved', 0, 'text-success'],
            ['Review', 0, 'text-info'],
            ['To do', 2, 'text-text-muted'],
          ]"
          :key="stat[0] as string"
          class="rounded-lg border py-2"
        >
          <p :class="['text-lg font-semibold', stat[2]]">{{ stat[1] }}</p>
          <p class="text-2xs uppercase text-text-soft">{{ stat[0] }}</p>
        </div>
      </div>
    </section>
    <section v-for="group in groups" :key="group.status" class="space-y-2">
      <div class="flex justify-between px-1">
        <h2 :class="['text-sm font-semibold', group.tone]">
          {{ group.title }}
          <span class="text-text-soft"
            >({{ documents.filter((document) => document.status === group.status).length }})</span
          >
        </h2>
        <p class="hidden text-micro text-text-soft sm:block">{{ group.hint }}</p>
      </div>
      <RouterLink
        v-for="document in documents.filter((item) => item.status === group.status)"
        :key="document.name"
        :to="{ path: '/student/upload', query: { type: document.name } }"
        class="group grid grid-cols-[auto_1fr_auto] items-center gap-3 rounded-xl border bg-surface p-3.5 hover:border-primary/40"
        ><span
          :class="['grid h-10 w-10 place-items-center rounded-xl', statusClasses[document.status]]"
          ><component :is="document.icon" :size="20"
        /></span>
        <div>
          <p class="text-sm font-medium">{{ document.name }}</p>
          <p class="text-micro text-text-muted">{{ document.updated }}</p>
        </div>
        <div class="flex items-center gap-2">
          <span
            :class="[
              'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-micro font-medium',
              statusClasses[document.status],
            ]"
            ><IconUpload v-if="document.status === 'missing'" :size="12" />{{
              document.label
            }}</span
          ><IconChevronRight :size="16" class="text-text-soft" /></div
      ></RouterLink>
    </section>
  </div>
</template>
