import { apiFetch, buildQuery } from "./client";
import type {
  ListQuery,
  PaginatedResponse,
  SocialMediaComment,
  SocialMediaIntegrationStatus,
  SocialMediaPost,
  SocialMediaPostTemplate,
} from "./types";

export type SocialMediaPostInput = {
  title: string;
  message: string;
  channel: "facebook";
  campaign?: string | null;
  batch_id?: number | null;
  approval_mode: "approval_required" | "pre_approved";
  scheduled_for?: string | null;
  metadata?: Record<string, unknown>;
};

export async function listSocialMediaPosts(
  params: ListQuery = {},
): Promise<PaginatedResponse<SocialMediaPost>> {
  return apiFetch<PaginatedResponse<SocialMediaPost>>(
    `/api/social-media-posts${buildQuery(params)}`,
  );
}

export async function createSocialMediaPost(
  data: SocialMediaPostInput,
): Promise<{ data: SocialMediaPost }> {
  return apiFetch<{ data: SocialMediaPost }>("/api/social-media-posts", {
    method: "POST",
    body: JSON.stringify(data),
  });
}

export async function getSocialMediaIntegrationStatus(): Promise<{ data: SocialMediaIntegrationStatus }> {
  return apiFetch<{ data: SocialMediaIntegrationStatus }>(
    "/api/social-media-posts/integration-status",
  );
}

export async function refreshSocialMediaPageProfile(): Promise<{ message: string; request_id: string }> {
  return apiFetch<{ message: string; request_id: string }>(
    "/api/social-media-posts/integration-status/refresh-page",
    { method: "POST" },
  );
}

export async function syncFacebookPagePosts(): Promise<{ message: string; count: number; data: SocialMediaPost[] }> {
  return apiFetch<{ message: string; count: number; data: SocialMediaPost[] }>(
    "/api/social-media-posts/sync-facebook",
    { method: "POST" },
  );
}

export async function reactToSocialMediaPost(id: string | number): Promise<{ message: string; data: SocialMediaPost }> {
  return apiFetch<{ message: string; data: SocialMediaPost }>(
    `/api/social-media-posts/${id}/react`,
    { method: "POST" },
  );
}

export async function listSocialMediaPostComments(
  id: string | number,
  params: { limit?: number } = {},
): Promise<{ message: string; count: number; data: SocialMediaComment[]; post: SocialMediaPost }> {
  return apiFetch<{ message: string; count: number; data: SocialMediaComment[]; post: SocialMediaPost }>(
    `/api/social-media-posts/${id}/comments${buildQuery(params)}`,
  );
}

export async function commentOnSocialMediaPost(
  id: string | number,
  message: string,
): Promise<{ message: string; data: SocialMediaComment; post: SocialMediaPost }> {
  return apiFetch<{ message: string; data: SocialMediaComment; post: SocialMediaPost }>(
    `/api/social-media-posts/${id}/comments`,
    {
      method: "POST",
      body: JSON.stringify({ message }),
    },
  );
}

export async function getSocialMediaPostTemplate(params: {
  batch_id?: number | null;
  channel?: "facebook";
} = {}): Promise<{ data: SocialMediaPostTemplate }> {
  return apiFetch<{ data: SocialMediaPostTemplate }>(
    `/api/social-media-posts/template${buildQuery({ ...params, channel: params.channel ?? "facebook" })}`,
  );
}

export async function dispatchSocialMediaPost(
  id: string | number,
  approvalMode?: "pre_approved",
): Promise<{ message: string; request_id: string; data: SocialMediaPost }> {
  return apiFetch(`/api/social-media-posts/${id}/dispatch`, {
    method: "POST",
    body: JSON.stringify(approvalMode ? { approval_mode: approvalMode } : {}),
  });
}
