import { apiFetch } from "../client";
import type { StudentNotification, PaginatedResponse } from "../types";

export async function listNotifications(): Promise<PaginatedResponse<StudentNotification>> {
  return apiFetch<PaginatedResponse<StudentNotification>>(
    "/api/student/notifications?per_page=50",
  );
}

export async function markNotificationRead(
  id: number,
): Promise<{ data: StudentNotification }> {
  return apiFetch(`/api/student/notifications/${id}/read`, { method: "POST" });
}

export async function markAllNotificationsRead(): Promise<{ ok: boolean }> {
  return apiFetch("/api/student/notifications/read-all", { method: "POST" });
}
