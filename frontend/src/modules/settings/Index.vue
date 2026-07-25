<script setup lang="ts">
import { ref } from "vue";
import {
  IconBuilding,
  IconCheck,
  IconDeviceLaptop,
  IconHistory,
  IconKey,
  IconLogout,
  IconMoon,
  IconPalette,
  IconShieldCheck,
  IconSun,
  IconUser,
} from "@tabler/icons-vue";
import DiceBearAvatar from "@/components/ui/DiceBearAvatar.vue";
import AppTour from "@/components/tour/AppTour.vue";

type Section = "general" | "organization" | "appearance" | "security" | "sessions";
const section = ref<Section>("general");
const dark = ref(
  typeof document !== "undefined" && document.documentElement.classList.contains("dark"),
);
const fullName = ref("System Administrator");
const nav = [
  ["general", "General", "Profile & avatar", IconUser],
  ["organization", "Organization", "Office & validation rules", IconBuilding],
  ["appearance", "Appearance", "Theme & font", IconPalette],
  ["security", "Security", "Password", IconKey],
  ["sessions", "Sessions", "Current device & history", IconHistory],
];
function setTheme(value: boolean) {
  dark.value = value;
  if (typeof document !== "undefined") document.documentElement.classList.toggle("dark", value);
  if (typeof localStorage !== "undefined") localStorage.setItem("theme", value ? "dark" : "light");
}
</script>

<template>
  <div>
    <header class="mb-4 flex items-start justify-between" data-tour="page-header">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">Settings</h1>
        <p class="mt-1 text-sm text-text-muted">
          Manage your profile, organization, security, and sign-in activity.
        </p>
      </div>
      <div class="flex gap-2">
        <AppTour />
        <button
          class="inline-flex items-center gap-1.5 rounded-md px-3 py-2 text-xs hover:bg-surface-muted"
        >
          <IconLogout :size="14" />Sign out
        </button>
      </div>
    </header>
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-[220px_minmax(0,1fr)]">
      <nav class="h-fit rounded-lg border bg-surface p-2 lg:sticky lg:top-20">
        <button
          v-for="item in nav"
          :key="item[0] as string"
          :class="[
            'mb-0.5 flex w-full items-start gap-2 rounded-md px-2.5 py-2 text-left',
            section === item[0] ? 'bg-sidebar-active text-[#f5e6c4]' : 'hover:bg-surface-muted',
          ]"
          @click="section = item[0] as Section"
        >
          <component :is="item[3]" :size="16" class="mt-0.5" /><span
            ><span class="block text-sm font-medium leading-tight">{{ item[1] }}</span
            ><span
              :class="[
                'block text-micro',
                section === item[0] ? 'text-[#f5e6c4]/80' : 'text-text-muted',
              ]"
              >{{ item[2] }}</span
            ></span
          >
        </button>
      </nav>
      <main class="min-w-0 space-y-3" data-tour="page-content">
        <section v-if="section === 'general'" class="rounded-lg border bg-surface">
          <h2 class="border-b px-4 py-3 text-sm font-semibold">Edit Profile</h2>
          <div class="grid items-start gap-5 p-5 md:grid-cols-[auto_minmax(0,1fr)]">
            <div class="text-center">
              <DiceBearAvatar seed="admin@unifast.gov.ph" alt="System Administrator" :size="80" />
              <button class="mt-2 text-xs text-primary">Change avatar</button>
            </div>
            <form class="max-w-xl space-y-4" @submit.prevent>
              <label class="block"
                ><span class="mb-1.5 block text-xs font-medium">Full name *</span
                ><input
                  v-model="fullName"
                  class="h-9 w-full rounded-md border bg-surface px-3 text-sm"
              /></label>
              <label class="block"
                ><span class="mb-1.5 block text-xs font-medium">Email</span
                ><input
                  value="admin@unifast.gov.ph"
                  disabled
                  class="h-9 w-full rounded-md border bg-surface-muted px-3 text-sm text-text-muted"
                /><span class="mt-1 block text-micro text-text-muted"
                  >Contact support to change your email.</span
                ></label
              ><button class="rounded-md bg-primary px-3 py-2 text-xs font-medium text-white">
                Save profile
              </button>
            </form>
          </div>
        </section>
        <template v-else-if="section === 'organization'"
          ><div class="grid gap-3 lg:grid-cols-2">
            <section class="rounded-lg border bg-surface">
              <h2 class="border-b px-4 py-3 text-sm font-semibold">Organization</h2>
              <div class="space-y-4 p-4">
                <label
                  v-for="field in [
                    ['Office name', 'UniFAST Office'],
                    ['Region', 'NCR'],
                    ['Contact email', 'info@unifast.gov.ph'],
                  ]"
                  :key="field[0]"
                  class="block"
                  ><span class="mb-1.5 block text-xs font-medium">{{ field[0] }}</span
                  ><input
                    :value="field[1]"
                    class="h-9 w-full rounded-md border bg-surface px-3 text-sm"
                /></label>
              </div>
            </section>
            <section class="rounded-lg border bg-surface">
              <h2 class="border-b px-4 py-3 text-sm font-semibold">Validation rules</h2>
              <div class="space-y-4 p-4">
                <label class="block"
                  ><span class="mb-1.5 block text-xs font-medium">Auto-approve risk threshold</span
                  ><select class="h-9 w-full rounded-md border bg-surface px-3 text-sm">
                    <option>20</option>
                    <option>30</option>
                  </select></label
                ><label class="block"
                  ><span class="mb-1.5 block text-xs font-medium">Retention GWA cap</span
                  ><input value="2.75" class="h-9 w-full rounded-md border px-3" /></label
                ><label class="block"
                  ><span class="mb-1.5 block text-xs font-medium"
                    >Max failed subjects per semester</span
                  ><input value="1" type="number" class="h-9 w-full rounded-md border px-3"
                /></label>
              </div>
            </section>
          </div>
          <div class="flex justify-end gap-2">
            <button class="rounded-md border px-3 py-2 text-xs">Cancel</button
            ><button class="rounded-md bg-primary px-3 py-2 text-xs text-white">
              Save changes
            </button>
          </div></template
        >
        <template v-else-if="section === 'appearance'"
          ><section class="rounded-lg border bg-surface">
            <h2 class="border-b px-4 py-3 text-sm font-semibold">Theme</h2>
            <div class="p-4">
              <p class="mb-3 text-xs text-text-muted">
                Heritage Maroon — deep maroon, ivory, and muted gold.
              </p>
              <div class="grid gap-3 sm:grid-cols-2">
                <button
                  v-for="theme in [
                    [false, 'Light', IconSun, ['#f6f7f9', '#fff', '#4a141d', '#b8894a']],
                    [true, 'Dark', IconMoon, ['#17110f', '#1f1815', '#2a0e14', '#d1a15c']],
                  ]"
                  :key="theme[1] as string"
                  :class="[
                    'rounded-md border p-3 text-left',
                    dark === theme[0] ? 'border-primary bg-primary-soft' : 'hover:bg-surface-muted',
                  ]"
                  @click="setTheme(theme[0] as boolean)"
                >
                  <div class="flex justify-between">
                    <span class="inline-flex items-center gap-2 text-sm font-medium"
                      ><component :is="theme[2]" :size="16" />{{ theme[1] }}</span
                    ><IconCheck v-if="dark === theme[0]" :size="16" class="text-primary" />
                  </div>
                  <div class="mt-3 flex h-8 overflow-hidden rounded border">
                    <i
                      v-for="color in theme[3]"
                      :key="color as string"
                      class="flex-1"
                      :style="{ backgroundColor: color as string }"
                    />
                  </div>
                </button>
              </div>
            </div>
          </section>
          <section class="rounded-lg border bg-surface">
            <h2 class="border-b px-4 py-3 text-sm font-semibold">Font family</h2>
            <div class="p-4">
              <label class="block"
                ><span class="mb-1.5 block text-xs font-medium">Font</span
                ><select class="h-9 w-full max-w-sm rounded-md border bg-surface px-3 text-sm">
                  <option>Absans</option>
                  <option>Inter</option>
                  <option>IBM Plex Sans</option>
                </select></label
              >
              <p class="mt-2 text-xs text-text-muted">
                The original institutional typeface used across the workspace.
              </p>
            </div>
          </section>
          <section class="rounded-lg border bg-surface p-4">
            <h2 class="text-sm font-semibold">Typography preview</h2>
            <p class="mt-4 text-2xs uppercase text-text-soft">Page title · 20/28 semibold</p>
            <p class="mt-1 text-xl font-semibold">Government scholarship applications</p>
            <p class="mt-4 text-2xs uppercase text-text-soft">Body · 14/20 regular</p>
            <p class="mt-1 text-sm">
              The Tertiary Education Subsidy covers tuition and other school fees for qualified
              Filipino students.
            </p>
          </section></template
        >
        <section v-else-if="section === 'security'" class="rounded-lg border bg-surface">
          <div class="flex items-center justify-between border-b px-4 py-3">
            <h2 class="text-sm font-semibold">Change Password</h2>
            <button class="inline-flex items-center gap-1 text-xs">
              <IconLogout :size="13" />Sign out
            </button>
          </div>
          <form class="max-w-xl space-y-4 p-4" @submit.prevent>
            <label
              v-for="label in ['Current password', 'New password', 'Confirm new password']"
              :key="label"
              class="block"
              ><span class="mb-1.5 block text-xs font-medium">{{ label }} *</span
              ><input
                type="password"
                placeholder="••••••••"
                class="h-9 w-full rounded-md border px-3"
            /></label>
            <div>
              <div class="flex justify-between text-micro">
                <span class="text-text-muted">Strength</span><span>Strong</span>
              </div>
              <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-surface-muted">
                <div class="h-full w-4/5 bg-success" />
              </div>
            </div>
            <button class="rounded-md bg-primary px-3 py-2 text-xs text-white">
              Update password
            </button>
          </form>
        </section>
        <template v-else
          ><section class="rounded-lg border bg-surface">
            <div class="flex justify-between border-b px-4 py-3">
              <h2 class="text-sm font-semibold">Current Session</h2>
              <button class="inline-flex items-center gap-1 text-xs">
                <IconLogout :size="13" />Sign out
              </button>
            </div>
            <div class="flex gap-3 p-4">
              <span
                class="grid h-10 w-10 place-items-center rounded-md bg-primary-soft text-primary"
                ><IconShieldCheck :size="18"
              /></span>
              <div>
                <p class="text-sm font-medium">System Administrator</p>
                <p class="text-micro text-text-muted">admin@unifast.gov.ph</p>
                <p class="mt-1 text-micro text-text-muted">
                  This device: <b class="text-text">Windows PC</b>
                </p>
              </div>
            </div>
          </section>
          <section class="rounded-lg border bg-surface">
            <div class="flex justify-between border-b px-4 py-3">
              <h2 class="text-sm font-semibold">Login Activity</h2>
              <button class="text-xs text-primary">Export CSV</button>
            </div>
            <div class="divide-y px-4">
              <div
                v-for="event in [
                  ['Windows PC', 'Jul 11, 2026, 7:41 PM', '192.168.1.14'],
                  ['Windows PC', 'Jul 10, 2026, 8:16 AM', '192.168.1.14'],
                  ['Android device', 'Jul 8, 2026, 6:35 PM', '192.168.1.22'],
                ]"
                :key="event[1]"
                class="flex items-center gap-3 py-3"
              >
                <span
                  class="grid h-8 w-8 place-items-center rounded-md bg-surface-muted text-text-muted"
                  ><IconDeviceLaptop :size="14"
                /></span>
                <div>
                  <p class="text-sm font-medium">{{ event[0] }}</p>
                  <p class="text-micro text-text-muted">{{ event[1] }} · {{ event[2] }}</p>
                </div>
              </div>
            </div>
          </section></template
        >
      </main>
    </div>
  </div>
</template>
