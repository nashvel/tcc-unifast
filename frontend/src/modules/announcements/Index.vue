<script setup lang="ts">
import { IconDeviceMobile, IconMail, IconMessage, IconPlus } from "@tabler/icons-vue";
import { announcements } from "@/constants/mockAdmin";
import PageHeader from "@/components/ui/PageHeader.vue";
</script>
<template>
  <div>
    <PageHeader
      title="Announcements"
      description="Broadcast updates to grantees by audience and channel."
      ><template #actions
        ><RouterLink
          data-tour="announcements-new"
          to="/app/announcements/new"
          class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-xs font-medium text-white"
        >
          <IconPlus :size="15" />New announcement
        </RouterLink></template
      ></PageHeader
    >
    <section class="space-y-2" data-tour="announcements-list">
      <article
        v-for="item in announcements"
        :key="item.title"
        class="flex flex-col justify-between gap-3 rounded-lg border bg-surface p-4 sm:flex-row sm:items-center"
      >
        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-2">
            <p class="text-sm font-semibold">{{ item.title }}</p>
            <span class="rounded-full bg-success-soft px-2 py-0.5 text-micro text-success">{{
              item.status
            }}</span>
          </div>
          <p class="mt-1 text-xs text-text-muted">{{ item.body }}</p>
          <div class="mt-2 flex flex-wrap gap-2">
            <span class="rounded-full bg-primary-soft px-2 py-0.5 text-micro text-primary">{{
              item.audience
            }}</span
            ><span
              v-for="channel in item.channels"
              :key="channel"
              class="inline-flex items-center gap-1 rounded-full bg-info-soft px-2 py-0.5 text-micro text-info"
              ><IconMessage v-if="channel === 'In-app'" :size="10" /><IconMail
                v-else-if="channel === 'Email'"
                :size="10"
              /><IconDeviceMobile v-else :size="10" />{{ channel }}</span
            ><span class="text-micro text-text-soft">{{ item.date }}</span>
          </div>
        </div>
        <div class="flex gap-2">
          <RouterLink to="/app/announcements/1/edit" class="h-8 rounded-md border px-3 py-2 text-xs"
            >Edit</RouterLink
          ><RouterLink to="/app/announcements/logs" class="h-8 rounded-md border px-3 py-2 text-xs"
            >Logs</RouterLink
          >
        </div>
      </article>
    </section>
  </div>
</template>
