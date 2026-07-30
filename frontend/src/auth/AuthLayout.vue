<script setup lang="ts">
import { IconShieldCheck } from "@tabler/icons-vue";
import { useI18n } from "vue-i18n";
import LanguageSwitcher from "@/components/LanguageSwitcher.vue";
import studentsCutout from "@/assets/auth/forgot-password-students.webp";

const { t } = useI18n();
</script>

<template>
  <div class="flex min-h-screen bg-bg">
    <aside class="hidden flex-1 flex-col border-none bg-[#57151f] text-white p-10 pb-6 lg:flex relative overflow-hidden">
      <div
        class="absolute inset-0"
        style="
          background:
            radial-gradient(circle at 41% 44%, rgba(126, 31, 44, 0.9), transparent 34%),
            radial-gradient(circle at 96% 3%, rgba(255, 255, 255, 0.08), transparent 20%),
            radial-gradient(circle at 58% 58%, rgba(154, 42, 42, 0.46), transparent 14%),
            linear-gradient(135deg, #4a141d 0%, #681b29 52%, #4a141d 100%);
        "
      />
      <div
        class="absolute bottom-32 right-10 h-36 w-36 opacity-20"
        style="
          background-image: radial-gradient(rgba(255, 255, 255, 0.48) 1px, transparent 1px);
          background-size: 14px 14px;
        "
      />

      <div class="relative z-10 flex items-center justify-between gap-4 mb-10">
        <div class="flex items-center gap-2">
          <span class="grid h-8 w-8 place-items-center rounded-md bg-white text-[#57151f]">
            <IconShieldCheck :size="18" />
          </span>
          <div class="leading-tight">
            <p class="text-sm font-semibold">{{ t("app.name") }}</p>
            <p class="text-xs text-white/70">{{ t("app.granteeManagement") }}</p>
          </div>
        </div>
        <LanguageSwitcher />
      </div>
      
      <div class="relative z-10 mb-6">
        <h2 class="max-w-md text-2xl font-semibold leading-tight tracking-tight">
          {{ t("auth.layoutTitle") }}
        </h2>
        <p class="mt-3 max-w-md text-sm text-white/80">
          {{ t("auth.layoutDescription") }}
        </p>
        <ul class="mt-6 space-y-2 text-xs text-white/80">
          <li>• {{ t("auth.layoutBulletMasterlist") }}</li>
          <li>• {{ t("auth.layoutBulletOcr") }}</li>
          <li>• {{ t("auth.layoutBulletEligibility") }}</li>
          <li>• {{ t("auth.layoutBulletAudit") }}</li>
        </ul>
      </div>

      <p class="relative z-10 mt-auto text-micro text-white/50">© 2026 Commission on Higher Education - UniFAST</p>

      <img
        :src="studentsCutout"
        alt="Students"
        class="absolute bottom-16 left-1/2 z-0 w-[94%] max-w-none -translate-x-1/2 object-contain xl:w-[90%]"
      />
    </aside>
    <main class="flex flex-1 items-center justify-center p-6">
      <div class="w-full max-w-sm"><slot /></div>
    </main>
  </div>
</template>
