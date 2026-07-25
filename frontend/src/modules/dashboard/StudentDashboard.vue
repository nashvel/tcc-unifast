<script setup lang="ts">
import { onMounted, ref } from "vue";
import { useRoute } from "vue-router";
import { useI18n } from "vue-i18n";
import {
  IconArrowRight,
  IconCalendarEvent,
  IconChevronLeft,
  IconChevronRight,
  IconClock,
  IconFileCheck,
  IconId,
  IconLock,
  IconPassword,
  IconSchool,
  IconShieldCheck,
  IconUpload,
  IconWorld,
} from "@tabler/icons-vue";
import AppTour from "@/components/tour/AppTour.vue";
import DiceBearAvatar from "@/components/ui/DiceBearAvatar.vue";
import { studentVerification } from "@/auth/studentVerification";
import { useSubmissionWindow } from "@/composables/useSubmissionWindow";
import { useNotificationChannel } from "@/composables/useEcho";
import { toast } from "@/composables/useToast";
import { withLang } from "@/i18n/routeLang";

const route = useRoute();
const { t } = useI18n();
const activityTab = ref("time");
const { windowState, loadingWindow, loadWindow } = useSubmissionWindow();
onMounted(loadWindow);

useNotificationChannel((payload) => {
  toast.info(payload.title, { description: payload.body });
});

const days = [
  ["dashboard.student.days.mon", 6],
  ["dashboard.student.days.tue", 4.5],
  ["dashboard.student.days.wed", 10],
  ["dashboard.student.days.thu", 7],
  ["dashboard.student.days.fri", 6.5],
  ["dashboard.student.days.sat", 5],
  ["dashboard.student.days.sun", 7.5],
];

const progress = [
  {
    nameKey: "dashboard.student.progress.requiredDocuments",
    metaKey: "dashboard.student.progress.requiredDocumentsMeta",
    value: 0,
    tone: "bg-primary",
    to: "/student/documents",
  },
  {
    nameKey: "dashboard.student.progress.tesApplication",
    metaKey: "dashboard.student.progress.tesApplicationMeta",
    value: 0,
    tone: "bg-gold",
    to: "/student/documents",
  },
];

const schedule = [
  {
    id: "orientation",
    time: "8:00 AM",
    titleKey: "dashboard.student.schedule.orientation",
    metaKey: "dashboard.student.schedule.orientationMeta",
    tone: "border-gold bg-gold-soft",
  },
  {
    id: "deadline",
    time: "10:30 AM",
    titleKey: "dashboard.student.schedule.documentDeadline",
    metaKey: "dashboard.student.schedule.documentDeadlineMeta",
    tone: "border-primary bg-primary-soft",
  },
  {
    id: "consultation",
    time: "2:00 PM",
    titleKey: "dashboard.student.schedule.consultation",
    metaKey: "dashboard.student.schedule.consultationMeta",
    tone: "border-info bg-info-soft",
  },
  {
    id: "literacy",
    time: "3:30 PM",
    titleKey: "dashboard.student.schedule.literacy",
    metaKey: "dashboard.student.schedule.literacyMeta",
    tone: "border-success bg-success-soft",
  },
];

const summaryCards = [
  {
    labelKey: "dashboard.student.summary.requirementsCompleted",
    value: "0 / 4",
    icon: IconFileCheck,
    footKey: "dashboard.student.summary.vaultRequired",
  },
  {
    labelKey: "dashboard.student.summary.applications",
    value: "1",
    icon: IconSchool,
    footKey: "dashboard.student.summary.documentsPending",
  },
  {
    labelKey: "dashboard.student.summary.upcomingEvents",
    value: "4",
    icon: IconCalendarEvent,
    footKey: "dashboard.student.summary.nextMay15",
  },
  {
    labelKey: "dashboard.student.summary.announcements",
    value: "12",
    icon: IconWorld,
    footKey: "dashboard.student.summary.unread",
  },
];

const activityTabs = [
  ["time", "dashboard.student.tabs.time"],
  ["requirements", "dashboard.student.tabs.requirements"],
  ["applications", "dashboard.student.tabs.applications"],
  ["announcements", "dashboard.student.tabs.announcements"],
] as const;
</script>

<template>
  <div class="student-dashboard mx-auto max-w-[1280px] space-y-4">
    <section
      v-if="false && !studentVerification.verified"
      class="rounded-2xl border bg-surface p-6 shadow-sm"
    >
      <div class="grid gap-5 lg:grid-cols-[1fr_340px]">
        <div>
          <span
            class="inline-flex items-center gap-2 rounded-full bg-warning-soft px-3 py-1 text-xs font-semibold text-warning"
          >
            <IconLock :size="14" /> {{ t("dashboard.student.locked.limitedAccess") }}
          </span>
          <h1 class="mt-4 text-3xl font-semibold tracking-tight">
            {{ t("dashboard.student.locked.title") }}
          </h1>
          <p class="mt-2 max-w-2xl text-sm text-text-muted">
            {{ t("dashboard.student.locked.description") }}
          </p>
          <div class="mt-5 flex flex-wrap gap-2">
            <RouterLink
              :to="withLang('/student/verify', route.query.lang)"
              class="inline-flex h-10 items-center gap-2 rounded-md bg-primary px-4 text-sm font-medium text-white"
            >
              <IconShieldCheck :size="16" /> {{ t("nav.verifyIdentity") }}
            </RouterLink>
            <RouterLink
              :to="withLang('/student/settings', route.query.lang)"
              class="inline-flex h-10 items-center gap-2 rounded-md border px-4 text-sm"
            >
              <IconPassword :size="16" /> {{ t("dashboard.student.changePassword") }}
            </RouterLink>
          </div>
        </div>
        <aside class="rounded-xl border bg-surface-muted p-4">
          <h2 class="text-sm font-semibold">{{ t("dashboard.student.locked.checklist") }}</h2>
          <ol class="mt-3 space-y-3 text-xs text-text-muted">
            <li class="flex gap-2">
              <IconId :size="15" class="text-primary" /> {{ t("dashboard.student.locked.uploadId") }}
            </li>
            <li class="flex gap-2">
              <IconShieldCheck :size="15" class="text-primary" />
              {{ t("dashboard.student.locked.faceMatch") }}
            </li>
            <li class="flex gap-2">
              <IconUpload :size="15" class="text-primary" />
              {{ t("dashboard.student.locked.courseHistory") }}
            </li>
            <li class="flex gap-2">
              <IconFileCheck :size="15" class="text-primary" />
              {{ t("dashboard.student.locked.cor") }}
            </li>
          </ol>
        </aside>
      </div>
    </section>

    <template v-else>
      <section class="rounded-xl border border-info/30 bg-info-soft p-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <p class="text-sm font-semibold text-info">
              {{
                windowState?.open
                  ? t("dashboard.student.window.open")
                  : t("dashboard.student.window.locked")
              }}
            </p>
            <p class="mt-0.5 text-xs text-text-muted">
              {{
                loadingWindow
                  ? t("dashboard.student.window.checking")
                  : windowState?.message || t("dashboard.student.window.unavailable")
              }}
            </p>
          </div>
          <div class="flex gap-2">
            <RouterLink
              :to="withLang('/student/settings', route.query.lang)"
              class="rounded-md border bg-surface px-3 py-2 text-xs"
            >
              {{ t("dashboard.student.changePassword") }}
            </RouterLink>
            <RouterLink
              :to="withLang('/student/documents', route.query.lang)"
              :class="[
                'rounded-md px-3 py-2 text-xs font-medium',
                windowState?.open ? 'bg-primary text-white' : 'border bg-surface text-text-muted',
              ]"
            >
              {{
                windowState?.open
                  ? t("dashboard.student.window.openVault")
                  : t("dashboard.student.window.viewVault")
              }}
            </RouterLink>
          </div>
        </div>
      </section>

      <header
        class="student-dashboard-header flex flex-wrap items-start justify-between gap-3"
        data-tour="page-header"
      >
        <div>
          <p class="text-xs text-text-muted">
            {{ t("dashboard.student.welcome", { name: "Maria" }) }}
          </p>
          <h1 class="mt-1 text-3xl font-semibold tracking-tight">
            {{ t("dashboard.student.headingPrefix") }}
            <span class="text-primary">{{ t("dashboard.student.headingHighlight") }}</span>
          </h1>
        </div>
        <AppTour />
      </header>

      <section class="grid grid-cols-2 gap-3 lg:grid-cols-4" data-tour="student-summary">
        <article
          v-for="(card, cardIndex) in summaryCards"
          :key="card.labelKey"
          class="student-summary-card rounded-xl border bg-surface p-4"
          :style="{ animationDelay: `${80 + cardIndex * 70}ms` }"
        >
          <p class="text-xs text-text-muted">{{ t(card.labelKey) }}</p>
          <p class="mt-2 text-2xl font-semibold">{{ card.value }}</p>
          <div class="mt-5 flex items-center justify-between">
            <span class="inline-flex items-center gap-1.5 text-micro text-text-muted"
              ><component :is="card.icon" :size="14" class="text-primary" />{{
                t(card.footKey)
              }}</span
            ><span class="text-micro font-medium text-success">↗ 4.6%</span>
          </div>
        </article>
      </section>

      <section class="grid gap-4 xl:grid-cols-[minmax(0,2.1fr)_minmax(300px,1fr)]">
        <div class="space-y-4">
          <article
            class="student-panel rounded-xl border bg-surface p-5"
            data-tour="student-progress"
          >
            <div class="flex items-center justify-between">
              <h2 class="text-base font-semibold">{{ t("dashboard.student.activity") }}</h2>
              <select class="h-8 rounded-md border bg-surface px-2 text-xs">
                <option>{{ t("dashboard.student.weekly") }}</option>
                <option>{{ t("dashboard.student.monthly") }}</option>
              </select>
            </div>
            <nav class="mt-3 flex gap-5 border-b">
              <button
                v-for="tab in activityTabs"
                :key="tab[0]"
                :class="[
                  'border-b-2 pb-2 text-xs',
                  activityTab === tab[0]
                    ? 'border-primary font-medium text-primary'
                    : 'border-transparent text-text-soft',
                ]"
                @click="activityTab = tab[0]"
              >
                {{ t(tab[1]) }}
              </button>
            </nav>
            <div class="mt-4 grid grid-cols-7 gap-2">
              <div v-for="(day, index) in days" :key="day[0]" class="flex flex-col items-center">
                <div
                  class="relative flex h-44 w-full items-end justify-center overflow-hidden rounded-md bg-surface-muted/70"
                >
                  <div
                    :class="[
                      'student-activity-bar w-full rounded-t-md',
                      index === 2 ? 'bg-primary' : 'bg-primary-soft',
                    ]"
                    :style="{ height: `${Number(day[1]) * 9}%` }"
                  ></div>
                  <span
                    v-if="index === 2"
                    class="absolute top-3 rounded-md bg-surface px-2 py-1 text-micro font-medium shadow"
                    >{{ t("dashboard.student.days.wed") }}<br /><b>8h 30m</b></span
                  >
                </div>
                <p class="mt-2 text-micro text-text-muted">{{ t(String(day[0])) }}</p>
              </div>
            </div>
          </article>
          <article class="student-panel rounded-xl border bg-surface p-5">
            <h2 class="text-base font-semibold">
              {{ t("dashboard.student.applicationProgress") }}
            </h2>
            <div
              class="mt-4 grid grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)_110px_34px] items-center gap-3 border-b pb-2 text-micro uppercase text-text-soft"
            >
              <span>{{ t("dashboard.student.item") }}</span
              ><span>{{ t("dashboard.student.status") }}</span
              ><span>{{ t("dashboard.student.progressLabel") }}</span
              ><span />
            </div>
            <RouterLink
              v-for="item in progress"
              :key="item.nameKey"
              :to="withLang(item.to, route.query.lang)"
              class="grid grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)_110px_34px] items-center gap-3 border-b py-3 last:border-0"
              ><span class="flex items-center gap-2 text-xs font-medium"
                ><span
                  class="grid size-7 place-items-center rounded-md bg-primary-soft text-primary"
                  ><IconFileCheck :size="14" /></span
                >{{ t(item.nameKey) }}</span
              ><span class="text-xs text-text-muted">{{ t(item.metaKey) }}</span
              ><span class="flex items-center gap-2"
                ><i class="h-1.5 flex-1 overflow-hidden rounded-full bg-surface-muted"
                  ><i
                    :class="['student-progress-fill block h-full', item.tone]"
                    :style="{ width: `${item.value}%` }" /></i
                ><b class="text-micro">{{ item.value }}%</b></span
              ><span class="grid size-7 place-items-center rounded-md border"
                ><IconArrowRight :size="13" /></span
            ></RouterLink>
          </article>
        </div>

        <aside class="student-panel rounded-xl border bg-surface p-5">
          <div class="flex items-start justify-between">
            <div>
              <h2 class="text-base font-semibold">{{ t("dashboard.student.schedule.title") }}</h2>
              <p class="mt-1 text-xs text-text-muted">
                {{ t("dashboard.student.schedule.date") }}
              </p>
            </div>
            <div class="flex gap-1">
              <button class="rounded-md border p-1.5"><IconChevronLeft :size="14" /></button
              ><button class="rounded-md border p-1.5"><IconChevronRight :size="14" /></button>
            </div>
          </div>
          <div class="relative mt-5 border-l pl-5">
            <div class="absolute left-[-1px] top-0 h-full w-px bg-border" />
            <article
              v-for="(event, eventIndex) in schedule"
              :key="event.id"
              class="student-schedule-item relative mb-5"
              :style="{ animationDelay: `${220 + eventIndex * 80}ms` }"
            >
              <span
                class="absolute -left-[26px] top-1 size-2.5 rounded-full border-2 border-surface bg-primary"
              />
              <p class="text-micro text-text-soft">{{ event.time }}</p>
              <div :class="['mt-1 rounded-lg border-l-2 p-3', event.tone]">
                <p class="text-xs font-semibold">{{ t(event.titleKey) }}</p>
                <p class="mt-1 flex items-center gap-1 text-micro text-text-muted">
                  <IconClock :size="11" />{{ t(event.metaKey) }}
                </p>
                <div v-if="event.id === 'orientation'" class="mt-2 flex -space-x-1">
                  <DiceBearAvatar
                    v-for="seed in ['Maria', 'John', 'Ana']"
                    :key="seed"
                    :seed="seed"
                    :alt="seed"
                    :size="20"
                  />
                </div>
              </div>
            </article>
          </div>
          <RouterLink
            :to="withLang('/student/announcements', route.query.lang)"
            class="mt-2 flex items-center justify-center gap-1 rounded-md border py-2 text-xs text-primary"
            >{{ t("dashboard.student.viewAllEvents") }}<IconArrowRight :size="13"
          /></RouterLink>
        </aside>
      </section>
    </template>
  </div>
</template>
