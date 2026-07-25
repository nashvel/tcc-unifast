<script setup lang="ts">
import { IconSpeakerphone } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
const announcements = [
  {
    title: "Scholarship orientation schedule",
    date: "May 12, 2025",
    author: "UniFAST Office",
    body: "Orientation for new TES grantees will be held at the TCC AVR on May 15, from 8:00 AM to 12:00 PM.",
  },
  {
    title: "TES application deadline",
    date: "May 8, 2025",
    author: "Scholarship Services",
    body: "Complete and upload all pending requirements before May 31 to keep your application active.",
  },
  {
    title: "Release schedule advisory",
    date: "May 2, 2025",
    author: "UniFAST Office",
    body: "The next subsidy disbursement window opens on June 15. Qualified grantees will receive a notification.",
  },
];
</script>
<template>
  <div>
    <PageHeader title="Announcements" description="Updates from the UniFAST Office." />
    <ul class="space-y-2">
      <li v-for="item in announcements" :key="item.title" class="rounded-lg border bg-surface p-4">
        <div class="flex gap-3">
          <span
            class="grid h-8 w-8 shrink-0 place-items-center rounded-md bg-primary-soft text-primary"
            ><IconSpeakerphone :size="16"
          /></span>
          <div>
            <p class="text-sm font-semibold">{{ item.title }}</p>
            <p class="mt-0.5 text-xs text-text-muted">{{ item.date }} · {{ item.author }}</p>
            <p class="mt-2 text-sm">{{ item.body }}</p>
          </div>
        </div>
      </li>
    </ul>
  </div>
</template>
