<script setup lang="ts">
import { computed, ref } from "vue";
import PageHeader from "@/components/ui/PageHeader.vue";

type HttpMethod = "GET" | "POST" | "PUT" | "PATCH" | "DELETE";

type Access = "public" | "authenticated" | "student" | "operations" | "developer" | "external";

type Endpoint = {
  method: HttpMethod;
  path: string;
  summary: string;
  access: Access;
  throttle?: string;
  request?: string;
  response: string;
};

type EndpointGroup = {
  name: string;
  description: string;
  endpoints: Endpoint[];
};

const endpointGroups: EndpointGroup[] = [
  {
    name: "Authentication & Activation",
    description: "Session bootstrap, login captcha, bearer token issuance, activation links, and public portal content.",
    endpoints: [
      { method: "POST", path: "/api/auth/login", summary: "Authenticate a user with reCAPTCHA and issue a Sanctum token.", access: "public", throttle: "5/min", request: '{ "email": "...", "password": "...", "captcha": "..." }', response: '{ "user": {...}, "token": "..." }' },
      { method: "GET", path: "/api/auth/me", summary: "Return the current authenticated user.", access: "authenticated", response: '{ "user": {...} }' },
      { method: "POST", path: "/api/auth/logout", summary: "Revoke the current token.", access: "authenticated", response: '{ "message": "Signed out." }' },
      { method: "GET", path: "/api/activation/{token}", summary: "Validate an invitation or activation token.", access: "public", throttle: "20/min", response: '{ "data": { "email": "...", "student": {...} } }' },
      { method: "POST", path: "/api/activation/{token}", summary: "Activate an invited account.", access: "public", throttle: "10/min", request: '{ "password": "...", "password_confirmation": "..." }', response: '{ "user": {...}, "token": "..." }' },
      { method: "GET", path: "/api/terms/active", summary: "Fetch the active public terms and conditions.", access: "public", response: '{ "data": {...} }' },
      { method: "GET", path: "/api/faqs", summary: "Fetch published FAQs for public support pages.", access: "public", response: '{ "data": [...] }' },
    ],
  },
  {
    name: "Student Portal",
    description: "Student-only KYC, identity onboarding, requirement vault, OCR, face verification, submission window, and notifications.",
    endpoints: [
      { method: "GET", path: "/api/student/kyc", summary: "Load the student's KYC reference, profile, and mismatches.", access: "student", response: '{ "data": { "reference": {...}, "profile": {...}, "mismatches": {...} } }' },
      { method: "POST", path: "/api/student/kyc", summary: "Submit KYC details and compare against masterlist data.", access: "student", throttle: "20/min", request: '{ "full_name": "...", "student_id": "...", "program": "...", "birthdate": "YYYY-MM-DD", "contact": "...", "address": "...", "guardian_name": "...", "household_income": 0 }', response: '{ "data": { "status": "...", "account_status": "...", "mismatches": {...} } }' },
      { method: "GET", path: "/api/student/identity-onboarding", summary: "Load identity onboarding progress.", access: "student", response: '{ "data": {...} }' },
      { method: "POST", path: "/api/student/identity-onboarding/id-scan/ocr-front", summary: "Validate front School ID OCR (name & student ID) before collecting the back. Does not complete id-scan.", access: "student", throttle: "30/min", request: "FormData: id_frame", response: '{ "data": { "ok": true, "extracted_name": "...", "extracted_student_id": "..." } }' },
      { method: "POST", path: "/api/student/identity-onboarding/id-scan", summary: "Store the onboarding school ID scan (front + back).", access: "student", throttle: "30/min", request: "FormData: id_frame, id_back, id_face_crop, face_descriptor[], face_quality_score", response: '{ "data": {...} }' },
      { method: "POST", path: "/api/student/identity-onboarding/liveness", summary: "Store onboarding liveness result and face match outcome.", access: "student", throttle: "10/min", request: '{ "challenge_sequence": [...], "result": "...", "distance": 0.42, "confidence_score": 92, "consent_accepted": true }', response: '{ "data": {...} }' },
      { method: "GET", path: "/api/student/identity-onboarding/references", summary: "Load stored onboarding face references for comparison.", access: "student", response: '{ "data": {...} }' },
      { method: "GET", path: "/api/student/submission-window", summary: "Return current batch window and vault availability.", access: "student", response: '{ "data": { "is_open": true, "deadline": "...", "batch": {...} } }' },
      { method: "GET", path: "/api/student/requirement-vault", summary: "Load vault slots, uploaded files, and identity status.", access: "student", response: '{ "data": { "documents": [...], "identity_check": {...}, "can_confirm": false } }' },
      { method: "POST", path: "/api/student/requirement-vault/id/ocr-front", summary: "Validate front School ID OCR (name & student ID) for the active vault. Does not complete Slot 1.", access: "student", throttle: "30/min", request: "FormData: id_frame", response: '{ "data": { "ok": true, "extracted_name": "...", "extracted_student_id": "..." } }' },
      { method: "POST", path: "/api/student/requirement-vault/id", summary: "Upload the live school ID scan (front + back + QR) for the active batch.", access: "student", throttle: "20/min", request: "FormData: id_frame, id_back, id_face_crop, qr_payload, face_descriptor[], face_quality_score, consent_accepted, precheck_accepted", response: '{ "data": {...} }' },
      { method: "POST", path: "/api/student/requirement-vault/document", summary: "Upload course history, grade slip, or specimen signature requirement.", access: "student", throttle: "20/min", request: "FormData: file, slot_key, document_type", response: '{ "data": {...} }' },
      { method: "POST", path: "/api/student/requirement-vault/identity-check", summary: "Submit the batch liveness challenge and face match result.", access: "student", throttle: "10/min", request: '{ "challenge_sequence": [...], "result": "match", "distance": 0.42, "confidence_score": 92, "consent_accepted": true }', response: '{ "data": {...} }' },
      { method: "POST", path: "/api/student/requirement-vault/confirm", summary: "Finalize all completed vault requirements.", access: "student", throttle: "10/min", response: '{ "grantee": { "submission_status": "submitted" } }' },
      { method: "POST", path: "/api/student/submissions/ocr", summary: "Run OCR extraction for a student-uploaded requirement file.", access: "student", throttle: "20/min", request: "FormData: file, document_type", response: '{ "ocr": { "document_type": "...", "result": {...} } }' },
      { method: "POST", path: "/api/student/identity/face-verify", summary: "Compare a live face capture against stored references.", access: "student", throttle: "10/min", request: "FormData: image", response: '{ "matched": true, "score": 0.42, "threshold": 0.5 }' },
      { method: "GET", path: "/api/student/notifications", summary: "List student notifications.", access: "student", response: '{ "data": [...], "meta": {...} }' },
      { method: "POST", path: "/api/student/notifications/{notification}/read", summary: "Mark one student notification as read.", access: "student", response: '{ "data": {...} }' },
      { method: "POST", path: "/api/student/notifications/read-all", summary: "Mark all student notifications as read.", access: "student", response: '{ "ok": true }' },
    ],
  },
  {
    name: "Operations",
    description: "Developer, admin, head, and staff routes for TES operations, masterlists, batches, grantees, academic records, files, documents, billing, and distribution.",
    endpoints: [
      { method: "GET", path: "/api/batches", summary: "List TES batches.", access: "operations", response: '{ "data": [...], "meta": {...} }' },
      { method: "POST", path: "/api/batches", summary: "Create a batch.", access: "operations", throttle: "20/min", request: '{ "name": "...", "academic_year": "...", "semester": "...", "submission_deadline": "..." }', response: '{ "data": {...} }' },
      { method: "GET", path: "/api/batches/{batch}", summary: "Get batch details and grantees.", access: "operations", response: '{ "data": {...} }' },
      { method: "PATCH", path: "/api/batches/{batch}", summary: "Update batch metadata.", access: "operations", throttle: "20/min", request: '{ "name": "...", "submission_deadline": "..." }', response: '{ "data": {...} }' },
      { method: "POST", path: "/api/batches/{batch}/activate", summary: "Open a batch submission window.", access: "operations", throttle: "10/min", response: '{ "data": {...}, "mail": {...} }' },
      { method: "POST", path: "/api/batches/{batch}/deactivate", summary: "Close a batch submission window.", access: "operations", throttle: "10/min", response: '{ "data": {...}, "mail": {...} }' },
      { method: "POST", path: "/api/batches/{batch}/extend-deadline", summary: "Move a batch deadline.", access: "operations", throttle: "10/min", request: '{ "submission_deadline": "..." }', response: '{ "data": {...}, "mail": {...} }' },
      { method: "POST", path: "/api/batches/{batch}/activation-notifications", summary: "Send batch activation notices.", access: "operations", throttle: "5/min", response: '{ "sent": 0, "failed": [] }' },
      { method: "GET", path: "/api/masterlist/imports", summary: "List masterlist import jobs.", access: "operations", response: '{ "data": [...], "meta": {...} }' },
      { method: "POST", path: "/api/masterlist/imports/preview", summary: "Preview a CSV masterlist upload.", access: "operations", throttle: "10/min", request: "FormData: file, batch_name, academic_year, semester", response: '{ "id": 1, "total_rows": 0, "valid_rows": 0, "invalid_rows": 0, "rows": [...] }' },
      { method: "GET", path: "/api/masterlist/imports/{import}", summary: "Inspect an import preview.", access: "operations", response: '{ "data": {...} }' },
      { method: "DELETE", path: "/api/masterlist/imports/{import}", summary: "Discard an import preview.", access: "operations", response: '{ "message": "Deleted." }' },
      { method: "POST", path: "/api/masterlist/imports/{import}/confirm", summary: "Import valid preview rows and queue activation mail.", access: "operations", throttle: "10/min", response: '{ "data": { "imported": 0, "skipped": 0 }, "mail": {...} }' },
      { method: "GET", path: "/api/grantees", summary: "List grantees with filters.", access: "operations", response: '{ "data": [...], "meta": {...} }' },
      { method: "GET", path: "/api/grantees/{grantee}", summary: "Get a grantee profile.", access: "operations", response: '{ "data": {...} }' },
      { method: "POST", path: "/api/students/{student}/id-sample", summary: "Store an admin-provided student ID sample.", access: "operations", throttle: "20/min", request: "FormData: file", response: '{ "data": {...} }' },
      { method: "GET", path: "/api/academic-records", summary: "List academic records.", access: "operations", response: '{ "data": [...], "meta": {...} }' },
      { method: "GET", path: "/api/academic-records/{record}", summary: "Get academic record details.", access: "operations", response: '{ "data": {...} }' },
      { method: "GET", path: "/api/academic-programs", summary: "List academic programs.", access: "operations", response: '{ "data": [...] }' },
      { method: "POST", path: "/api/academic-programs", summary: "Create an academic program.", access: "operations", throttle: "20/min", request: '{ "code": "...", "name": "...", "is_active": true }', response: '{ "data": {...} }' },
      { method: "PATCH", path: "/api/academic-programs/{academicProgram}", summary: "Update an academic program.", access: "operations", throttle: "20/min", request: '{ "name": "...", "is_active": true }', response: '{ "data": {...} }' },
      { method: "DELETE", path: "/api/academic-programs/{academicProgram}", summary: "Delete an academic program.", access: "operations", throttle: "20/min", response: '{ "message": "Deleted." }' },
      { method: "GET", path: "/api/policy-settings", summary: "Load eligibility policy thresholds.", access: "operations", response: '{ "data": {...} }' },
      { method: "PUT", path: "/api/policy-settings", summary: "Update eligibility policy thresholds.", access: "operations", throttle: "20/min", request: '{ "max_gwa": 2.5, "max_failed_subjects": 0, "max_dropped_subjects": 0 }', response: '{ "data": {...} }' },
      { method: "GET", path: "/api/document-submissions", summary: "List document submissions.", access: "operations", response: '{ "data": [...], "meta": {...} }' },
      { method: "GET", path: "/api/document-submission-packages", summary: "List submission packages (one row per grantee+batch).", access: "operations", response: '{ "data": [...], "meta": {...} }' },
      { method: "GET", path: "/api/document-submission-packages/{granteeId}/{batchId}", summary: "Get package with slot tabs metadata.", access: "operations", response: '{ "data": {...} }' },
      { method: "GET", path: "/api/document-submissions/{submission}", summary: "Get submission detail and extracted signals.", access: "operations", response: '{ "data": {...} }' },
      { method: "POST", path: "/api/document-submissions/{submission}/review", summary: "Approve, reject, or request resubmission.", access: "operations", request: '{ "decision": "approved|rejected|resubmission", "notes": "..." }', response: '{ "data": {...} }' },
      { method: "GET", path: "/api/eligibility", summary: "List eligibility outcomes.", access: "operations", response: '{ "data": [...], "meta": {...} }' },
      { method: "GET", path: "/api/eligibility/{grantee}", summary: "Get a grantee eligibility breakdown.", access: "operations", response: '{ "data": {...} }' },
      { method: "POST", path: "/api/eligibility/{grantee}/notify", summary: "Notify a grantee about eligibility status.", access: "operations", throttle: "20/min", response: '{ "data": {...} }' },
      { method: "GET", path: "/api/files", summary: "List File Manager rows (tab=requirements|imports). Requirements include batch/student/slot fields and preview/download URLs. Not a general upload vault; identity photos are excluded.", access: "operations", response: '{ "data": [...], "tab": "requirements", "summary": {...}, "batches": [...], "meta": {...} }' },
      { method: "GET", path: "/api/files/imports/{import}/download", summary: "Download a masterlist import CSV.", access: "operations", response: "Binary file response" },
      { method: "GET", path: "/api/audit-logs", summary: "List audit trail records.", access: "operations", response: '{ "data": [...], "meta": {...} }' },
      { method: "POST", path: "/api/audit-events", summary: "Emit an audit event from any authenticated UI.", access: "authenticated", throttle: "240/min", request: '{ "action": "...", "module": "...", "target": "...", "context": {...} }', response: '{ "ok": true }' },
      { method: "GET", path: "/api/notifications", summary: "List staff/admin notifications.", access: "operations", response: '{ "data": [...], "meta": {...} }' },
      { method: "POST", path: "/api/notifications/{notification}/read", summary: "Mark one staff/admin notification as read.", access: "operations", response: '{ "data": {...} }' },
      { method: "POST", path: "/api/notifications/read-all", summary: "Mark all staff/admin notifications as read.", access: "operations", response: '{ "ok": true }' },
      { method: "GET", path: "/api/billing-reports", summary: "List billing reports.", access: "operations", response: '{ "data": [...], "meta": {...} }' },
      { method: "POST", path: "/api/billing-reports", summary: "Generate a billing report.", access: "operations", throttle: "10/min", request: '{ "batch_id": 1, "period": "..." }', response: '{ "data": {...} }' },
      { method: "GET", path: "/api/billing-reports/{report}", summary: "Get billing report details.", access: "operations", response: '{ "data": {...} }' },
      { method: "GET", path: "/api/billing-reports/{report}/download", summary: "Download a billing report file.", access: "operations", response: "Binary file response" },
      { method: "GET", path: "/api/distribution-reports", summary: "List distribution reports.", access: "operations", response: '{ "data": [...], "meta": {...} }' },
      { method: "POST", path: "/api/distribution-reports", summary: "Generate a distribution report.", access: "operations", throttle: "10/min", request: '{ "batch_id": 1, "period": "..." }', response: '{ "data": {...} }' },
      { method: "GET", path: "/api/distribution-reports/{report}", summary: "Get distribution report details.", access: "operations", response: '{ "data": {...} }' },
      { method: "GET", path: "/api/distribution-reports/{report}/download", summary: "Download a distribution report file.", access: "operations", response: "Binary file response" },
    ],
  },
  {
    name: "Developer Administration",
    description: "Developer/admin-only RBAC, database inspection, content management, support, collaborator, changelog, and health routes.",
    endpoints: [
      { method: "GET", path: "/api/changelogs", summary: "Load GitHub commit history.", access: "developer", response: '{ "data": [...], "repo": "...", "has_token": true }' },
      { method: "GET", path: "/api/rbac/roles", summary: "List RBAC roles.", access: "developer", response: '{ "data": [...] }' },
      { method: "POST", path: "/api/rbac/roles", summary: "Create a role.", access: "developer", request: '{ "name": "...", "permissions": [...] }', response: '{ "data": {...} }' },
      { method: "GET", path: "/api/rbac/roles/{role}", summary: "Get role detail.", access: "developer", response: '{ "data": {...} }' },
      { method: "PUT", path: "/api/rbac/roles/{role}", summary: "Replace role data.", access: "developer", request: '{ "name": "...", "permissions": [...] }', response: '{ "data": {...} }' },
      { method: "DELETE", path: "/api/rbac/roles/{role}", summary: "Delete a role.", access: "developer", response: '{ "message": "Deleted." }' },
      { method: "GET", path: "/api/rbac/permissions", summary: "List permissions.", access: "developer", response: '{ "data": [...] }' },
      { method: "POST", path: "/api/rbac/permissions", summary: "Create a permission.", access: "developer", request: '{ "name": "...", "description": "..." }', response: '{ "data": {...} }' },
      { method: "DELETE", path: "/api/rbac/permissions/{permission}", summary: "Delete a permission.", access: "developer", response: '{ "message": "Deleted." }' },
      { method: "GET", path: "/api/rbac/users/{user}/roles", summary: "List roles assigned to a user.", access: "developer", response: '{ "data": [...] }' },
      { method: "POST", path: "/api/rbac/users/{user}/roles", summary: "Assign a role to a user.", access: "developer", request: '{ "role_id": 1 }', response: '{ "data": {...} }' },
      { method: "PUT", path: "/api/rbac/users/{user}/roles", summary: "Sync all user roles.", access: "developer", request: '{ "role_ids": [1, 2] }', response: '{ "data": [...] }' },
      { method: "DELETE", path: "/api/rbac/users/{user}/roles/{role}", summary: "Remove a role from a user.", access: "developer", response: '{ "message": "Removed." }' },
      { method: "POST", path: "/api/rbac/check-permission", summary: "Check whether a user can perform a permission.", access: "developer", request: '{ "user_id": 1, "permission": "..." }', response: '{ "allowed": true }' },
      { method: "GET", path: "/api/database/tables", summary: "List database tables and summary stats.", access: "developer", response: '{ "data": [...], "summary": {...} }' },
      { method: "GET", path: "/api/database/stats", summary: "Load database health stats.", access: "developer", response: '{ "data": {...} }' },
      { method: "GET", path: "/api/database/tables/{table}", summary: "Inspect table columns.", access: "developer", response: '{ "data": {...} }' },
      { method: "GET", path: "/api/database/tables/{table}/rows", summary: "List table rows.", access: "developer", response: '{ "data": [...], "meta": {...} }' },
      { method: "GET", path: "/api/terms", summary: "List terms documents.", access: "developer", response: '{ "data": [...] }' },
      { method: "POST", path: "/api/terms", summary: "Create terms content.", access: "developer", request: '{ "title": "...", "content": "...", "is_active": false }', response: '{ "data": {...} }' },
      { method: "GET", path: "/api/terms/{term}", summary: "Get a terms document.", access: "developer", response: '{ "data": {...} }' },
      { method: "PUT", path: "/api/terms/{term}", summary: "Update terms content.", access: "developer", request: '{ "title": "...", "content": "...", "is_active": true }', response: '{ "data": {...} }' },
      { method: "DELETE", path: "/api/terms/{term}", summary: "Delete terms content.", access: "developer", response: '{ "message": "Deleted." }' },
      { method: "GET", path: "/api/faqs/all", summary: "List all FAQs, including drafts/inactive items.", access: "developer", response: '{ "data": [...] }' },
      { method: "POST", path: "/api/faqs", summary: "Create an FAQ.", access: "developer", request: '{ "question": "...", "answer": "...", "is_active": true }', response: '{ "data": {...} }' },
      { method: "GET", path: "/api/faqs/{faq}", summary: "Get FAQ detail.", access: "developer", response: '{ "data": {...} }' },
      { method: "PUT", path: "/api/faqs/{faq}", summary: "Update an FAQ.", access: "developer", request: '{ "question": "...", "answer": "...", "is_active": true }', response: '{ "data": {...} }' },
      { method: "DELETE", path: "/api/faqs/{faq}", summary: "Delete an FAQ.", access: "developer", response: '{ "message": "Deleted." }' },
      { method: "POST", path: "/api/faqs/reorder", summary: "Persist FAQ display order.", access: "developer", request: '{ "items": [{ "id": 1, "sort_order": 1 }] }', response: '{ "ok": true }' },
      { method: "GET", path: "/api/support-tickets", summary: "List support tickets.", access: "developer", response: '{ "data": [...], "meta": {...} }' },
      { method: "POST", path: "/api/support-tickets", summary: "Create a support ticket.", access: "developer", request: '{ "subject": "...", "message": "...", "priority": "..." }', response: '{ "data": {...} }' },
      { method: "PATCH", path: "/api/support-tickets/{supportTicket}", summary: "Update support ticket status or assignment.", access: "developer", request: '{ "status": "...", "assigned_to": 1 }', response: '{ "data": {...} }' },
      { method: "GET", path: "/api/collaborators", summary: "List collaborators.", access: "developer", response: '{ "data": [...] }' },
      { method: "POST", path: "/api/collaborators/invite", summary: "Invite a collaborator.", access: "developer", request: '{ "email": "...", "role": "..." }', response: '{ "data": {...} }' },
      { method: "DELETE", path: "/api/collaborators/{user}", summary: "Remove collaborator access.", access: "developer", response: '{ "message": "Removed." }' },
      { method: "GET", path: "/api/system/health", summary: "Load system health telemetry.", access: "developer", response: '{ "data": {...} }' },
    ],
  },
  {
    name: "External Integrations",
    description: "Webhook-style n8n integration routes. These are intentionally outside Sanctum auth and protected by throttling.",
    endpoints: [
      { method: "POST", path: "/api/integrations/n8n/tcc-unifast/sync", summary: "Receive TCC UniFAST student sync payloads from n8n.", access: "external", throttle: "30/min", request: '{ "students": [...] }', response: '{ "ok": true, "imported": 0, "updated": 0 }' },
      { method: "GET", path: "/api/integrations/n8n/tcc-unifast/students", summary: "Expose TCC UniFAST student records for n8n.", access: "external", throttle: "120/min", response: '{ "data": [...] }' },
    ],
  },
];

const allEndpoints = computed(() => endpointGroups.flatMap((group) => group.endpoints.map((endpoint) => ({ ...endpoint, group: group.name }))));
const totalRoutes = computed(() => allEndpoints.value.length);
const writeRoutes = computed(() => allEndpoints.value.filter((endpoint) => endpoint.method !== "GET").length);
const protectedRoutes = computed(() => allEndpoints.value.filter((endpoint) => endpoint.access !== "public" && endpoint.access !== "external").length);
const query = ref("");
const selectedGroup = ref("All");

function accessLabel(access: Access): string {
  switch (access) {
    case "public":
      return "Public";
    case "authenticated":
      return "Any authenticated user";
    case "student":
      return "Student";
    case "operations":
      return "Developer, admin, head, or staff";
    case "developer":
      return "Developer or admin";
    case "external":
      return "External integration";
  }
}

const methodClass: Record<HttpMethod, string> = {
  GET: "border-emerald-500/30 bg-emerald-500/10 text-emerald-500",
  POST: "border-blue-500/30 bg-blue-500/10 text-blue-500",
  PUT: "border-amber-500/30 bg-amber-500/10 text-amber-500",
  PATCH: "border-orange-500/30 bg-orange-500/10 text-orange-500",
  DELETE: "border-red-500/30 bg-red-500/10 text-red-500",
};

const accessClass: Record<Access, string> = {
  public: "bg-surface-muted text-text-muted",
  authenticated: "bg-warning-soft text-warning",
  student: "bg-info-soft text-info",
  operations: "bg-primary-soft text-primary",
  developer: "bg-danger-soft text-danger",
  external: "bg-success-soft text-success",
};

const filteredGroups = computed(() => {
  const term = query.value.trim().toLowerCase();

  return endpointGroups
    .filter((group) => selectedGroup.value === "All" || group.name === selectedGroup.value)
    .map((group) => ({
      ...group,
      endpoints: group.endpoints.filter((endpoint) => {
        if (!term) return true;
        return [
          group.name,
          endpoint.method,
          endpoint.path,
          endpoint.summary,
          endpoint.request ?? "",
          endpoint.response,
          accessLabel(endpoint.access),
        ]
          .join(" ")
          .toLowerCase()
          .includes(term);
      }),
    }))
    .filter((group) => group.endpoints.length > 0);
});

const activeRouteCount = computed(() =>
  filteredGroups.value.reduce((total, group) => total + group.endpoints.length, 0),
);
</script>

<template>
  <div class="space-y-5">
    <PageHeader
      title="API Documentation"
      description="Lightweight REST route reference for the Laravel API and Vue client integration."
    />

    <section class="grid gap-3 sm:grid-cols-3">
      <div class="rounded-lg border bg-surface p-4">
        <p class="text-xs font-medium text-text-muted">Documented Routes</p>
        <p class="mt-2 font-mono text-2xl font-bold text-text">{{ totalRoutes }}</p>
      </div>
      <div class="rounded-lg border bg-surface p-4">
        <p class="text-xs font-medium text-text-muted">Write Operations</p>
        <p class="mt-2 font-mono text-2xl font-bold text-text">{{ writeRoutes }}</p>
      </div>
      <div class="rounded-lg border bg-surface p-4">
        <p class="text-xs font-medium text-text-muted">Sanctum Protected</p>
        <p class="mt-2 font-mono text-2xl font-bold text-text">{{ protectedRoutes }}</p>
      </div>
    </section>

    <section class="grid gap-4 lg:grid-cols-[260px_1fr]">
      <aside class="lg:sticky lg:top-20 lg:max-h-[calc(100dvh-6rem)] lg:overflow-y-auto">
        <div class="rounded-lg border bg-surface p-3">
          <label class="block text-2xs font-semibold uppercase text-text-soft" for="api-filter">
            Search
          </label>
          <input
            id="api-filter"
            v-model="query"
            class="mt-2 h-9 w-full rounded-md border bg-surface-muted px-3 text-xs text-text placeholder:text-text-soft"
            placeholder="Path, method, access..."
          />

          <div class="mt-4 space-y-1">
            <button
              :class="[
                'flex w-full items-center justify-between rounded-md px-2 py-2 text-left text-xs',
                selectedGroup === 'All' ? 'bg-primary-soft text-primary' : 'text-text-muted hover:bg-surface-muted hover:text-text',
              ]"
              @click="selectedGroup = 'All'"
            >
              <span>All routes</span>
              <span class="font-mono text-2xs">{{ totalRoutes }}</span>
            </button>
            <button
              v-for="group in endpointGroups"
              :key="group.name"
              :class="[
                'flex w-full items-center justify-between gap-2 rounded-md px-2 py-2 text-left text-xs',
                selectedGroup === group.name ? 'bg-primary-soft text-primary' : 'text-text-muted hover:bg-surface-muted hover:text-text',
              ]"
              @click="selectedGroup = group.name"
            >
              <span class="truncate">{{ group.name }}</span>
              <span class="font-mono text-2xs">{{ group.endpoints.length }}</span>
            </button>
          </div>
        </div>
      </aside>

      <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border bg-surface px-4 py-3">
          <p class="text-xs text-text-muted">
            Showing <span class="font-mono font-semibold text-text">{{ activeRouteCount }}</span> routes
          </p>
          <p class="text-2xs text-text-soft">Sanctum bearer token is required unless marked Public or External.</p>
        </div>

        <section
          v-for="group in filteredGroups"
          :id="group.name.toLowerCase().replaceAll(' ', '-')"
          :key="group.name"
          class="overflow-hidden rounded-lg border bg-surface"
        >
          <header class="border-b bg-surface-muted/40 px-4 py-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <h2 class="text-sm font-semibold text-text">{{ group.name }}</h2>
              <span class="rounded bg-surface-muted px-2 py-1 font-mono text-2xs text-text-muted">
                {{ group.endpoints.length }} routes
              </span>
            </div>
            <p class="mt-1 text-xs leading-relaxed text-text-muted">{{ group.description }}</p>
          </header>

          <div class="divide-y">
            <details
              v-for="endpoint in group.endpoints"
              :key="`${endpoint.method}-${endpoint.path}`"
              class="group"
            >
              <summary class="flex cursor-pointer list-none flex-col gap-2 px-4 py-3 hover:bg-surface-muted/50 sm:flex-row sm:items-center sm:justify-between">
                <span class="flex min-w-0 items-center gap-2">
                  <span :class="['w-14 rounded border px-2 py-0.5 text-center font-mono text-2xs font-bold', methodClass[endpoint.method]]">
                    {{ endpoint.method }}
                  </span>
                  <code class="truncate font-mono text-xs text-text">{{ endpoint.path }}</code>
                </span>
                <span class="flex shrink-0 items-center gap-2">
                  <span :class="['rounded px-1.5 py-0.5 text-2xs font-medium', accessClass[endpoint.access]]">
                    {{ accessLabel(endpoint.access) }}
                  </span>
                  <span v-if="endpoint.throttle" class="rounded bg-surface-muted px-1.5 py-0.5 font-mono text-2xs text-text-muted">
                    {{ endpoint.throttle }}
                  </span>
                </span>
              </summary>

              <div class="grid gap-3 px-4 pb-4 sm:grid-cols-[1fr_1fr]">
                <div>
                  <h3 class="text-2xs font-semibold uppercase text-text-soft">Summary</h3>
                  <p class="mt-1 text-xs leading-relaxed text-text-muted">{{ endpoint.summary }}</p>
                </div>
                <div>
                  <h3 class="text-2xs font-semibold uppercase text-text-soft">Response</h3>
                  <pre class="mt-1 overflow-auto rounded-md bg-surface-muted p-3 font-mono text-2xs text-text-muted">{{ endpoint.response }}</pre>
                </div>
                <div v-if="endpoint.request" class="sm:col-span-2">
                  <h3 class="text-2xs font-semibold uppercase text-text-soft">Request</h3>
                  <pre class="mt-1 overflow-auto rounded-md bg-surface-muted p-3 font-mono text-2xs text-text-muted">{{ endpoint.request }}</pre>
                </div>
              </div>
            </details>
          </div>
        </section>

        <div v-if="filteredGroups.length === 0" class="rounded-lg border bg-surface p-8 text-center text-sm text-text-muted">
          No routes match the current filters.
        </div>
      </div>
    </section>
  </div>
</template>
