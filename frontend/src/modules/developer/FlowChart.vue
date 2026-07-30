<script setup lang="ts">
import { computed, ref } from "vue";
import {
  IconAlertTriangle,
  IconArrowRight,
  IconCheck,
  IconGitBranch,
  IconLock,
  IconRefresh,
  IconShieldCheck,
} from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";

type StepKind = "entry" | "process" | "decision" | "success" | "blocked" | "integration";

type FlowStep = {
  id: string;
  title: string;
  detail: string;
  kind: StepKind;
  api?: string;
  next?: string[];
};

type Flow = {
  id: string;
  name: string;
  summary: string;
  steps: FlowStep[];
};

const flows: Flow[] = [
  {
    id: "portal",
    name: "Portal Access",
    summary: "How the Vue router and Sanctum session send users to the correct portal.",
    steps: [
      { id: "visit", title: "User opens app", detail: "Public pages include login, activation, forgot password, and help support.", kind: "entry", next: ["captcha"] },
      { id: "captcha", title: "Captcha and credentials", detail: "Login fetches a captcha, posts credentials, and receives a Sanctum bearer token.", kind: "process", api: "GET /api/auth/captcha, POST /api/auth/login", next: ["session"] },
      { id: "session", title: "Session resolved", detail: "The router loads /api/auth/me before entering /app or /student.", kind: "decision", api: "GET /api/auth/me", next: ["staff", "student", "locked"] },
      { id: "staff", title: "Operations portal", detail: "Developer, admin, head, and staff users enter /app and use operations modules by role.", kind: "success", next: ["logout"] },
      { id: "student", title: "Student portal", detail: "Verified students enter /student dashboard, documents, announcements, notifications, and settings.", kind: "success", next: ["logout"] },
      { id: "locked", title: "Student guard", detail: "Unverified, pending KYC, blocked, or pending identity students are redirected into required setup screens.", kind: "blocked", next: ["kyc"] },
      { id: "kyc", title: "Required setup", detail: "The student completes KYC first, then identity onboarding when account status requires it.", kind: "process", api: "GET/POST /api/student/kyc, /api/student/identity-onboarding", next: ["student"] },
      { id: "logout", title: "Logout", detail: "The current token is revoked and local auth state is cleared.", kind: "process", api: "POST /api/auth/logout" },
    ],
  },
  {
    id: "activation",
    name: "Masterlist to Activation",
    summary: "How staff import CHED data and invite grantees into the student portal.",
    steps: [
      { id: "csv", title: "Upload CHED masterlist", detail: "Operations users upload a CSV with batch metadata for preview.", kind: "entry", api: "POST /api/masterlist/imports/preview", next: ["preview"] },
      { id: "preview", title: "Preview rows", detail: "The system validates row shape, required fields, and import status before committing records.", kind: "decision", api: "GET /api/masterlist/imports/{import}", next: ["discard", "confirm"] },
      { id: "discard", title: "Discard failed import", detail: "Invalid previews can be deleted without creating grantees.", kind: "blocked", api: "DELETE /api/masterlist/imports/{import}" },
      { id: "confirm", title: "Confirm import", detail: "Valid rows become grantees and mail delivery metadata is returned.", kind: "process", api: "POST /api/masterlist/imports/{import}/confirm", next: ["batch"] },
      { id: "batch", title: "Manage batch", detail: "Staff activate, deactivate, extend deadlines, and send activation notifications.", kind: "process", api: "POST /api/batches/{batch}/activate, /activation-notifications", next: ["token"] },
      { id: "token", title: "Student activation link", detail: "The public activation route validates the token and creates the initial account password.", kind: "process", api: "GET/POST /api/activation/{token}", next: ["portal"] },
      { id: "portal", title: "Portal guard begins", detail: "The new student is routed to KYC or identity onboarding based on account status.", kind: "success" },
    ],
  },
  {
    id: "identity",
    name: "Student KYC & Identity",
    summary: "The first-time student verification path enforced before full portal access.",
    steps: [
      { id: "kyc-load", title: "Load KYC reference", detail: "The student sees masterlist reference data and any saved profile values.", kind: "entry", api: "GET /api/student/kyc", next: ["kyc-submit"] },
      { id: "kyc-submit", title: "Submit KYC profile", detail: "Name, student ID, program, contact, guardian, and household data are compared with masterlist records.", kind: "process", api: "POST /api/student/kyc", next: ["match"] },
      { id: "match", title: "Masterlist match?", detail: "Mismatches keep the student in KYC. Matched records advance to identity onboarding when required.", kind: "decision", next: ["retry", "id-scan"] },
      { id: "retry", title: "Correct mismatches", detail: "The student fixes blocked fields and resubmits KYC.", kind: "blocked", next: ["kyc-submit"] },
      { id: "id-scan", title: "Onboarding ID scan", detail: "The student submits school ID scan signals including QR/OCR/face data and consent.", kind: "process", api: "POST /api/student/identity-onboarding/id-scan", next: ["liveness"] },
      { id: "liveness", title: "Liveness challenge", detail: "The student completes face challenge steps and submits match distance/confidence.", kind: "process", api: "POST /api/student/identity-onboarding/liveness", next: ["verified"] },
      { id: "verified", title: "Identity verified", detail: "The student can use the standard student portal. Requirement vault access still depends on the active batch window.", kind: "success" },
    ],
  },
  {
    id: "vault",
    name: "Requirement Vault",
    summary: "The active batch submission path for school ID, academic PDFs, specimen signatures, liveness, and final confirmation.",
    steps: [
      { id: "window", title: "Check submission window", detail: "The student portal asks whether the student's batch is open.", kind: "entry", api: "GET /api/student/submission-window", next: ["open"] },
      { id: "open", title: "Window open?", detail: "Closed, inactive, or expired batches keep the vault locked.", kind: "decision", next: ["locked", "vault"] },
      { id: "locked", title: "Vault locked", detail: "The UI shows deadline or availability messaging and blocks final submission.", kind: "blocked" },
      { id: "vault", title: "Load vault slots", detail: "The vault returns current files, slot status, and identity-check readiness.", kind: "process", api: "GET /api/student/requirement-vault", next: ["id"] },
      { id: "id", title: "Upload school ID", detail: "The school ID scan is uploaded first and can unlock the rest of the requirement slots.", kind: "process", api: "POST /api/student/requirement-vault/id", next: ["docs"] },
      { id: "docs", title: "Upload requirements", detail: "Course history, grade slip, and specimen signature files are uploaded to named slots.", kind: "process", api: "POST /api/student/requirement-vault/document", next: ["ocr"] },
      { id: "ocr", title: "Extraction checks", detail: "OCR/PDF/QR signals are extracted and attached to submission metadata.", kind: "process", api: "POST /api/student/submissions/ocr", next: ["face"] },
      { id: "face", title: "Face verification", detail: "Live face signals are compared against stored onboarding references.", kind: "process", api: "POST /api/student/identity/face-verify", next: ["identity"] },
      { id: "identity", title: "Batch identity check", detail: "The student submits the liveness challenge result for the active batch.", kind: "process", api: "POST /api/student/requirement-vault/identity-check", next: ["confirm"] },
      { id: "confirm", title: "Confirm vault", detail: "All completed slots and identity checks finalize the grantee submission status.", kind: "success", api: "POST /api/student/requirement-vault/confirm" },
    ],
  },
  {
    id: "review",
    name: "Staff Review & Eligibility",
    summary: "How operations users inspect submissions, review risk, notify students, and report outcomes.",
    steps: [
      { id: "queue", title: "Review queue", detail: "Staff list document submissions with filters, risk badges, and identity-review flags.", kind: "entry", api: "GET /api/document-submissions", next: ["detail"] },
      { id: "detail", title: "Submission detail", detail: "Staff inspect files, OCR text, metadata payload, face scores, and review notes.", kind: "process", api: "GET /api/document-submissions/{submission}", next: ["decision"] },
      { id: "decision", title: "Review decision", detail: "Staff approve, reject, or request resubmission with notes.", kind: "decision", api: "POST /api/document-submissions/{submission}/review", next: ["eligible", "resubmit"] },
      { id: "resubmit", title: "Student notified", detail: "Rejected or resubmission outcomes return the student to the document path.", kind: "blocked", api: "GET/POST /api/notifications", next: ["queue"] },
      { id: "eligible", title: "Eligibility computed", detail: "GWA, failed subjects, dropped subjects, and configured policies drive eligibility status.", kind: "process", api: "GET /api/eligibility, GET/PUT /api/policy-settings", next: ["notify"] },
      { id: "notify", title: "Notify grantee", detail: "Staff can send eligibility notifications to the student.", kind: "process", api: "POST /api/eligibility/{grantee}/notify", next: ["reports"] },
      { id: "reports", title: "Billing and distribution", detail: "Approved outcomes feed billing and distribution report generation/downloads.", kind: "success", api: "GET/POST /api/billing-reports, /api/distribution-reports" },
    ],
  },
  {
    id: "integration",
    name: "n8n Integration",
    summary: "Webhook-style routes that exchange TCC UniFAST student records with n8n.",
    steps: [
      { id: "source", title: "n8n workflow", detail: "An external workflow pushes or pulls student data without Sanctum auth.", kind: "entry", next: ["throttle"] },
      { id: "throttle", title: "Throttle boundary", detail: "Sync writes are limited to 30 requests/minute; student reads are limited to 120 requests/minute.", kind: "decision", next: ["sync", "students"] },
      { id: "sync", title: "Inbound sync", detail: "n8n posts TCC UniFAST student payloads into the backend integration controller.", kind: "integration", api: "POST /api/integrations/n8n/tcc-unifast/sync", next: ["audit"] },
      { id: "students", title: "Outbound student feed", detail: "n8n can retrieve student records for downstream workflows.", kind: "integration", api: "GET /api/integrations/n8n/tcc-unifast/students", next: ["audit"] },
      { id: "audit", title: "Operational visibility", detail: "Imported data appears in grantee, batch, database, and audit/admin views depending on workflow result.", kind: "success" },
    ],
  },
];

const selectedFlowId = ref(flows[0].id);
const selectedFlow = computed(() => flows.find((flow) => flow.id === selectedFlowId.value) ?? flows[0]);

const kindMeta: Record<StepKind, { label: string; className: string; icon: typeof IconGitBranch }> = {
  entry: { label: "Entry", className: "border-success/30 bg-success-soft text-success", icon: IconGitBranch },
  process: { label: "Process", className: "border-primary/30 bg-primary-soft text-primary", icon: IconRefresh },
  decision: { label: "Decision", className: "border-warning/30 bg-warning-soft text-warning", icon: IconGitBranch },
  success: { label: "End State", className: "border-info/30 bg-info-soft text-info", icon: IconCheck },
  blocked: { label: "Blocked Path", className: "border-danger/30 bg-danger-soft text-danger", icon: IconAlertTriangle },
  integration: { label: "Integration", className: "border-text-muted/30 bg-surface-muted text-text-muted", icon: IconShieldCheck },
};
</script>

<template>
  <div class="space-y-5">
    <PageHeader
      title="System Flow Charts"
      description="Current workflow map for portal access, student verification, requirement submission, staff review, and integrations."
    />

    <div class="flex gap-2 overflow-x-auto">
      <button
        v-for="flow in flows"
        :key="flow.id"
        :class="[
          'h-9 shrink-0 rounded-md border px-3 text-xs font-medium transition-colors',
          selectedFlowId === flow.id
            ? 'border-primary bg-primary text-white'
            : 'bg-surface text-text-muted hover:bg-surface-muted',
        ]"
        @click="selectedFlowId = flow.id"
      >
        {{ flow.name }}
      </button>
    </div>

    <section class="rounded-lg border bg-surface">
      <div class="border-b px-5 py-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 class="text-base font-semibold text-text">{{ selectedFlow.name }}</h2>
            <p class="mt-1 text-xs text-text-muted">{{ selectedFlow.summary }}</p>
          </div>
          <span class="rounded bg-surface-muted px-2 py-1 font-mono text-2xs text-text-muted">
            {{ selectedFlow.steps.length }} steps
          </span>
        </div>
      </div>

      <div class="overflow-x-auto p-5">
        <div class="flex min-w-[760px] items-stretch gap-3">
          <template v-for="(step, index) in selectedFlow.steps" :key="step.id">
            <article class="flex w-56 shrink-0 flex-col rounded-lg border bg-background p-3">
              <div class="flex items-start justify-between gap-2">
                <span :class="['inline-flex items-center gap-1 rounded border px-1.5 py-0.5 text-2xs font-medium', kindMeta[step.kind].className]">
                  <component :is="kindMeta[step.kind].icon" :size="11" />
                  {{ kindMeta[step.kind].label }}
                </span>
                <span class="font-mono text-2xs text-text-soft">{{ index + 1 }}</span>
              </div>
              <h3 class="mt-3 text-sm font-semibold leading-snug text-text">{{ step.title }}</h3>
              <p class="mt-2 text-xs leading-relaxed text-text-muted">{{ step.detail }}</p>
              <code v-if="step.api" class="mt-3 block rounded bg-surface-muted p-2 font-mono text-2xs leading-relaxed text-text-muted">
                {{ step.api }}
              </code>
              <div v-if="step.next?.length" class="mt-auto pt-3">
                <p class="text-2xs font-semibold uppercase text-text-soft">Next</p>
                <p class="mt-1 font-mono text-2xs text-text-muted">{{ step.next.join(", ") }}</p>
              </div>
            </article>

            <div v-if="index < selectedFlow.steps.length - 1" class="flex items-center text-text-soft">
              <IconArrowRight :size="18" />
            </div>
          </template>
        </div>
      </div>
    </section>

    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
      <div
        v-for="(meta, kind) in kindMeta"
        :key="kind"
        class="flex items-center gap-3 rounded-lg border bg-surface p-3"
      >
        <span :class="['grid size-8 place-items-center rounded border', meta.className]">
          <component :is="meta.icon" :size="15" />
        </span>
        <div>
          <p class="text-xs font-semibold text-text">{{ meta.label }}</p>
          <p class="text-2xs text-text-muted">
            {{ kind === 'blocked' ? 'Failure, retry, or locked branch' : kind === 'decision' ? 'Branching condition' : 'Workflow node' }}
          </p>
        </div>
      </div>
    </section>

    <section class="rounded-lg border bg-surface p-4">
      <div class="flex items-start gap-3">
        <span class="mt-0.5 grid size-8 shrink-0 place-items-center rounded-md bg-warning-soft text-warning">
          <IconLock :size="16" />
        </span>
        <div>
          <h2 class="text-sm font-semibold text-text">Route Guard Notes</h2>
          <p class="mt-1 text-xs leading-relaxed text-text-muted">
            The Vue router keeps unauthenticated users out of /app and /student, sends students away from /app,
            sends staff/admin users away from /student, and forces locked student accounts through KYC or identity
            onboarding before ordinary student pages are available.
          </p>
        </div>
      </div>
    </section>
  </div>
</template>
