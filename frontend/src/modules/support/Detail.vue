<script setup lang="ts">
import { ref } from "vue";
import {
  IconArchive,
  IconArrowLeft,
  IconArrowsJoin,
  IconBan,
  IconBellRinging,
  IconCheck,
  IconDots,
  IconLock,
  IconPaperclip,
  IconRefresh,
  IconSend,
  IconStar,
  IconUserPlus,
} from "@tabler/icons-vue";
import DiceBearAvatar from "@/components/ui/DiceBearAvatar.vue";
const reply = ref("");
const sent = ref(false);
const mode = ref<"reply" | "note">("reply");
const status = ref("In progress");
const assignee = ref("TES Support");
const priority = ref("High");
const feedback = ref("");
const messages = [
  {
    sender: "Admin User",
    seed: "admin@unifast.gov.ph",
    time: "July 11, 2026 · 9:14 AM",
    body: "The exported eligibility report contains an incorrect period label. I selected AY 2025–2026, but the generated report shows the previous academic year.",
  },
  {
    sender: "TES Support",
    seed: "TES Support",
    time: "July 11, 2026 · 10:02 AM",
    body: "Thanks for reporting this. We reproduced the issue and are reviewing the report parameters. We will update this conversation when the correction is ready.",
  },
];
const act = (message: string) => (feedback.value = message);
</script>
<template>
  <div>
    <div class="mb-3 flex items-center justify-between">
      <RouterLink
        to="/app/support"
        class="inline-flex items-center gap-1 text-xs text-text-muted hover:text-primary"
        ><IconArrowLeft :size="14" />Back to inbox</RouterLink
      >
      <div class="flex gap-1">
        <button class="rounded-md border p-2 text-text-muted"><IconStar :size="15" /></button
        ><button class="rounded-md border p-2 text-text-muted"><IconArchive :size="15" /></button
        ><button class="rounded-md border p-2 text-text-muted"><IconDots :size="15" /></button>
      </div>
    </div>
    <section class="mx-auto max-w-6xl overflow-hidden rounded-xl border bg-surface">
      <header class="border-b p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <div class="flex flex-wrap items-center gap-2">
              <h1 class="text-xl font-semibold">Incorrect period on eligibility report</h1>
              <span class="rounded-full bg-warning-soft px-2 py-0.5 text-micro text-warning">{{
                status
              }}</span>
            </div>
            <p class="mt-1 text-xs text-text-muted">
              SUP-2026-0184 · Technical incident · {{ priority }} priority
            </p>
          </div>
          <div class="flex flex-wrap gap-2">
            <select v-model="assignee" class="h-9 rounded-md border bg-surface px-3 text-xs">
              <option>TES Support</option>
              <option>System Administrator</option>
              <option>UniFAST Staff</option>
              <option>Security Team</option>
              <option>Unassigned</option></select
            ><select v-model="priority" class="h-9 rounded-md border bg-surface px-3 text-xs">
              <option>Low</option>
              <option>Normal</option>
              <option>High</option>
              <option>Urgent</option>
              <option>Critical</option></select
            ><select v-model="status" class="h-9 rounded-md border bg-surface px-3 text-xs">
              <option>Open</option>
              <option>In progress</option>
              <option>Waiting for requester</option>
              <option>Waiting for third party</option>
              <option>On hold</option>
              <option>Resolved</option>
              <option>Closed</option>
              <option>Reopened</option>
            </select>
          </div>
        </div>
        <div class="mt-4 flex flex-wrap gap-2 border-t pt-4">
          <button
            class="inline-flex items-center gap-1.5 rounded-md border px-3 py-2 text-xs"
            @click="act('Ticket escalated to tier 2 support.')"
          >
            <IconBellRinging :size="14" />Escalate</button
          ><button
            class="inline-flex items-center gap-1.5 rounded-md border px-3 py-2 text-xs"
            @click="act('Collaborator selector opened.')"
          >
            <IconUserPlus :size="14" />Collaborator</button
          ><button
            class="inline-flex items-center gap-1.5 rounded-md border px-3 py-2 text-xs"
            @click="act('Related-ticket merge opened.')"
          >
            <IconArrowsJoin :size="14" />Merge</button
          ><button
            class="inline-flex items-center gap-1.5 rounded-md border px-3 py-2 text-xs"
            @click="
              status = 'Resolved';
              act('Ticket marked resolved.');
            "
          >
            <IconCheck :size="14" />Resolve</button
          ><button
            class="inline-flex items-center gap-1.5 rounded-md border px-3 py-2 text-xs"
            @click="
              status = 'Reopened';
              act('Ticket reopened.');
            "
          >
            <IconRefresh :size="14" />Reopen</button
          ><button
            class="inline-flex items-center gap-1.5 rounded-md border px-3 py-2 text-xs text-danger"
            @click="act('Ticket marked as spam or abuse.')"
          >
            <IconBan :size="14" />Spam
          </button>
        </div>
        <p v-if="feedback" class="mt-3 rounded-md bg-info-soft p-2 text-xs text-info">
          {{ feedback }}
        </p>
      </header>
      <div class="min-h-[380px] space-y-6 p-5 sm:p-7">
        <article v-for="message in messages" :key="message.time" class="flex gap-3">
          <DiceBearAvatar :seed="message.seed" :alt="message.sender" :size="36" />
          <div class="min-w-0 flex-1">
            <div class="flex justify-between gap-2">
              <p class="text-sm font-semibold">{{ message.sender }}</p>
              <p class="text-micro text-text-soft">{{ message.time }}</p>
            </div>
            <div
              class="mt-2 rounded-lg rounded-tl-none border p-4 text-sm leading-6 text-text-muted"
            >
              {{ message.body }}
            </div>
          </div>
        </article>
      </div>
      <section class="grid gap-3 border-t bg-surface-muted/25 p-5 sm:grid-cols-2 lg:grid-cols-4">
        <div
          v-for="item in [
            ['Requester', 'Admin User'],
            ['Assignee', assignee],
            ['SLA target', '4 hours · 2h 18m left'],
            ['Related record', 'Eligibility report #REP-482'],
            ['Watchers', 'Admin User, TES Head'],
            ['Channel', 'Web portal'],
            ['Created', 'July 11, 2026'],
            ['Last activity', '52 minutes ago'],
          ]"
          :key="item[0] as string"
        >
          <p class="text-micro uppercase text-text-soft">{{ item[0] }}</p>
          <p class="mt-1 text-xs font-medium">{{ item[1] }}</p>
        </div>
      </section>
      <form
        class="border-t bg-surface-muted/30 p-5"
        @submit.prevent="
          sent = true;
          reply = '';
        "
      >
        <div class="mb-2 flex gap-1">
          <button
            type="button"
            :class="[
              'rounded-md px-3 py-1.5 text-xs',
              mode === 'reply' ? 'bg-primary text-white' : 'text-text-muted',
            ]"
            @click="mode = 'reply'"
          >
            Public reply</button
          ><button
            type="button"
            :class="[
              'inline-flex items-center gap-1 rounded-md px-3 py-1.5 text-xs',
              mode === 'note' ? 'bg-warning text-white' : 'text-text-muted',
            ]"
            @click="mode = 'note'"
          >
            <IconLock :size="12" />Internal note
          </button>
        </div>
        <div class="rounded-lg border bg-surface">
          <textarea
            v-model="reply"
            class="min-h-28 w-full resize-none border-0 bg-transparent p-3 text-sm outline-none"
            :placeholder="
              mode === 'reply'
                ? 'Reply to this conversation…'
                : 'Add a private note for support staff…'
            "
          />
          <div class="flex items-center justify-between border-t px-3 py-2">
            <button type="button" class="rounded p-1.5 text-text-muted">
              <IconPaperclip :size="16" /></button
            ><button
              class="inline-flex items-center gap-1.5 rounded-md bg-primary px-4 py-2 text-xs text-white"
            >
              <IconSend :size="14" />{{ mode === "reply" ? "Send reply" : "Save note" }}
            </button>
          </div>
        </div>
        <p v-if="sent" class="mt-2 text-xs text-success">
          Mock {{ mode === "reply" ? "reply sent" : "internal note saved" }}.
        </p>
      </form>
    </section>
  </div>
</template>
