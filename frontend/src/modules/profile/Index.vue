<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import {
  IconCheck,
  IconClock,
  IconDeviceLaptop,
  IconHistory,
  IconId,
  IconKey,
  IconLifebuoy,
  IconPencil,
  IconSettings,
  IconUser,
} from "@tabler/icons-vue";
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

type MainTab = "overview" | "settings";
type SettingsSubTab = "profile" | "security" | "sessions";

const route = useRoute();
const router = useRouter();

const activeTab = ref<MainTab>((route.query.tab as MainTab) || "overview");
const settingsSection = ref<SettingsSubTab>((route.query.section as SettingsSubTab) || "profile");

watch(
  () => route.query,
  (query) => {
    if (query.tab === "settings") {
      activeTab.value = "settings";
    } else if (query.tab === "overview") {
      activeTab.value = "overview";
    }
    if (query.section) {
      settingsSection.value = query.section as SettingsSubTab;
    }
  },
);

const kycLoading = ref(true);
const kyc = ref<KycProfile | null>(null);

const saved = ref(false);
const passwordUpdated = ref(false);

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

const settingsNav = [
  ["profile", "Profile Info", "Contact & personal settings", IconUser],
  ["security", "Security", "Change password & authentication", IconKey],
  ["sessions", "Sign-in Sessions", "Active logins & activity", IconHistory],
];

function setMainTab(tab: MainTab) {
  activeTab.value = tab;
  const query = { ...route.query, tab };
  if (tab === "overview") delete query.section;
  void router.replace({ query });
}

function setSettingsSection(sec: SettingsSubTab) {
  settingsSection.value = sec;
  void router.replace({ query: { ...route.query, tab: "settings", section: sec } });
}

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
      description="View your personal and academic information or manage your account settings."
    />

    <!-- Main Navigation Tabs -->
    <div class="mb-5 flex border-b">
      <button
        type="button"
        :class="[
          'flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium transition-colors',
          activeTab === 'overview'
            ? 'border-primary text-primary font-semibold'
            : 'border-transparent text-text-muted hover:text-text',
        ]"
        @click="setMainTab('overview')"
      >
        <IconUser :size="16" />
        Overview
      </button>
      <button
        type="button"
        :class="[
          'flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium transition-colors',
          activeTab === 'settings'
            ? 'border-primary text-primary font-semibold'
            : 'border-transparent text-text-muted hover:text-text',
        ]"
        @click="setMainTab('settings')"
      >
        <IconSettings :size="16" />
        Settings & Security
      </button>
    </div>

    <!-- OVERVIEW TAB CONTENT -->
    <div v-if="activeTab === 'overview'">
      <section class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border bg-surface p-4">
        <div class="flex items-center gap-3">
          <DiceBearAvatar :seed="displayEmail" :alt="displayName" :size="56" />
          <div>
            <p class="text-sm font-semibold">{{ displayName }}</p>
            <p class="text-xs text-text-muted">{{ displayEmail }}</p>
          </div>
        </div>
        <button
          type="button"
          class="inline-flex h-8 items-center gap-1.5 rounded-md border bg-surface px-3 text-xs font-medium hover:bg-surface-muted"
          @click="setMainTab('settings')"
        >
          <IconPencil :size="14" /> Manage Settings
        </button>
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
            { title: 'Personal Information', fields: personal },
            { title: 'Academic Information', fields: academic },
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

    <!-- SETTINGS TAB CONTENT -->
    <div v-else-if="activeTab === 'settings'" class="grid gap-4 lg:grid-cols-[240px_1fr]">
      <nav class="h-fit rounded-lg border bg-surface p-2">
        <button
          v-for="item in settingsNav"
          :key="item[0] as string"
          type="button"
          :class="[
            'mb-1 flex w-full items-start gap-2.5 rounded-md px-3 py-2.5 text-left transition-colors',
            settingsSection === item[0]
              ? 'bg-sidebar-active font-medium text-[var(--sidebar-active-text,#f5e6c4)]'
              : 'hover:bg-surface-muted text-text',
          ]"
          @click="setSettingsSection(item[0] as SettingsSubTab)"
        >
          <component :is="item[3]" :size="17" class="mt-0.5 shrink-0" />
          <div>
            <b class="block text-sm leading-tight">{{ item[1] }}</b>
            <span class="text-micro opacity-80 leading-tight block mt-0.5">{{ item[2] }}</span>
          </div>
        </button>
      </nav>

      <main>
        <!-- PROFILE SETTINGS -->
        <section v-if="settingsSection === 'profile'" class="rounded-lg border bg-surface">
          <h2 class="border-b px-4 py-3 text-sm font-semibold">Edit Personal Settings</h2>
          <div class="grid gap-5 p-5 md:grid-cols-[auto_1fr]">
            <div class="flex flex-col items-center">
              <DiceBearAvatar
                :seed="displayEmail"
                :size="80"
                :alt="displayName"
              />
              <span class="mt-2 text-micro text-text-muted">Avatar generated</span>
            </div>
            <form class="max-w-xl space-y-4" @submit.prevent="saved = true">
              <label class="block"
                ><span class="mb-1.5 block text-xs font-medium">Full name *</span
                ><input
                  :value="displayName"
                  disabled
                  class="h-9 w-full rounded-md border bg-surface-muted px-3 text-sm text-text-muted"
                />
                <span class="mt-1 block text-micro text-text-muted">
                  Your name is based on the official masterlist and cannot be edited here.
                </span></label
              >
              <div class="rounded-lg border border-info/30 bg-info-soft p-3 text-xs">
                <p class="flex items-center gap-2 font-semibold text-info">
                  <IconLifebuoy :size="14" /> Name correction request?
                </p>
                <p class="mt-1 text-text-muted">
                  Contact support or the UniFAST office if your name is misspelled or does not match
                  your official records.
                </p>
                <a
                  href="mailto:registrar@tcc.edu.ph?subject=Student%20name%20correction%20request"
                  class="mt-2 inline-flex rounded-md border bg-surface px-3 py-1.5 text-micro font-medium text-primary hover:bg-surface-muted"
                >
                  Contact support
                </a>
              </div>
              <label class="block"
                ><span class="mb-1.5 block text-xs font-medium">Email</span>
                <input
                  :value="displayEmail"
                  disabled
                  class="h-9 w-full rounded-md border bg-surface-muted px-3 text-sm text-text-muted"
                /></label
              >
              <label class="block"
                ><span class="mb-1.5 block text-xs font-medium">Contact number</span>
                <input
                  :value="kyc?.contact || '+63 917 123 4567'"
                  class="h-9 w-full rounded-md border bg-surface px-3 text-sm"
                /></label
              >
              <p v-if="saved" class="inline-flex items-center gap-1 text-xs text-success">
                <IconCheck :size="12" /> Profile settings saved.
              </p>
              <button type="submit" class="block h-9 rounded-md bg-primary px-4 text-xs font-medium text-white hover:opacity-90">
                Save changes
              </button>
            </form>
          </div>
        </section>

        <!-- SECURITY SETTINGS -->
        <section v-else-if="settingsSection === 'security'" class="rounded-lg border bg-surface">
          <h2 class="border-b px-4 py-3 text-sm font-semibold">Change Password</h2>
          <form class="max-w-xl space-y-4 p-5" @submit.prevent="passwordUpdated = true">
            <label
              v-for="label in ['Current password', 'New password', 'Confirm new password']"
              :key="label"
              class="block"
              ><span class="mb-1.5 block text-xs font-medium">{{ label }} *</span
              ><input
                type="password"
                placeholder="••••••••"
                class="h-9 w-full rounded-md border bg-surface px-3 text-sm"
            /></label>
            <div>
              <div class="flex justify-between text-micro">
                <span class="text-text-muted">Password Strength</span>
                <span class="font-medium text-success">Strong</span>
              </div>
              <div class="mt-1 h-1.5 rounded-full bg-surface-muted">
                <div class="h-full w-4/5 rounded-full bg-success" />
              </div>
            </div>
            <p v-if="passwordUpdated" class="inline-flex items-center gap-1 text-xs text-success">
              <IconCheck :size="12" /> Password updated successfully.
            </p>
            <button type="submit" class="block h-9 rounded-md bg-primary px-4 text-xs font-medium text-white hover:opacity-90">
              Update password
            </button>
          </form>
        </section>

        <!-- SESSIONS & LOGIN ACTIVITY -->
        <template v-else>
          <section class="mb-4 rounded-lg border bg-surface p-4">
            <div class="flex items-center gap-3">
              <span class="grid h-10 w-10 place-items-center rounded-md bg-primary-soft text-primary">
                <IconDeviceLaptop :size="18" />
              </span>
              <div>
                <p class="text-sm font-medium">Current session · Active Device</p>
                <p class="text-micro text-text-muted">Web Browser · Manila, Philippines · Active now</p>
              </div>
            </div>
          </section>

          <section class="rounded-lg border bg-surface">
            <h2 class="border-b px-4 py-3 text-sm font-semibold">Login Activity</h2>
            <div class="divide-y px-4">
              <div
                v-for="event in [
                  ['Windows PC', 'Jul 12, 2026, 9:14 AM', '192.168.1.14'],
                  ['Android device', 'Jul 10, 2026, 6:35 PM', '192.168.1.22'],
                  ['Windows PC', 'Jul 8, 2026, 8:02 AM', '192.168.1.14'],
                ]"
                :key="event[1]"
                class="flex items-center gap-3 py-3"
              >
                <IconDeviceLaptop :size="16" class="text-text-muted" />
                <div>
                  <p class="text-sm font-medium">{{ event[0] }}</p>
                  <p class="text-micro text-text-muted">{{ event[1] }} · {{ event[2] }}</p>
                </div>
              </div>
            </div>
          </section>
        </template>
      </main>
    </div>
  </div>
</template>
