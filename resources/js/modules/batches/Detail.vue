<script setup lang="ts">
import { onMounted, ref } from "vue";
import { useRoute } from "vue-router";
import {
  IconArrowLeft,
  IconCalendarDue,
  IconLock,
  IconMail,
  IconPower,
  IconRefresh,
  IconUserPlus,
} from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import DataTable from "@/components/tables/DataTable.vue";
import AppDialog from "@/components/dialogs/AppDialog.vue";
import { csrfToken } from "@/auth/session";

type Grantee = {
  id: number;
  student_id: string;
  student_number: string | null;
  full_name: string;
  email: string;
  program: string;
  status: string;
  account_status: string | null;
};
type Batch = {
  id: number;
  name: string;
  academic_year: string;
  semester: string;
  submission_deadline: string | null;
  is_active: boolean;
  window_status: "draft" | "active" | "closed" | "expired";
  grantees_count: number;
  grantees: Grantee[];
};

const route = useRoute();
const batch = ref<Batch | null>(null);
const loading = ref(true);
const busy = ref("");
const error = ref("");
const mailResult = ref("");
const extendDialog = ref(false);
const newDeadline = ref("");

onMounted(loadBatch);

async function loadBatch() {
  loading.value = true;
  error.value = "";
  try {
    const response = await fetch(`/api/batches/${route.params.id}`, {
      headers: { Accept: "application/json" },
    });
    const payload = await response.json();
    if (!response.ok) throw new Error(payload.message || "Unable to load batch.");
    batch.value = payload.data;
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "Unable to load batch.";
  } finally {
    loading.value = false;
  }
}

async function action(path: string, label: string, body?: Record<string, string>) {
  if (!batch.value) return;
  busy.value = label;
  error.value = "";
  mailResult.value = "";
  try {
    const response = await fetch(`/api/batches/${batch.value.id}/${path}`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": csrfToken(),
        Accept: "application/json",
      },
      body: body ? JSON.stringify(body) : undefined,
    });
    const payload = await response.json();
    if (!response.ok) {
      const validation = payload.errors ? Object.values(payload.errors).flat().join(" ") : "";
      throw new Error(validation || payload.message || "Batch action failed.");
    }
    batch.value = { ...batch.value, ...payload.data };
    mailResult.value = payload.mail
      ? `${payload.mail.sent} email notifications sent, ${payload.mail.failed.length} failed.`
      : "";
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "Batch action failed.";
  } finally {
    busy.value = "";
  }
}

async function extend(close: () => void) {
  await action("extend-deadline", "extend", { submission_deadline: newDeadline.value });
  if (!error.value) close();
}

function statusClass(status: Batch["window_status"]) {
  if (status === "active") return "bg-success-soft text-success";
  if (status === "expired") return "bg-danger-soft text-danger";
  if (status === "closed") return "bg-warning-soft text-warning";
  return "bg-surface-muted text-text-muted";
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
      :title="batch?.name || 'Batch'"
      :description="batch ? `${batch.academic_year} - ${batch.semester}` : 'Loading batch'"
    >
      <template v-if="batch" #actions>
        <button
          v-if="batch.window_status !== 'active'"
          class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-xs text-white disabled:opacity-60"
          :disabled="Boolean(busy)"
          @click="action('activate', 'activate')"
        >
          <IconPower :size="14" />Activate window
        </button>
        <button
          v-else
          class="inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-xs disabled:opacity-60"
          :disabled="Boolean(busy)"
          @click="action('deactivate', 'deactivate')"
        >
          <IconLock :size="14" />Close window
        </button>
        <button
          class="inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-xs"
          @click="
            newDeadline = batch.submission_deadline ? batch.submission_deadline.slice(0, 16) : '';
            extendDialog = true;
          "
        >
          <IconRefresh :size="14" />Extend deadline
        </button>
      </template>
    </PageHeader>

    <p v-if="loading" class="rounded-lg border bg-surface p-4 text-sm text-text-muted">
      Loading batch...
    </p>
    <p v-if="error" class="mb-4 rounded-md border border-danger/30 bg-danger-soft p-3 text-xs text-danger">
      {{ error }}
    </p>
    <p v-if="mailResult" class="mb-4 rounded-md border bg-surface-muted p-3 text-xs text-text-muted">
      <IconMail :size="14" class="mr-1 inline text-primary" />{{ mailResult }}
    </p>

    <template v-if="batch">
      <section class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <article class="rounded-lg border bg-surface p-4">
          <p class="text-xs text-text-muted">Window status</p>
          <p :class="['mt-1 w-fit rounded-full px-2 py-1 text-xs font-semibold', statusClass(batch.window_status)]">
            {{ batch.window_status }}
          </p>
        </article>
        <article class="rounded-lg border bg-surface p-4">
          <p class="text-xs text-text-muted">Grantees</p>
          <p class="mt-1 text-xl font-semibold">{{ batch.grantees_count }}</p>
        </article>
        <article class="rounded-lg border bg-surface p-4 lg:col-span-2">
          <p class="flex items-center gap-1 text-xs text-text-muted">
            <IconCalendarDue :size="14" />Submission deadline
          </p>
          <p class="mt-1 text-sm font-semibold">
            {{ batch.submission_deadline ? new Date(batch.submission_deadline).toLocaleString() : "No deadline set" }}
          </p>
        </article>
      </section>

      <DataTable
        :headings="['Student ID', 'Student name', 'Email', 'Program', 'Account', 'Grantee']"
      >
        <tr v-for="member in batch.grantees" :key="member.id">
          <td class="px-3 py-3 font-mono">{{ member.student_id }}</td>
          <td class="px-3 py-3 font-medium">{{ member.full_name }}</td>
          <td class="px-3 py-3 text-text-muted">{{ member.email }}</td>
          <td class="px-3 py-3 text-text-muted">{{ member.program }}</td>
          <td class="px-3 py-3 capitalize">{{ member.account_status || "not linked" }}</td>
          <td class="px-3 py-3 capitalize">{{ member.status }}</td>
        </tr>
        <tr v-if="!batch.grantees.length">
          <td colspan="6" class="px-3 py-8 text-center text-text-muted">
            No grantees assigned to this batch yet.
          </td>
        </tr>
      </DataTable>

      <section class="mt-4 rounded-lg border bg-surface p-4">
        <h2 class="flex items-center gap-2 text-sm font-semibold">
          <IconUserPlus :size="16" /> Add grantees through Masterlist Import
        </h2>
        <p class="mt-1 text-xs text-text-muted">
          Grantees are assigned to a batch when the CHED masterlist import is confirmed.
        </p>
      </section>
    </template>

    <AppDialog
      v-model="extendDialog"
      title="Extend submission deadline"
      description="Set a future deadline. If this batch is active, students regain upload access until the new deadline."
      size="sm"
    >
      <label class="block text-xs font-medium">
        New deadline
        <input
          v-model="newDeadline"
          type="datetime-local"
          class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm"
        />
      </label>
      <template #footer="{ close }">
        <button class="rounded-md border px-4 py-2 text-xs" @click="close">Cancel</button>
        <button
          class="rounded-md bg-primary px-4 py-2 text-xs text-white disabled:opacity-60"
          :disabled="busy === 'extend'"
          @click="extend(close)"
        >
          {{ busy === "extend" ? "Saving..." : "Extend deadline" }}
        </button>
      </template>
    </AppDialog>
  </div>
</template>
