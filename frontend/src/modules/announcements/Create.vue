<script setup lang="ts">
import { ref } from "vue";
import { IconArrowLeft, IconEye, IconSend } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
const title = ref("");
const message = ref("");
const audience = ref("All grantees");
const channels = ref(["In-app", "Email"]);
</script>
<template>
  <div>
    <RouterLink
      to="/app/announcements"
      class="mb-3 inline-flex items-center gap-1 text-xs text-text-muted"
      ><IconArrowLeft :size="14" />Announcements</RouterLink
    >
    <PageHeader
      title="Create Announcement"
      description="Compose and publish an update to the selected audience."
    />
    <form class="grid gap-4 xl:grid-cols-[2fr_1fr]">
      <section class="rounded-lg border bg-surface p-5">
        <label class="block text-xs font-medium"
          >Title<input
            v-model="title"
            class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm"
            placeholder="Announcement title"
        /></label>
        <label class="mt-4 block text-xs font-medium"
          >Message<textarea
            v-model="message"
            class="mt-1.5 min-h-52 w-full rounded-md border p-3 text-sm"
            placeholder="Write the announcement"
          />
        </label>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
          <label class="text-xs font-medium"
            >Audience<select
              v-model="audience"
              class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm"
            >
              <option>All grantees</option>
              <option>Selected batch</option>
              <option>Staff only</option>
            </select></label
          >
          <label class="text-xs font-medium"
            >Publish schedule<input
              type="datetime-local"
              class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm"
          /></label>
        </div>
        <fieldset class="mt-4">
          <legend class="text-xs font-medium">Delivery channels</legend>
          <div class="mt-2 flex flex-wrap gap-3">
            <label
              v-for="channel in ['In-app', 'Email', 'SMS']"
              :key="channel"
              class="flex items-center gap-2 text-xs"
              ><input v-model="channels" type="checkbox" :value="channel" />{{ channel }}</label
            >
          </div>
        </fieldset>
      </section>
      <aside class="h-fit rounded-lg border bg-surface p-5">
        <h2 class="flex items-center gap-2 text-sm font-semibold"><IconEye :size="16" />Preview</h2>
        <div class="mt-4 rounded-md border p-4">
          <p class="text-sm font-semibold">{{ title || "Announcement title" }}</p>
          <p class="mt-2 whitespace-pre-line text-xs leading-5 text-text-muted">
            {{ message || "Your announcement message will appear here." }}
          </p>
          <p class="mt-4 text-micro text-text-soft">{{ audience }} · {{ channels.join(", ") }}</p>
        </div>
        <button
          class="mt-4 inline-flex w-full items-center justify-center gap-1.5 rounded-md bg-primary px-4 py-2.5 text-xs text-white"
        >
          <IconSend :size="14" />Publish announcement
        </button>
      </aside>
    </form>
  </div>
</template>
