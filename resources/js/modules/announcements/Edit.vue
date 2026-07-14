<script setup lang="ts">
import { ref } from "vue";
import { IconArrowLeft, IconDeviceFloppy } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
const title = ref("TES application deadline reminder");
const body = ref(
  "Please complete and submit all required documents before the announced deadline.",
);
const audience = ref("All grantees");
const saved = ref(false);
</script>
<template>
  <div>
    <RouterLink
      to="/app/announcements"
      class="mb-3 inline-flex items-center gap-1 text-xs text-text-muted"
      ><IconArrowLeft :size="14" />Announcements</RouterLink
    ><PageHeader
      title="Edit Announcement"
      description="Update content, audience, and publishing details."
    />
    <form class="max-w-3xl rounded-lg border bg-surface p-5" @submit.prevent="saved = true">
      <label class="block text-xs font-medium"
        >Title<input
          v-model="title"
          class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm" /></label
      ><label class="mt-4 block text-xs font-medium"
        >Message<textarea
          v-model="body"
          class="mt-1.5 min-h-44 w-full rounded-md border p-3 text-sm"
        /></label
      ><label class="mt-4 block text-xs font-medium"
        >Audience<select
          v-model="audience"
          class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm"
        >
          <option>All grantees</option>
          <option>Staff only</option>
          <option>Selected batch</option>
        </select></label
      >
      <div class="mt-5 flex items-center justify-between">
        <span v-if="saved" class="text-xs text-success">Changes saved in this mock.</span
        ><span v-else /><button
          class="inline-flex items-center gap-1.5 rounded-md bg-primary px-4 py-2 text-xs text-white"
        >
          <IconDeviceFloppy :size="15" />Save changes
        </button>
      </div>
    </form>
  </div>
</template>
