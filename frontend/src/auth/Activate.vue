<script setup lang="ts">
import { apiUrl, ensureCsrfCookie } from "@/api/client";
import { onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import {
  IconAlertTriangle,
  IconEye,
  IconEyeOff,
  IconMail,
  IconShieldCheck,
} from "@tabler/icons-vue";
import AuthLayout from "./AuthLayout.vue";
import { authSession } from "@/auth/session";
import { withLang } from "@/i18n/routeLang";

type ActivationPreview = {
  email: string;
  name: string;
  student_id: string;
  program: string;
  expires_at: string;
};

const route = useRoute();
const router = useRouter();
const { t } = useI18n();
const token = String(route.params.token ?? "");
const preview = ref<ActivationPreview | null>(null);
const password = ref("");
const passwordConfirmation = ref("");
const showPassword = ref(false);
const showPasswordConfirmation = ref(false);
const loading = ref(Boolean(token));
const busy = ref(false);
const error = ref("");

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
      throw new Error(payload.message || payload.errors?.token?.[0] || t("auth.invalidActivationLink"));
    }
    preview.value = payload.data;
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : t("auth.invalidActivationLink");
  } finally {
    loading.value = false;
  }
});

async function activate() {
  busy.value = true;
  error.value = "";

  try {
    await ensureCsrfCookie();
    const response = await fetch(apiUrl(`/api/activation/${token}`), {
      method: "POST",
      credentials: "include",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({
        password: password.value,
        password_confirmation: passwordConfirmation.value,
      }),
    });
    const payload = await response.json();
    if (!response.ok) {
      const fieldError = payload.errors
        ? Object.values(payload.errors).flat().join(" ")
        : payload.message;
      throw new Error(String(fieldError || t("auth.activationFailed")));
    }
    if (!payload.user) {
      throw new Error(t("auth.activationFailed"));
    }
    // HttpOnly access/refresh cookies set by the API — no token in JS storage.
    authSession.user = payload.user;
    authSession.loaded = true;
    await router.push(withLang("/student/kyc", route.query.lang));
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
      {{ t("auth.activateGranteeDescription") }}
    </p>

    <div v-if="loading" class="mt-5 rounded-md border bg-surface-muted p-3 text-sm text-text-muted">
      {{ t("auth.checkingActivation") }}
    </div>

    <form v-else-if="preview" class="mt-5 space-y-4" @submit.prevent="activate">
      <article class="rounded-lg border bg-surface-muted p-3">
        <p class="text-sm font-semibold">{{ preview.name }}</p>
        <p class="mt-1 text-xs text-text-muted">{{ preview.student_id }} - {{ preview.program }}</p>
        <p class="mt-1 flex items-center gap-1 text-xs text-text-muted">
          <IconMail :size="13" />{{ preview.email }}
        </p>
      </article>

      <label class="block">
        <span class="mb-1.5 block text-xs font-medium">{{ t("auth.newPassword") }} *</span>
        <span class="relative block">
          <input
            v-model="password"
            :type="showPassword ? 'text' : 'password'"
            autocomplete="new-password"
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
        <span class="relative block">
          <input
            v-model="passwordConfirmation"
            :type="showPasswordConfirmation ? 'text' : 'password'"
            autocomplete="new-password"
            class="h-10 w-full rounded-md border px-3 pr-10"
          />
          <button
            type="button"
            class="absolute right-2 top-1/2 grid h-7 w-7 -translate-y-1/2 place-items-center rounded text-text-soft hover:text-text"
            :aria-label="
              showPasswordConfirmation ? t('common.hidePassword') : t('common.showPassword')
            "
            :aria-pressed="showPasswordConfirmation"
            @click="showPasswordConfirmation = !showPasswordConfirmation"
          >
            <IconEyeOff v-if="showPasswordConfirmation" :size="16" />
            <IconEye v-else :size="16" />
          </button>
        </span>
      </label>
      <p class="rounded-md border bg-surface-muted p-2.5 text-xs text-text-muted">
        {{ t("auth.activationKycNotice") }}
      </p>
      <p v-if="error" class="text-xs text-danger">{{ error }}</p>
      <button
        :disabled="busy"
        class="h-10 w-full rounded-md bg-primary text-sm font-medium text-white disabled:opacity-60"
      >
        {{ busy ? t("auth.activating") : t("auth.activateAndContinue") }}
      </button>
    </form>

    <div
      v-else
      class="mt-5 flex gap-2 rounded-md border border-danger/30 bg-danger-soft p-3 text-xs text-danger"
    >
      <IconAlertTriangle :size="14" />{{ error }}
    </div>

    <RouterLink
      :to="withLang('/login', route.query.lang)"
      class="mt-4 inline-block text-sm text-primary hover:underline"
    >
      {{ t("auth.backToSignIn") }}
    </RouterLink>
  </AuthLayout>
</template>
