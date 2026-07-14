<script setup lang="ts">
import { computed, ref } from "vue";
import { IconDownload, IconSearch } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import DataTable from "@/components/tables/DataTable.vue";
import { grantees } from "./data";

const query = ref("");
const account = ref("all");
const submission = ref("all");
const eligibility = ref("all");
const risk = ref("all");
const filtered = computed(() =>
  grantees.filter(
    (g) =>
      (!query.value ||
        `${g.name} ${g.studentNumber}`.toLowerCase().includes(query.value.toLowerCase())) &&
      (account.value === "all" || g.account === account.value) &&
      (submission.value === "all" || g.submission === submission.value) &&
      (eligibility.value === "all" || g.eligibility === eligibility.value) &&
      (risk.value === "all" || g.risk === risk.value),
  ),
);
const tone = (value: string) =>
  value.includes("active") || value === "approved" || value === "eligible" || value === "low"
    ? "bg-success-soft text-success"
    : value === "high" || value === "rejected" || value === "ineligible" || value === "locked"
      ? "bg-danger-soft text-danger"
      : value === "medium" || value === "pending_activation"
        ? "bg-warning-soft text-warning"
        : "bg-info-soft text-info";
</script>
<template>
  <div>
    <PageHeader title="Grantees" description="Search, filter, and manage TES grantee records."
      ><template #actions
        ><button class="inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-xs">
          <IconDownload :size="14" />Export CSV
        </button></template
      ></PageHeader
    >
    <section class="mb-4 grid gap-2 rounded-lg border bg-surface p-3 md:grid-cols-6">
      <div class="relative md:col-span-2">
        <IconSearch
          :size="14"
          class="absolute left-3 top-1/2 -translate-y-1/2 text-text-soft"
        /><input
          v-model="query"
          placeholder="Search by name or student #"
          class="h-9 w-full rounded-md border pl-9 pr-3 text-xs"
        />
      </div>
      <select class="rounded-md border bg-surface px-2 text-xs">
        <option>All batches</option></select
      ><select v-model="account" class="rounded-md border bg-surface px-2 text-xs">
        <option value="all">All accounts</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
        <option value="pending_activation">Pending activation</option>
        <option value="locked">Locked</option></select
      ><select v-model="submission" class="rounded-md border bg-surface px-2 text-xs">
        <option value="all">All submissions</option>
        <option value="approved">Approved</option>
        <option value="submitted">Submitted</option>
        <option value="under_review">Under review</option>
        <option value="not_submitted">Not submitted</option>
      </select>
      <div class="grid grid-cols-2 gap-2 md:contents">
        <select v-model="eligibility" class="rounded-md border bg-surface px-2 text-xs">
          <option value="all">All eligibility</option>
          <option value="eligible">Eligible</option>
          <option value="ineligible">Ineligible</option>
          <option value="pending">Pending</option>
          <option value="for_evaluation">For evaluation</option></select
        ><select v-model="risk" class="rounded-md border bg-surface px-2 text-xs">
          <option value="all">All risk</option>
          <option value="low">Low</option>
          <option value="medium">Medium</option>
          <option value="high">High</option>
        </select>
      </div>
    </section>
    <DataTable
      :headings="[
        'Student #',
        'Name',
        'Program',
        'Batch',
        'Account',
        'Submission',
        'Eligibility',
        'Risk',
      ]"
      ><tr v-for="g in filtered" :key="g.id">
        <td class="px-3 py-3 font-mono">{{ g.studentNumber }}</td>
        <td class="px-3 py-3">
          <RouterLink :to="`/app/grantees/${g.id}`" class="font-medium hover:text-primary">{{
            g.name
          }}</RouterLink>
        </td>
        <td class="px-3 py-3 text-text-muted">{{ g.program }}</td>
        <td class="px-3 py-3 text-text-muted">{{ g.batch }}</td>
        <td
          v-for="value in [g.account, g.submission, g.eligibility, g.risk]"
          :key="value"
          class="px-3 py-3"
        >
          <span :class="['rounded-full px-2 py-0.5 text-micro capitalize', tone(value)]">{{
            value.replaceAll("_", " ")
          }}</span>
        </td>
      </tr>
      <tr v-if="!filtered.length">
        <td colspan="8" class="p-8 text-center text-text-muted">No grantees found.</td>
      </tr>
      <template #footer
        ><footer class="flex justify-between border-t px-3 py-2.5 text-xs text-text-muted">
          <span>Showing {{ filtered.length }} of {{ grantees.length }}</span
          ><span>Page 1 of 1</span>
        </footer></template
      ></DataTable
    >
  </div>
</template>
