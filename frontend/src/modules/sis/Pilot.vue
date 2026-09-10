<script setup lang="ts">
import { ref } from "vue";
import { useI18n } from "vue-i18n";
import { useRoute } from "vue-router";
import { useOnline } from "@vueuse/core";
import { withLang } from "@/i18n/routeLang";
import { sisMessages } from "./messages";
import { startSis } from "./api";

const { t } = useI18n({ useScope: "local", messages: sisMessages });
const route = useRoute();
const online = useOnline();
const token = typeof window !== "undefined" ? window.location.hash.slice(1) : "";
if (typeof window !== "undefined") window.history.replaceState(window.history.state, "", window.location.pathname + window.location.search);
const valid = /^[A-Za-z0-9]{64}$/.test(token);
const remember = ref(false);
const busy = ref(false);
const failed = ref(false);
async function begin() {
  if (busy.value || !valid) return;
  busy.value = true;
  try { await startSis(remember.value, token); }
  catch { failed.value = true; busy.value = false; }
}
</script>

<template>
  <main class="mx-auto my-12 max-w-lg space-y-5 rounded-xl border bg-surface p-6">
    <h1 class="text-2xl font-semibold">{{ t("pilotTitle") }}</h1>
    <p class="text-sm text-text-muted">{{ t("pilotBody") }}</p>
    <p v-if="!valid" role="alert">{{ t("pilotInvalid") }}</p>
    <p v-if="failed" role="alert" class="text-danger">{{ t("unavailable") }}</p>
    <p v-if="!online" role="status">{{ t("offline") }}</p>
    <label class="flex items-center gap-2 text-sm"><input v-model="remember" type="checkbox" :disabled="busy" />{{ t("remember") }}</label>
    <button :disabled="!valid || busy || !online" :aria-busy="busy" class="w-full rounded-lg bg-primary px-4 py-3 text-white disabled:opacity-50" @click="begin">{{ busy ? t("starting") : t("continue") }}</button>
    <p class="text-xs text-text-muted">{{ t("logoutNote") }}</p>
    <RouterLink class="block text-sm underline" :to="withLang('/login', route.query.lang)">{{ t("back") }}</RouterLink>
  </main>
</template>
