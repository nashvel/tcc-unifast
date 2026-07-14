<script setup lang="ts">
import { ref } from "vue";
import {
  IconArrowLeft,
  IconBrandFacebook,
  IconDownload,
  IconFileSpreadsheet,
  IconId,
  IconMail,
  IconNote,
  IconUserPlus,
} from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import DataTable from "@/components/tables/DataTable.vue";
import AppDialog from "@/components/dialogs/AppDialog.vue";
import { csrfToken } from "@/auth/session";

const memberDialog = ref(false);
const notifyDialog = ref(false);
const spreadsheetDialog = ref(false);
const facebookDialog = ref(false);
const idSampleDialog = ref(false);
const postTemplate = ref(
  "TCC UniFAST TES Batch 1 grantees are now available for account activation. Please check your registered email for your temporary password and activate your student portal account. After login, upload your student ID and complete face verification.",
);
const internalNote = ref(
  "Coordinate with registrar before public posting. Confirm bounced emails through SMTP logs.",
);
const emailSubject = ref("Activate your TCC UniFAST TES student portal account");
const emailMessage = ref(
  "Your student portal account has been created from the TES masterlist. Use the temporary password below to activate your account, then change your password and complete identity verification.",
);
const notifying = ref(false);
const notifyResult = ref("");
const selectedStudent = ref<string[]>([]);
const idSampleFile = ref<File | null>(null);
const idSampleBusy = ref(false);
const idSampleResult = ref("");

const members = [
  ["STU-0001", "Maria Angela Santos", "student001@tcc.edu.ph", "2024-00182", "Inactive"],
  ["STU-0002", "John Paul Ramirez", "student002@tcc.edu.ph", "2024-00194", "Inactive"],
  ["STU-0003", "Nicole Anne Flores", "student003@tcc.edu.ph", "2024-00207", "Inactive"],
  ["STU-0004", "Christian Dela Cruz", "student004@tcc.edu.ph", "2024-00231", "Inactive"],
];

async function notifyBatch(close: () => void) {
  notifying.value = true;
  notifyResult.value = "";
  try {
    const response = await fetch("/api/batches/1/activation-notifications", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": csrfToken(),
        Accept: "application/json",
      },
      body: JSON.stringify({ subject: emailSubject.value, message: emailMessage.value }),
    });
    const payload = await response.json();
    if (!response.ok && response.status !== 207) {
      throw new Error(payload.message || "Unable to send activation emails.");
    }
    notifyResult.value = `Queued through ${payload.mailer}: ${payload.sent} sent, ${payload.failed?.length ?? 0} failed.`;
    if (!payload.failed?.length) window.setTimeout(close, 900);
  } catch (error) {
    notifyResult.value =
      error instanceof Error ? error.message : "Unable to send activation emails.";
  } finally {
    notifying.value = false;
  }
}

function openIdSample(member: string[]) {
  selectedStudent.value = member;
  idSampleFile.value = null;
  idSampleResult.value = "";
  idSampleDialog.value = true;
}

function chooseIdSample(event: Event) {
  idSampleFile.value = (event.target as HTMLInputElement).files?.[0] ?? null;
  idSampleResult.value = "";
}

async function uploadIdSample(close: () => void) {
  if (!idSampleFile.value || !selectedStudent.value.length) {
    idSampleResult.value = "Choose an ID sample file first.";
    return;
  }

  idSampleBusy.value = true;
  idSampleResult.value = "";
  const body = new FormData();
  body.append("id_sample", idSampleFile.value);

  try {
    const response = await fetch(`/api/students/${selectedStudent.value[3]}/id-sample`, {
      method: "POST",
      headers: { "X-CSRF-TOKEN": csrfToken(), Accept: "application/json" },
      body,
    });
    const payload = await response.json();
    if (!response.ok) throw new Error(payload.message || "Unable to upload ID sample.");
    idSampleResult.value = payload.message || "Reference ID sample saved.";
    window.setTimeout(close, 800);
  } catch (error) {
    idSampleResult.value = error instanceof Error ? error.message : "Unable to upload ID sample.";
  } finally {
    idSampleBusy.value = false;
  }
}
</script>

<template>
  <div>
    <RouterLink
      to="/app/batches"
      class="mb-3 inline-flex items-center gap-1 text-xs text-text-muted hover:text-primary"
    >
      <IconArrowLeft :size="14" />Back to batches
    </RouterLink>

    <PageHeader
      title="TES 2025 — Batch 1"
      description="AY 2025–2026 · 1st Semester · accounts created inactive"
    >
      <template #actions>
        <button
          class="inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-xs"
          @click="spreadsheetDialog = true"
        >
          <IconFileSpreadsheet :size="14" />Generate spreadsheet
        </button>
        <button
          class="inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-xs"
          @click="facebookDialog = true"
        >
          <IconBrandFacebook :size="14" />Facebook post
        </button>
        <button
          class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-xs text-white"
          @click="notifyDialog = true"
        >
          <IconMail :size="14" />Notify activation
        </button>
        <button
          class="inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-xs"
          @click="memberDialog = true"
        >
          <IconUserPlus :size="14" />Add grantees
        </button>
      </template>
    </PageHeader>

    <section class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
      <article
        v-for="item in [
          ['Grantees', '1,248'],
          ['Inactive accounts', '1,248'],
          ['Activation emails', 'Ready'],
          ['Generated passwords', 'Secure'],
        ]"
        :key="item[0]"
        class="rounded-lg border bg-surface p-4"
      >
        <p class="text-xs text-text-muted">{{ item[0] }}</p>
        <p class="mt-1 text-xl font-semibold">{{ item[1] }}</p>
      </article>
    </section>

    <section class="mb-4 grid gap-3 lg:grid-cols-3">
      <article class="rounded-lg border bg-surface p-4 lg:col-span-2">
        <h2 class="flex items-center gap-2 text-sm font-semibold">
          <IconMail :size="16" /> Batch activation flow
        </h2>
        <ol class="mt-3 grid gap-2 text-xs text-text-muted sm:grid-cols-3">
          <li class="rounded-md bg-surface-muted p-3">
            1. Accounts are created inactive from the uploaded masterlist.
          </li>
          <li class="rounded-md bg-surface-muted p-3">
            2. SMTP sends activation link plus a random temporary password.
          </li>
          <li class="rounded-md bg-surface-muted p-3">
            3. Students activate, verify identity, then upload Course History and COR.
          </li>
        </ol>
      </article>
      <article class="rounded-lg border bg-surface p-4">
        <h2 class="flex items-center gap-2 text-sm font-semibold">
          <IconNote :size="16" /> Staff notes
        </h2>
        <textarea
          v-model="internalNote"
          class="mt-3 min-h-24 w-full rounded-md border p-3 text-xs"
        />
      </article>
    </section>

    <DataTable
      :headings="[
        'Student ID',
        'Student name',
        'Email',
        'Student number',
        'Account',
        'ID sample',
        '',
      ]"
    >
      <tr v-for="m in members" :key="m[0]">
        <td class="px-3 py-3 font-mono">{{ m[0] }}</td>
        <td class="px-3 py-3 font-medium">{{ m[1] }}</td>
        <td class="px-3 py-3 text-text-muted">{{ m[2] }}</td>
        <td class="px-3 py-3 font-mono text-text-muted">{{ m[3] }}</td>
        <td class="px-3 py-3">
          <span class="rounded-full bg-warning-soft px-2 py-0.5 text-micro text-warning">
            {{ m[4] }}
          </span>
        </td>
        <td class="px-3 py-3">
          <button
            class="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-micro text-primary"
            @click="openIdSample(m)"
          >
            <IconId :size="12" /> Add sample
          </button>
        </td>
        <td class="px-3 py-3 text-right">
          <RouterLink to="/app/grantees/1" class="text-primary">View</RouterLink>
        </td>
      </tr>
    </DataTable>

    <AppDialog
      v-model="memberDialog"
      title="Add grantees to batch"
      description="Select eligible grantees that are not already assigned to this batch."
      size="lg"
    >
      <input
        class="h-10 w-full rounded-md border px-3 text-sm"
        placeholder="Search student number or name"
      />
      <div class="mt-3 divide-y rounded-md border">
        <label
          v-for="name in [
            'Angelica Reyes · 2024-00252',
            'Mark Anthony Garcia · 2024-00268',
            'Princess Mae Lim · 2024-00281',
          ]"
          :key="name"
          class="flex items-center gap-3 p-3 text-sm"
        >
          <input type="checkbox" />{{ name }}
        </label>
      </div>
      <template #footer="{ close }">
        <button class="rounded-md border px-4 py-2 text-xs" @click="close">Cancel</button>
        <button class="rounded-md bg-primary px-4 py-2 text-xs text-white" @click="close">
          Add selected
        </button>
      </template>
    </AppDialog>

    <AppDialog
      v-model="idSampleDialog"
      title="Attach admin ID sample"
      :description="`Upload the official reference ID/photo for ${selectedStudent[1] ?? 'this student'}. Face verification can use this as the basis instead of asking the student for another reference sample.`"
      size="md"
    >
      <div class="space-y-3">
        <label
          class="block rounded-lg border-2 border-dashed border-border-strong p-4 text-center hover:border-primary"
        >
          <input
            type="file"
            accept=".pdf,.jpg,.jpeg,.png,.webp"
            class="hidden"
            @change="chooseIdSample"
          />
          <IconId :size="26" class="mx-auto text-primary" />
          <b class="mt-2 block text-sm">Upload official ID sample</b>
          <span class="mt-1 block text-xs text-text-muted">
            {{ idSampleFile ? idSampleFile.name : "PDF or image from registrar/masterlist record" }}
          </span>
        </label>
        <p class="rounded-md border bg-surface-muted p-2.5 text-xs text-text-muted">
          This file becomes the trusted reference image for face matching. It is stored privately in
          the app storage path and is not included in public spreadsheets or Facebook posts.
        </p>
        <p v-if="idSampleResult" class="text-xs text-text-muted">{{ idSampleResult }}</p>
      </div>
      <template #footer="{ close }">
        <button class="rounded-md border px-4 py-2 text-xs" @click="close">Cancel</button>
        <button
          class="rounded-md bg-primary px-4 py-2 text-xs text-white disabled:opacity-50"
          :disabled="idSampleBusy"
          @click="uploadIdSample(close)"
        >
          {{ idSampleBusy ? "Uploading..." : "Save ID sample" }}
        </button>
      </template>
    </AppDialog>

    <AppDialog
      v-model="notifyDialog"
      title="Notify Batch 1 to activate"
      description="SMTP will send activation instructions with a safely generated temporary password for each inactive student."
      size="lg"
    >
      <div class="space-y-3 text-sm">
        <div class="rounded-md border bg-surface-muted p-3 text-xs text-text-muted">
          Example generated password format: <b class="text-text">TCC-8F4K-29QZ</b>. The real value
          is generated per student and never stored in the frontend.
        </div>
        <label class="block text-xs font-medium">
          Email subject
          <input v-model="emailSubject" class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm" />
        </label>
        <label class="block text-xs font-medium">
          Email message
          <textarea
            v-model="emailMessage"
            class="mt-1.5 min-h-28 w-full rounded-md border p-3 text-sm"
          />
        </label>
        <p
          v-if="notifyResult"
          class="rounded-md border bg-surface-muted p-2.5 text-xs text-text-muted"
        >
          {{ notifyResult }}
        </p>
      </div>
      <template #footer="{ close }">
        <button class="rounded-md border px-4 py-2 text-xs" @click="close">Cancel</button>
        <button
          class="rounded-md bg-primary px-4 py-2 text-xs text-white disabled:opacity-50"
          :disabled="notifying"
          @click="notifyBatch(close)"
        >
          {{ notifying ? "Sending..." : "Queue SMTP notifications" }}
        </button>
      </template>
    </AppDialog>

    <AppDialog
      v-model="spreadsheetDialog"
      title="Generate Batch 1 spreadsheet"
      description="Create a Google Sheets-ready list for Batch 1 publication and tracking."
      size="md"
    >
      <div class="space-y-2 text-xs text-text-muted">
        <p>
          <IconDownload :size="14" class="mr-1 inline text-primary" />
          Includes student ID, name, student number, and activation status.
        </p>
        <p>Confidential fields such as temporary passwords are excluded from public exports.</p>
      </div>
      <template #footer="{ close }">
        <button class="rounded-md border px-4 py-2 text-xs" @click="close">Cancel</button>
        <button class="rounded-md bg-primary px-4 py-2 text-xs text-white" @click="close">
          Generate spreadsheet
        </button>
      </template>
    </AppDialog>

    <AppDialog
      v-model="facebookDialog"
      title="Facebook upload template"
      description="Prepare a predefined public post for Batch 1. Staff can edit and save new templates."
      size="lg"
    >
      <div class="space-y-3">
        <label class="block text-xs font-medium">
          Post template
          <textarea
            v-model="postTemplate"
            class="mt-1.5 min-h-36 w-full rounded-md border p-3 text-sm"
          />
        </label>
        <label class="block text-xs font-medium">
          Template notes
          <input
            class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm"
            placeholder="Example: Use this when activation emails are already sent."
          />
        </label>
      </div>
      <template #footer="{ close }">
        <button class="rounded-md border px-4 py-2 text-xs" @click="close">
          Save as new template
        </button>
        <button class="rounded-md bg-primary px-4 py-2 text-xs text-white" @click="close">
          Prepare Facebook upload
        </button>
      </template>
    </AppDialog>
  </div>
</template>
