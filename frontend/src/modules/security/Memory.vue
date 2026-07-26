<script setup lang="ts">
import { computed, ref } from "vue";
import { IconDatabase, IconSearch, IconShieldCheck, IconTrash } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import DataTable from "@/components/tables/DataTable.vue";
const query = ref("");
const records = ref([
  [
    "SEC-2026-0184",
    "Repeated failed login pattern",
    "Authentication",
    "System Developer",
    "July 11, 2026",
    "Active",
  ],
  [
    "SEC-2026-0172",
    "Document hash duplicate",
    "File integrity",
    "Maria Santos",
    "July 9, 2026",
    "Retained",
  ],
  [
    "SEC-2026-0158",
    "Unusual export volume",
    "Data access",
    "Staff Account",
    "July 5, 2026",
    "Reviewed",
  ],
]);
const rows = computed(() =>
  records.value.filter((record) =>
    record.join(" ").toLowerCase().includes(query.value.toLowerCase()),
  ),
);
</script>
<template>
  <div>
    <PageHeader
      title="Security Memory"
      description="Review retained security signals and resolved detection context."
    />
    <section class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
      <article
        v-for="item in [
          ['Retained signals', '184'],
          ['Active patterns', '12'],
          ['Reviewed', '159'],
          ['Retention', '365 days'],
        ]"
        :key="item[0]"
        class="rounded-lg border bg-surface p-4"
      >
        <IconDatabase :size="17" class="text-primary" />
        <p class="mt-3 text-xs text-text-muted">{{ item[0] }}</p>
        <p class="mt-1 text-lg font-semibold">{{ item[1] }}</p>
      </article>
    </section>
    <div class="relative mb-3 max-w-xl">
      <IconSearch
        :size="14"
        class="absolute left-3 top-1/2 -translate-y-1/2 text-text-soft"
      /><input
        v-model="query"
        class="h-9 w-full rounded-md border pl-9 pr-3 text-xs"
        placeholder="Search retained security context"
      />
    </div>
    <DataTable
      :headings="['Signal ID', 'Summary', 'Category', 'Subject', 'Last observed', 'Status', '']"
      ><tr v-for="record in rows" :key="record[0]">
        <td class="px-3 py-3 font-mono">{{ record[0] }}</td>
        <td class="px-3 py-3 font-medium">{{ record[1] }}</td>
        <td class="px-3 py-3 text-text-muted">{{ record[2] }}</td>
        <td class="px-3 py-3">{{ record[3] }}</td>
        <td class="px-3 py-3 text-text-muted">{{ record[4] }}</td>
        <td class="px-3 py-3">
          <span
            class="inline-flex items-center gap-1 rounded-full bg-success-soft px-2 py-0.5 text-micro text-success"
            ><IconShieldCheck :size="11" />{{ record[5] }}</span
          >
        </td>
        <td class="px-3 py-3 text-right">
          <button class="text-text-soft hover:text-danger" aria-label="Remove retained signal">
            <IconTrash :size="14" />
          </button>
        </td>
      </tr>
      <tr v-if="!rows.length">
        <td colspan="7" class="p-8 text-center text-text-muted">
          No security memory records found.
        </td>
      </tr></DataTable
    >
  </div>
</template>
