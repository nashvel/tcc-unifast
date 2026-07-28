<script setup lang="ts">
import { IconShieldCheck } from "@tabler/icons-vue";
import { useI18n } from "vue-i18n";
import LanguageSwitcher from "@/components/LanguageSwitcher.vue";
import studentsCutout from "@/assets/auth/forgot-password-students.webp";

const { t } = useI18n();
</script>

<template>
  <div class="flex min-h-screen bg-bg">
    <aside class="hidden flex-1 flex-col border-r bg-surface p-10 pb-24 lg:flex relative overflow-hidden">
      <div class="relative z-10 flex items-center justify-between gap-4 mb-10">
        <div class="flex items-center gap-2">
          <span class="grid h-8 w-8 place-items-center rounded-md bg-primary text-white">
            <IconShieldCheck :size="18" />
          </span>
          <div class="leading-tight">
            <p class="text-sm font-semibold">{{ t("app.name") }}</p>
            <p class="text-xs text-text-muted">{{ t("app.granteeManagement") }}</p>
          </div>
        </div>
        <LanguageSwitcher />
      </div>
      
      <div class="relative z-10 mb-6">
        <h2 class="max-w-md text-2xl font-semibold leading-tight tracking-tight">
          {{ t("auth.layoutTitle") }}
        </h2>
        <p class="mt-3 max-w-md text-sm text-text-muted">
          {{ t("auth.layoutDescription") }}
        </p>
        <ul class="mt-6 space-y-2 text-xs text-text-muted">
          <li>• {{ t("auth.layoutBulletMasterlist") }}</li>
          <li>• {{ t("auth.layoutBulletOcr") }}</li>
          <li>• {{ t("auth.layoutBulletEligibility") }}</li>
          <li>• {{ t("auth.layoutBulletAudit") }}</li>
        </ul>
      </div>

      <p class="relative z-10 text-micro text-text-soft">© 2026 Commission on Higher Education - UniFAST</p>

      <img
        :src="studentsCutout"
        alt="Students"
        class="absolute bottom-12 left-1/2 z-0 w-[90%] max-w-none -translate-x-1/2 object-contain"
      />
    </aside>
    <main class="flex flex-1 items-center justify-center p-6">
      <div class="w-full max-w-sm"><slot /></div>
    </main>
  </div>
</template>
