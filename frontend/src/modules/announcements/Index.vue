<script setup lang="ts">
import { defineAsyncComponent, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import {
  IconBrandFacebook,
  IconDeviceMobile,
  IconMail,
  IconMessage,
  IconPlus,
  IconSpeakerphone,
} from "@tabler/icons-vue";
import { announcements } from "@/constants/mockAdmin";
import PageHeader from "@/components/ui/PageHeader.vue";

const route = useRoute();
const router = useRouter();

const SocialPostsIndex = defineAsyncComponent(
  () => import("@/modules/social-posts/Index.vue")
);

type Tab = "announcements" | "social";
const activeTab = ref<Tab>((route.query.tab as Tab) === "social" ? "social" : "announcements");

watch(
  () => route.query.tab,
  (val) => {
    if (val === "social" || val === "announcements") {
      activeTab.value = val;
    } else if (!val) {
      activeTab.value = "announcements";
    }
  }
);

function selectTab(tab: Tab) {
  activeTab.value = tab;
  router.replace({
    query: {
      ...route.query,
      tab: tab === "announcements" ? undefined : tab,
    },
  });
}

const tabs: { key: Tab; label: string; icon: any }[] = [
  { key: "announcements", label: "Announcements", icon: IconSpeakerphone },
  { key: "social", label: "Social Media Posts", icon: IconBrandFacebook },
];
</script>

<template>
  <div>
    <PageHeader
      title="Announcements & Publishing"
      description="Broadcast updates to grantees and publish to connected social media channels in one unified hub."
    >
      <template #actions>
        <RouterLink
          v-if="activeTab === 'announcements'"
          data-tour="announcements-new"
          to="/app/announcements/new"
          class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-xs font-medium text-white"
        >
          <IconPlus :size="15" /> New announcement
        </RouterLink>
      </template>
    </PageHeader>

    <!-- Tabs -->
    <div class="mb-5 flex flex-wrap gap-1 border-b">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        type="button"
        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium transition-colors"
        :class="
          activeTab === tab.key
            ? 'border-b-2 border-primary text-primary'
            : 'text-text-muted hover:text-text'
        "
        @click="selectTab(tab.key)"
      >
        <component :is="tab.icon" :size="16" />
        {{ tab.label }}
      </button>
    </div>

    <!-- ── Announcements Tab ── -->
    <template v-if="activeTab === 'announcements'">
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
              }}</span>
              <span
                v-for="channel in item.channels"
                :key="channel"
                class="inline-flex items-center gap-1 rounded-full bg-info-soft px-2 py-0.5 text-micro text-info"
              >
                <IconMessage v-if="channel === 'In-app'" :size="10" />
                <IconMail v-else-if="channel === 'Email'" :size="10" />
                <IconDeviceMobile v-else :size="10" />
                {{ channel }}
              </span>
              <span class="text-micro text-text-soft">{{ item.date }}</span>
            </div>
          </div>
          <div class="flex gap-2">
            <RouterLink to="/app/announcements/1/edit" class="h-8 rounded-md border px-3 py-2 text-xs">
              Edit
            </RouterLink>
            <RouterLink to="/app/announcements/logs" class="h-8 rounded-md border px-3 py-2 text-xs">
              Logs
            </RouterLink>
          </div>
        </article>
      </section>
    </template>

    <!-- ── Social Media Tab ── -->
    <template v-else-if="activeTab === 'social'">
      <Suspense>
        <template #default>
          <SocialPostsIndex />
        </template>
        <template #fallback>
          <div class="rounded-lg border bg-surface p-12 text-center text-xs text-text-muted">
            Loading social media posts…
          </div>
        </template>
      </Suspense>
    </template>
  </div>
</template>
