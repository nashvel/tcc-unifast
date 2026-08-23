<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref, watch } from "vue";
import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/vue-query";
import {
  IconAlertTriangle,
  IconArrowLeft,
  IconBrandFacebook,
  IconCalendarTime,
  IconCheck,
  IconChevronDown,
  IconMessageCircle,
  IconDots,
  IconFileText,
  IconHistory,
  IconShare3,
  IconPhoto,
  IconRefresh,
  IconSend,
  IconShieldCheck,
  IconSparkles,
  IconWorld,
  IconX,
} from "@tabler/icons-vue";
import CardSkeleton from "@/components/ui/CardSkeleton.vue";
import EmptyState from "@/components/ui/EmptyState.vue";
import {
  commentOnSocialMediaPost,
  createSocialMediaPost,
  dispatchSocialMediaPost,
  getSocialMediaIntegrationStatus,
  getSocialMediaPostTemplate,
  listBatches,
  listSocialMediaPostComments,
  listSocialMediaPosts,
  reactToSocialMediaPost,
  refreshSocialMediaPageProfile,
  syncFacebookPagePosts,
} from "@/api";
import { queryKeys } from "@/api/queryKeys";
import type { PaginatedResponse, SocialMediaComment, SocialMediaPost, SocialMediaPostTemplate } from "@/api";
import { toast } from "@/composables/useToast";

const queryClient = useQueryClient();
type PageTab = "all" | "posts" | "about" | "reels" | "photos" | "followers";

const activeTab = ref<PageTab>("all");
const pageTabs: Array<{ key: Exclude<PageTab, "posts">; label: string }> = [
  { key: "all", label: "All" },
  { key: "about", label: "About" },
  { key: "reels", label: "Reels" },
  { key: "photos", label: "Photos" },
  { key: "followers", label: "Followers" },
];
const page = ref(1);
const selectedStatus = ref("all");
const advancedOpen = ref(false);
const createPostModalOpen = ref(false);
const createPostModalView = ref<"composer" | "templates">("composer");
const facebookPostsAutoSynced = ref(false);
const savedDraft = ref<SocialMediaPost | null>(null);
const openCommentsPostId = ref<number | null>(null);
const commentsByPost = reactive<Record<number, SocialMediaComment[]>>({});
const commentDrafts = reactive<Record<number, string>>({});
const commentsLoadingPostId = ref<number | null>(null);
let bodyOverflowBeforeModal: string | null = null;

type PostTemplatePresetKey = "general" | "deadline" | "requirements" | "release";

const form = reactive({
  title: "",
  campaign: "",
  batch_id: null as number | null,
  approval_mode: "approval_required" as "approval_required" | "pre_approved",
  scheduled_for: "",
  message: "",
});

const templateFacts = ref<SocialMediaPostTemplate["facts"] | null>(null);
const selectedTemplatePreset = ref<PostTemplatePresetKey | null>(null);
const postTemplatePresets: Array<{
  key: PostTemplatePresetKey;
  label: string;
  description: string;
  campaign: string;
}> = [
  {
    key: "general",
    label: "General advisory",
    description: "Broad official TES announcement.",
    campaign: "general_advisory",
  },
  {
    key: "deadline",
    label: "Deadline reminder",
    description: "Urgent reminder before submission closes.",
    campaign: "deadline_reminder",
  },
  {
    key: "requirements",
    label: "Requirements notice",
    description: "Documents and portal instruction update.",
    campaign: "requirements_notice",
  },
  {
    key: "release",
    label: "Release update",
    description: "Batch or scholarship status update.",
    campaign: "release_update",
  },
];

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
const totalEngagement = computed(() =>
  posts.value.reduce(
    (total, post) => ({
      reactions: total.reactions + (post.engagement?.reactions ?? 0),
      comments: total.comments + (post.engagement?.comments ?? 0),
      shares: total.shares + (post.engagement?.shares ?? 0),
    }),
    { reactions: 0, comments: 0, shares: 0 },
  ),
);
const engagementNotifications = computed(() =>
  posts.value
    .filter((post) => (post.engagement?.reactions ?? 0) + (post.engagement?.comments ?? 0) + (post.engagement?.shares ?? 0) > 0)
    .slice(0, 4),
);
const batches = computed(() => batchesQuery.data.value?.data ?? []);
const selectedBatch = computed(() => batches.value.find((batch) => batch.id === form.batch_id) ?? null);
const characterCount = computed(() => form.message.length);
const hasContent = computed(() => form.title.trim().length > 0 && form.message.trim().length >= 20);
const integration = computed(() => integrationQuery.data.value?.data ?? null);
const facebookPage = computed(() => integration.value?.page ?? null);
const hasVerifiedFacebookPage = computed(() => Boolean(facebookPage.value?.name?.trim() && facebookPage.value?.id?.trim()));
const pageName = computed(() => facebookPage.value?.name?.trim() || "");
const pageDisplayName = computed(() => pageName.value || "Facebook Page not loaded");
const pagePicture = computed(() => (hasVerifiedFacebookPage.value ? facebookPage.value?.picture_url : null));
const pageCover = computed(() => (hasVerifiedFacebookPage.value ? facebookPage.value?.cover_url : null));
const pageCoverStyle = computed(() => {
  return pageCover.value
    ? { backgroundImage: `linear-gradient(to top, rgba(0,0,0,.35), transparent 55%), url(${pageCover.value})` }
    : {};
});
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

watch(createPostModalOpen, (isOpen) => {
  if (typeof document === "undefined") return;

  if (isOpen) {
    bodyOverflowBeforeModal = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    return;
  }

  document.body.style.overflow = bodyOverflowBeforeModal ?? "";
  bodyOverflowBeforeModal = null;
});

onBeforeUnmount(() => {
  if (typeof document === "undefined") return;
  if (bodyOverflowBeforeModal !== null) document.body.style.overflow = bodyOverflowBeforeModal;
});

function applyTemplate(template: SocialMediaPostTemplate) {
  selectedTemplatePreset.value = null;
  form.title = template.title;
  form.campaign = template.campaign;
  form.batch_id = template.batch_id;
  form.approval_mode = template.approval_mode;
  form.scheduled_for = template.scheduled_for ?? "";
  form.message = template.message;
  templateFacts.value = template.facts;
}

function applyTemplatePreset(key: PostTemplatePresetKey) {
  const batch = selectedBatch.value;
  const batchName = batch?.name ?? "UniFAST TES";
  const academicContext = batch ? `${batch.academic_year} ${batch.semester}` : "the current application period";
  const deadline = batch?.submission_deadline ? formatDate(batch.submission_deadline) : "the announced deadline";
  const portalUrl = "https://tcc-unifast.nashvel.online/login";
  const templates: Record<PostTemplatePresetKey, { title: string; message: string }> = {
    general: {
      title: `${batchName} Advisory`,
      message: [
        `TCC UniFAST TES Advisory: ${batchName}`,
        `Students covered by ${academicContext} are advised to check the UniFAST TES portal for official updates, account status, and required actions.`,
        `Use the official portal only: ${portalUrl}`,
        "For assistance, contact the TCC UniFAST/TES office through official school channels.",
        "#TCCUniFAST #TES #TagoloanCommunityCollege",
      ].join("\n\n"),
    },
    deadline: {
      title: `${batchName} Deadline Reminder`,
      message: [
        `Reminder: ${batchName}`,
        `TES grantees for ${academicContext} must complete required portal actions before ${deadline}. Late or incomplete submissions may affect processing.`,
        `Portal: ${portalUrl}`,
        "Please review your account and submit only accurate information.",
        "#TCCUniFAST #TES #StudentAdvisory",
      ].join("\n\n"),
    },
    requirements: {
      title: `${batchName} Requirements Notice`,
      message: [
        `Requirements Notice: ${batchName}`,
        `Qualified TES grantees for ${academicContext} should prepare and verify all required documents in the UniFAST TES portal.`,
        "Make sure uploaded files are clear, complete, and match your student records.",
        `Portal: ${portalUrl}`,
        "#TCCUniFAST #TES #Requirements",
      ].join("\n\n"),
    },
    release: {
      title: `${batchName} Status Update`,
      message: [
        `TCC UniFAST TES Update: ${batchName}`,
        `The scholarship office has posted an update for ${academicContext}. Students are advised to sign in to the portal and review their current status.`,
        `Portal: ${portalUrl}`,
        "Follow this Page for official TES announcements from TCC UniFAST.",
        "#TCCUniFAST #TES #ScholarshipUpdate",
      ].join("\n\n"),
    },
  };
  const preset = postTemplatePresets.find((item) => item.key === key);
  const template = templates[key];

  selectedTemplatePreset.value = key;
  form.title = template.title;
  form.campaign = preset?.campaign ?? key;
  form.message = template.message;
  templateFacts.value = null;
  createPostModalView.value = "composer";
  toast.success("Template applied", {
    description: `${preset?.label ?? "Template"} is ready to edit.`,
  });
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

const refreshPageMutation = useMutation({
  mutationFn: refreshSocialMediaPageProfile,
  onSuccess: () => {
    void queryClient.invalidateQueries({ queryKey: ["social-media-integration-status"] });
    toast.success("Fetching Facebook Page profile", {
      description: "n8n is loading the real Page name, profile image, and cover.",
    });
    window.setTimeout(() => {
      void integrationQuery.refetch();
    }, 2500);
  },
  onError: (error) => {
    toast.error(error instanceof Error ? error.message : "Unable to fetch the Facebook Page profile.");
  },
});

const syncFacebookPostsMutation = useMutation({
  mutationFn: syncFacebookPagePosts,
  onSuccess: (payload) => {
    void queryClient.invalidateQueries({ queryKey: ["social-media-posts"] });
    void queryClient.invalidateQueries({ queryKey: ["social-media-integration-status"] });
    toast.success("Facebook posts synced", {
      description: `${payload.count} Page post${payload.count === 1 ? "" : "s"} loaded into UniFAST.`,
    });
  },
  onError: (error) => {
    toast.error(error instanceof Error ? error.message : "Unable to sync Facebook Page posts.");
  },
});

const reactPostMutation = useMutation({
  mutationFn: (post: SocialMediaPost) => reactToSocialMediaPost(post.id),
  onMutate: async (post) => {
    await queryClient.cancelQueries({ queryKey: ["social-media-posts"] });
    const snapshots = queryClient.getQueriesData<PaginatedResponse<SocialMediaPost>>({ queryKey: ["social-media-posts"] });

    queryClient.setQueriesData<PaginatedResponse<SocialMediaPost>>({ queryKey: ["social-media-posts"] }, (old) => {
      if (!old) return old;

      return {
        ...old,
        data: old.data.map((item) => {
          if (item.id !== post.id) return item;

          return {
            ...item,
            page_reacted: !item.page_reacted,
            page_reaction_type: item.page_reacted ? null : "LIKE",
            engagement: {
              ...item.engagement,
              reactions: item.page_reacted
                ? Math.max(0, item.engagement.reactions - 1)
                : item.engagement.reactions + 1,
            },
          };
        }),
      };
    });

    return { snapshots };
  },
  onSuccess: (payload) => {
    queryClient.setQueriesData<PaginatedResponse<SocialMediaPost>>({ queryKey: ["social-media-posts"] }, (old) => {
      if (!old) return old;

      return {
        ...old,
        data: old.data.map((post) => (post.id === payload.data.id ? payload.data : post)),
      };
    });
    toast.success(payload.data.page_reacted ? "Liked as Facebook Page" : "Unliked as Facebook Page", {
      description: payload.data.page_reacted
        ? "The Page liked this post on Facebook."
        : "The Page reaction was removed from Facebook.",
    });
  },
  onError: (error, _post, context) => {
    context?.snapshots.forEach(([key, value]) => queryClient.setQueryData(key, value));
    toast.error(error instanceof Error ? error.message : "Unable to react as the Facebook Page.");
  },
  onSettled: () => {
    void queryClient.invalidateQueries({ queryKey: ["social-media-posts"] });
  },
});

const commentPostMutation = useMutation({
  mutationFn: ({ post, message }: { post: SocialMediaPost; message: string }) =>
    commentOnSocialMediaPost(post.id, message),
  onSuccess: (payload, variables) => {
    replacePostInQueryCache(payload.post);
    const existing = commentsByPost[variables.post.id] ?? [];
    commentsByPost[variables.post.id] = payload.data.id
      ? [payload.data, ...existing.filter((comment) => comment.id !== payload.data.id)]
      : existing;
    commentDrafts[variables.post.id] = "";
    toast.success("Comment posted", {
      description: "The Facebook Page commented on this post.",
    });
  },
  onError: (error) => {
    toast.error(error instanceof Error ? error.message : "Unable to comment as the Facebook Page.");
  },
});

watch(
  hasVerifiedFacebookPage,
  (verified) => {
    if (!verified || facebookPostsAutoSynced.value || syncFacebookPostsMutation.isPending.value) return;

    facebookPostsAutoSynced.value = true;
    syncFacebookPostsMutation.mutate();
  },
  { immediate: true },
);

function startNewPost() {
  selectedTemplatePreset.value = null;
  createPostModalView.value = "composer";
  form.title = "";
  form.campaign = "";
  form.batch_id = null;
  form.approval_mode = "approval_required";
  form.scheduled_for = "";
  form.message = "";
  templateFacts.value = null;
  savedDraft.value = null;
  advancedOpen.value = false;
  activeTab.value = "all";
  createPostModalOpen.value = true;
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

function canReactToPost(post: SocialMediaPost) {
  return Boolean(post.external_post_id && post.status === "published");
}

function isReactingPost(post: SocialMediaPost) {
  return reactPostMutation.isPending.value && reactPostMutation.variables.value?.id === post.id;
}

function toggleLikeAsPage(post: SocialMediaPost) {
  if (!canReactToPost(post) || isReactingPost(post)) return;

  reactPostMutation.mutate(post);
}

function replacePostInQueryCache(updatedPost: SocialMediaPost) {
  queryClient.setQueriesData<PaginatedResponse<SocialMediaPost>>({ queryKey: ["social-media-posts"] }, (old) => {
    if (!old) return old;

    return {
      ...old,
      data: old.data.map((post) => (post.id === updatedPost.id ? updatedPost : post)),
    };
  });
}

function canViewFacebookThread(post: SocialMediaPost) {
  return Boolean(post.external_post_id && post.status === "published");
}

function isCommentsLoading(post: SocialMediaPost) {
  return commentsLoadingPostId.value === post.id;
}

function isCommentingPost(post: SocialMediaPost) {
  return commentPostMutation.isPending.value && commentPostMutation.variables.value?.post.id === post.id;
}

async function loadComments(post: SocialMediaPost) {
  if (!canViewFacebookThread(post) || isCommentsLoading(post)) return;

  commentsLoadingPostId.value = post.id;
  try {
    const payload = await listSocialMediaPostComments(post.id, { limit: 25 });
    commentsByPost[post.id] = payload.data;
    replacePostInQueryCache(payload.post);
  } catch (error) {
    toast.error(error instanceof Error ? error.message : "Unable to load Facebook comments.");
  } finally {
    commentsLoadingPostId.value = null;
  }
}

function toggleComments(post: SocialMediaPost) {
  if (!canViewFacebookThread(post)) {
    toast.error("Sync or publish this post to Facebook before loading comments.");
    return;
  }

  if (openCommentsPostId.value === post.id) {
    openCommentsPostId.value = null;
    return;
  }

  openCommentsPostId.value = post.id;
  if (!commentsByPost[post.id]) void loadComments(post);
}

function submitComment(post: SocialMediaPost) {
  const message = (commentDrafts[post.id] ?? "").trim();
  if (!message || !canViewFacebookThread(post) || isCommentingPost(post)) return;

  commentPostMutation.mutate({ post, message });
}

function sharePost(post: SocialMediaPost) {
  if (!post.external_permalink) {
    toast.error("This post does not have a Facebook permalink yet.");
    return;
  }

  const shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(post.external_permalink)}`;
  const opened = window.open(shareUrl, "_blank", "noopener,noreferrer,width=680,height=620");

  if (!opened) {
    void navigator.clipboard?.writeText(post.external_permalink);
    toast.success("Post link copied", {
      description: "Your browser blocked the share window, so the Facebook post link was copied.",
    });
  }
}

function commentAuthorInitial(comment: SocialMediaComment) {
  return comment.author_name.trim().charAt(0).toUpperCase() || "F";
}

function formatDate(value: string | null) {
  return value ? new Date(value).toLocaleString() : "Publish after approval";
}
</script>

<template>
  <div class="-m-4 min-h-screen bg-[#f0f2f5] text-[#050505] sm:-m-6">
    <div class="border-b border-[#d8dadf] bg-white shadow-sm">
      <div class="w-full px-4 pt-3 sm:px-6 lg:px-8">
        <div
          :class="[
            'h-44 rounded-lg bg-cover bg-center sm:h-64',
            hasVerifiedFacebookPage ? 'bg-[#d8dadf]' : 'border border-dashed border-[#ced0d4] bg-white',
          ]"
          :style="pageCoverStyle"
        >
          <div v-if="!hasVerifiedFacebookPage" class="grid h-full place-items-center text-sm font-semibold text-[#65676b]">
            Page cover will appear after Facebook verifies the Page profile.
          </div>
        </div>

        <div class="relative px-3 pb-3 sm:px-8">
          <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between">
            <div class="flex flex-col items-center sm:flex-row sm:items-end">
              <div class="-mt-12 grid size-32 shrink-0 place-items-center overflow-hidden rounded-full border-4 border-white bg-white shadow sm:-mt-8 sm:size-40">
                <img v-if="pagePicture" :src="pagePicture" :alt="pageDisplayName" class="size-full object-cover" />
                <span v-else class="text-center text-xs font-semibold text-[#65676b]">No Page<br />profile</span>
              </div>
              <div class="pb-3 text-center sm:ml-4 sm:text-left">
                <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ pageDisplayName }}</h1>
                <p class="mt-1 text-sm font-medium text-[#65676b]">
                  <template v-if="facebookPage?.followers_count != null">{{ facebookPage.followers_count.toLocaleString() }} followers · </template>
                  <template v-if="facebookPage?.fan_count != null">{{ facebookPage.fan_count.toLocaleString() }} likes</template>
                  <template v-if="!hasVerifiedFacebookPage">No fallback Page is shown. Fetch the real profile from n8n.</template>
                  <template v-else-if="facebookPage?.followers_count == null && facebookPage?.fan_count == null">Verified from n8n Facebook workflow</template>
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
                @click="activeTab = 'all'; createPostModalOpen = true"
              >
                <IconFileText :size="16" />Create post
              </button>
              <button
                v-if="!hasVerifiedFacebookPage"
                class="inline-flex h-9 items-center gap-2 rounded-md bg-[#e7f3ff] px-4 text-sm font-semibold text-[#1877f2] hover:bg-[#dbeeff] disabled:opacity-60"
                :disabled="refreshPageMutation.isPending.value"
                @click="refreshPageMutation.mutate()"
              >
                <IconRefresh :size="16" />{{ refreshPageMutation.isPending.value ? "Fetching..." : "Fetch Page profile" }}
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
                v-for="tab in pageTabs"
                :key="tab.key"
                :class="[
                  'relative h-12 shrink-0 px-4 text-sm font-semibold',
                  activeTab === tab.key ? 'text-[#1877f2]' : 'text-[#65676b] hover:bg-[#f2f3f5]',
                ]"
                @click="activeTab = tab.key"
              >
                {{ tab.label }}
                <span v-if="activeTab === tab.key" class="absolute inset-x-1 bottom-0 h-[3px] rounded-t bg-[#1877f2]" />
              </button>
              <button class="inline-flex h-12 shrink-0 items-center gap-1 rounded-md px-4 text-sm font-semibold text-[#65676b] hover:bg-[#f2f3f5]">
                More
                <IconChevronDown :size="18" />
              </button>
            </nav>
          </div>
        </div>
      </div>
    </div>

    <main class="w-full px-4 py-5 sm:px-6 lg:px-8">
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
              <button
                v-if="!hasVerifiedFacebookPage && integration?.n8n_configured"
                class="h-8 rounded-md bg-[#e7f3ff] px-3 text-xs font-semibold text-[#1877f2] disabled:opacity-60"
                :disabled="refreshPageMutation.isPending.value"
                @click="refreshPageMutation.mutate()"
              >
                {{ refreshPageMutation.isPending.value ? 'Fetching profile...' : 'Fetch real Page profile' }}
              </button>
              <span v-if="integration?.state === 'ready_for_first_post'" class="self-center text-xs text-[#65676b]">This is a setup step, not an error.</span>
            </div>
          </div>
        </div>
      </section>

      <div v-if="activeTab === 'all'" class="grid gap-4 xl:grid-cols-[320px_minmax(0,1fr)_320px]">
        <aside class="order-2 space-y-4 lg:order-1">
          <section class="rounded-lg bg-white p-4 shadow-sm">
            <h2 class="text-lg font-bold">Page details</h2>
            <div class="mt-4 space-y-3 text-sm text-[#65676b]">
              <p class="flex items-start gap-3">
                <IconBrandFacebook :size="20" class="shrink-0 text-[#1877f2]" />
                <span v-if="hasVerifiedFacebookPage">Posts publish to {{ pageDisplayName }} through the connected n8n workflow.</span>
                <span v-else>No Facebook Page identity is loaded. Use Fetch Page profile after updating the n8n Facebook Page token.</span>
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
            <div class="p-4">
              <div class="flex items-center gap-3">
                <img v-if="pagePicture" :src="pagePicture" :alt="pageDisplayName" class="size-10 rounded-full border bg-white object-cover" />
                <span v-else class="grid size-10 place-items-center rounded-full border bg-white text-[10px] font-semibold text-[#65676b]">
                  N/A
                </span>
                <button
                  type="button"
                  class="h-10 min-w-0 flex-1 rounded-full bg-[#f0f2f5] px-4 text-left text-sm font-medium text-[#65676b] hover:bg-[#e4e6eb]"
                  @click="createPostModalOpen = true"
                >
                  What's on your mind?
                </button>
              </div>
              <div class="mt-3 grid grid-cols-3 gap-2 border-t border-[#e4e6eb] pt-3">
                <button class="inline-flex h-9 items-center justify-center gap-2 rounded-md text-sm font-semibold text-[#65676b] hover:bg-[#f2f3f5]" @click="createPostModalOpen = true">
                  <IconBrandFacebook :size="18" class="text-[#1877f2]" />Post
                </button>
                <button class="inline-flex h-9 items-center justify-center gap-2 rounded-md text-sm font-semibold text-[#65676b] hover:bg-[#f2f3f5]" @click="createPostModalOpen = true">
                  <IconPhoto :size="18" class="text-[#42b72a]" />Photo
                </button>
                <button class="inline-flex h-9 items-center justify-center gap-2 rounded-md text-sm font-semibold text-[#65676b] hover:bg-[#f2f3f5]" @click="advancedOpen = true; createPostModalOpen = true">
                  <IconDots :size="18" />More
                </button>
              </div>
              <div v-if="savedDraft" class="mt-3 rounded-lg border border-[#b7e1aa] bg-[#f0faed] p-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                  <p class="text-sm font-semibold">
                    {{ savedDraft.status === 'sent_to_n8n' ? 'Sent to the Facebook workflow' : 'Draft saved' }}
                  </p>
                  <div class="flex flex-wrap gap-2">
                    <button
                      v-if="['draft', 'failed'].includes(savedDraft.status)"
                      class="inline-flex h-8 items-center gap-2 rounded-md bg-[#1877f2] px-3 text-xs font-semibold text-white disabled:opacity-60"
                      :disabled="dispatchMutation.isPending.value"
                      @click="dispatchMutation.mutate({ postId: savedDraft.id })"
                    >
                      <IconSend :size="14" />{{ dispatchMutation.isPending.value ? "Sending..." : "Send to n8n" }}
                    </button>
                    <button class="h-8 rounded-md bg-[#e4e6eb] px-3 text-xs font-semibold" @click="createPostModalOpen = true">Edit draft</button>
                    <button class="h-8 rounded-md bg-[#e4e6eb] px-3 text-xs font-semibold" @click="activeTab = 'posts'">View posts</button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <section class="rounded-lg bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#e4e6eb] px-4 py-3">
              <h2 class="text-base font-bold">Posts</h2>
              <button
                class="inline-flex h-8 items-center gap-2 rounded-md bg-[#e7f3ff] px-3 text-xs font-semibold text-[#1877f2] disabled:opacity-50"
                :disabled="syncFacebookPostsMutation.isPending.value"
                @click="syncFacebookPostsMutation.mutate()"
              >
                <IconRefresh :size="14" />{{ syncFacebookPostsMutation.isPending.value ? "Fetching..." : "Fetch from Facebook" }}
              </button>
            </div>

            <div class="space-y-3 p-4">
              <template v-if="postsQuery.isLoading.value || syncFacebookPostsMutation.isPending.value">
                <CardSkeleton v-for="i in 2" :key="i" :lines="4" />
              </template>
              <EmptyState
                v-else-if="postsQuery.isError.value"
                variant="error"
                title="Couldn't load Facebook posts"
                :hint="postsQuery.error.value instanceof Error ? postsQuery.error.value.message : 'Unable to load posts.'"
                @retry="postsQuery.refetch()"
              />
              <template v-else-if="posts.length">
                <article v-for="post in posts" :key="post.id" class="rounded-lg border border-[#e4e6eb] bg-white">
                  <div class="flex items-start justify-between p-4 pb-2">
                    <div class="flex min-w-0 items-center gap-3">
                      <img v-if="pagePicture" :src="pagePicture" :alt="pageDisplayName" class="size-10 rounded-full border bg-white object-cover" />
                      <span v-else class="grid size-10 place-items-center rounded-full border bg-white text-[10px] font-semibold text-[#65676b]">
                        N/A
                      </span>
                      <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                          <p class="truncate text-sm font-semibold">{{ pageDisplayName }}</p>
                          <span :class="['rounded-full px-2 py-0.5 text-[10px] font-semibold', statusClass(post.status)]">{{ statusLabel(post.status) }}</span>
                        </div>
                        <p class="flex flex-wrap items-center gap-1 text-xs text-[#65676b]">
                          {{ formatDate(post.published_at || post.created_at) }} · <IconWorld :size="12" />
                        </p>
                      </div>
                    </div>
                    <IconDots :size="20" class="text-[#65676b]" />
                  </div>

                  <div class="px-4 py-2">
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
                      <IconBrandFacebook :size="15" />View on Facebook
                    </a>
                  </div>

                  <div class="mt-2 flex border-t border-[#e4e6eb] px-4 py-1 text-sm font-semibold text-[#65676b]">
                    <button
                      class="flex flex-1 items-center justify-center gap-1.5 rounded-md py-2 text-center hover:bg-[#f0f2f5] disabled:cursor-not-allowed disabled:opacity-60"
                      :class="post.page_reacted ? 'text-[#0866ff]' : 'text-[#65676b]'"
                      :disabled="!canReactToPost(post) || isReactingPost(post)"
                      :title="canReactToPost(post) ? (post.page_reacted ? 'Remove the Facebook Page like' : 'Like this post as the Facebook Page') : 'Sync or publish this post to Facebook first'"
                      :aria-label="post.page_reacted ? 'Unlike as Facebook Page' : 'Like this post as Facebook Page'"
                      @click="toggleLikeAsPage(post)"
                    >
                      <svg
                        class="size-[19px] shrink-0"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                        focusable="false"
                      >
                        <path
                          fill="currentColor"
                          d="M8.1 21H5.25A2.25 2.25 0 0 1 3 18.75v-7.5A2.25 2.25 0 0 1 5.25 9H8.1v12Zm2.1 0V9.35l3.06-5.55c.35-.64.98-1 1.71-1 .97 0 1.78.69 1.94 1.64l.08.45c.18 1.05-.03 2.12-.59 3.03L15.74 9h3.51A2.75 2.75 0 0 1 22 11.75c0 .28-.04.57-.13.84l-1.9 6.06A3.25 3.25 0 0 1 16.86 21H10.2Z"
                        />
                      </svg>
                      <span class="tabular-nums">{{ (post.engagement?.reactions ?? 0).toLocaleString() }}</span>
                      <span v-if="isReactingPost(post)" class="sr-only">{{ post.page_reacted ? "Unliking" : "Liking" }}</span>
                    </button>
                    <button
                      class="flex flex-1 items-center justify-center gap-1.5 rounded-md py-2 text-center hover:bg-[#f0f2f5] disabled:cursor-not-allowed disabled:opacity-60"
                      :class="openCommentsPostId === post.id ? 'text-[#0866ff]' : 'text-[#65676b]'"
                      :disabled="!canViewFacebookThread(post) || isCommentsLoading(post)"
                      :title="canViewFacebookThread(post) ? 'View and write comments' : 'Sync or publish this post to Facebook first'"
                      @click="toggleComments(post)"
                    >
                      <IconMessageCircle :size="19" />
                      <span class="tabular-nums">{{ (post.engagement?.comments ?? 0).toLocaleString() }}</span>
                    </button>
                    <button
                      class="flex flex-1 items-center justify-center gap-1.5 rounded-md py-2 text-center hover:bg-[#f0f2f5] disabled:cursor-not-allowed disabled:opacity-60"
                      :disabled="!post.external_permalink"
                      title="Share this Facebook post"
                      @click="sharePost(post)"
                    >
                      <IconShare3 :size="19" />
                      <span class="tabular-nums">{{ (post.engagement?.shares ?? 0).toLocaleString() }}</span>
                    </button>
                  </div>

                  <div v-if="openCommentsPostId === post.id" class="border-t border-[#e4e6eb] px-4 py-3">
                    <form class="flex items-center gap-2" @submit.prevent="submitComment(post)">
                      <img v-if="pagePicture" :src="pagePicture" :alt="pageDisplayName" class="size-8 rounded-full border bg-white object-cover" />
                      <span v-else class="grid size-8 shrink-0 place-items-center rounded-full border bg-white text-[10px] font-semibold text-[#65676b]">N/A</span>
                      <input
                        v-model="commentDrafts[post.id]"
                        class="h-9 min-w-0 flex-1 rounded-full border-0 bg-[#f0f2f5] px-4 text-sm outline-none placeholder:text-[#65676b] focus:ring-2 focus:ring-[#1877f2]/30"
                        :placeholder="`Comment as ${pageDisplayName}`"
                        :disabled="isCommentingPost(post)"
                      />
                      <button
                        class="grid size-9 shrink-0 place-items-center rounded-full bg-[#1877f2] text-white disabled:bg-[#e4e6eb] disabled:text-[#bcc0c4]"
                        :disabled="!commentDrafts[post.id]?.trim() || isCommentingPost(post)"
                        aria-label="Post comment"
                      >
                        <IconSend :size="16" />
                      </button>
                    </form>

                    <div v-if="isCommentsLoading(post)" class="mt-3 space-y-2">
                      <div v-for="i in 2" :key="i" class="flex gap-2">
                        <div class="size-8 rounded-full bg-[#e4e6eb]" />
                        <div class="h-12 flex-1 rounded-2xl bg-[#e4e6eb]" />
                      </div>
                    </div>

                    <div v-else-if="commentsByPost[post.id]?.length" class="mt-3 space-y-3">
                      <div v-for="comment in commentsByPost[post.id]" :key="comment.id" class="flex items-start gap-2">
                        <span class="grid size-8 shrink-0 place-items-center rounded-full bg-[#e4e6eb] text-xs font-bold text-[#65676b]">
                          {{ commentAuthorInitial(comment) }}
                        </span>
                        <div class="min-w-0">
                          <div class="rounded-2xl bg-[#f0f2f5] px-3 py-2">
                            <p class="text-xs font-bold">{{ comment.author_name }}</p>
                            <p class="whitespace-pre-line break-words text-sm leading-5">{{ comment.message || "(No comment text)" }}</p>
                          </div>
                          <p class="mt-1 px-3 text-[11px] font-semibold text-[#65676b]">
                            {{ formatDate(comment.created_at) }}
                            <span v-if="comment.like_count"> · {{ comment.like_count.toLocaleString() }} likes</span>
                            <span v-if="comment.comment_count"> · {{ comment.comment_count.toLocaleString() }} replies</span>
                          </p>
                        </div>
                      </div>
                    </div>

                    <div v-else class="mt-3 rounded-lg bg-[#f0f2f5] p-3 text-sm text-[#65676b]">
                      No Facebook comments loaded for this post yet.
                    </div>
                  </div>
                </article>
              </template>
              <EmptyState v-else title="No Facebook posts found" hint="Click Fetch from Facebook to load Page posts from Graph API." />
            </div>
          </section>

        </section>

        <aside class="order-3 space-y-4">
          <section class="rounded-lg bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between gap-3">
              <h2 class="text-lg font-bold">Notifications</h2>
              <button
                class="inline-flex h-8 items-center gap-2 rounded-md bg-[#e7f3ff] px-3 text-xs font-semibold text-[#1877f2] disabled:opacity-50"
                :disabled="syncFacebookPostsMutation.isPending.value"
                @click="syncFacebookPostsMutation.mutate()"
              >
                <IconRefresh :size="14" />Sync
              </button>
            </div>
            <div class="mt-4 space-y-3">
              <div v-if="syncFacebookPostsMutation.isPending.value" class="space-y-2">
                <CardSkeleton :lines="3" />
              </div>
              <template v-else-if="engagementNotifications.length">
                <div v-for="post in engagementNotifications" :key="post.id" class="flex gap-3 rounded-lg bg-[#f0f2f5] p-3">
                  <span class="grid size-9 shrink-0 place-items-center rounded-full bg-[#e7f3ff] text-[#1877f2]">
                    <IconMessageCircle :size="17" />
                  </span>
                  <div class="min-w-0">
                    <p class="line-clamp-2 text-sm font-semibold">{{ post.title }}</p>
                    <p class="mt-1 text-xs text-[#65676b]">
                      {{ (post.engagement?.reactions ?? 0).toLocaleString() }} reactions ·
                      {{ (post.engagement?.comments ?? 0).toLocaleString() }} comments ·
                      {{ (post.engagement?.shares ?? 0).toLocaleString() }} shares
                    </p>
                  </div>
                </div>
              </template>
              <div v-else class="rounded-lg bg-[#f0f2f5] p-4 text-sm text-[#65676b]">
                No reactions, comments, or shares yet. Sync after people engage with the Facebook Page.
              </div>
            </div>
          </section>

          <section class="rounded-lg bg-white p-4 shadow-sm">
            <h2 class="text-lg font-bold">Engagements</h2>
            <div class="mt-4 grid grid-cols-3 gap-2 text-center">
              <div class="rounded-lg bg-[#f0f2f5] p-3">
                <svg
                  class="mx-auto size-[18px] text-[#0866ff]"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                  focusable="false"
                >
                  <path
                    fill="currentColor"
                    d="M8.1 21H5.25A2.25 2.25 0 0 1 3 18.75v-7.5A2.25 2.25 0 0 1 5.25 9H8.1v12Zm2.1 0V9.35l3.06-5.55c.35-.64.98-1 1.71-1 .97 0 1.78.69 1.94 1.64l.08.45c.18 1.05-.03 2.12-.59 3.03L15.74 9h3.51A2.75 2.75 0 0 1 22 11.75c0 .28-.04.57-.13.84l-1.9 6.06A3.25 3.25 0 0 1 16.86 21H10.2Z"
                  />
                </svg>
                <p class="mt-2 text-lg font-bold">{{ totalEngagement.reactions.toLocaleString() }}</p>
                <p class="text-[10px] text-[#65676b]">Reactions</p>
              </div>
              <div class="rounded-lg bg-[#f0f2f5] p-3">
                <IconMessageCircle :size="18" class="mx-auto text-[#1877f2]" />
                <p class="mt-2 text-lg font-bold">{{ totalEngagement.comments.toLocaleString() }}</p>
                <p class="text-[10px] text-[#65676b]">Comments</p>
              </div>
              <div class="rounded-lg bg-[#f0f2f5] p-3">
                <IconShare3 :size="18" class="mx-auto text-[#1877f2]" />
                <p class="mt-2 text-lg font-bold">{{ totalEngagement.shares.toLocaleString() }}</p>
                <p class="text-[10px] text-[#65676b]">Shares</p>
              </div>
            </div>
            <p v-if="totalEngagement.reactions + totalEngagement.comments + totalEngagement.shares === 0" class="mt-3 text-xs leading-5 text-[#65676b]">
              Comment and reaction counts need one more Facebook Page read permission before they can appear.
            </p>
          </section>
        </aside>
      </div>

      <section v-else-if="activeTab === 'posts'" class="grid gap-4 xl:grid-cols-[320px_minmax(0,1fr)]">
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
            <button
              class="mt-2 inline-flex h-9 w-full items-center justify-center gap-2 rounded-md bg-[#e7f3ff] text-sm font-semibold text-[#1877f2] disabled:opacity-50"
              :disabled="syncFacebookPostsMutation.isPending.value"
              @click="syncFacebookPostsMutation.mutate()"
            >
              <IconRefresh :size="15" />{{ syncFacebookPostsMutation.isPending.value ? "Syncing..." : "Sync from Facebook" }}
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
                  <img v-if="pagePicture" :src="pagePicture" :alt="pageDisplayName" class="size-10 rounded-full border bg-white object-cover" />
                  <span v-else class="grid size-10 place-items-center rounded-full border bg-white text-[10px] font-semibold text-[#65676b]">
                    N/A
                  </span>
                  <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                      <p class="truncate text-sm font-semibold">{{ pageDisplayName }}</p>
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
              <EmptyState title="No posts found" hint="Sync your Facebook Page posts or create a new announcement." />
              <button
                class="mt-3 inline-flex h-9 w-full items-center justify-center gap-2 rounded-md bg-[#e7f3ff] text-sm font-semibold text-[#1877f2] disabled:opacity-50"
                :disabled="syncFacebookPostsMutation.isPending.value"
                @click="syncFacebookPostsMutation.mutate()"
              >
                <IconRefresh :size="15" />{{ syncFacebookPostsMutation.isPending.value ? "Syncing..." : "Sync from Facebook" }}
              </button>
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

      <section v-else class="grid gap-4 xl:grid-cols-[320px_minmax(0,1fr)]">
        <aside class="space-y-4">
          <section class="rounded-lg bg-white p-4 shadow-sm">
            <h2 class="text-lg font-bold">Page details</h2>
            <div class="mt-4 space-y-3 text-sm text-[#65676b]">
              <p class="flex items-start gap-3">
                <IconBrandFacebook :size="20" class="shrink-0 text-[#1877f2]" />
                <span>{{ pageDisplayName }}</span>
              </p>
              <p class="flex items-start gap-3">
                <IconWorld :size="20" class="shrink-0" />
                <span>{{ facebookPage?.url || "No public Page URL loaded." }}</span>
              </p>
              <p class="flex items-start gap-3">
                <IconShieldCheck :size="20" class="shrink-0 text-[#42b72a]" />
                <span>{{ pageConnectionLabel }}</span>
              </p>
            </div>
          </section>
        </aside>

        <div class="space-y-4">
          <section v-if="activeTab === 'about'" class="rounded-lg bg-white p-5 shadow-sm">
            <h2 class="text-xl font-bold">About</h2>
            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
              <div class="rounded-lg border border-[#e4e6eb] p-3">
                <dt class="font-semibold text-[#65676b]">Followers</dt>
                <dd class="mt-1 text-lg font-bold">{{ facebookPage?.followers_count ?? 0 }}</dd>
              </div>
              <div class="rounded-lg border border-[#e4e6eb] p-3">
                <dt class="font-semibold text-[#65676b]">Likes</dt>
                <dd class="mt-1 text-lg font-bold">{{ facebookPage?.fan_count ?? 0 }}</dd>
              </div>
              <div class="rounded-lg border border-[#e4e6eb] p-3 sm:col-span-2">
                <dt class="font-semibold text-[#65676b]">Facebook Page</dt>
                <dd class="mt-1 break-words">{{ facebookPage?.url || "Not available" }}</dd>
              </div>
            </dl>
          </section>

          <section v-else-if="activeTab === 'photos'" class="rounded-lg bg-white p-5 shadow-sm">
            <h2 class="text-xl font-bold">Photos</h2>
            <div class="mt-4 grid max-w-sm gap-3">
              <img v-if="pagePicture" :src="pagePicture" :alt="pageDisplayName" class="aspect-square rounded-lg border border-[#e4e6eb] object-cover" />
              <EmptyState v-else title="No Page photos loaded" hint="The profile photo will appear after Facebook returns it." />
            </div>
          </section>

          <section v-else-if="activeTab === 'followers'" class="rounded-lg bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
              <div>
                <h2 class="text-xl font-bold">Followers</h2>
                <p class="mt-1 text-sm text-[#65676b]">
                  {{ integration?.page_refreshed_at ? `Synced ${formatDate(integration.page_refreshed_at)}` : pageConnectionLabel }}
                </p>
              </div>
              <button
                class="inline-flex h-9 items-center gap-2 rounded-md bg-[#e7f3ff] px-4 text-sm font-semibold text-[#1877f2] hover:bg-[#dbeeff] disabled:opacity-60"
                :disabled="refreshPageMutation.isPending.value"
                @click="refreshPageMutation.mutate()"
              >
                <IconRefresh :size="16" />{{ refreshPageMutation.isPending.value ? "Syncing..." : "Sync followers" }}
              </button>
            </div>

            <div v-if="integrationQuery.isLoading.value" class="mt-4 grid gap-3 sm:grid-cols-2">
              <CardSkeleton v-for="i in 2" :key="i" :lines="3" />
            </div>
            <div v-else class="mt-4 grid gap-3 sm:grid-cols-2">
              <div class="rounded-lg border border-[#e4e6eb] p-4">
                <p class="text-sm font-semibold text-[#65676b]">Followers</p>
                <p class="mt-2 text-3xl font-bold">{{ (facebookPage?.followers_count ?? 0).toLocaleString() }}</p>
                <p class="mt-1 text-xs text-[#65676b]">People following {{ pageDisplayName }}.</p>
              </div>
              <div class="rounded-lg border border-[#e4e6eb] p-4">
                <p class="text-sm font-semibold text-[#65676b]">Page likes</p>
                <p class="mt-2 text-3xl font-bold">{{ (facebookPage?.fan_count ?? 0).toLocaleString() }}</p>
                <p class="mt-1 text-xs text-[#65676b]">People who liked the Page.</p>
              </div>
            </div>

            <div class="mt-4 rounded-lg bg-[#f0f2f5] p-4">
              <div class="flex items-center gap-3">
                <img v-if="pagePicture" :src="pagePicture" :alt="pageDisplayName" class="size-12 rounded-full border bg-white object-cover" />
                <span v-else class="grid size-12 shrink-0 place-items-center rounded-full border bg-white text-xs font-semibold text-[#65676b]">N/A</span>
                <div class="min-w-0">
                  <p class="truncate text-sm font-bold">{{ pageDisplayName }}</p>
                  <p class="text-xs text-[#65676b]">
                    {{ (facebookPage?.followers_count ?? 0).toLocaleString() }} followers ·
                    {{ (facebookPage?.fan_count ?? 0).toLocaleString() }} likes
                  </p>
                </div>
              </div>
              <a
                v-if="facebookPage?.url"
                :href="facebookPage.url"
                target="_blank"
                rel="noopener noreferrer"
                class="mt-4 inline-flex h-9 items-center gap-2 rounded-md bg-[#e4e6eb] px-4 text-sm font-semibold hover:bg-[#d8dadf]"
              >
                <IconBrandFacebook :size="16" />Open Page followers
              </a>
            </div>
          </section>

          <section v-else class="rounded-lg bg-white p-5 shadow-sm">
            <EmptyState
              :title="`${pageTabs.find((tab) => tab.key === activeTab)?.label ?? 'Page'} is not synced yet`"
              hint="This tab is available in the page layout. Historical Facebook content can be synced from Graph API in a follow-up."
            />
          </section>
        </div>
      </section>
    </main>

    <Teleport to="body">
      <div
        v-if="createPostModalOpen"
        class="fixed inset-0 z-[1000] grid place-items-center overflow-y-auto bg-black/50 px-3 py-6"
        role="dialog"
        aria-modal="true"
        aria-label="Create post"
        @click.self="createPostModalOpen = false"
      >
        <section class="relative flex max-h-[92vh] w-full max-w-2xl flex-col overflow-hidden rounded-lg bg-white shadow-xl">
        <div v-if="createPostModalView === 'templates'" class="overflow-y-auto p-4 pt-5">
          <button
            class="absolute right-3 top-3 z-10 grid size-9 place-items-center rounded-full bg-[#e4e6eb] text-[#65676b] hover:bg-[#d8dadf]"
            aria-label="Close create post"
            @click="createPostModalOpen = false"
          >
            <IconX :size="18" />
          </button>
          <button
            type="button"
            class="inline-flex h-9 items-center gap-2 rounded-md px-2 text-sm font-semibold text-[#65676b] hover:bg-[#f0f2f5]"
            @click="createPostModalView = 'composer'"
          >
            <IconArrowLeft :size="18" />Back
          </button>

          <div class="mt-4 flex items-start justify-between gap-3 pr-12">
            <div>
              <h3 class="text-xl font-bold">Templates</h3>
              <p class="mt-1 text-sm text-[#65676b]">Choose a starting format, then edit the post before saving.</p>
            </div>
            <IconSparkles :size="20" class="mt-1 shrink-0 text-[#1877f2]" />
          </div>

          <div class="mt-4 grid gap-3">
            <button
              v-for="preset in postTemplatePresets"
              :key="preset.key"
              type="button"
              :class="[
                'rounded-lg border p-4 text-left transition-colors',
                selectedTemplatePreset === preset.key
                  ? 'border-[#1877f2] bg-[#e7f3ff]'
                  : 'border-[#e4e6eb] bg-white hover:bg-[#f0f2f5]',
              ]"
              @click="applyTemplatePreset(preset.key)"
            >
              <span class="block text-base font-semibold">{{ preset.label }}</span>
              <span class="mt-1 block text-sm leading-5 text-[#65676b]">{{ preset.description }}</span>
              <span class="mt-3 inline-flex rounded-md bg-[#e4e6eb] px-2 py-1 text-xs font-semibold text-[#65676b]">
                {{ preset.campaign }}
              </span>
            </button>
          </div>
        </div>

        <div v-else class="overflow-y-auto p-4 pt-5">
          <button
            class="absolute right-3 top-3 z-10 grid size-9 place-items-center rounded-full bg-[#e4e6eb] text-[#65676b] hover:bg-[#d8dadf]"
            aria-label="Close create post"
            @click="createPostModalOpen = false"
          >
            <IconX :size="18" />
          </button>
          <div class="flex items-center gap-3 pr-12">
            <img v-if="pagePicture" :src="pagePicture" :alt="pageDisplayName" class="size-10 rounded-full border bg-white object-cover" />
            <span v-else class="grid size-10 place-items-center rounded-full border bg-white text-[10px] font-semibold text-[#65676b]">
              N/A
            </span>
            <div>
              <p class="text-sm font-semibold">{{ pageDisplayName }}</p>
              <span class="mt-0.5 inline-flex items-center gap-1 rounded-md bg-[#e4e6eb] px-2 py-0.5 text-xs font-semibold text-[#65676b]">
                <IconWorld :size="11" />Public
              </span>
            </div>
          </div>

          <label class="mt-4 block">
            <span class="sr-only">Facebook message</span>
            <textarea
              v-model="form.message"
              class="min-h-44 w-full resize-y border-0 bg-transparent p-0 text-lg leading-7 text-[#050505] outline-none placeholder:text-[#65676b] focus:ring-0"
              placeholder="What do you want to announce?"
            />
          </label>

          <div class="mt-2 flex items-center justify-between text-xs text-[#65676b]">
            <span>{{ characterCount.toLocaleString() }} characters</span>
            <span>Public information only</span>
          </div>

          <div class="mt-4 flex items-center justify-between rounded-lg border border-[#ced0d4] px-3 py-2">
            <div class="min-w-0">
              <p class="text-sm font-semibold">Post options</p>
              <p class="truncate text-xs text-[#65676b]">
                {{ selectedTemplatePreset ? `${postTemplatePresets.find((preset) => preset.key === selectedTemplatePreset)?.label} template` : 'General announcement' }}
                · {{ form.approval_mode === 'pre_approved' ? 'Approved to publish' : 'Review first' }}
              </p>
            </div>
            <div class="flex items-center gap-1">
              <button
                type="button"
                class="grid size-9 place-items-center rounded-full bg-[#e7f3ff] text-[#1877f2]"
                title="Templates"
                aria-label="Open templates"
                @click="createPostModalView = 'templates'"
              >
                <IconSparkles :size="18" />
              </button>
              <span class="grid size-9 place-items-center rounded-full bg-[#f0f2f5] text-[#1877f2]" title="Facebook post">
                <IconBrandFacebook :size="19" />
              </span>
              <span class="grid size-9 place-items-center rounded-full bg-[#eaf7e6] text-[#42b72a]" title="Media can be added in n8n">
                <IconPhoto :size="19" />
              </span>
              <button
                class="grid size-9 place-items-center rounded-full bg-[#f0f2f5] text-[#65676b]"
                :aria-expanded="advancedOpen"
                aria-label="Toggle post options"
                @click="advancedOpen = !advancedOpen"
              >
                <IconDots :size="19" />
              </button>
            </div>
          </div>

          <div v-if="advancedOpen" class="mt-3 space-y-3 rounded-lg bg-[#f0f2f5] p-3">
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

            <input
              v-model="form.title"
              class="h-10 w-full rounded-md border border-[#ced0d4] bg-white px-3 text-sm text-[#050505]"
              placeholder="Internal title"
            />

            <div class="grid gap-2 sm:grid-cols-2">
              <label
                :class="[
                  'cursor-pointer rounded-lg border bg-white p-3',
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
                  'cursor-pointer rounded-lg border bg-white p-3',
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

            <div class="grid gap-3 sm:grid-cols-2">
              <label class="text-xs font-semibold text-[#65676b]">
                Campaign code
                <input v-model="form.campaign" class="mt-1.5 h-9 w-full rounded-md border border-[#ced0d4] bg-white px-3 text-sm text-[#050505]" placeholder="batch_1_release" />
              </label>
              <label class="text-xs font-semibold text-[#65676b]">
                Schedule
                <input v-model="form.scheduled_for" type="datetime-local" class="mt-1.5 h-9 w-full rounded-md border border-[#ced0d4] bg-white px-3 text-sm text-[#050505]" />
              </label>
            </div>
          </div>

          <div v-if="savedDraft" class="mt-4 rounded-lg border border-[#b7e1aa] bg-[#f0faed] p-4">
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
                  <button class="h-9 rounded-md bg-[#e4e6eb] px-4 text-sm font-semibold" @click="activeTab = 'posts'; createPostModalOpen = false">View posts</button>
                  <button class="h-9 rounded-md bg-[#e4e6eb] px-4 text-sm font-semibold" @click="startNewPost">New post</button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <footer v-if="createPostModalView === 'composer'" class="border-t border-[#e4e6eb] p-4">
          <button
            v-if="!savedDraft"
            class="h-10 w-full rounded-md bg-[#1877f2] text-sm font-semibold text-white hover:bg-[#166fe5] disabled:bg-[#e4e6eb] disabled:text-[#bcc0c4]"
            :disabled="!hasContent || createMutation.isPending.value"
            @click="createMutation.mutate()"
          >
            {{ createMutation.isPending.value ? "Saving draft..." : "Save Facebook draft" }}
          </button>
          <button
            v-else
            class="h-10 w-full rounded-md bg-[#e4e6eb] text-sm font-semibold text-[#050505] hover:bg-[#d8dadf]"
            @click="createPostModalOpen = false"
          >
            Done
          </button>
        </footer>
        </section>
      </div>
    </Teleport>
  </div>
</template>
