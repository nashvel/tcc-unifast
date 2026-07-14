<script setup lang="ts">
import { computed, ref } from "vue";
import { IconArrowLeft, IconInfoCircle, IconPaperclip, IconSend } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
const submitted = ref(false);
const category = ref("Technical incident");
const priority = ref("Normal");
const impact = ref("Single user");
const urgency = ref("Normal");
const anonymous = ref(false);
const categories: Record<string, string[]> = {
  "Technical incident": [
    "Page error",
    "Upload failure",
    "Report/export issue",
    "Performance",
    "Mobile/display issue",
    "Integration failure",
  ],
  "Account & access": [
    "Cannot sign in",
    "Activation",
    "Locked account",
    "Password reset",
    "Role/permission",
    "Suspicious access",
  ],
  "Data correction": [
    "Personal details",
    "Academic record",
    "Eligibility result",
    "Document metadata",
    "Batch assignment",
    "Duplicate record",
  ],
  "Service request": [
    "New user",
    "Access request",
    "Report request",
    "Bulk update",
    "Configuration change",
    "Training request",
  ],
  "Feature request": [
    "New feature",
    "Workflow improvement",
    "UI improvement",
    "Automation",
    "Integration",
  ],
  "Disbursement concern": [
    "Missing payment",
    "Incorrect amount",
    "Delayed release",
    "Payment status",
    "Bank/account details",
  ],
  "Security & privacy": [
    "Security incident",
    "Privacy concern",
    "Phishing",
    "Data exposure",
    "Lost device",
    "Vulnerability",
  ],
  "Complaint or appeal": [
    "Service complaint",
    "Eligibility appeal",
    "Document decision appeal",
    "Staff conduct",
    "Policy concern",
  ],
  "General inquiry": [
    "Program question",
    "Deadline",
    "Requirements",
    "Process guidance",
    "Other inquiry",
  ],
};
const subcategories = computed(() => categories[category.value] ?? []);
const sla = computed(() =>
  priority.value === "Critical"
    ? "15-minute response"
    : priority.value === "Urgent"
      ? "1-hour response"
      : priority.value === "High"
        ? "4-hour response"
        : "1-business-day response",
);
</script>
<template>
  <div>
    <RouterLink
      to="/app/support"
      class="mb-3 inline-flex items-center gap-1 text-xs text-text-muted"
      ><IconArrowLeft :size="14" />Support inbox</RouterLink
    >
    <PageHeader
      title="New support ticket"
      description="Provide enough context for the support team to route and resolve the request."
    />
    <form
      class="grid gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)]"
      @submit.prevent="submitted = true"
    >
      <div class="space-y-4">
        <section class="rounded-xl border bg-surface p-5">
          <h2 class="text-sm font-semibold">Request classification</h2>
          <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <label class="text-xs font-medium sm:col-span-2"
              >Subject<input
                required
                class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm"
                placeholder="Briefly describe the issue or request"
            /></label>
            <label class="text-xs font-medium"
              >Category<select
                v-model="category"
                class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm"
              >
                <option v-for="(_, name) in categories" :key="name">{{ name }}</option>
              </select></label
            >
            <label class="text-xs font-medium"
              >Scenario<select class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm">
                <option v-for="item in subcategories" :key="item">{{ item }}</option>
              </select></label
            >
            <label class="text-xs font-medium"
              >Related module<select
                class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm"
              >
                <option>Dashboard</option>
                <option>Masterlist</option>
                <option>Grantees</option>
                <option>Documents</option>
                <option>Academic records</option>
                <option>Eligibility</option>
                <option>Batches</option>
                <option>Reports</option>
                <option>Users & roles</option>
                <option>Student portal</option>
                <option>Not applicable</option>
              </select></label
            >
            <label class="text-xs font-medium"
              >Related record<input
                class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm"
                placeholder="Student #, batch, report, or ticket ID"
            /></label>
          </div>
        </section>
        <section class="rounded-xl border bg-surface p-5">
          <h2 class="text-sm font-semibold">Impact and urgency</h2>
          <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <label class="text-xs font-medium"
              >Priority<select
                v-model="priority"
                class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm"
              >
                <option>Low</option>
                <option>Normal</option>
                <option>High</option>
                <option>Urgent</option>
                <option>Critical</option>
              </select></label
            >
            <label class="text-xs font-medium"
              >Impact<select
                v-model="impact"
                class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm"
              >
                <option>Single user</option>
                <option>Multiple users</option>
                <option>Whole office</option>
                <option>All users</option>
              </select></label
            >
            <label class="text-xs font-medium"
              >Urgency<select
                v-model="urgency"
                class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm"
              >
                <option>Low</option>
                <option>Normal</option>
                <option>High</option>
                <option>Immediate</option>
              </select></label
            >
            <label class="text-xs font-medium"
              >Environment<select
                class="mt-1.5 h-10 w-full rounded-md border bg-surface px-3 text-sm"
              >
                <option>Production</option>
                <option>Testing</option>
                <option>Mobile</option>
                <option>Not applicable</option>
              </select></label
            >
          </div>
        </section>
        <section class="rounded-xl border bg-surface p-5">
          <h2 class="text-sm font-semibold">Details</h2>
          <div class="mt-4 space-y-4">
            <label class="block text-xs font-medium"
              >Description<textarea
                required
                class="mt-1.5 min-h-36 w-full rounded-md border p-3 text-sm"
                placeholder="What happened, who is affected, and what outcome do you need?"
              />
            </label>
            <div class="grid gap-4 sm:grid-cols-2">
              <label class="text-xs font-medium"
                >Steps to reproduce<textarea
                  class="mt-1.5 min-h-28 w-full rounded-md border p-3 text-sm"
                  placeholder="1. Open…&#10;2. Select…&#10;3. Error appears…"
                /></label
              ><label class="text-xs font-medium"
                >Expected result<textarea
                  class="mt-1.5 min-h-28 w-full rounded-md border p-3 text-sm"
                  placeholder="Describe the expected behavior or correct data"
                />
              </label>
            </div>
            <label class="block text-xs font-medium"
              >Attachment
              <div
                class="mt-1.5 flex min-h-24 items-center justify-center rounded-md border border-dashed text-xs text-text-muted"
              >
                <IconPaperclip :size="15" class="mr-2" />Add screenshots, PDFs, logs, or supporting
                documents
              </div></label
            >
          </div>
        </section>
        <section class="rounded-xl border bg-surface p-5">
          <h2 class="text-sm font-semibold">People and notifications</h2>
          <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <label class="text-xs font-medium"
              >Requested for<input
                class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm"
                value="Admin User" /></label
            ><label class="text-xs font-medium"
              >CC / watchers<input
                class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm"
                placeholder="Names or email addresses"
            /></label>
          </div>
          <div class="mt-4 flex flex-wrap gap-5">
            <label class="flex items-center gap-2 text-xs"
              ><input type="checkbox" checked />Email updates</label
            ><label class="flex items-center gap-2 text-xs"
              ><input type="checkbox" checked />In-app updates</label
            ><label class="flex items-center gap-2 text-xs"
              ><input v-model="anonymous" type="checkbox" />Submit confidentially</label
            >
          </div>
        </section>
      </div>
      <aside class="h-fit space-y-4 xl:sticky xl:top-20">
        <section class="rounded-xl border bg-surface p-5">
          <h2 class="text-sm font-semibold">Ticket summary</h2>
          <dl class="mt-4 space-y-3 text-xs">
            <div class="flex justify-between">
              <dt class="text-text-muted">Category</dt>
              <dd class="font-medium">{{ category }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-text-muted">Priority</dt>
              <dd class="font-medium">{{ priority }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-text-muted">Impact</dt>
              <dd class="font-medium">{{ impact }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-text-muted">Urgency</dt>
              <dd class="font-medium">{{ urgency }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-text-muted">Visibility</dt>
              <dd class="font-medium">{{ anonymous ? "Confidential" : "Standard" }}</dd>
            </div>
          </dl>
        </section>
        <section class="rounded-xl border border-info/30 bg-info-soft p-4">
          <p class="flex items-center gap-2 text-xs font-semibold text-info">
            <IconInfoCircle :size="15" />Expected service level
          </p>
          <p class="mt-2 text-xs leading-5 text-text-muted">
            {{ sla }} based on the selected priority. Critical security and widespread outages are
            escalated immediately.
          </p>
        </section>
        <button
          class="inline-flex w-full items-center justify-center gap-1.5 rounded-md bg-primary px-4 py-2.5 text-xs text-white"
        >
          <IconSend :size="14" />Submit ticket
        </button>
        <p v-if="submitted" class="rounded-md bg-success-soft p-3 text-center text-xs text-success">
          Mock ticket SUP-2026-0185 created.
        </p>
      </aside>
    </form>
  </div>
</template>
