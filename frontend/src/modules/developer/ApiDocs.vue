<script setup lang="ts">
import { ref } from "vue";
import { IconCode, IconCopy, IconCheck } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";

type Endpoint = {
  method: string;
  path: string;
  description: string;
  auth: boolean;
  role?: string;
  body?: string;
  response: string;
};

const endpoints: Endpoint[] = [
  // Auth
  { method: "POST", path: "/api/auth/login", description: "Authenticate user and return token", auth: false, body: '{ "email": "...", "password": "..." }', response: '{ "user": {...}, "token": "..." }' },
  { method: "GET", path: "/api/auth/me", description: "Get current authenticated user", auth: true, response: '{ "user": {...} }' },
  { method: "POST", path: "/api/auth/logout", description: "Revoke current token", auth: true, response: '{ "message": "Signed out." }' },

  // Batches
  { method: "GET", path: "/api/batches", description: "List all batches", auth: true, role: "developer,admin,staff", response: '{ "data": [...], "meta": {...} }' },
  { method: "POST", path: "/api/batches", description: "Create a new batch", auth: true, role: "developer,admin", body: '{ "name": "...", "academic_year": "...", "semester": "...", "submission_deadline": "..." }', response: '{ "data": {...} }' },
  { method: "GET", path: "/api/batches/:id", description: "Get batch details", auth: true, role: "developer,admin,staff", response: '{ "data": {...} }' },
  { method: "POST", path: "/api/batches/:id/activate", description: "Activate batch window", auth: true, role: "developer,admin", response: '{ "data": {...} }' },
  { method: "POST", path: "/api/batches/:id/deactivate", description: "Deactivate batch window", auth: true, role: "developer,admin", response: '{ "data": {...} }' },
  { method: "POST", path: "/api/batches/:id/extend-deadline", description: "Extend submission deadline", auth: true, role: "developer,admin", body: '{ "submission_deadline": "..." }', response: '{ "data": {...} }' },

  // Grantees
  { method: "GET", path: "/api/grantees", description: "List all grantees with filters", auth: true, role: "developer,admin,staff", response: '{ "data": [...], "meta": {...} }' },
  { method: "GET", path: "/api/grantees/:id", description: "Get grantee details", auth: true, role: "developer,admin,staff", response: '{ "data": {...} }' },

  // Academic
  { method: "GET", path: "/api/academic-records", description: "List academic records", auth: true, role: "developer,admin,staff", response: '{ "data": [...], "meta": {...} }' },
  { method: "GET", path: "/api/academic-records/:id", description: "Get academic record details", auth: true, role: "developer,admin,staff", response: '{ "data": {...} }' },

  // Documents
  { method: "GET", path: "/api/document-submissions", description: "List document submissions", auth: true, role: "developer,admin,staff", response: '{ "data": [...], "meta": {...} }' },
  { method: "GET", path: "/api/document-submissions/:id", description: "Get submission details", auth: true, role: "developer,admin,staff", response: '{ "data": {...} }' },
  { method: "POST", path: "/api/document-submissions/:id/review", description: "Review a submission", auth: true, role: "developer,admin,staff", body: '{ "decision": "approved|rejected|resubmission", "notes": "..." }', response: '{ "data": {...} }' },

  // Student
  { method: "GET", path: "/api/student/kyc", description: "Get student KYC profile", auth: true, role: "student", response: '{ "data": {...} }' },
  { method: "POST", path: "/api/student/kyc", description: "Submit KYC profile", auth: true, role: "student", body: '{ "full_name": "...", "student_id": "...", ... }', response: '{ "data": {...} }' },
  { method: "GET", path: "/api/student/submission-window", description: "Get submission window status", auth: true, role: "student", response: '{ "data": {...} }' },
  { method: "GET", path: "/api/student/notifications", description: "List student notifications", auth: true, role: "student", response: '{ "data": [...], "meta": {...} }' },
  { method: "POST", path: "/api/student/notifications/:id/read", description: "Mark notification as read", auth: true, role: "student", response: '{ "data": {...} }' },
  { method: "POST", path: "/api/student/notifications/read-all", description: "Mark all notifications as read", auth: true, role: "student", response: '{ "ok": true }' },

  // Masterlist
  { method: "POST", path: "/api/masterlist/imports/preview", description: "Preview masterlist import", auth: true, role: "developer,admin,staff", body: "FormData: file, batch_name, academic_year, semester", response: '{ "data": {...} }' },
  { method: "POST", path: "/api/masterlist/imports/:id/confirm", description: "Confirm masterlist import", auth: true, role: "developer,admin", response: '{ "data": {...}, "mail": {...} }' },

  // Audit
  { method: "GET", path: "/api/audit-logs", description: "List audit logs", auth: true, role: "developer,admin,staff", response: '{ "data": [...], "meta": {...} }' },
  { method: "POST", path: "/api/audit-events", description: "Log an audit event", auth: true, response: '{ "ok": true }' },
];

const methodColors: Record<string, string> = {
  GET: "bg-green-100 text-green-700",
  POST: "bg-blue-100 text-blue-700",
  PUT: "bg-yellow-100 text-yellow-700",
  PATCH: "bg-orange-100 text-orange-700",
  DELETE: "bg-red-100 text-red-700",
};

const copied = ref<string | null>(null);

function copyToClipboard(text: string, id: string) {
  navigator.clipboard.writeText(text);
  copied.value = id;
  setTimeout(() => (copied.value = null), 2000);
}

const filter = ref("");
const filteredEndpoints = computed(() =>
  endpoints.filter(
    (e) =>
      !filter.value ||
      e.path.toLowerCase().includes(filter.value.toLowerCase()) ||
      e.description.toLowerCase().includes(filter.value.toLowerCase()),
  ),
);

import { computed } from "vue";
</script>

<template>
  <div>
    <PageHeader
      title="API Documentation"
      description="REST API endpoints for the UniFAST TES system."
    />

    <div class="mb-4">
      <input
        v-model="filter"
        class="h-9 w-full max-w-md rounded-md border px-3 text-xs"
        placeholder="Filter endpoints..."
      />
    </div>

    <div class="space-y-2">
      <div
        v-for="(endpoint, idx) in filteredEndpoints"
        :key="idx"
        class="rounded-lg border bg-surface p-4"
      >
        <div class="flex flex-wrap items-center gap-2">
          <span :class="['rounded px-2 py-0.5 text-xs font-bold', methodColors[endpoint.method]]">
            {{ endpoint.method }}
          </span>
          <code class="text-xs font-mono text-text">{{ endpoint.path }}</code>
          <span v-if="endpoint.auth" class="rounded bg-warning-soft px-1.5 py-0.5 text-2xs text-warning">
            Auth
          </span>
          <span v-if="endpoint.role" class="rounded bg-info-soft px-1.5 py-0.5 text-2xs text-info">
            {{ endpoint.role }}
          </span>
        </div>
        <p class="mt-2 text-xs text-text-muted">{{ endpoint.description }}</p>

        <div v-if="endpoint.body || endpoint.response" class="mt-3 grid gap-2 sm:grid-cols-2">
          <div v-if="endpoint.body" class="rounded bg-surface-muted p-3">
            <div class="flex items-center justify-between">
              <span class="text-2xs font-semibold uppercase text-text-muted">Request Body</span>
              <button
                class="text-text-muted hover:text-text"
                @click="copyToClipboard(endpoint.body!, `body-${idx}`)"
              >
                <IconCheck v-if="copied === `body-${idx}`" :size="12" class="text-success" />
                <IconCopy v-else :size="12" />
              </button>
            </div>
            <pre class="mt-2 overflow-auto text-2xs text-text-muted">{{ endpoint.body }}</pre>
          </div>
          <div class="rounded bg-surface-muted p-3">
            <div class="flex items-center justify-between">
              <span class="text-2xs font-semibold uppercase text-text-muted">Response</span>
              <button
                class="text-text-muted hover:text-text"
                @click="copyToClipboard(endpoint.response, `res-${idx}`)"
              >
                <IconCheck v-if="copied === `res-${idx}`" :size="12" class="text-success" />
                <IconCopy v-else :size="12" />
              </button>
            </div>
            <pre class="mt-2 overflow-auto text-2xs text-text-muted">{{ endpoint.response }}</pre>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
