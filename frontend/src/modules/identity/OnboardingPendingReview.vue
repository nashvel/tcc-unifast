<script setup lang="ts">
import { onBeforeUnmount, onMounted } from "vue";
import { IconClockHour4 } from "@tabler/icons-vue";
import { useRoute, useRouter } from "vue-router";
import PageHeader from "@/components/ui/PageHeader.vue";
import { authSession, loadAuthUser } from "@/auth/session";
import { logout } from "@/api/auth";
import { studentHomePath } from "@/auth/onboardingResume";
import { withLang } from "@/i18n/routeLang";

const POLL_MS = 20_000;

const route = useRoute();
const router = useRouter();

let pollTimer: ReturnType<typeof setInterval> | null = null;

function isStillPending(): boolean {
  const user = authSession.user;
  if (!user) return false;
  return (
    user.account_status === "pending_face_review" ||
    user.onboarding_next_step === "face_review"
  );
}

async function refreshAndExitIfReady() {
  const user = await loadAuthUser();
  if (!user) {
    await router.replace(withLang("/login", route.query.lang));
    return;
  }
  if (isStillPending()) return;
  await router.replace(withLang(studentHomePath(user), route.query.lang));
}

async function signOut() {
  await logout();
  await router.push(withLang("/login", route.query.lang));
}

onMounted(() => {
  void refreshAndExitIfReady();
  pollTimer = window.setInterval(() => {
    void refreshAndExitIfReady();
  }, POLL_MS);
});

onBeforeUnmount(() => {
  if (pollTimer) {
    clearInterval(pollTimer);
    pollTimer = null;
  }
});
</script>

<template>
  <div class="space-y-5">
    <PageHeader
      title="Almost there — staff review"
      description="Your face match is being reviewed by UniFAST staff. No action needed from you right now."
    />

    <!-- Step progress — Goal-Gradient Effect: student sees they are nearly done -->
    <section class="rounded-2xl border bg-surface p-5 shadow-sm sm:p-6">
      <p class="mb-4 text-xs font-semibold uppercase tracking-wide text-text-muted">Your progress</p>
      <ol class="space-y-3">
        <li class="flex items-center gap-3 text-sm">
          <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-primary text-white text-xs font-bold">✓</span>
          <span class="text-text-muted line-through">KYC profile</span>
        </li>
        <li class="flex items-center gap-3 text-sm">
          <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-primary text-white text-xs font-bold">✓</span>
          <span class="text-text-muted line-through">School ID scan</span>
        </li>
        <!-- Active step — Von Restorff: highlighted row pops against completed/greyed steps -->
        <li class="flex items-center gap-3 rounded-lg bg-warning-soft/40 px-3 py-2 text-sm -mx-3">
          <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-warning text-white text-xs font-bold">
            <IconClockHour4 :size="13" />
          </span>
          <span class="font-semibold text-text">
            Staff review
            <span class="ml-1 inline-flex items-center gap-1 rounded-full bg-warning-soft px-2 py-0.5 text-[10px] font-medium text-warning">
              In progress
              <!-- Doherty Threshold: pulse dot shows the page is live and checking for updates -->
              <span class="h-1.5 w-1.5 rounded-full bg-warning animate-pulse" title="Checking for updates every 20 seconds" />
            </span>
          </span>
        </li>
        <li class="flex items-center gap-3 text-sm opacity-40">
          <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full border-2 border-surface-muted text-xs font-bold text-text-muted">4</span>
          <span class="text-text-muted">Set your password</span>
        </li>
      </ol>
    </section>

    <!-- What happens next — scannable, no double-negatives -->
    <section class="rounded-2xl border bg-surface p-5 shadow-sm sm:p-6 space-y-3 text-sm">
      <p class="font-semibold">What happens next</p>
      <ul class="space-y-2 text-text-muted">
        <li class="flex items-start gap-2">
          <span class="mt-0.5 font-bold text-primary">→</span>
          Staff will compare your School ID photo and your onboarding selfie.
        </li>
        <li class="flex items-start gap-2">
          <span class="mt-0.5 font-bold text-primary">→</span>
          If approved, you will receive an email with a link to set your password.
        </li>
        <li class="flex items-start gap-2">
          <span class="mt-0.5 font-bold text-primary">→</span>
          If they need a retry, you will receive an email with a fresh verification link.
        </li>
      </ul>
      <p class="pt-1 text-xs text-text-muted">
        <span class="font-medium text-text">You do not need to stay on this page.</span>
        Watch your email — a link will be sent either way. This page checks automatically every
        20 seconds if you leave it open.
      </p>
    </section>

    <!-- Primary CTA — Peak-End Rule: end on a calm, forward-looking action -->
    <div class="flex items-center gap-3">
      <button
        class="inline-flex h-9 items-center rounded-md bg-primary px-4 text-xs font-medium text-white transition hover:bg-primary/90"
        type="button"
        @click="signOut"
      >
        Sign out and wait for email
      </button>
      <p class="text-xs text-text-muted">We'll send you a link when staff finish.</p>
    </div>
  </div>
</template>
