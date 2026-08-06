<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { IconCheck, IconClock, IconId, IconPencil, IconUser } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import DiceBearAvatar from "@/components/ui/DiceBearAvatar.vue";
import { apiFetch } from "@/api/client";
import { authSession, loadAuthUser } from "@/auth/session";

type KycProfile = {
  full_name?: string | null;
  first_name?: string | null;
  middle_name?: string | null;
  last_name?: string | null;
  student_id?: string | null;
  program?: string | null;
  year_level?: string | null;
  birthdate?: string | null;
  contact?: string | null;
  address?: string | null;
};

const kycLoading = ref(true);
const kyc = ref<KycProfile | null>(null);

const user = computed(() => authSession.user);

/** Server onboarding completion — not localStorage mock flags. */
const onboardingDone = computed(() => {
  const u = user.value;
  if (!u) return false;
  return u.account_status === "active" || u.onboarding_next_step === "done";
});

const displayName = computed(() => {
  const fromKyc = kyc.value?.full_name?.trim();
  if (fromKyc) return fromKyc;
  return user.value?.name?.trim() || "—";
});

const displayEmail = computed(() => user.value?.email?.trim() || "—");

function formatBirthdate(raw: string | null | undefined): string {
  if (!raw) return "—";
  const d = new Date(`${raw}T00:00:00`);
  if (Number.isNaN(d.getTime())) return raw;
  return d.toLocaleDateString(undefined, { year: "numeric", month: "long", day: "numeric" });
}

const personal = computed(() => [
  ["Full name", displayName.value],
  ["Birthdate", formatBirthdate(kyc.value?.birthdate)],
  ["Email", displayEmail.value],
  ["Contact", kyc.value?.contact?.trim() || "—"],
]);

const academic = computed(() => [
  ["University", "Tagoloan Community College"],
  ["Program", kyc.value?.program?.trim() || "—"],
  ["Year level", kyc.value?.year_level?.trim() || "—"],
  ["Student #", kyc.value?.student_id?.trim() || user.value?.student_id?.trim() || "—"],
]);

onMounted(async () => {
  if (!authSession.loaded) {
    await loadAuthUser();
  }
  kycLoading.value = true;
  try {
    const payload = await apiFetch<{ data: { profile: KycProfile | null } }>("/api/student/kyc");
    kyc.value = payload.data.profile;
  } catch {
    kyc.value = null;
  } finally {
    kycLoading.value = false;
  }
});
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
      <DiceBearAvatar :seed="displayEmail" :alt="displayName" :size="56" />
      <div>
        <p class="text-sm font-semibold">{{ displayName }}</p>
        <p class="text-xs text-text-muted">{{ displayEmail }}</p>
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
        <p v-if="kycLoading" class="text-xs text-text-muted">Loading profile…</p>
        <template v-else>
          <label v-for="field in group.fields" :key="field[0]" class="block"
            ><span class="mb-1.5 block text-xs font-medium">{{ field[0] }}</span
            ><input
              :value="field[1]"
              disabled
              class="h-9 w-full rounded-md border bg-surface-muted px-3 text-sm text-text-muted"
          /></label>
        </template>
      </section>
    </div>
  </div>
</template>
