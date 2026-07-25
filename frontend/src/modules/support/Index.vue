<script setup lang="ts">
import { computed, ref } from "vue";
import {
  IconArchive,
  IconArrowLeft,
  IconArrowRight,
  IconBug,
  IconChevronRight,
  IconClock,
  IconHelp,
  IconInbox,
  IconMessage,
  IconPlus,
  IconSearch,
  IconSend,
  IconStar,
} from "@tabler/icons-vue";
import { tickets } from "@/constants/mockAdmin";
import DiceBearAvatar from "@/components/ui/DiceBearAvatar.vue";
import PageHeader from "@/components/ui/PageHeader.vue";

const query = ref("");
const folder = ref("Inbox");
const selected = ref(0);
const reply = ref("");
const sent = ref(false);
type BoardStatus = "Open" | "In progress" | "Waiting" | "Resolved";
const boardTickets = ref([
  {
    id: "SUP-185",
    title: "Unable to replace uploaded transcript",
    requester: "Maria Santos",
    priority: "High",
    status: "Open" as BoardStatus,
    sla: "48m",
  },
  {
    id: "SUP-181",
    title: "Student account activation failed",
    requester: "Ana Reyes",
    priority: "Urgent",
    status: "Open" as BoardStatus,
    sla: "12m",
  },
  {
    id: "SUP-179",
    title: "Question about eligibility result",
    requester: "John Ramirez",
    priority: "Normal",
    status: "In progress" as BoardStatus,
    sla: "3h",
  },
  {
    id: "SUP-176",
    title: "Incorrect period on exported report",
    requester: "Admin User",
    priority: "High",
    status: "In progress" as BoardStatus,
    sla: "2h",
  },
  {
    id: "SUP-172",
    title: "Request to update contact number",
    requester: "Nicole Flores",
    priority: "Normal",
    status: "Waiting" as BoardStatus,
    sla: "1d",
  },
  {
    id: "SUP-168",
    title: "Duplicate masterlist record",
    requester: "TES Staff",
    priority: "High",
    status: "Resolved" as BoardStatus,
    sla: "Met",
  },
]);
const boardColumns: BoardStatus[] = ["Open", "In progress", "Waiting", "Resolved"];
const boardRows = (status: BoardStatus) =>
  boardTickets.value.filter((ticket) => ticket.status === status);
function move(ticket: (typeof boardTickets.value)[number], direction: number) {
  const index = boardColumns.indexOf(ticket.status);
  ticket.status = boardColumns[Math.max(0, Math.min(boardColumns.length - 1, index + direction))];
}
const rows = computed(() =>
  tickets.filter((ticket) =>
    `${ticket[0]} ${ticket[4]} ${ticket[1]}`.toLowerCase().includes(query.value.toLowerCase()),
  ),
);
const current = computed(() => rows.value[selected.value] ?? rows.value[0]);
const folders = [
  ["Inbox", IconInbox, 8],
  ["Assigned to me", IconStar, 3],
  ["In progress", IconClock, 4],
  ["Sent", IconSend, 0],
  ["Archived", IconArchive, 0],
];
const categoryIcon = (category: string) =>
  category === "bug" ? IconBug : category === "question" ? IconHelp : IconMessage;
const preview = (category: string) =>
  category === "bug"
    ? "I encountered an issue while working with a submitted record. Please help me resolve it."
    : category === "question"
      ? "I need clarification about the latest result shown in my account."
      : "Please review this account update request when available.";
</script>

<template>
  <div>
    <PageHeader
      title="Support Inbox"
      description="Read, assign, and respond to support conversations."
    >
      <template #actions>
        <RouterLink
          to="/app/support/new"
          class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-xs text-white"
          ><IconPlus :size="14" />New ticket</RouterLink
        >
      </template>
    </PageHeader>

    <section
      :class="[
        'grid min-h-[650px] overflow-hidden rounded-xl border bg-surface',
        folder === 'Assigned to me'
          ? 'lg:grid-cols-[190px_minmax(0,1fr)]'
          : 'lg:grid-cols-[190px_340px_minmax(0,1fr)]',
      ]"
    >
      <aside class="border-b p-3 lg:border-b-0 lg:border-r" data-tour="support-mailbox">
        <p class="mb-2 px-2 text-2xs font-semibold uppercase tracking-wider text-text-soft">
          Mailbox
        </p>
        <button
          v-for="item in folders"
          :key="item[0] as string"
          :class="[
            'flex h-9 w-full items-center gap-2 rounded-md px-2.5 text-left text-xs',
            folder === item[0]
              ? 'bg-primary-soft font-medium text-primary'
              : 'text-text-muted hover:bg-surface-muted',
          ]"
          @click="folder = item[0] as string"
        >
          <component :is="item[1]" :size="15" /><span class="flex-1">{{ item[0] }}</span
          ><span
            v-if="item[2]"
            class="rounded-full bg-primary px-1.5 py-0.5 text-micro text-white"
            >{{ item[2] }}</span
          >
        </button>
        <div class="mt-5 border-t pt-4">
          <p class="mb-2 px-2 text-2xs font-semibold uppercase tracking-wider text-text-soft">
            Labels
          </p>
          <p
            v-for="label in [
              ['Urgent', 'bg-danger'],
              ['Technical', 'bg-warning'],
              ['Question', 'bg-info'],
            ]"
            :key="label[0]"
            class="flex items-center gap-2 px-2 py-1.5 text-xs text-text-muted"
          >
            <i :class="['size-2 rounded-full', label[1]]" />{{ label[0] }}
          </p>
        </div>
      </aside>

      <template v-if="folder !== 'Assigned to me'">
        <div class="border-b lg:border-b-0 lg:border-r">
          <div class="border-b p-3">
            <div class="relative">
              <IconSearch
                :size="14"
                class="absolute left-3 top-1/2 -translate-y-1/2 text-text-soft"
              /><input
                v-model="query"
                class="h-9 w-full rounded-md border pl-9 pr-3 text-xs"
                placeholder="Search conversations"
              />
            </div>
          </div>
          <div class="max-h-[590px] overflow-y-auto">
            <button
              v-for="(ticket, index) in rows"
              :key="ticket[0] as string"
              :class="[
                'w-full border-b p-3 text-left transition-colors',
                selected === index ? 'bg-primary-soft/70' : 'hover:bg-surface-muted/60',
              ]"
              @click="
                selected = index;
                sent = false;
              "
            >
              <div class="flex items-start gap-2.5">
                <DiceBearAvatar :seed="String(ticket[4])" :alt="String(ticket[4])" :size="30" />
                <div class="min-w-0 flex-1">
                  <div class="flex items-center justify-between gap-2">
                    <p class="truncate text-xs font-semibold">{{ ticket[4] }}</p>
                    <span class="shrink-0 text-micro text-text-soft">{{
                      String(ticket[6]).split(",")[0]
                    }}</span>
                  </div>
                  <p class="mt-1 truncate text-xs font-medium">{{ ticket[0] }}</p>
                  <p class="mt-1 line-clamp-2 text-micro leading-4 text-text-muted">
                    {{ preview(String(ticket[1])) }}
                  </p>
                  <div class="mt-2 flex items-center gap-2">
                    <component
                      :is="categoryIcon(String(ticket[1]))"
                      :size="11"
                      class="text-text-soft"
                    /><span
                      :class="[
                        'rounded-full px-1.5 py-0.5 text-micro',
                        ticket[2] === 'High'
                          ? 'bg-danger-soft text-danger'
                          : 'bg-info-soft text-info',
                      ]"
                      >{{ ticket[2] }}</span
                    ><span class="text-micro text-text-soft">{{ ticket[3] }}</span>
                  </div>
                </div>
              </div>
            </button>
            <p v-if="!rows.length" class="p-8 text-center text-xs text-text-muted">
              No conversations found.
            </p>
          </div>
        </div>

        <div v-if="current" class="flex min-w-0 flex-col" data-tour="support-conversation">
          <header class="flex items-start justify-between gap-3 border-b p-4">
            <div>
              <div class="flex items-center gap-2">
                <h2 class="text-base font-semibold">{{ current[0] }}</h2>
                <span class="rounded-full bg-warning-soft px-2 py-0.5 text-micro text-warning">{{
                  current[3]
                }}</span>
              </div>
              <p class="mt-1 text-xs text-text-muted">
                SUP-2026-0184 · {{ current[1] }} · {{ current[2] }} priority
              </p>
            </div>
            <RouterLink
              to="/app/support/1"
              class="inline-flex items-center gap-1 text-xs text-primary"
              >Open full view<IconChevronRight :size="14"
            /></RouterLink>
          </header>
          <div class="flex-1 space-y-5 overflow-y-auto p-5">
            <article class="flex gap-3">
              <DiceBearAvatar :seed="String(current[4])" :alt="String(current[4])" :size="34" />
              <div class="min-w-0 flex-1">
                <div class="flex justify-between gap-2">
                  <p class="text-sm font-semibold">{{ current[4] }}</p>
                  <p class="text-micro text-text-soft">{{ current[6] }}</p>
                </div>
                <div
                  class="mt-2 rounded-lg rounded-tl-none bg-surface-muted p-4 text-sm leading-6 text-text-muted"
                >
                  {{ preview(String(current[1])) }}
                </div>
              </div>
            </article>
            <article class="flex gap-3">
              <DiceBearAvatar seed="TES Support" alt="TES Support" :size="34" />
              <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold">TES Support</p>
                <div
                  class="mt-2 rounded-lg rounded-tl-none border p-4 text-sm leading-6 text-text-muted"
                >
                  Thanks for contacting support. We are reviewing the ticket and will update you
                  here.
                </div>
              </div>
            </article>
          </div>
          <form
            class="border-t p-4"
            @submit.prevent="
              sent = true;
              reply = '';
            "
          >
            <textarea
              v-model="reply"
              class="min-h-24 w-full resize-none rounded-md border p-3 text-sm"
              placeholder="Write a reply…"
            />
            <div class="mt-2 flex items-center justify-between">
              <span v-if="sent" class="text-xs text-success">Mock reply sent.</span
              ><span v-else class="text-micro text-text-soft"
                >Replies remain mocked in this prototype.</span
              ><button
                class="inline-flex items-center gap-1.5 rounded-md bg-primary px-4 py-2 text-xs text-white"
              >
                <IconSend :size="14" />Send reply
              </button>
            </div>
          </form>
        </div>
      </template>

      <div v-else class="min-w-0 bg-bg/50 p-4" data-tour="support-conversation">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 class="text-base font-semibold">My ticket board</h2>
            <p class="mt-1 text-xs text-text-muted">
              Tickets assigned to you, grouped by workflow status.
            </p>
          </div>
          <div class="relative w-full sm:w-64">
            <IconSearch
              :size="14"
              class="absolute left-3 top-1/2 -translate-y-1/2 text-text-soft"
            /><input
              class="h-9 w-full rounded-md border bg-surface pl-9 pr-3 text-xs"
              placeholder="Search assigned tickets"
            />
          </div>
        </div>
        <div class="grid gap-3 overflow-x-auto pb-2 xl:grid-cols-4">
          <section
            v-for="(column, columnIndex) in boardColumns"
            :key="column"
            class="min-w-64 rounded-lg border bg-surface-muted/60 p-2.5"
          >
            <header class="mb-2 flex items-center justify-between px-1">
              <div class="flex items-center gap-2">
                <i
                  :class="[
                    'size-2 rounded-full',
                    column === 'Open'
                      ? 'bg-info'
                      : column === 'In progress'
                        ? 'bg-warning'
                        : column === 'Waiting'
                          ? 'bg-gold'
                          : 'bg-success',
                  ]"
                />
                <h3 class="text-xs font-semibold">{{ column }}</h3>
              </div>
              <span class="rounded-full bg-surface px-2 py-0.5 text-micro text-text-muted">{{
                boardRows(column).length
              }}</span>
            </header>
            <div class="space-y-2">
              <article
                v-for="ticket in boardRows(column)"
                :key="ticket.id"
                class="rounded-lg border bg-surface p-3 shadow-sm transition hover:border-primary/40 hover:shadow"
              >
                <div class="flex items-center justify-between">
                  <span class="font-mono text-micro text-text-soft">{{ ticket.id }}</span
                  ><span
                    :class="[
                      'rounded-full px-1.5 py-0.5 text-micro',
                      ticket.priority === 'Urgent'
                        ? 'bg-danger-soft text-danger'
                        : ticket.priority === 'High'
                          ? 'bg-warning-soft text-warning'
                          : 'bg-info-soft text-info',
                    ]"
                    >{{ ticket.priority }}</span
                  >
                </div>
                <RouterLink
                  to="/app/support/1"
                  class="mt-2 block text-xs font-semibold leading-5 hover:text-primary"
                  >{{ ticket.title }}</RouterLink
                >
                <div class="mt-3 flex items-center gap-2">
                  <DiceBearAvatar
                    :seed="ticket.requester"
                    :alt="ticket.requester"
                    :size="24"
                  /><span class="min-w-0 flex-1 truncate text-micro text-text-muted">{{
                    ticket.requester
                  }}</span
                  ><span
                    :class="[
                      'text-micro',
                      ticket.sla === '12m' || ticket.sla === '48m'
                        ? 'text-danger'
                        : 'text-text-soft',
                    ]"
                    >SLA {{ ticket.sla }}</span
                  >
                </div>
                <div class="mt-3 flex justify-between border-t pt-2">
                  <button
                    :disabled="columnIndex === 0"
                    class="rounded p-1 text-text-muted hover:bg-surface-muted disabled:opacity-25"
                    title="Move left"
                    @click="move(ticket, -1)"
                  >
                    <IconArrowLeft :size="13" /></button
                  ><button
                    :disabled="columnIndex === boardColumns.length - 1"
                    class="rounded p-1 text-text-muted hover:bg-surface-muted disabled:opacity-25"
                    title="Move right"
                    @click="move(ticket, 1)"
                  >
                    <IconArrowRight :size="13" />
                  </button>
                </div>
              </article>
              <div
                v-if="!boardRows(column).length"
                class="rounded-md border border-dashed p-6 text-center text-micro text-text-soft"
              >
                No tickets
              </div>
            </div>
          </section>
        </div>
      </div>
    </section>
  </div>
</template>
