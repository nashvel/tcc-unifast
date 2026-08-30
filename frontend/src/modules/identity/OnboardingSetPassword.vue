<script setup lang="ts">
/**
 * Terminal onboarding step: choose a password AFTER identity has been verified.
 *
 * Reachable only while account_status is 'identity_verified'. On success the API
 * swaps the scoped onboarding session for a full one and the account becomes active.
 */
import { computed, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import { IconEye, IconEyeOff, IconLock, IconShieldCheck } from "@tabler/icons-vue";
import { apiFetch } from "@/api";
import { authSession } from "@/auth/session";
import { studentHomePath } from "@/auth/onboardingResume";
import { toast } from "@/composables/useToast";
import { withLang } from "@/i18n/routeLang";

const route = useRoute();
const router = useRouter();
const { t } = useI18n();

const password = ref("");
const passwordConfirmation = ref("");
const showPassword = ref(false);
const busy = ref(false);
const error = ref("");

const canSubmit = computed(
  () => password.value.length >= 8 && password.value === passwordConfirmation.value && !busy.value,
);
const mismatch = computed(
  () => passwordConfirmation.value.length > 0 && password.value !== passwordConfirmation.value,
);

onMounted(async () => {
  const user = authSession.user;
  // Guard against deep-links: only an identity-verified account belongs here.
  if (user && user.onboarding_next_step !== "credentials") {
    await router.replace(withLang(studentHomePath(user), route.query.lang));
  }
});

async function submit() {
  if (!canSubmit.value) return;
  busy.value = true;
  error.value = "";

  try {
    const payload = await apiFetch<{ user: NonNullable<typeof authSession.user> }>(
      "/api/onboarding/credentials",
      {
        method: "POST",
        body: JSON.stringify({
          password: password.value,
          password_confirmation: passwordConfirmation.value,
        }),
      },
    );

    authSession.user = payload.user;
    authSession.loaded = true;
    toast.success(t("onboarding.passwordSetSuccess"));
    await router.replace(withLang("/student", route.query.lang));
  } catch (exception) {
    error.value =
      exception instanceof Error ? exception.message : t("onboarding.passwordSetFailed");
    toast.error(error.value);
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <section class="mx-auto max-w-md p-4">
    <div class="mb-4 flex items-center gap-2">
      <span class="grid h-9 w-9 place-items-center rounded-md bg-success-soft text-success">
        <IconShieldCheck :size="18" />
      </span>
      <div>
        <h1 class="text-lg font-semibold tracking-tight">
          {{ t("onboarding.identityVerifiedTitle") }}
        </h1>
        <p class="text-xs text-text-muted">{{ t("onboarding.setPasswordSubtitle") }}</p>
      </div>
    </div>

    <form class="space-y-4 rounded-lg border bg-surface p-4" @submit.prevent="submit">
      <label class="block">
        <span class="mb-1.5 block text-xs font-medium">{{ t("auth.newPassword") }} *</span>
        <span class="relative block">
          <input
            v-model="password"
            :type="showPassword ? 'text' : 'password'"
            autocomplete="new-password"
            minlength="8"
            required
            class="h-10 w-full rounded-md border px-3 pr-10"
          />
          <button
            type="button"
            class="absolute right-2 top-1/2 grid h-7 w-7 -translate-y-1/2 place-items-center rounded text-text-soft hover:text-text"
            :aria-label="showPassword ? t('common.hidePassword') : t('common.showPassword')"
            :aria-pressed="showPassword"
            @click="showPassword = !showPassword"
          >
            <IconEyeOff v-if="showPassword" :size="16" />
            <IconEye v-else :size="16" />
          </button>
        </span>
      </label>

      <label class="block">
        <span class="mb-1.5 block text-xs font-medium">{{ t("auth.confirmPassword") }} *</span>
        <input
          v-model="passwordConfirmation"
          :type="showPassword ? 'text' : 'password'"
          autocomplete="new-password"
          required
          class="h-10 w-full rounded-md border px-3"
          :aria-invalid="mismatch"
        />
        <span v-if="mismatch" class="mt-1 block text-xs text-danger">
          {{ t("auth.passwordsDoNotMatch") }}
        </span>
      </label>

      <p class="flex items-start gap-2 rounded-md border bg-surface-muted p-2.5 text-xs text-text-muted">
        <IconLock :size="14" class="mt-0.5 shrink-0" />
        {{ t("onboarding.passwordUnlocksVault") }}
      </p>

      <p v-if="error" class="text-xs text-danger" role="alert">{{ error }}</p>

      <button
        :disabled="!canSubmit"
        class="h-10 w-full rounded-md bg-primary text-sm font-medium text-white disabled:opacity-60"
      >
        {{ busy ? t("onboarding.settingPassword") : t("onboarding.setPasswordAndFinish") }}
      </button>
    </form>
  </section>
</template>
