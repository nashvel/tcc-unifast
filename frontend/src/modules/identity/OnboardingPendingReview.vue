<script setup lang="ts">
import { onBeforeUnmount, onMounted } from "vue";
import { IconClockHour4 } from "@tabler/icons-vue";
import { useRoute, useRouter } from "vue-router";
import PageHeader from "@/components/ui/PageHeader.vue";
import { authSession, clearAuthToken, loadAuthUser } from "@/auth/session";
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
  clearAuthToken();
  authSession.user = null;
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
      title="Under staff review"
      description="Uncertain face match — waiting for staff. This is not a block. Portal features stay locked until staff approve or reject."
    />

    <section class="rounded-2xl border bg-surface p-5 shadow-sm sm:p-6">
      <div class="flex items-start gap-3">
        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-warning-soft text-warning">
          <IconClockHour4 :size="20" />
        </span>
        <div class="space-y-2">
          <p class="text-sm font-semibold">Uncertain ≠ blocked</p>
          <p class="text-sm text-text-muted">
            The automatic face comparison was inconclusive (uncertain zone). UniFAST staff will
            compare your School ID reference photo with your onboarding selfie at
            <span class="font-medium text-text">/app/face-reviews</span>. Approve activates your
            account; reject blocks it.
          </p>
          <p class="text-xs text-text-muted">
            You can sign out and return later. You do not need to re-scan while review is pending.
            This page refreshes automatically when staff decide.
          </p>
          <button
            class="mt-2 inline-flex h-9 items-center rounded-md border px-3 text-xs font-medium"
            type="button"
            @click="signOut"
          >
            Sign out
          </button>
        </div>
      </div>
    </section>
  </div>
</template>
