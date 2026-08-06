<script setup lang="ts">
import { IconShieldCheck } from "@tabler/icons-vue";
import { useI18n } from "vue-i18n";
import { useRoute } from "vue-router";
import LanguageSwitcher from "@/components/LanguageSwitcher.vue";
const { t } = useI18n();
const route = useRoute();
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
        <template v-if="route.path.includes('activate')">
          <h2 class="max-w-md text-2xl font-semibold leading-tight tracking-tight">
            Account Activation
          </h2>
          <p class="mt-3 max-w-md text-sm text-white/80">
            Welcome to the UniFAST TES Grantee Management System. Set up your account to get started.
          </p>
          <ul class="mt-6 space-y-2 text-xs text-white/80">
            <li>• Create a secure password</li>
            <li>• Verify your identity</li>
            <li>• Access grantee records instantly</li>
            <li>• Secure and centralized platform</li>
          </ul>
        </template>
        <template v-else>
          <h2 class="max-w-md text-2xl font-semibold leading-tight tracking-tight">
            System Access & Security
          </h2>
          <p class="mt-3 max-w-md text-sm text-white/80">
            Securely manage your account and maintain protected access to the UniFAST TES Grantee Management System.
          </p>
          <ul class="mt-6 space-y-2 text-xs text-white/80">
            <li>• Secure identity verification</li>
            <li>• Protected access to grantee records</li>
            <li>• Automated password recovery</li>
            <li>• Full audit trail for security</li>
          </ul>
        </template>
      </div>

      <p class="relative z-10 mt-auto text-micro text-white/50">© 2026 Commission on Higher Education - UniFAST</p>
    </aside>
    <main class="flex flex-1 items-center justify-center p-6">
      <div class="w-full max-w-sm"><slot /></div>
    </main>
  </div>
</template>
