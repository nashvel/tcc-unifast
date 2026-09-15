<script setup lang="ts">
import { ref } from "vue";
import { useRouter } from "vue-router";
import { IconArrowLeft, IconEye, IconSend } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import { apiFetch } from "@/api/client";
import { toast } from "@/composables/useToast";
const router = useRouter();
const title = ref("");
const message = ref("");
const audience = ref("all");
const channels = ref(["in_app", "email"]);
const scheduledAt = ref("");
const saving = ref(false);
async function publish() {
  if (!title.value.trim() || !message.value.trim()) return toast.error("Title and message are required.");
  saving.value = true;
  try {
    await apiFetch("/api/announcements", { method: "POST", body: JSON.stringify({ title: title.value, body: message.value, audience_type: audience.value, channels: channels.value, status: scheduledAt.value ? "scheduled" : "sent", scheduled_at: scheduledAt.value || null }) });
    toast.success("Announcement published.");
    router.push("/app/announcements");
  } catch (error) {
    toast.error(error instanceof Error ? error.message : "Unable to publish announcement.");
  } finally { saving.value = false; }
}
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
    <form class="grid gap-4 xl:grid-cols-[2fr_1fr]" @submit.prevent="publish">
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
              <option value="all">All grantees</option>
              <option value="batch">Selected batch</option>
              <option value="program">Selected program</option>
            </select></label
          >
          <label class="text-xs font-medium"
            >Publish schedule<input
              type="datetime-local"
              v-model="scheduledAt"
              class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm"
          /></label>
        </div>
        <fieldset class="mt-4">
          <legend class="text-xs font-medium">Delivery channels</legend>
          <div class="mt-2 flex flex-wrap gap-3">
            <label
              v-for="channel in [{ label: 'In-app', value: 'in_app' }, { label: 'Email', value: 'email' }, { label: 'SMS', value: 'sms' }]"
              :key="channel.value"
              class="flex items-center gap-2 text-xs"
              ><input v-model="channels" type="checkbox" :value="channel.value" />{{ channel.label }}</label
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
          :disabled="saving"
          class="mt-4 inline-flex w-full items-center justify-center gap-1.5 rounded-md bg-primary px-4 py-2.5 text-xs text-white disabled:opacity-50"
        >
          <IconSend :size="14" />{{ saving ? 'Publishing…' : 'Publish announcement' }}
        </button>
      </aside>
    </form>
  </div>
</template>
