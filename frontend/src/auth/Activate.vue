<script setup lang="ts">
/**
 * Identity-first activation entry point.
 *
 * The link no longer sets a password — it opens a scoped onboarding session and
 * drops the student into the identity funnel. The password is chosen at the end,
 * in OnboardingSetPassword.vue, once identity has been verified.
 *
 * The API deliberately returns no identifying details here (this route is public),
 * so only a masked email is shown.
 */
import { apiUrl, ensureCsrfCookie } from "@/api/client";
import { onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import { IconAlertTriangle, IconMail, IconShieldCheck } from "@tabler/icons-vue";
import AuthLayout from "./AuthLayout.vue";
import { authSession } from "@/auth/session";
import { studentHomePath } from "@/auth/onboardingResume";
import { withLang } from "@/i18n/routeLang";

type ActivationPreview = {
  valid: boolean;
  masked_email: string;
  expires_at: string;
};

const route = useRoute();
const router = useRouter();
const { t } = useI18n();
const token = String(route.params.token ?? "");
const preview = ref<ActivationPreview | null>(null);
const loading = ref(Boolean(token));
const busy = ref(false);
const error = ref("");
/** Token is dead rather than merely invalid — offer the resend path. */
const expired = ref(false);

onMounted(async () => {
  if (!token) {
    error.value = t("auth.openActivationLink");
    loading.value = false;
    return;
  }

  try {
    const response = await fetch(apiUrl(`/api/activation/${token}`), {
      credentials: "include",
      headers: { Accept: "application/json" },
    });
    const payload = await response.json();
    if (!response.ok) {
      expired.value = true;
      throw new Error(
        payload.message || payload.errors?.token?.[0] || t("auth.invalidActivationLink"),
      );
    }
    preview.value = payload.data;
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : t("auth.invalidActivationLink");
  } finally {
    loading.value = false;
  }
});

async function begin() {
  busy.value = true;
  error.value = "";

  try {
    await ensureCsrfCookie();
    const response = await fetch(apiUrl(`/api/activation/${token}/begin`), {
      method: "POST",
      credentials: "include",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
    });
    const payload = await response.json();
    if (!response.ok) {
      const fieldError = payload.errors
        ? Object.values(payload.errors).flat().join(" ")
        : payload.message;
      if (response.status === 422) expired.value = true;
      throw new Error(String(fieldError || t("auth.activationFailed")));
    }
    if (!payload.user) {
      throw new Error(t("auth.activationFailed"));
    }
    // Scoped onboarding session lives in an HttpOnly cookie — nothing in JS storage.
    authSession.user = payload.user;
    authSession.loaded = true;
    await router.push(withLang(studentHomePath(payload.user), route.query.lang));
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : t("auth.activationFailed");
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <AuthLayout>
    <div class="mb-3 flex items-center gap-2">
      <span class="grid h-8 w-8 place-items-center rounded-md bg-primary-soft text-primary">
        <IconShieldCheck :size="16" />
      </span>
      <p class="text-micro font-semibold uppercase tracking-wider text-text-soft">
        {{ t("auth.activationLabel") }}
      </p>
    </div>
    <h1 class="text-xl font-semibold tracking-tight">{{ t("auth.activateGranteeTitle") }}</h1>
    <p class="mt-1 text-sm text-text-muted">
      {{ t("auth.activateVerifyFirstDescription") }}
    </p>

    <div v-if="loading" class="mt-5 rounded-md border bg-surface-muted p-3 text-sm text-text-muted">
      {{ t("auth.checkingActivation") }}
    </div>

    <form v-else-if="preview" class="mt-5 space-y-4" @submit.prevent="begin">
      <article class="rounded-lg border bg-surface-muted p-3">
        <p class="flex items-center gap-1 text-xs text-text-muted">
          <IconMail :size="13" />{{ preview.masked_email }}
        </p>
      </article>

      <ol class="space-y-2 rounded-md border bg-surface-muted p-3 text-xs text-text-muted">
        <li>1. {{ t("auth.activationStepKyc") }}</li>
        <li>2. {{ t("auth.activationStepIdScan") }}</li>
        <li>3. {{ t("auth.activationStepLiveness") }}</li>
        <li class="font-medium text-text">4. {{ t("auth.activationStepPassword") }}</li>
      </ol>

      <p class="rounded-md border border-warning/30 bg-warning-soft p-2.5 text-xs text-warning">
        {{ t("auth.activationHaveIdReady") }}
      </p>

      <p v-if="error" class="text-xs text-danger">{{ error }}</p>
      <button
        :disabled="busy"
        class="h-10 w-full rounded-md bg-primary text-sm font-medium text-white disabled:opacity-60"
      >
        {{ busy ? t("auth.activating") : t("auth.startVerification") }}
      </button>
    </form>

    <div v-else class="mt-5 space-y-3">
      <div
        class="flex gap-2 rounded-md border border-danger/30 bg-danger-soft p-3 text-xs text-danger"
      >
        <IconAlertTriangle :size="14" />{{ error }}
      </div>
      <RouterLink
        v-if="expired"
        :to="withLang('/activation/resend', route.query.lang)"
        class="inline-block text-sm text-primary hover:underline"
      >
        {{ t("auth.requestNewLink") }}
      </RouterLink>
    </div>

    <RouterLink
      :to="withLang('/login', route.query.lang)"
      class="mt-4 inline-block text-sm text-primary hover:underline"
    >
      {{ t("auth.backToSignIn") }}
    </RouterLink>
  </AuthLayout>
</template>
