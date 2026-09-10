<script setup lang="ts">
import { ref } from "vue";
import { useQuery } from "@tanstack/vue-query";
import { useI18n } from "vue-i18n";
import { useOnline } from "@vueuse/core";
import { apiFetch } from "@/api/client";
import { sisKeys, startSis, type SisCapabilities } from "./api";
import { sisMessages } from "./messages";

defineProps<{ disabled?: boolean }>();
const { t } = useI18n({ useScope: "local", messages: sisMessages });
const online = useOnline();
const busy = ref(false);
const remember = ref(false);
const failed = ref(false);
const capability = useQuery({ queryKey: sisKeys.capabilities, queryFn: () => apiFetch<SisCapabilities>("/api/auth/capabilities"), staleTime: 30_000, retry: false });
async function begin() {
  if (busy.value) return;
  busy.value = true;
  failed.value = false;
  try { await startSis(remember.value); }
  catch { failed.value = true; busy.value = false; }
}
</script>

<template>
  <div v-if="capability.data.value?.sis.available" class="space-y-2">
    <label class="flex items-center gap-2 text-xs text-text-muted">
      <input v-model="remember" type="checkbox" :disabled="busy || disabled" />{{ t("remember") }}
    </label>
    <button type="button" :disabled="busy || disabled || !online" :aria-busy="busy" class="flex min-h-10 w-full items-center justify-center rounded-md border px-3 py-2 text-sm font-semibold hover:bg-surface-muted disabled:opacity-60" @click="begin">
      {{ busy ? t("starting") : t("signIn", { name: capability.data.value.sis.display_name }) }}
    </button>
    <p v-if="failed" role="alert" class="text-xs text-danger">{{ t("unavailable") }}</p>
    <p v-if="!online" role="status" class="text-xs text-text-muted">{{ t("offline") }}</p>
  </div>
</template>
