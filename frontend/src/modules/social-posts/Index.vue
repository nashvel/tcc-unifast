<script setup lang="ts">
import { computed, reactive, ref, watch } from "vue";
import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/vue-query";
import {
  IconAlertTriangle,
  IconBrandFacebook,
  IconCalendarTime,
  IconCheck,
  IconChevronDown,
  IconDots,
  IconFileText,
  IconHistory,
  IconPhoto,
  IconRefresh,
  IconSend,
  IconShieldCheck,
  IconWorld,
} from "@tabler/icons-vue";
import CardSkeleton from "@/components/ui/CardSkeleton.vue";
import EmptyState from "@/components/ui/EmptyState.vue";
import {
  createSocialMediaPost,
  dispatchSocialMediaPost,
  getSocialMediaIntegrationStatus,
  getSocialMediaPostTemplate,
  listBatches,
  listSocialMediaPosts,
} from "@/api";
import { queryKeys } from "@/api/queryKeys";
import type { PaginatedResponse, SocialMediaPost, SocialMediaPostTemplate } from "@/api";
import { toast } from "@/composables/useToast";
import pageLogo from "@/assets/system-logo.webp";
import coverImage from "@/assets/dashboard-header.jpg";

const queryClient = useQueryClient();
const activeTab = ref<"create" | "history">("create");
const page = ref(1);
const selectedStatus = ref("all");
const advancedOpen = ref(false);
const savedDraft = ref<SocialMediaPost | null>(null);

const form = reactive({
  title: "",
  campaign: "",
  batch_id: null as number | null,
  approval_mode: "approval_required" as "approval_required" | "pre_approved",
  scheduled_for: "",
  message: "",
});

const templateFacts = ref<SocialMediaPostTemplate["facts"] | null>(null);

const postsQuery = useQuery({
  queryKey: computed(() => queryKeys.socialMediaPosts({ page: page.value, status: selectedStatus.value })),
  queryFn: () => listSocialMediaPosts({ page: page.value, per_page: 8, status: selectedStatus.value }),
  placeholderData: keepPreviousData,
});

const batchesQuery = useQuery({
  queryKey: queryKeys.batches,
  queryFn: () => listBatches({ per_page: 100 }),
});

const integrationQuery = useQuery({
  queryKey: ["social-media-integration-status"],
  queryFn: getSocialMediaIntegrationStatus,
  refetchInterval: 30_000,
});

const posts = computed(() => postsQuery.data.value?.data ?? []);
const meta = computed(() => postsQuery.data.value?.meta);
const batches = computed(() => batchesQuery.data.value?.data ?? []);
const selectedBatch = computed(() => batches.value.find((batch) => batch.id === form.batch_id) ?? null);
const characterCount = computed(() => form.message.length);
const hasContent = computed(() => form.title.trim().length > 0 && form.message.trim().length >= 20);
const integration = computed(() => integrationQuery.data.value?.data ?? null);
const facebookPage = computed(() => integration.value?.page ?? null);
const pageName = computed(() => facebookPage.value?.name || "TCC UniFAST Facebook Page");
const pagePicture = computed(() => facebookPage.value?.picture_url || pageLogo);
const pageCover = computed(() => facebookPage.value?.cover_url || coverImage);
const pageConnectionLabel = computed(() => {
  if (integrationQuery.isLoading.value) return "Checking Facebook connection...";
  if (integrationQuery.isError.value) return "Unable to read integration status";
  const labels: Record<string, string> = {
    connected: "Facebook Page connected and confirmed",
    ready_for_first_post: "n8n configured · create your first post",
    draft_saved: "Draft saved · not sent to n8n yet",
    awaiting_approval: "n8n reached · waiting for your approval",
    awaiting_facebook_callback: "n8n reached · waiting for Facebook result",
    failed: "Latest Facebook workflow failed",
    not_configured: "n8n integration is not configured",
  };
  return labels[integration.value?.state ?? "not_configured"];
});
const integrationError = computed(() => {
  const error = integrationQuery.error.value;
  return error instanceof Error ? error.message : "The integration status API could not be loaded.";
});
const integrationNeedsAttention = computed(() =>
  integrationQuery.isError.value || ["not_configured", "failed"].includes(integration.value?.state ?? ""),
);
const integrationTitle = computed(() => {
  const state = integration.value?.state;
  if (state === "ready_for_first_post") return "Ready for your first Facebook post";
  if (state === "draft_saved") return "Facebook draft ready";
  if (state === "awaiting_approval") return "Post ready for approval";
  if (state === "awaiting_facebook_callback") return "Facebook publishing in progress";
  return "Facebook connection diagnostics";
});
const integrationDiagnostic = computed(() => {
  const state = integration.value?.state;
  if (state === "ready_for_first_post") {
    return "n8n is configured, but no social post exists in this Laravel database yet. Save a Facebook draft below to begin.";
  }
  if (state === "draft_saved") {
    return "Your draft exists only in Laravel. Click Send to n8n to start the Facebook workflow.";
  }
  if (state === "awaiting_approval") {
    return "n8n accepted the post and stopped before Facebook as requested. Review the copy, then click Approve and publish.";
  }
  if (state === "awaiting_facebook_callback") {
    return "n8n accepted the approved post. The app is waiting for the Facebook Graph API result and secure callback.";
  }
  if (state === "failed") {
    return integration.value?.latest_post?.error_message || "The latest n8n dispatch failed. Open the post history for the recorded error and retry.";
  }
  return "The Laravel n8n webhook URL or shared secret is not configured on this backend.";
});

watch(
  form,
  () => {
    if (savedDraft.value) savedDraft.value = null;
  },
  { deep: true },
);

function applyTemplate(template: SocialMediaPostTemplate) {
  form.title = template.title;
  form.campaign = template.campaign;
  form.batch_id = template.batch_id;
  form.approval_mode = template.approval_mode;
  form.scheduled_for = template.scheduled_for ?? "";
  form.message = template.message;
  templateFacts.value = template.facts;
}

const templateMutation = useMutation({
  mutationFn: () => getSocialMediaPostTemplate({ batch_id: form.batch_id }),
  onSuccess: (payload) => {
    applyTemplate(payload.data);
    toast.success("Facebook draft generated", {
      description: "The message now uses the selected batch information.",
    });
  },
  onError: (error) => {
    toast.error(error instanceof Error ? error.message : "Unable to generate the Facebook draft.");
  },
});

const createMutation = useMutation({
  mutationFn: () =>
    createSocialMediaPost({
      title: form.title.trim(),
      message: form.message.trim(),
      channel: "facebook",
      campaign: form.campaign.trim() || null,
      batch_id: form.batch_id,
      approval_mode: form.approval_mode,
      scheduled_for: form.scheduled_for || null,
      metadata: {
        source: "social-posts-module",
        template_facts: templateFacts.value,
      },
    }),
  onSuccess: (payload) => {
    savedDraft.value = payload.data;
    void queryClient.invalidateQueries({ queryKey: ["social-media-posts"] });
    void queryClient.invalidateQueries({ queryKey: ["social-media-integration-status"] });
    toast.success("Draft saved", {
      description: "It is ready to send to the n8n Facebook workflow.",
    });
  },
  onError: (error) => {
    toast.error(error instanceof Error ? error.message : "Unable to save social post.");
  },
});

const dispatchMutation = useMutation({
  mutationFn: ({ postId, approve = false }: { postId: number; approve?: boolean }) =>
    dispatchSocialMediaPost(postId, approve ? "pre_approved" : undefined),
  onSuccess: (payload) => {
    savedDraft.value = payload.data;
    void queryClient.invalidateQueries({ queryKey: ["social-media-posts"] });
    void queryClient.invalidateQueries({ queryKey: ["social-media-integration-status"] });
    toast.success("Sent to n8n", {
      description: "The Facebook workflow accepted this post.",
    });
  },
  onError: (error) => {
    toast.error(error instanceof Error ? error.message : "Unable to send post to n8n.");
  },
});

function startNewPost() {
  form.title = "";
  form.campaign = "";
  form.batch_id = null;
  form.approval_mode = "approval_required";
  form.scheduled_for = "";
  form.message = "";
  templateFacts.value = null;
  savedDraft.value = null;
  advancedOpen.value = false;
  activeTab.value = "create";
}

function statusClass(status: string) {
  if (status === "published") return "bg-[#e6f4ea] text-[#1e7e34]";
  if (status === "sent_to_n8n" || status === "queued" || status === "scheduled") return "bg-[#e7f3ff] text-[#1877f2]";
  if (status === "failed") return "bg-[#fde8e8] text-[#b42318]";
  return "bg-[#e4e6eb] text-[#65676b]";
}

function statusLabel(status: string) {
  const labels: Record<string, string> = {
    draft: "Draft",
    queued: "Sending",
    sent_to_n8n: "Sent to n8n",
    scheduled: "Scheduled",
    failed: "Failed",
    published: "Published",
  };
  return labels[status] ?? status.replaceAll("_", " ");
}

function formatDate(value: string | null) {
  return value ? new Date(value).toLocaleString() : "Publish after approval";
}
</script>

<template>
  <div class="-m-4 min-h-screen bg-[#f0f2f5] text-[#050505] sm:-m-6">
    <div class="border-b border-[#d8dadf] bg-white shadow-sm">
      <div class="mx-auto max-w-6xl px-3 pt-3 sm:px-6">
        <div
          class="h-44 rounded-lg bg-cover bg-center sm:h-64"
          :style="{ backgroundImage: `linear-gradient(to top, rgba(0,0,0,.35), transparent 55%), url(${pageCover})` }"
        />

        <div class="relative px-3 pb-3 sm:px-8">
          <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between">
            <div class="flex flex-col items-center sm:flex-row sm:items-end">
              <div class="-mt-12 grid size-32 shrink-0 place-items-center overflow-hidden rounded-full border-4 border-white bg-white shadow sm:-mt-8 sm:size-40">
                <img :src="pagePicture" :alt="pageName" class="size-full object-contain p-2" />
              </div>
              <div class="pb-3 text-center sm:ml-4 sm:text-left">
                <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ pageName }}</h1>
                <p class="mt-1 text-sm font-medium text-[#65676b]">
                  <template v-if="facebookPage?.followers_count != null">{{ facebookPage.followers_count.toLocaleString() }} followers · </template>
                  <template v-if="facebookPage?.fan_count != null">{{ facebookPage.fan_count.toLocaleString() }} likes</template>
                  <template v-if="facebookPage?.followers_count == null && facebookPage?.fan_count == null">Official scholarship announcements and updates</template>
                </p>
                <p :class="['mt-1 text-xs font-medium', integration?.state === 'connected' ? 'text-[#1e7e34]' : 'text-[#65676b]']">
                  {{ pageConnectionLabel }}
                </p>
              </div>
            </div>
            <div class="flex justify-center gap-2 pb-3 sm:justify-end">
              <a
                v-if="facebookPage?.url"
                :href="facebookPage.url"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex h-9 items-center gap-2 rounded-md bg-[#e4e6eb] px-4 text-sm font-semibold text-[#050505] hover:bg-[#d8dadf]"
              >
                <IconBrandFacebook :size="16" />Open Facebook Page
              </a>
              <button
                class="inline-flex h-9 items-center gap-2 rounded-md bg-[#1877f2] px-4 text-sm font-semibold text-white hover:bg-[#166fe5]"
                @click="activeTab = 'create'"
              >
                <IconFileText :size="16" />Create post
              </button>
              <button
                class="grid size-9 place-items-center rounded-md bg-[#e4e6eb] text-[#050505] hover:bg-[#d8dadf]"
                aria-label="More page actions"
              >
                <IconDots :size="18" />
              </button>
            </div>
          </div>

          <div class="border-t border-[#ced0d4]">
            <nav class="flex items-center gap-1 overflow-x-auto pt-1">
              <button
                :class="[
                  'relative h-12 shrink-0 px-4 text-sm font-semibold',
                  activeTab === 'create' ? 'text-[#1877f2]' : 'text-[#65676b] hover:bg-[#f2f3f5]',
                ]"
                @click="activeTab = 'create'"
              >
                Create post
                <span v-if="activeTab === 'create'" class="absolute inset-x-1 bottom-0 h-[3px] rounded-t bg-[#1877f2]" />
              </button>
              <button
                :class="[
                  'relative h-12 shrink-0 px-4 text-sm font-semibold',
                  activeTab === 'history' ? 'text-[#1877f2]' : 'text-[#65676b] hover:bg-[#f2f3f5]',
                ]"
                @click="activeTab = 'history'"
              >
                Posts
                <span v-if="activeTab === 'history'" class="absolute inset-x-1 bottom-0 h-[3px] rounded-t bg-[#1877f2]" />
              </button>
              <span class="h-12 px-4 py-4 text-sm font-semibold text-[#65676b]">About</span>
              <span class="h-12 px-4 py-4 text-sm font-semibold text-[#65676b]">More</span>
            </nav>
          </div>
        </div>
      </div>
    </div>

    <main class="mx-auto max-w-6xl px-3 py-5 sm:px-6">
      <section
        v-if="integrationQuery.isError.value || (integration && integration.state !== 'connected')"
        :class="[
          'mb-4 rounded-lg border bg-white p-4 shadow-sm',
          integrationNeedsAttention ? 'border-[#f5a6a6]' : 'border-[#b7d7ff]',
        ]"
      >
        <div class="flex items-start gap-3">
          <span
            :class="[
              'grid size-9 shrink-0 place-items-center rounded-full',
              integrationNeedsAttention ? 'bg-[#fde8e8] text-[#b42318]' : 'bg-[#e7f3ff] text-[#1877f2]',
            ]"
          >
            <IconAlertTriangle :size="18" />
          </span>
          <div class="min-w-0 flex-1">
            <h2 class="text-sm font-bold">{{ integrationTitle }}</h2>
            <p v-if="integrationQuery.isError.value" class="mt-1 break-words text-sm text-[#b42318]">
              {{ integrationError }}
            </p>
            <p v-else class="mt-1 text-sm text-[#65676b]">
              {{ integrationDiagnostic }}
            </p>
            <div class="mt-3 flex flex-wrap gap-2">
              <button
                class="h-8 rounded-md bg-[#e4e6eb] px-3 text-xs font-semibold"
                :disabled="integrationQuery.isFetching.value"
                @click="integrationQuery.refetch()"
              >
                {{ integrationQuery.isFetching.value ? 'Checking...' : 'Check again' }}
              </button>
              <span v-if="integration?.state === 'ready_for_first_post'" class="self-center text-xs text-[#65676b]">This is a setup step, not an error.</span>
            </div>
          </div>
        </div>
      </section>

      <div v-if="activeTab === 'create'" class="grid gap-4 lg:grid-cols-[280px_minmax(0,1fr)_280px]">
        <aside class="order-2 space-y-4 lg:order-1">
          <section class="rounded-lg bg-white p-4 shadow-sm">
            <h2 class="text-lg font-bold">Page details</h2>
            <div class="mt-4 space-y-3 text-sm text-[#65676b]">
              <p class="flex items-start gap-3">
                <IconBrandFacebook :size="20" class="shrink-0 text-[#1877f2]" />
                <span>Posts publish to {{ pageName }} through the connected n8n workflow.</span>
              </p>
              <p class="flex items-start gap-3">
                <IconShieldCheck :size="20" class="shrink-0 text-[#42b72a]" />
                <span v-if="integration?.n8n_configured">n8n configuration is active; Facebook credentials remain inside n8n.</span>
                <span v-else>Configure the n8n webhook before sending posts.</span>
              </p>
              <p class="flex items-start gap-3">
                <IconWorld :size="20" class="shrink-0" />
                <span>All published posts are public.</span>
              </p>
            </div>
            <div v-if="integration" class="mt-4 grid grid-cols-3 gap-2 border-t border-[#e4e6eb] pt-4 text-center">
              <div><p class="text-lg font-bold">{{ integration.counts.published }}</p><p class="text-[10px] text-[#65676b]">Published</p></div>
              <div><p class="text-lg font-bold">{{ integration.counts.processing }}</p><p class="text-[10px] text-[#65676b]">Processing</p></div>
              <div><p class="text-lg font-bold">{{ integration.counts.drafts }}</p><p class="text-[10px] text-[#65676b]">Drafts</p></div>
            </div>
          </section>

          <section v-if="selectedBatch" class="rounded-lg bg-white p-4 shadow-sm">
            <h2 class="text-lg font-bold">Selected batch</h2>
            <p class="mt-3 text-sm font-semibold">{{ selectedBatch.name }}</p>
            <p class="mt-1 text-sm text-[#65676b]">{{ selectedBatch.academic_year }} · {{ selectedBatch.semester }}</p>
            <div class="mt-3 rounded-lg bg-[#f0f2f5] p-3 text-sm">
              <p><strong>{{ selectedBatch.grantees_count.toLocaleString() }}</strong> grantees</p>
              <p class="mt-1 text-xs text-[#65676b]">Deadline: {{ formatDate(selectedBatch.submission_deadline) }}</p>
            </div>
          </section>
        </aside>

        <section class="order-1 space-y-4 lg:order-2">
          <div class="rounded-lg bg-white shadow-sm">
            <div class="border-b border-[#e4e6eb] px-4 py-3 text-center">
              <h2 class="text-base font-bold">Create post</h2>
            </div>

            <div class="p-4">
              <div class="flex items-center gap-3">
                <img :src="pagePicture" :alt="pageName" class="size-10 rounded-full border bg-white object-contain p-1" />
                <div>
                  <p class="text-sm font-semibold">{{ pageName }}</p>
                  <span class="mt-0.5 inline-flex items-center gap-1 rounded-md bg-[#e4e6eb] px-2 py-0.5 text-xs font-semibold text-[#65676b]">
                    <IconWorld :size="11" />Public
                  </span>
                </div>
              </div>

              <label class="mt-4 block">
                <span class="sr-only">Facebook message</span>
                <textarea
                  v-model="form.message"
                  class="min-h-52 w-full resize-y border-0 bg-transparent p-0 text-lg leading-7 text-[#050505] outline-none placeholder:text-[#65676b] focus:ring-0"
                  placeholder="What do you want to announce?"
                />
              </label>

              <div class="mt-2 flex items-center justify-between text-xs text-[#65676b]">
                <span>{{ characterCount.toLocaleString() }} characters</span>
                <span>Public information only</span>
              </div>

              <div class="mt-4 rounded-lg border border-[#ced0d4] p-3">
                <div class="flex flex-col gap-2 sm:flex-row">
                  <select
                    v-model.number="form.batch_id"
                    class="h-10 min-w-0 flex-1 rounded-md border border-[#ced0d4] bg-white px-3 text-sm"
                    :disabled="batchesQuery.isLoading.value"
                  >
                    <option :value="null">General announcement</option>
                    <option v-for="batch in batches" :key="batch.id" :value="batch.id">
                      {{ batch.name }} · {{ batch.academic_year }}
                    </option>
                  </select>
                  <button
                    class="h-10 rounded-md bg-[#e7f3ff] px-4 text-sm font-semibold text-[#1877f2] hover:bg-[#dbeeff] disabled:opacity-60"
                    :disabled="templateMutation.isPending.value"
                    @click="templateMutation.mutate()"
                  >
                    {{ templateMutation.isPending.value ? "Generating..." : "Generate from batch" }}
                  </button>
                </div>
              </div>

              <label class="mt-4 block text-xs font-semibold text-[#65676b]">
                Internal title
                <input
                  v-model="form.title"
                  class="mt-1.5 h-10 w-full rounded-md border border-[#ced0d4] px-3 text-sm text-[#050505]"
                  placeholder="Example: Batch 1 release announcement"
                />
              </label>

              <div class="mt-4 flex items-center justify-between rounded-lg border border-[#ced0d4] px-3 py-2">
                <span class="text-sm font-semibold">Add to your post</span>
                <div class="flex items-center gap-1">
                  <span class="grid size-9 place-items-center rounded-full bg-[#e7f3ff] text-[#1877f2]" title="Facebook post">
                    <IconBrandFacebook :size="19" />
                  </span>
                  <span class="grid size-9 place-items-center rounded-full bg-[#eaf7e6] text-[#42b72a]" title="Media can be added in n8n">
                    <IconPhoto :size="19" />
                  </span>
                  <button class="grid size-9 place-items-center rounded-full bg-[#f0f2f5] text-[#65676b]" @click="advancedOpen = !advancedOpen">
                    <IconDots :size="19" />
                  </button>
                </div>
              </div>

              <div v-if="advancedOpen" class="mt-3 grid gap-3 rounded-lg bg-[#f0f2f5] p-3 sm:grid-cols-2">
                <label class="text-xs font-semibold text-[#65676b]">
                  Campaign code
                  <input v-model="form.campaign" class="mt-1.5 h-9 w-full rounded-md border border-[#ced0d4] bg-white px-3 text-sm text-[#050505]" placeholder="batch_1_release" />
                </label>
                <label class="text-xs font-semibold text-[#65676b]">
                  Schedule
                  <input v-model="form.scheduled_for" type="datetime-local" class="mt-1.5 h-9 w-full rounded-md border border-[#ced0d4] bg-white px-3 text-sm text-[#050505]" />
                </label>
              </div>

              <div class="mt-4 grid gap-2 sm:grid-cols-2">
                <label
                  :class="[
                    'cursor-pointer rounded-lg border p-3',
                    form.approval_mode === 'approval_required' ? 'border-[#1877f2] bg-[#e7f3ff]' : 'border-[#ced0d4]',
                  ]"
                >
                  <span class="flex items-start gap-2">
                    <input v-model="form.approval_mode" type="radio" value="approval_required" class="mt-0.5" />
                    <span>
                      <span class="block text-sm font-semibold">Review first</span>
                      <span class="mt-1 block text-xs text-[#65676b]">Stop in n8n for approval.</span>
                    </span>
                  </span>
                </label>
                <label
                  :class="[
                    'cursor-pointer rounded-lg border p-3',
                    form.approval_mode === 'pre_approved' ? 'border-[#f5a623] bg-[#fff8e6]' : 'border-[#ced0d4]',
                  ]"
                >
                  <span class="flex items-start gap-2">
                    <input v-model="form.approval_mode" type="radio" value="pre_approved" class="mt-0.5" />
                    <span>
                      <span class="block text-sm font-semibold">Approved to publish</span>
                      <span class="mt-1 block text-xs text-[#65676b]">Allow n8n to post.</span>
                    </span>
                  </span>
                </label>
              </div>

              <button
                v-if="!savedDraft"
                class="mt-4 h-10 w-full rounded-md bg-[#1877f2] text-sm font-semibold text-white hover:bg-[#166fe5] disabled:bg-[#e4e6eb] disabled:text-[#bcc0c4]"
                :disabled="!hasContent || createMutation.isPending.value"
                @click="createMutation.mutate()"
              >
                {{ createMutation.isPending.value ? "Saving draft..." : "Save Facebook draft" }}
              </button>

              <div v-else class="mt-4 rounded-lg border border-[#b7e1aa] bg-[#f0faed] p-4">
                <div class="flex items-start gap-3">
                  <span class="grid size-9 shrink-0 place-items-center rounded-full bg-[#42b72a] text-white">
                    <IconCheck :size="18" />
                  </span>
                  <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold">
                      {{ savedDraft.status === 'sent_to_n8n' ? 'Sent to the Facebook workflow' : 'Draft saved' }}
                    </p>
                    <p class="mt-1 text-xs text-[#65676b]">
                      {{ savedDraft.status === 'sent_to_n8n' ? 'Open n8n to review the execution and Facebook result.' : 'Send the saved post to n8n when ready.' }}
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2">
                      <button
                        v-if="['draft', 'failed'].includes(savedDraft.status)"
                        class="inline-flex h-9 items-center gap-2 rounded-md bg-[#1877f2] px-4 text-sm font-semibold text-white disabled:opacity-60"
                        :disabled="dispatchMutation.isPending.value"
                        @click="dispatchMutation.mutate({ postId: savedDraft.id })"
                      >
                        <IconSend :size="15" />{{ dispatchMutation.isPending.value ? "Sending..." : "Send to n8n" }}
                      </button>
                      <button
                        v-if="savedDraft.status === 'sent_to_n8n' && savedDraft.approval_mode === 'approval_required'"
                        class="inline-flex h-9 items-center gap-2 rounded-md bg-[#1877f2] px-4 text-sm font-semibold text-white disabled:opacity-60"
                        :disabled="dispatchMutation.isPending.value"
                        @click="dispatchMutation.mutate({ postId: savedDraft.id, approve: true })"
                      >
                        <IconShieldCheck :size="15" />{{ dispatchMutation.isPending.value ? "Publishing..." : "Approve and publish" }}
                      </button>
                      <button class="h-9 rounded-md bg-[#e4e6eb] px-4 text-sm font-semibold" @click="activeTab = 'history'">View posts</button>
                      <button class="h-9 rounded-md bg-[#e4e6eb] px-4 text-sm font-semibold" @click="startNewPost">New post</button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <article v-if="form.message" class="rounded-lg bg-white shadow-sm">
            <div class="flex items-center justify-between p-4 pb-2">
              <div class="flex items-center gap-3">
                <img :src="pagePicture" :alt="pageName" class="size-10 rounded-full border bg-white object-contain p-1" />
                <div>
                  <p class="text-sm font-semibold">{{ pageName }}</p>
                  <p class="flex items-center gap-1 text-xs text-[#65676b]">Preview · <IconWorld :size="12" /></p>
                </div>
              </div>
              <IconDots :size="20" class="text-[#65676b]" />
            </div>
            <p class="whitespace-pre-line px-4 py-2 text-[15px] leading-6">{{ form.message }}</p>
            <div class="mt-3 flex border-t border-[#e4e6eb] px-4 py-1 text-sm font-semibold text-[#65676b]">
              <span class="flex-1 rounded-md py-2 text-center">Like</span>
              <span class="flex-1 rounded-md py-2 text-center">Comment</span>
              <span class="flex-1 rounded-md py-2 text-center">Share</span>
            </div>
          </article>
        </section>

        <aside class="order-3 space-y-4">
          <section class="rounded-lg bg-white p-4 shadow-sm">
            <h2 class="text-lg font-bold">Publishing</h2>
            <div class="mt-4 space-y-4">
              <div class="flex gap-3">
                <span class="grid size-8 shrink-0 place-items-center rounded-full bg-[#e7f3ff] text-sm font-bold text-[#1877f2]">1</span>
                <div><p class="text-sm font-semibold">Save the draft</p><p class="mt-0.5 text-xs text-[#65676b]">Keeps a record in UniFAST.</p></div>
              </div>
              <div class="flex gap-3">
                <span class="grid size-8 shrink-0 place-items-center rounded-full bg-[#e7f3ff] text-sm font-bold text-[#1877f2]">2</span>
                <div><p class="text-sm font-semibold">Send to n8n</p><p class="mt-0.5 text-xs text-[#65676b]">Starts the Facebook workflow.</p></div>
              </div>
              <div class="flex gap-3">
                <span class="grid size-8 shrink-0 place-items-center rounded-full bg-[#e7f3ff] text-sm font-bold text-[#1877f2]">3</span>
                <div><p class="text-sm font-semibold">Publish to Facebook</p><p class="mt-0.5 text-xs text-[#65676b]">n8n uses the Page token.</p></div>
              </div>
            </div>
          </section>

          <section class="rounded-lg bg-white p-4 shadow-sm">
            <h2 class="text-lg font-bold">Post settings</h2>
            <div class="mt-3 divide-y divide-[#e4e6eb] text-sm">
              <div class="flex items-center justify-between py-3"><span class="text-[#65676b]">Audience</span><span class="font-semibold">Public</span></div>
              <div class="flex items-center justify-between py-3"><span class="text-[#65676b]">Approval</span><span class="font-semibold">{{ form.approval_mode === 'approval_required' ? 'Required' : 'Approved' }}</span></div>
              <div class="flex items-center justify-between py-3"><span class="text-[#65676b]">Schedule</span><span class="max-w-32 text-right text-xs font-semibold">{{ formatDate(form.scheduled_for || null) }}</span></div>
            </div>
          </section>
        </aside>
      </div>

      <section v-else class="grid gap-4 lg:grid-cols-[280px_minmax(0,1fr)]">
        <aside class="space-y-4">
          <div class="rounded-lg bg-white p-4 shadow-sm">
            <h2 class="text-lg font-bold">Manage posts</h2>
            <button class="mt-4 h-10 w-full rounded-md bg-[#1877f2] text-sm font-semibold text-white" @click="startNewPost">Create new post</button>
            <label class="mt-4 block text-xs font-semibold text-[#65676b]">
              Show
              <select v-model="selectedStatus" class="mt-1.5 h-10 w-full rounded-md border border-[#ced0d4] bg-white px-3 text-sm text-[#050505]">
                <option value="all">All posts</option>
                <option value="draft">Drafts</option>
                <option value="sent_to_n8n">Sent to n8n</option>
                <option value="scheduled">Scheduled</option>
                <option value="failed">Failed</option>
                <option value="published">Published</option>
              </select>
            </label>
            <button
              class="mt-3 inline-flex h-9 w-full items-center justify-center gap-2 rounded-md bg-[#e4e6eb] text-sm font-semibold disabled:opacity-50"
              :disabled="postsQuery.isFetching.value"
              @click="postsQuery.refetch()"
            >
              <IconRefresh :size="15" />Refresh posts
            </button>
          </div>
        </aside>

        <div class="space-y-4">
          <template v-if="postsQuery.isLoading.value">
            <CardSkeleton v-for="i in 3" :key="i" :lines="5" />
          </template>
          <div v-else-if="postsQuery.isError.value" class="rounded-lg bg-white p-4 shadow-sm">
            <EmptyState
              variant="error"
              title="Couldn't load Facebook posts"
              :hint="postsQuery.error.value instanceof Error ? postsQuery.error.value.message : 'Unable to load posts.'"
              @retry="postsQuery.refetch()"
            />
          </div>
          <template v-else>
            <article v-for="post in posts" :key="post.id" class="rounded-lg bg-white shadow-sm">
              <div class="flex items-start justify-between p-4 pb-2">
                <div class="flex min-w-0 items-center gap-3">
                  <img :src="pagePicture" :alt="pageName" class="size-10 rounded-full border bg-white object-contain p-1" />
                  <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                      <p class="truncate text-sm font-semibold">{{ pageName }}</p>
                      <span :class="['rounded-full px-2 py-0.5 text-[10px] font-semibold', statusClass(post.status)]">{{ statusLabel(post.status) }}</span>
                    </div>
                    <p class="flex flex-wrap items-center gap-1 text-xs text-[#65676b]">
                      {{ formatDate(post.created_at) }} · <IconWorld :size="12" />
                      <span v-if="post.batch">· {{ post.batch.name }}</span>
                    </p>
                  </div>
                </div>
                <IconDots :size="20" class="text-[#65676b]" />
              </div>

              <div class="px-4 py-2">
                <p class="mb-2 text-xs font-semibold text-[#65676b]">{{ post.title }}</p>
                <p class="whitespace-pre-line text-[15px] leading-6">{{ post.message }}</p>
                <p v-if="post.error_message" class="mt-3 rounded-md bg-[#fde8e8] p-2 text-xs text-[#b42318]">{{ post.error_message }}</p>
              </div>

              <div v-if="post.external_permalink" class="px-4 pb-3">
                <a
                  :href="post.external_permalink"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="inline-flex items-center gap-2 text-sm font-semibold text-[#1877f2] hover:underline"
                >
                  <IconBrandFacebook :size="15" />View the real Facebook post
                </a>
              </div>

              <div class="mt-3 border-t border-[#e4e6eb] p-2">
                <button
                  v-if="['draft', 'failed'].includes(post.status)"
                  class="inline-flex h-9 w-full items-center justify-center gap-2 rounded-md bg-[#e7f3ff] text-sm font-semibold text-[#1877f2] disabled:opacity-50"
                  :disabled="dispatchMutation.isPending.value"
                  @click="dispatchMutation.mutate({ postId: post.id })"
                >
                  <IconSend :size="15" />Send to n8n Facebook workflow
                </button>
                <button
                  v-else-if="post.status === 'sent_to_n8n' && post.approval_mode === 'approval_required'"
                  class="inline-flex h-9 w-full items-center justify-center gap-2 rounded-md bg-[#1877f2] text-sm font-semibold text-white disabled:opacity-50"
                  :disabled="dispatchMutation.isPending.value"
                  @click="dispatchMutation.mutate({ postId: post.id, approve: true })"
                >
                  <IconShieldCheck :size="15" />Approve and publish
                </button>
                <div v-else class="flex items-center justify-center gap-2 py-2 text-sm font-semibold text-[#65676b]">
                  <IconCalendarTime :size="15" />{{ formatDate(post.scheduled_for) }}
                </div>
              </div>
            </article>
            <div v-if="!posts.length" class="rounded-lg bg-white p-4 shadow-sm">
              <EmptyState title="No posts found" hint="Create a Facebook announcement to start the publishing workflow." />
            </div>
          </template>

          <div v-if="meta && meta.last_page > 1" class="flex items-center justify-between rounded-lg bg-white p-3 text-sm text-[#65676b] shadow-sm">
            <span>Page {{ meta.current_page }} of {{ meta.last_page }}</span>
            <div class="flex gap-2">
              <button class="rounded-md bg-[#e4e6eb] px-3 py-1.5 font-semibold disabled:opacity-40" :disabled="meta.current_page <= 1" @click="page = meta.current_page - 1">Previous</button>
              <button class="rounded-md bg-[#e4e6eb] px-3 py-1.5 font-semibold disabled:opacity-40" :disabled="meta.current_page >= meta.last_page" @click="page = meta.current_page + 1">Next</button>
            </div>
          </div>
        </div>
      </section>
    </main>
  </div>
</template>
