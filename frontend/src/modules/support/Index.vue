<script setup lang="ts">
import { computed, ref } from "vue";
import { useQuery, useQueryClient } from "@tanstack/vue-query";
import { IconInbox, IconLoader, IconPlus, IconSearch, IconSend } from "@tabler/icons-vue";
import { apiFetch } from "@/api/client";
import DiceBearAvatar from "@/components/ui/DiceBearAvatar.vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import { toast } from "@/composables/useToast";

type Reply = { author: string; message: string; created_at: string };
type Ticket = {
  id: number; ticket_id: string; title: string; category: string; priority: string; status: string;
  description?: string | null; created_at: string; reporter: string; assignee: string; replies: Reply[];
};

const queryClient = useQueryClient();
const query = ref("");
const selectedId = ref<number | null>(null);
const reply = ref("");
const saving = ref(false);
const { data, isLoading, isError, error } = useQuery({
  queryKey: ["support-tickets"],
  queryFn: () => apiFetch<{ data: Ticket[] }>("/api/support-tickets"),
});
const tickets = computed(() => (data.value?.data ?? []).filter((ticket) =>
  `${ticket.ticket_id} ${ticket.title} ${ticket.reporter}`.toLowerCase().includes(query.value.toLowerCase()),
));
const selected = computed(() => tickets.value.find((ticket) => ticket.id === selectedId.value) ?? tickets.value[0] ?? null);

async function updateTicket(payload: Record<string, string>) {
  if (!selected.value) return;
  saving.value = true;
  try {
    await apiFetch(`/api/support-tickets/${selected.value.id}`, { method: "PATCH", body: JSON.stringify(payload) });
    await queryClient.invalidateQueries({ queryKey: ["support-tickets"] });
    reply.value = "";
    toast.success("Ticket updated");
  } catch (err) {
    toast.error(err instanceof Error ? err.message : "Unable to update ticket.");
  } finally {
    saving.value = false;
  }
}

function updateStatus(event: Event) {
  updateTicket({ status: (event.target as HTMLSelectElement).value });
}
</script>

<template>
  <div>
    <PageHeader title="Support Inbox" description="Read, update, and respond to real support conversations.">
      <template #actions><RouterLink to="/app/support/new" class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-xs text-white"><IconPlus :size="14" />New ticket</RouterLink></template>
    </PageHeader>
    <section class="grid min-h-[600px] overflow-hidden rounded-xl border bg-surface lg:grid-cols-[340px_minmax(0,1fr)]">
      <aside class="border-b lg:border-b-0 lg:border-r">
        <div class="border-b p-3"><div class="relative"><IconSearch :size="14" class="absolute left-3 top-1/2 -translate-y-1/2 text-text-soft" /><input v-model="query" class="h-9 w-full rounded-md border pl-9 pr-3 text-xs" placeholder="Search conversations" /></div></div>
        <div v-if="isLoading" class="p-8 text-center text-xs text-text-muted"><IconLoader :size="16" class="mr-2 inline animate-spin" />Loading tickets…</div>
        <div v-else-if="isError" class="p-8 text-center text-xs text-danger">{{ error instanceof Error ? error.message : "Unable to load tickets." }}</div>
        <div v-else class="max-h-[540px] overflow-y-auto">
          <button v-for="ticket in tickets" :key="ticket.id" class="w-full border-b p-3 text-left hover:bg-surface-muted" :class="selected?.id === ticket.id ? 'bg-primary-soft/70' : ''" @click="selectedId = ticket.id">
            <div class="flex gap-2.5"><DiceBearAvatar :seed="ticket.reporter" :alt="ticket.reporter" :size="30" /><div class="min-w-0 flex-1"><div class="flex justify-between gap-2"><p class="truncate text-xs font-semibold">{{ ticket.reporter }}</p><span class="text-micro text-text-soft">{{ ticket.status }}</span></div><p class="mt-1 truncate text-xs font-medium">{{ ticket.title }}</p><p class="mt-1 text-micro text-text-muted">{{ ticket.ticket_id }} · {{ ticket.priority }}</p></div></div>
          </button>
          <p v-if="!tickets.length" class="p-8 text-center text-xs text-text-muted">No conversations found.</p>
        </div>
      </aside>
      <main v-if="selected" class="flex min-w-0 flex-col">
        <header class="flex flex-wrap items-start justify-between gap-3 border-b p-4"><div><h2 class="text-base font-semibold">{{ selected.title }}</h2><p class="mt-1 text-xs text-text-muted">{{ selected.ticket_id }} · {{ selected.category }} · {{ selected.priority }} priority</p></div><select :value="selected.status" class="h-9 rounded-md border bg-surface px-3 text-xs" :disabled="saving" @change="updateStatus"><option>Open</option><option>In Progress</option><option>Waiting</option><option>Resolved</option></select></header>
        <div class="flex-1 space-y-5 overflow-y-auto p-5"><article class="flex gap-3"><DiceBearAvatar :seed="selected.reporter" :alt="selected.reporter" :size="34" /><div><p class="text-sm font-semibold">{{ selected.reporter }}</p><p class="mt-2 rounded-lg rounded-tl-none bg-surface-muted p-4 text-sm text-text-muted">{{ selected.description || 'No description was provided.' }}</p></div></article><article v-for="(item, index) in selected.replies" :key="index" class="flex gap-3"><DiceBearAvatar :seed="item.author" :alt="item.author" :size="34" /><div><p class="text-sm font-semibold">{{ item.author }}</p><p class="mt-2 rounded-lg rounded-tl-none border p-4 text-sm text-text-muted">{{ item.message }}</p></div></article></div>
        <form class="border-t p-4" @submit.prevent="reply.trim() && updateTicket({ reply: reply.trim() })"><textarea v-model="reply" class="min-h-24 w-full resize-none rounded-md border p-3 text-sm" placeholder="Write a reply…" /><div class="mt-2 flex justify-end"><button :disabled="saving || !reply.trim()" class="inline-flex items-center gap-1.5 rounded-md bg-primary px-4 py-2 text-xs text-white disabled:opacity-50"><IconSend :size="14" />{{ saving ? 'Sending…' : 'Send reply' }}</button></div></form>
      </main>
      <main v-else class="grid place-items-center p-12 text-center text-sm text-text-muted"><div><IconInbox :size="32" class="mx-auto mb-3" />Select a support ticket to view its conversation.</div></main>
    </section>
  </div>
</template>
