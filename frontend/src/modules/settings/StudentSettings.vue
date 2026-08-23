<script setup lang="ts">
import { ref } from "vue";
import {
  IconCheck,
  IconDeviceLaptop,
  IconHistory,
  IconKey,
  IconLifebuoy,
  IconLogout,
  IconUser,
} from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import DiceBearAvatar from "@/components/ui/DiceBearAvatar.vue";

import { authSession, loadAuthUser } from "@/auth/session";
import { computed } from "vue";
import { apiFetch } from "@/api/client";
import { toast } from "@/composables/useToast";

type Section = "profile" | "security" | "sessions";
const section = ref<Section>("profile");
const saved = ref(false);
const passwordUpdated = ref(false);
const nav = [
  ["profile", "Profile", "Personal information", IconUser],
  ["security", "Security", "Change password & PIN", IconKey],
  ["sessions", "Sessions", "Sign-in activity", IconHistory],
];

const user = computed(() => authSession.user);
async function refreshUser() {
  await loadAuthUser();
}

const pinForm = ref({
  current_password: "",
  pin: "",
});
const pinBusy = ref(false);

async function savePin() {
  pinBusy.value = true;
  try {
    await apiFetch("/api/student/settings/pin", {
      method: "POST",
      body: JSON.stringify(pinForm.value),
    });
    toast.success(pinForm.value.pin ? "Security PIN set successfully." : "Security PIN removed.");
    pinForm.value.current_password = "";
    pinForm.value.pin = "";
    await refreshUser();
  } catch (err: unknown) {
    toast.error(err instanceof Error ? err.message : "Failed to update PIN");
  } finally {
    pinBusy.value = false;
  }
}
</script>

<template>
  <div>
    <PageHeader title="Settings" description="Manage your profile, password, and sign-in activity."
      ><template #actions
        ><RouterLink
          to="/login"
          class="inline-flex h-9 items-center gap-1.5 rounded-md px-3 text-xs hover:bg-surface-muted"
          ><IconLogout :size="14" />Sign out</RouterLink
        ></template
      ></PageHeader
    >
    <div class="grid gap-4 lg:grid-cols-[220px_1fr]">
      <nav class="h-fit rounded-lg border bg-surface p-2">
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
            ><b class="block text-sm">{{ item[1] }}</b
            ><span class="text-micro opacity-80">{{ item[2] }}</span></span
          >
        </button>
      </nav>
      <main>
        <section v-if="section === 'profile'" class="rounded-lg border bg-surface">
          <h2 class="border-b px-4 py-3 text-sm font-semibold">Edit Profile</h2>
          <div class="grid gap-5 p-5 md:grid-cols-[auto_1fr]">
            <div>
              <DiceBearAvatar
                seed="mc.delacruz@tcc.edu.ph"
                :size="80"
                alt="Maria Clara Dela Cruz"
              /><button class="mt-2 w-full text-xs text-primary">Change avatar</button>
            </div>
            <form class="max-w-xl space-y-4" @submit.prevent="saved = true">
              <label class="block"
                ><span class="mb-1.5 block text-xs font-medium">Full name *</span
                ><input
                  value="Maria Clara Dela Cruz"
                  disabled
                  class="h-9 w-full rounded-md border bg-surface-muted px-3 text-sm text-text-muted"
                />
                <span class="mt-1 block text-micro text-text-muted">
                  Your name is based on the official masterlist and cannot be edited here.
                </span></label
              >
              <div class="rounded-lg border border-info/30 bg-info-soft p-3 text-xs">
                <p class="flex items-center gap-2 font-semibold text-info">
                  <IconLifebuoy :size="14" /> Name issue?
                </p>
                <p class="mt-1 text-text-muted">
                  Contact support or the UniFAST office if your name is misspelled or does not match
                  your official records.
                </p>
                <a
                  href="mailto:registrar@tcc.edu.ph?subject=Student%20name%20correction%20request"
                  class="mt-2 inline-flex rounded-md border bg-surface px-3 py-1.5 text-micro font-medium text-primary"
                >
                  Contact support
                </a>
              </div>
              <label class="block"
                ><span class="mb-1.5 block text-xs font-medium">Email</span
                ><input
                  value="mc.delacruz@tcc.edu.ph"
                  disabled
                  class="h-9 w-full rounded-md border bg-surface-muted px-3 text-sm text-text-muted" /></label
              ><label class="block"
                ><span class="mb-1.5 block text-xs font-medium">Contact number</span
                ><input value="+63 917 123 4567" class="h-9 w-full rounded-md border px-3 text-sm"
              /></label>
              <p v-if="saved" class="inline-flex items-center gap-1 text-xs text-success">
                <IconCheck :size="12" />Profile updated.
              </p>
              <button class="block h-9 rounded-md bg-primary px-3 text-xs text-white">
                Save profile
              </button>
            </form>
          </div>
        </section>
        <section v-else-if="section === 'security'" class="rounded-lg border bg-surface">
          <h2 class="border-b px-4 py-3 text-sm font-semibold">Change Password</h2>
          <form class="max-w-xl space-y-4 p-4" @submit.prevent="passwordUpdated = true">
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
              <div class="mt-1 h-1.5 rounded-full bg-surface-muted">
                <div class="h-full w-4/5 rounded-full bg-success" />
              </div>
            </div>
            <p v-if="passwordUpdated" class="inline-flex items-center gap-1 text-xs text-success">
              <IconCheck :size="12" />Password updated successfully.
            </p>
            <button class="block h-9 rounded-md bg-primary px-3 text-xs text-white">
              Update password
            </button>
          </form>

          <h2 class="border-t px-4 py-3 text-sm font-semibold mt-4">Submission Security PIN</h2>
          <div class="px-4 pb-4">
            <p class="text-xs text-text-muted mb-4 max-w-xl">
              Set an optional 4-6 digit PIN as an extra layer of security when submitting requirements. 
              Leave the PIN field blank to remove your existing PIN.
            </p>
            <form class="max-w-xl space-y-4" @submit.prevent="savePin">
              <label class="block">
                <span class="mb-1.5 block text-xs font-medium">Current Password *</span>
                <input
                  v-model="pinForm.current_password"
                  type="password"
                  placeholder="Enter your password to confirm changes"
                  required
                  class="h-9 w-full rounded-md border px-3 text-sm"
                />
              </label>
              <label class="block">
                <span class="mb-1.5 block text-xs font-medium">New Security PIN (Optional)</span>
                <input
                  v-model="pinForm.pin"
                  type="password"
                  maxlength="6"
                  pattern="[0-9]*"
                  inputmode="numeric"
                  placeholder="4-6 digits"
                  class="h-9 w-full rounded-md border px-3 text-sm"
                />
              </label>
              <div class="flex items-center gap-3">
                <button 
                  type="submit" 
                  :disabled="pinBusy"
                  class="block h-9 rounded-md bg-primary px-3 text-xs text-white hover:bg-primary-hover disabled:opacity-50"
                >
                  {{ pinBusy ? "Saving..." : (pinForm.pin ? "Set PIN" : "Remove PIN") }}
                </button>
                <p v-if="user?.has_security_pin" class="inline-flex items-center gap-1 text-xs text-success">
                  <IconCheck :size="12" /> You have a PIN configured.
                </p>
              </div>
            </form>
          </div>
        </section>
        <template v-else
          ><section class="mb-3 rounded-lg border bg-surface p-4">
            <div class="flex gap-3">
              <span
                class="grid h-10 w-10 place-items-center rounded-md bg-primary-soft text-primary"
                ><IconDeviceLaptop :size="18"
              /></span>
              <div>
                <p class="text-sm font-medium">Current session · Windows PC</p>
                <p class="text-micro text-text-muted">Chrome · Manila, Philippines · Active now</p>
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
          </section></template
        >
      </main>
    </div>
  </div>
</template>
