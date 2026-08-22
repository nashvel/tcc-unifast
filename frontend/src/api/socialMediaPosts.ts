import { apiFetch, buildQuery } from "./client";
import type {
  ListQuery,
  PaginatedResponse,
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
