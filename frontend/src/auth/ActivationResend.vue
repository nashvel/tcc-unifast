<script setup lang="ts">
/**
 * Self-service activation-link recovery.
 *
 * The API always returns the same generic response whether or not the address
 * exists, so this view must render success unconditionally — branching on the
 * result would reintroduce the account-enumeration oracle the API avoids.
 */
import { computed, ref } from "vue";
import { useRoute } from "vue-router";
import { useI18n } from "vue-i18n";
import { IconAlertTriangle, IconMailForward } from "@tabler/icons-vue";
import { apiUrl, ensureCsrfCookie } from "@/api/client";
import AuthLayout from "./AuthLayout.vue";
import { withLang } from "@/i18n/routeLang";

const route = useRoute();
const { t } = useI18n();

const email = ref("");
const busy = ref(false);
const sent = ref(false);
const error = ref("");
const sessionExpired = computed(() => route.query.reason === "session_expired");

async function submit() {
  if (busy.value) return;
  busy.value = true;
  error.value = "";

  try {
    await ensureCsrfCookie();
    const response = await fetch(apiUrl("/api/activation/resend"), {
      method: "POST",
      credentials: "include",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({ email: email.value }),
    });

    if (response.status === 429) {
      throw new Error(t("auth.resendTooMany"));
    }
    if (!response.ok) {
      const payload = await response.json().catch(() => ({}));
      const fieldError = payload.errors
        ? Object.values(payload.errors).flat().join(" ")
        : payload.message;
      throw new Error(String(fieldError || t("auth.resendFailed")));
    }

    // Deliberately unconditional: never reveal whether the account exists.
    sent.value = true;
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : t("auth.resendFailed");
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <AuthLayout>
    <div class="mb-3 flex items-center gap-2">
      <span class="grid h-8 w-8 place-items-center rounded-md bg-primary-soft text-primary">
        <IconMailForward :size="16" />
      </span>
      <p class="text-micro font-semibold uppercase tracking-wider text-text-soft">
        {{ t("auth.activationLabel") }}
      </p>
    </div>
    <h1 class="text-xl font-semibold tracking-tight">{{ t("auth.requestNewLinkTitle") }}</h1>
    <p class="mt-1 text-sm text-text-muted">{{ t("auth.requestNewLinkDescription") }}</p>

    <!-- Arrived here because a short onboarding session lapsed, not because the
         account is broken — say so, since progress really is saved. -->
    <p
      v-if="sessionExpired"
      class="mt-3 rounded-md border border-warning/30 bg-warning-soft p-2.5 text-xs text-warning"
    >
      {{ t("auth.sessionTimedOutReopenLink") }}
    </p>

    <div
      v-if="sent"
      class="mt-5 rounded-md border border-success/30 bg-success-soft p-3 text-sm text-success"
      role="status"
    >
      {{ t("auth.resendGenericConfirmation") }}
    </div>

    <form v-else class="mt-5 space-y-4" @submit.prevent="submit">
      <label class="block">
        <span class="mb-1.5 block text-xs font-medium">{{ t("auth.email") }} *</span>
        <input
          v-model="email"
          type="email"
          autocomplete="email"
          required
          class="h-10 w-full rounded-md border px-3"
        />
      </label>

      <div
        v-if="error"
        class="flex gap-2 rounded-md border border-danger/30 bg-danger-soft p-3 text-xs text-danger"
        role="alert"
      >
        <IconAlertTriangle :size="14" />{{ error }}
      </div>

      <button
        :disabled="busy"
        class="h-10 w-full rounded-md bg-primary text-sm font-medium text-white disabled:opacity-60"
      >
        {{ busy ? t("auth.sending") : t("auth.sendNewLink") }}
      </button>
    </form>

    <RouterLink
      :to="withLang('/login', route.query.lang)"
      class="mt-4 inline-block text-sm text-primary hover:underline"
    >
      {{ t("auth.backToSignIn") }}
    </RouterLink>
  </AuthLayout>
</template>
