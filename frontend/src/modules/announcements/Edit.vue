<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useQuery } from "@tanstack/vue-query";
import { IconArrowLeft, IconDeviceFloppy, IconLoader } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import { apiFetch } from "@/api/client";
import { toast } from "@/composables/useToast";

type Announcement = { id: number; title: string; body: string; audience_type: "all" | "batch" | "program"; channels: string[]; status: "draft" | "scheduled" | "sent" | "cancelled"; scheduled_at?: string | null };
const route = useRoute();
const router = useRouter();
const id = computed(() => Number(route.params.id));
const title = ref(""); const body = ref(""); const audience = ref<Announcement["audience_type"]>("all"); const channels = ref<string[]>(["in_app"]); const status = ref<Announcement["status"]>("draft"); const saving = ref(false);
const announcementQuery = useQuery({ queryKey: ["announcement", id], queryFn: () => apiFetch<{ data: Announcement }>(`/api/announcements/${id.value}`), enabled: computed(() => Number.isInteger(id.value) && id.value > 0) });
watch(() => announcementQuery.data.value?.data, (item) => { if (!item) return; title.value = item.title; body.value = item.body; audience.value = item.audience_type; channels.value = item.channels; status.value = item.status; }, { immediate: true });
async function save() {
  if (!title.value.trim() || !body.value.trim()) return toast.error("Title and message are required.");
  saving.value = true;
  try {
    await apiFetch(`/api/announcements/${id.value}`, { method: "PUT", body: JSON.stringify({ title: title.value, body: body.value, audience_type: audience.value, channels: channels.value, status: status.value }) });
    toast.success("Announcement saved."); router.push("/app/announcements");
  } catch (error) { toast.error(error instanceof Error ? error.message : "Unable to save announcement."); } finally { saving.value = false; }
}
</script>
<template>
  <div>
    <RouterLink to="/app/announcements" class="mb-3 inline-flex items-center gap-1 text-xs text-text-muted"><IconArrowLeft :size="14" />Announcements</RouterLink>
    <PageHeader title="Edit Announcement" description="Update content, audience, channels, and publishing details." />
    <div v-if="announcementQuery.isLoading.value" class="grid min-h-48 place-items-center rounded-lg border bg-surface text-sm text-text-muted"><IconLoader :size="18" class="mr-2 animate-spin" />Loading announcement…</div>
    <div v-else-if="announcementQuery.isError.value" class="rounded-lg border border-danger bg-surface p-6 text-sm text-danger">Unable to load this announcement.</div>
    <form v-else class="max-w-3xl rounded-lg border bg-surface p-5" @submit.prevent="save">
      <label class="block text-xs font-medium">Title<input v-model="title" class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm" /></label>
      <label class="mt-4 block text-xs font-medium">Message<textarea v-model="body" class="mt-1.5 min-h-44 w-full rounded-md border p-3 text-sm" /></label>
      <div class="mt-4 grid gap-4 sm:grid-cols-2"><label class="text-xs font-medium">Audience<select v-model="audience" class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm"><option value="all">All grantees</option><option value="batch">Selected batch</option><option value="program">Selected program</option></select></label><label class="text-xs font-medium">Status<select v-model="status" class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm"><option value="draft">Draft</option><option value="scheduled">Scheduled</option><option value="sent">Sent</option><option value="cancelled">Cancelled</option></select></label></div>
      <fieldset class="mt-4"><legend class="text-xs font-medium">Delivery channels</legend><div class="mt-2 flex gap-3"><label v-for="channel in [{ label: 'In-app', value: 'in_app' }, { label: 'Email', value: 'email' }, { label: 'SMS', value: 'sms' }]" :key="channel.value" class="flex items-center gap-2 text-xs"><input v-model="channels" type="checkbox" :value="channel.value" />{{ channel.label }}</label></div></fieldset>
      <div class="mt-5 flex justify-end"><button :disabled="saving" class="inline-flex items-center gap-1.5 rounded-md bg-primary px-4 py-2 text-xs text-white disabled:opacity-50"><IconDeviceFloppy :size="15" />{{ saving ? 'Saving…' : 'Save changes' }}</button></div>
    </form>
  </div>
</template>
