<script setup lang="ts">
import { ref } from "vue";
import { useI18n } from "vue-i18n";
import { useRouter, useRoute } from "vue-router";
import { useOnline } from "@vueuse/core";
import { logout } from "@/api/auth";
import { clearAuthSession, loadAuthUser } from "@/auth/session";
import { studentHomePath } from "@/auth/onboardingResume";
import { withLang } from "@/i18n/routeLang";
import { sisMessages } from "./messages";

const { t } = useI18n({ useScope: "local", messages: sisMessages });
const router = useRouter();
const route = useRoute();
const online = useOnline();
const busy = ref(false);
const failed = ref(false);
async function act(signOut = false) {
  busy.value = true;
  failed.value = false;
  try {
    if (signOut) { await logout(); clearAuthSession(); await router.replace(withLang('/login', route.query.lang)); }
    else { const user = await loadAuthUser(); await router.replace(withLang(user ? studentHomePath(user) : '/login', route.query.lang)); }
  } catch { failed.value = true; }
  finally { busy.value = false; }
}
</script>

<template>
  <main class="mx-auto my-12 max-w-xl space-y-5 rounded-xl border bg-surface p-6">
    <h1 class="text-2xl font-semibold">{{ t("reviewTitle") }}</h1>
    <p role="status" class="text-text-muted">{{ t("reviewBody") }}</p>
    <p v-if="failed" role="alert" class="text-danger">{{ t("failed") }}</p>
    <p v-if="!online" role="status">{{ t("offline") }}</p>
    <div class="flex flex-wrap gap-3">
      <button class="rounded-lg bg-primary px-4 py-2 text-white disabled:opacity-50" :disabled="busy || !online" @click="act()">{{ t("checkStatus") }}</button>
      <button class="rounded-lg border px-4 py-2 disabled:opacity-50" :disabled="busy || !online" @click="act(true)">{{ t("signOut") }}</button>
    </div>
    <RouterLink class="block text-sm underline" :to="withLang('/help/support', route.query.lang)">{{ t("support") }}</RouterLink>
    <p class="text-xs text-text-muted">{{ t("logoutNote") }}</p>
  </main>
</template>
