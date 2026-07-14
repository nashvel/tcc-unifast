<script setup lang="ts">
import { computed } from "vue";
import { IconCheck, IconClock, IconId, IconPencil, IconUser } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import DiceBearAvatar from "@/components/ui/DiceBearAvatar.vue";

const completedAt =
  typeof localStorage !== "undefined"
    ? localStorage.getItem("unifast.mock.onboarding_completed_at")
    : null;
const onboardingDone = computed(() => Boolean(completedAt));
const personal = [
  ["Full name", "Maria Clara Dela Cruz"],
  ["Birthdate", "May 14, 2003"],
  ["Email", "mc.delacruz@tcc.edu.ph"],
  ["Contact", "+63 917 123 4567"],
];
const academic = [
  ["University", "Tagoloan Community College"],
  ["Program", "BS Information Technology"],
  ["Year level", "2"],
  ["Student #", "2024-00182"],
];
</script>

<template>
  <div>
    <PageHeader
      title="My Profile"
      description="Personal and academic information on file. View only — manage edits from Settings."
    >
      <template #actions
        ><RouterLink
          to="/student/settings"
          class="inline-flex h-9 items-center gap-1.5 rounded-md border bg-surface px-3 text-xs"
          ><IconPencil :size="14" />Edit in Settings</RouterLink
        ></template
      >
    </PageHeader>
    <section class="mb-4 flex items-center gap-3 rounded-lg border bg-surface p-4">
      <DiceBearAvatar seed="mc.delacruz@tcc.edu.ph" alt="Maria Clara Dela Cruz" :size="56" />
      <div>
        <p class="text-sm font-semibold">Maria Clara Dela Cruz</p>
        <p class="text-xs text-text-muted">mc.delacruz@tcc.edu.ph</p>
      </div>
    </section>
    <section class="mb-4 rounded-lg border bg-surface p-4">
      <div class="mb-3">
        <p class="text-sm font-semibold">Onboarding verification</p>
        <p class="text-xs text-text-muted">
          {{
            onboardingDone
              ? "Identity verification completed."
              : "Complete your scans on next sign-in."
          }}
        </p>
      </div>
      <div class="grid gap-3 sm:grid-cols-2">
        <div
          v-for="item in [
            ['ID scan', IconId],
            ['Face scan', IconUser],
          ]"
          :key="item[0] as string"
          class="flex items-center gap-3 rounded-md border p-3"
        >
          <span class="grid h-9 w-9 place-items-center rounded-lg bg-primary-soft text-primary"
            ><component :is="item[1]" :size="18"
          /></span>
          <div class="flex-1">
            <p class="text-sm font-medium">{{ item[0] }}</p>
            <p class="text-micro text-text-muted">{{ onboardingDone ? "Verified" : "Pending" }}</p>
          </div>
          <span
            :class="[
              'inline-flex items-center gap-1 rounded-full px-2 py-1 text-micro font-medium',
              onboardingDone ? 'bg-success-soft text-success' : 'bg-warning-soft text-warning',
            ]"
            ><IconCheck v-if="onboardingDone" :size="12" /><IconClock v-else :size="12" />{{
              onboardingDone ? "Completed" : "Pending"
            }}</span
          >
        </div>
      </div>
    </section>
    <div class="grid gap-4 lg:grid-cols-2">
      <section
        v-for="group in [
          { title: 'Personal', fields: personal },
          { title: 'Academic', fields: academic },
        ]"
        :key="group.title"
        class="space-y-3 rounded-lg border bg-surface p-4"
      >
        <h2 class="text-sm font-semibold">{{ group.title }}</h2>
        <label v-for="field in group.fields" :key="field[0]" class="block"
          ><span class="mb-1.5 block text-xs font-medium">{{ field[0] }}</span
          ><input
            :value="field[1]"
            disabled
            class="h-9 w-full rounded-md border bg-surface-muted px-3 text-sm text-text-muted"
        /></label>
      </section>
    </div>
  </div>
</template>
