<script setup lang="ts">
import { computed, ref } from "vue";
import {
  IconAlertTriangle,
  IconArrowRight,
  IconCheck,
  IconMail,
  IconFileSpreadsheet,
  IconSearch,
  IconUpload,
  IconUsers,
} from "@tabler/icons-vue";
import DataTable from "@/components/tables/DataTable.vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import AppDialog from "@/components/dialogs/AppDialog.vue";
import { masterlist } from "./data";

const previewed = ref(false);
const query = ref("");
const confirmDialog = ref(false);

const rows = computed(() =>
  masterlist.filter((row) => {
    return `${row[0]} ${row[1]} ${row[2]} ${row[3]}`
      .toLowerCase()
      .includes(query.value.toLowerCase());
  }),
);

const counts = computed(() => ({
  total: masterlist.length,
  active: masterlist.filter((row) => row[4] === "active").length,
  pending: masterlist.filter((row) => row[4] === "pending_activation").length,
  inactive: masterlist.filter((row) => row[4] === "inactive").length,
  duplicate: masterlist.filter((row) => row[4] === "duplicate").length,
  invalid: masterlist.filter((row) => row[4] === "invalid").length,
}));

const importableCount = computed(
  () => counts.value.total - counts.value.duplicate - counts.value.invalid,
);
</script>

<template>
  <div>
    <PageHeader
      title="Masterlist Import"
      description="Head uploads the TES masterlist. For the mockup, it only needs student ID, student name, email, and student number."
    >
      <template #actions>
        <button class="inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-xs">
          <IconUpload :size="14" />
          Download template
        </button>
        <button
          class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-xs font-medium text-white"
          @click="previewed = true"
        >
          <IconArrowRight :size="14" />
          Process import
        </button>
      </template>
    </PageHeader>

    <div v-if="!previewed" class="grid gap-4 lg:grid-cols-3">
      <button
        data-tour="masterlist-upload"
        class="flex min-h-64 flex-col items-center justify-center rounded-lg border-2 border-dashed bg-surface p-8 text-center transition hover:border-primary/50 hover:bg-primary/5 lg:col-span-2"
        @click="previewed = true"
      >
        <span
          class="mb-3 grid size-12 place-items-center rounded-full bg-primary-soft text-primary"
        >
          <IconFileSpreadsheet :size="24" />
        </span>
        <span class="text-sm font-semibold">Drop your masterlist here or browse</span>
        <span class="mt-1 text-xs text-text-muted">CSV or XLSX up to 20MB</span>
      </button>

      <aside class="rounded-lg border bg-surface p-4" data-tour="masterlist-rules">
        <h2 class="text-sm font-semibold">Import rules</h2>
        <ul class="mt-3 list-inside list-disc space-y-2 text-xs leading-5 text-text-muted">
          <li>The Office Head uploads the student masterlist.</li>
          <li>Required columns: student ID, student name, email, and student number.</li>
          <li>Student accounts are auto-generated from those records.</li>
          <li>All new accounts are inactive by default.</li>
          <li>Students activate by verifying their identity and contact details.</li>
          <li>Duplicate student numbers are flagged and skipped.</li>
          <li>Missing or malformed required data is marked invalid.</li>
        </ul>
      </aside>
    </div>

    <div v-else class="space-y-4">
      <section
        class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6"
        data-tour="masterlist-stats"
      >
        <article
          v-for="item in [
            ['Total rows', counts.total, 'text-text'],
            ['Active', counts.active, 'text-success'],
            ['Pending activation', counts.pending, 'text-warning'],
            ['Inactive', counts.inactive, 'text-text-muted'],
            ['Duplicate', counts.duplicate, 'text-info'],
            ['Invalid', counts.invalid, 'text-danger'],
          ]"
          :key="String(item[0])"
          class="rounded-lg border bg-surface p-3"
        >
          <p class="text-xs text-text-muted">{{ item[0] }}</p>
          <p :class="['mt-0.5 text-xl font-semibold tabular-nums', item[2]]">{{ item[1] }}</p>
        </article>
      </section>

      <div
        class="flex items-start gap-2 rounded-lg border border-warning/30 bg-warning-soft p-3 text-xs"
      >
        <IconAlertTriangle :size="15" class="mt-0.5 shrink-0 text-warning" />
        <div>
          <p class="font-medium text-warning">Some rows need attention</p>
          <p class="mt-0.5 text-text-muted">
            {{ counts.duplicate }} duplicate and {{ counts.invalid }} invalid row detected. They
            will not create accounts.
          </p>
        </div>
      </div>

      <section class="rounded-lg border bg-surface p-3">
        <div class="relative">
          <IconSearch :size="14" class="absolute left-3 top-1/2 -translate-y-1/2 text-text-soft" />
          <input
            v-model="query"
            class="h-9 w-full rounded-md border pl-9 pr-3 text-xs"
            placeholder="Search by student ID, name, email, or student number"
          />
        </div>
      </section>

      <DataTable :headings="['Student ID', 'Student name', 'Student email', 'Student number']">
        <tr v-for="row in rows" :key="String(row[0] || row[1])">
          <td class="px-3 py-3 font-mono">
            <span v-if="row[0]">{{ row[0] }}</span>
            <span v-else class="italic text-danger">missing</span>
          </td>
          <td class="px-3 py-3 font-medium">{{ row[1] }}</td>
          <td class="px-3 py-3 text-text-muted">{{ row[2] }}</td>
          <td class="px-3 py-3 text-text-muted">{{ row[3] }}</td>
        </tr>
        <tr v-if="!rows.length">
          <td colspan="4" class="p-8 text-center text-text-muted">No matching rows found.</td>
        </tr>
        <template #footer>
          <footer class="flex justify-between border-t px-3 py-2.5 text-xs text-text-muted">
            <span>Showing {{ rows.length }} of {{ masterlist.length }}</span>
            <span>Page 1 of 1</span>
          </footer>
        </template>
      </DataTable>

      <div class="flex justify-end gap-2">
        <button class="h-9 rounded-md border px-3 text-xs" @click="previewed = false">
          Cancel
        </button>
        <button
          class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-xs font-medium text-white"
          @click="confirmDialog = true"
        >
          <IconCheck :size="14" />
          Confirm import ({{ importableCount }} accounts)
        </button>
      </div>

      <section class="grid gap-3 rounded-xl border bg-surface p-4 lg:grid-cols-[1fr_auto]">
        <div>
          <h2 class="text-sm font-semibold">Next flow after upload</h2>
          <p class="mt-1 text-xs text-text-muted">
            Import creates inactive student accounts from the masterlist. When Batch 1 is ready, the
            Head can notify students by SMTP with a safely generated temporary password.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <RouterLink
            to="/app/batches/1"
            class="inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-xs"
          >
            <IconUsers :size="14" /> Open Batch 1
          </RouterLink>
          <RouterLink
            to="/app/batches/1"
            class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-xs font-medium text-white"
          >
            <IconMail :size="14" /> Notify Batch 1 activation
          </RouterLink>
        </div>
      </section>
    </div>
    <AppDialog
      v-model="confirmDialog"
      title="Confirm masterlist import"
      :description="`${importableCount} accounts will be created. Duplicate and invalid rows will be skipped.`"
      size="sm"
      ><div class="space-y-2 text-sm text-text-muted">
        <p>New student accounts will start inactive and require identity verification.</p>
        <label class="flex items-center gap-2 text-xs"
          ><input type="checkbox" />I reviewed the duplicate and invalid rows.</label
        >
      </div>
      <template #footer="{ close }"
        ><button class="rounded-md border px-4 py-2 text-xs" @click="close">Cancel</button
        ><button class="rounded-md bg-primary px-4 py-2 text-xs text-white" @click="close">
          Confirm import
        </button></template
      ></AppDialog
    >
  </div>
</template>
