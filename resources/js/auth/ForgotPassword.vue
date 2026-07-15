<script setup lang="ts">
import { ref } from "vue";
import { useRoute } from "vue-router";
import { useI18n } from "vue-i18n";
import { IconCheck } from "@tabler/icons-vue";
import AuthLayout from "./AuthLayout.vue";
import { withLang } from "@/i18n/routeLang";

const route = useRoute();
const { t } = useI18n();
const email = ref("");
const sent = ref(false);
</script>

<template>
  <AuthLayout>
    <template v-if="sent">
      <span class="mb-3 grid h-9 w-9 place-items-center rounded-full bg-success-soft text-success"
        ><IconCheck :size="18"
      /></span>
      <h1 class="text-xl font-semibold">{{ t("auth.checkEmailTitle") }}</h1>
      <p class="mt-1 text-sm text-text-muted">
        {{ t("auth.resetSent", { email }) }}
      </p>
      <RouterLink
        :to="withLang('/login', route.query.lang)"
        class="mt-5 inline-block text-sm text-primary hover:underline"
        >← {{ t("auth.backToSignIn") }}</RouterLink
      >
    </template>
    <template v-else>
      <h1 class="text-xl font-semibold tracking-tight">{{ t("auth.forgotPasswordTitle") }}</h1>
      <p class="mt-1 text-sm text-text-muted">{{ t("auth.forgotPasswordDescription") }}</p>
      <form class="mt-5 space-y-4" @submit.prevent="sent = true">
        <label class="block"
          ><span class="mb-1.5 block text-xs font-medium">{{ t("common.email") }} *</span
          ><input
            v-model="email"
            required
            type="email"
            placeholder="you@unifast.gov.ph"
            class="h-10 w-full rounded-md border bg-surface px-3 text-sm"
        /></label>
        <button class="h-10 w-full rounded-md bg-primary text-sm font-medium text-white">
          {{ t("auth.sendResetLink") }}
        </button>
      </form>
      <RouterLink
        :to="withLang('/login', route.query.lang)"
        class="mt-4 inline-block text-sm text-primary hover:underline"
        >← {{ t("auth.backToSignIn") }}</RouterLink
      >
    </template>
  </AuthLayout>
</template>
