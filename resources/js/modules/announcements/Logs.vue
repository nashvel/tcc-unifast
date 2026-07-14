<script setup lang="ts">
import { IconArrowLeft } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import DataTable from "@/components/tables/DataTable.vue";
const logs = [
  ["TES application deadline reminder", "Email", "2,318", "2,301", "17", "May 12, 2026 9:00 AM"],
  ["Scholarship orientation schedule", "In-app", "2,486", "2,486", "0", "May 10, 2026 2:15 PM"],
  ["System maintenance advisory", "SMS", "184", "179", "5", "May 8, 2026 5:30 PM"],
];
</script>
<template>
  <div>
    <RouterLink
      to="/app/announcements"
      class="mb-3 inline-flex items-center gap-1 text-xs text-text-muted"
      ><IconArrowLeft :size="14" />Announcements</RouterLink
    ><PageHeader
      title="Notification Logs"
      description="Delivery results per channel for recent announcements."
    /><DataTable :headings="['Announcement', 'Channel', 'Sent', 'Delivered', 'Failed', 'Timestamp']"
      ><tr v-for="l in logs" :key="l[0] + l[1]">
        <td class="px-3 py-3 font-medium">{{ l[0] }}</td>
        <td class="px-3 py-3">{{ l[1] }}</td>
        <td class="px-3 py-3">{{ l[2] }}</td>
        <td class="px-3 py-3 text-success">{{ l[3] }}</td>
        <td class="px-3 py-3 text-danger">{{ l[4] }}</td>
        <td class="px-3 py-3 text-text-muted">{{ l[5] }}</td>
      </tr></DataTable
    >
  </div>
</template>
