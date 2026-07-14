<script setup lang="ts">
import { computed, ref } from "vue";
import { useRoute } from "vue-router";
import {
  IconArrowLeft,
  IconCheck,
  IconFileText,
  IconHistory,
  IconNote,
  IconSchool,
  IconUser,
} from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import DiceBearAvatar from "@/components/ui/DiceBearAvatar.vue";
import { grantees } from "./data";
const route = useRoute();
const grantee = computed(() => grantees.find((item) => item.id === route.params.id) ?? grantees[0]);
const tab = ref("overview");
const notes = ref(grantee.value.notes ?? "");
</script>
<template>
  <div>
    <RouterLink
      to="/app/grantees"
      class="mb-3 inline-flex items-center gap-1 text-xs text-text-muted"
      ><IconArrowLeft :size="13" />Back to grantees</RouterLink
    ><PageHeader
      :title="grantee.name"
      :description="`${grantee.studentNumber} · ${grantee.program}`"
    />
    <section class="mb-4 flex flex-wrap items-center gap-4 rounded-lg border bg-surface p-4">
      <DiceBearAvatar :seed="grantee.email" :alt="grantee.name" :size="56" />
      <div class="min-w-0 flex-1">
        <p class="text-sm font-semibold">{{ grantee.email }}</p>
        <p class="text-xs text-text-muted">{{ grantee.contact }} · Year {{ grantee.yearLevel }}</p>
      </div>
      <div class="grid grid-cols-3 gap-5 text-center">
        <div>
          <p class="text-lg font-semibold">{{ grantee.gwa }}</p>
          <p class="text-micro text-text-muted">GWA</p>
        </div>
        <div>
          <p class="text-lg font-semibold">{{ grantee.completion }}%</p>
          <p class="text-micro text-text-muted">Profile</p>
        </div>
        <div>
          <p class="text-lg font-semibold capitalize">{{ grantee.risk }}</p>
          <p class="text-micro text-text-muted">Risk</p>
        </div>
      </div>
    </section>
    <nav class="mb-4 flex gap-1 border-b">
      <button
        v-for="item in [
          ['overview', 'Overview', IconUser],
          ['requirements', 'Requirements', IconFileText],
          ['history', 'History', IconHistory],
          ['notes', 'Notes', IconNote],
        ]"
        :key="item[0] as string"
        :class="[
          'inline-flex items-center gap-1.5 border-b-2 px-3 py-2 text-xs',
          tab === item[0] ? 'border-primary text-primary' : 'border-transparent text-text-muted',
        ]"
        @click="tab = item[0] as string"
      >
        <component :is="item[2]" :size="14" />{{ item[1] }}
      </button>
    </nav>
    <div v-if="tab === 'overview'" class="grid gap-4 lg:grid-cols-2">
      <section
        v-for="group in [
          {
            title: 'Personal Information',
            icon: IconUser,
            rows: [
              ['Full name', grantee.name],
              ['Birthdate', grantee.birthdate],
              ['Email', grantee.email],
              ['Contact', grantee.contact],
            ],
          },
          {
            title: 'Academic Information',
            icon: IconSchool,
            rows: [
              ['University', grantee.university],
              ['Program', grantee.program],
              ['Year level', String(grantee.yearLevel)],
              ['Batch', grantee.batch],
            ],
          },
        ]"
        :key="group.title"
        class="rounded-lg border bg-surface p-4"
      >
        <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold">
          <component :is="group.icon" :size="16" class="text-primary" />{{ group.title }}
        </h2>
        <dl class="divide-y">
          <div v-for="row in group.rows" :key="row[0]" class="grid grid-cols-3 py-2 text-xs">
            <dt class="text-text-muted">{{ row[0] }}</dt>
            <dd class="col-span-2">{{ row[1] }}</dd>
          </div>
        </dl>
      </section>
    </div>
    <section v-else-if="tab === 'requirements'" class="rounded-lg border bg-surface p-4">
      <h2 class="text-sm font-semibold">Submitted Requirements</h2>
      <ul class="mt-3 divide-y">
        <li
          v-for="item in [
            'PSA Birth Certificate',
            'Certificate of Enrollment',
            'Grades (Transcript)',
            'Income Tax Return',
            '2x2 ID Picture',
          ]"
          :key="item"
          class="flex items-center justify-between py-3 text-sm"
        >
          <span class="flex items-center gap-2"
            ><IconFileText :size="15" class="text-primary" />{{ item }}</span
          ><span
            class="inline-flex items-center gap-1 rounded-full bg-success-soft px-2 py-0.5 text-micro text-success"
            ><IconCheck :size="11" />Approved</span
          >
        </li>
      </ul>
    </section>
    <section v-else-if="tab === 'history'" class="rounded-lg border bg-surface p-4">
      <h2 class="text-sm font-semibold">Validation History</h2>
      <ol class="mt-4 space-y-4">
        <li
          v-for="event in [
            ['Document validation completed', 'May 12, 2025 · UniFAST Staff'],
            ['Eligibility evaluation passed', 'May 10, 2025 · Rules engine'],
            ['Account activated', 'May 4, 2025 · Student'],
          ]"
          :key="event[0]"
          class="flex gap-3"
        >
          <span class="mt-1 h-2 w-2 rounded-full bg-primary" />
          <div>
            <p class="text-sm font-medium">{{ event[0] }}</p>
            <p class="text-xs text-text-muted">{{ event[1] }}</p>
          </div>
        </li>
      </ol>
    </section>
    <section v-else class="rounded-lg border bg-surface p-4">
      <h2 class="text-sm font-semibold">Staff Notes</h2>
      <textarea
        v-model="notes"
        rows="6"
        placeholder="Add an internal note…"
        class="mt-3 w-full rounded-md border bg-surface p-3 text-sm"
      />
      <div class="mt-2 flex justify-end">
        <button class="rounded-md bg-primary px-3 py-2 text-xs text-white">Save note</button>
      </div>
    </section>
  </div>
</template>
