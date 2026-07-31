<script setup lang="ts">
import { apiUrl } from "@/api/client";
import { onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import { IconAlertTriangle, IconKey, IconMail, IconShieldCheck } from "@tabler/icons-vue";
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
const temporaryPassword = ref("");
const password = ref("");
const passwordConfirmation = ref("");
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
    const response = await fetch(apiUrl(`/api/activation/${token}`), {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({
        temporary_password: temporaryPassword.value,
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
        <span class="mb-1.5 block text-xs font-medium">{{ t("auth.temporaryPassword") }} *</span>
        <span class="relative block">
          <IconKey :size="15" class="absolute left-3 top-1/2 -translate-y-1/2 text-text-soft" />
          <input
            v-model="temporaryPassword"
            placeholder="TCC-8F4K-29QZ"
            class="h-10 w-full rounded-md border pl-9 pr-3 text-sm"
          />
        </span>
      </label>
      <label class="block">
        <span class="mb-1.5 block text-xs font-medium">{{ t("auth.newPassword") }} *</span>
        <input v-model="password" type="password" class="h-10 w-full rounded-md border px-3" />
      </label>
      <label class="block">
        <span class="mb-1.5 block text-xs font-medium">{{ t("auth.confirmPassword") }} *</span>
        <input
          v-model="passwordConfirmation"
          type="password"
          class="h-10 w-full rounded-md border px-3"
        />
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
