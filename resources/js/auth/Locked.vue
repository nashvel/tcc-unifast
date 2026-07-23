<script setup lang="ts">
import { useRoute } from "vue-router";
import { useI18n } from "vue-i18n";
import { IconLock } from "@tabler/icons-vue";
import AuthLayout from "./AuthLayout.vue";
import { withLang } from "@/i18n/routeLang";

const route = useRoute();
const { t } = useI18n();
</script>

<template>
  <AuthLayout>
    <div class="text-center">
      <span
        class="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-full bg-danger-soft text-danger"
        ><IconLock :size="22"
      /></span>
      <h1 class="text-xl font-semibold">{{ t("auth.lockedTitle") }}</h1>
      <p class="mx-auto mt-1 max-w-xs text-sm text-text-muted">
        {{ t("auth.lockedDescription") }}
      </p>
      <RouterLink
        :to="withLang('/login', route.query.lang)"
        class="mt-5 inline-block text-sm font-medium text-primary hover:underline"
        >← {{ t("auth.backToSignIn") }}</RouterLink
      >
    </div>
  </AuthLayout>
</template>
