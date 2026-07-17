<script setup lang="ts">
import { ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import { ArrowRight, Lock, Mail, UserRound } from "lucide-vue-next";
import logo from "@/assets/system-logo.png";
import studentsCutout from "@/assets/auth/tcc-students-cutout.png";
import { authSession, csrfToken } from "@/auth/session";
import LanguageSwitcher from "@/components/LanguageSwitcher.vue";
import { withLang } from "@/i18n/routeLang";

const route = useRoute();
const router = useRouter();
const { t } = useI18n();
const email = ref("");
const password = ref("");
const error = ref("");
const busy = ref(false);
const mode = route.path.includes("forgot")
  ? "forgot"
  : route.path.includes("activate")
    ? "activate"
    : "login";
const demoAccounts: Record<string, string> = {
  Administrator: "admin@unifast.gov.ph",
  "Office Head": "head@unifast.gov.ph",
  "UniFAST Staff": "staff@unifast.gov.ph",
  Student: "student@tcc.edu.ph",
};

async function submit() {
  if (mode === "login" && (!email.value || !password.value)) {
    error.value = t("auth.emailPasswordRequired");
    return;
  }

  busy.value = true;
  error.value = "";
  try {
    const response = await fetch("/api/auth/login", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": csrfToken(),
        Accept: "application/json",
      },
      body: JSON.stringify({ email: email.value, password: password.value }),
    });
    const payload = await response.json();
    if (!response.ok) throw new Error(payload.message || t("auth.signInFailed"));
    authSession.user = payload.user;
    authSession.loaded = true;
    await router.push(withLang(payload.user.role === "student" ? "/student" : "/app", route.query.lang));
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : t("auth.signInFailed");
  } finally {
    busy.value = false;
  }
}

async function quickLogin(label: string) {
  email.value = demoAccounts[label];
  password.value = "password";
  await submit();
}

function demoAccountLabel(role: string) {
  const labels: Record<string, string> = {
    Administrator: t("auth.administrator"),
    "Office Head": t("auth.officeHead"),
    "UniFAST Staff": t("auth.staff"),
    Student: t("auth.student"),
  };
  return labels[role] ?? role;
}
</script>

<template>
  <div class="grid h-screen overflow-hidden bg-surface lg:h-[80vh] lg:grid-cols-[1.08fr_.92fr]">
    <section
      class="relative hidden h-screen overflow-hidden bg-[#57151f] p-8 text-white lg:flex lg:h-[80vh] lg:flex-col xl:p-10"
    >
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
      <div class="relative z-10 flex items-center gap-3">
        <div class="h-10 w-10 rounded-lg bg-white p-1.5 shadow-sm">
          <img :src="logo" class="h-full w-full object-contain" alt="UniFAST TES" />
        </div>
        <div>
          <p class="text-base font-semibold">UniFAST TES</p>
          <p class="text-xs text-white/65">{{ t("app.granteeManagement") }}</p>
        </div>
      </div>
      <div class="relative z-10 mt-16 max-w-xl xl:mt-20">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#f2bd4c]">
          {{ t("app.college") }}
        </p>
        <h1
          class="mt-4 max-w-[31rem] text-3xl font-semibold leading-[1.14] tracking-tight xl:text-4xl"
        >
          {{ t("auth.heroTitle") }}
        </h1>
        <p class="mt-4 max-w-md text-sm leading-6 text-white/82">
          {{ t("auth.heroDescription") }}
        </p>
      </div>
      <img
        :src="studentsCutout"
        alt="Tagoloan Community College students"
        class="absolute bottom-0 left-1/2 z-10 w-[94%] max-w-none -translate-x-1/2 object-contain xl:w-[90%]"
      />
    </section>

    <main
      class="relative flex h-screen items-center justify-center overflow-hidden bg-white p-5 sm:p-6 lg:h-[80vh]"
    >
      <div class="absolute right-6 top-6 hidden lg:block">
        <LanguageSwitcher />
      </div>

      <div class="w-full max-w-[31rem]">
        <div class="mb-6 flex items-center gap-3 lg:hidden">
          <span
            class="grid h-12 w-12 place-items-center rounded-xl border bg-white p-1.5 shadow-sm"
          >
            <img :src="logo" class="h-full w-full object-contain" alt="UniFAST TES" />
          </span>
          <div>
            <p class="font-semibold">{{ t("app.name") }}</p>
            <p class="text-xs text-text-muted">{{ t("app.college") }}</p>
          </div>
          <div class="ml-auto"><LanguageSwitcher /></div>
        </div>

        <h2 class="text-xl font-semibold tracking-tight text-text">
          {{
            mode === "forgot"
              ? t("auth.forgotTitle")
              : mode === "activate"
                ? t("auth.activateTitle")
                : t("auth.loginTitle")
          }}
        </h2>
        <p class="mt-1 text-sm text-text-muted">
          {{
            mode === "login"
              ? t("auth.loginDescription")
              : t("auth.emailDescription")
          }}
        </p>

        <form class="mt-5 space-y-3.5" @submit.prevent="submit">
          <label class="block">
            <span class="mb-1.5 block text-xs font-medium">{{ t("common.email") }} <b class="text-danger">*</b></span>
            <div class="relative">
              <Mail :size="17" class="absolute left-3 top-1/2 -translate-y-1/2 text-text-soft" />
              <input
                v-model="email"
                type="email"
                placeholder="you@unifast.gov.ph"
                class="h-10 w-full rounded-md border bg-[#f1f5fb] pl-10 pr-3 text-sm shadow-inner shadow-slate-200/40"
              />
            </div>
          </label>
          <label v-if="mode === 'login'" class="block">
            <span class="mb-1.5 block text-xs font-medium"
              >{{ t("common.password") }} <b class="text-danger">*</b></span
            >
            <div class="relative">
              <Lock :size="17" class="absolute left-3 top-1/2 -translate-y-1/2 text-text-soft" />
              <input
                v-model="password"
                type="password"
                :placeholder="t('common.password')"
                class="h-10 w-full rounded-md border bg-[#f1f5fb] pl-10 pr-3 text-sm shadow-inner shadow-slate-200/40"
              />
            </div>
          </label>
          <p v-if="error" class="text-xs text-danger">{{ error }}</p>
          <button
            :disabled="busy"
            class="flex h-10 w-full items-center justify-center gap-2 rounded-md bg-primary text-sm font-semibold text-white shadow-sm hover:bg-primary-hover disabled:opacity-60"
          >
            {{ busy ? t("auth.signingIn") : mode === "login" ? t("common.signIn") : t("common.continue") }}
            <ArrowRight :size="15" />
          </button>
        </form>

        <div v-if="mode === 'login'" class="mt-4 flex justify-between text-xs text-primary">
          <RouterLink :to="withLang('/forgot-password', route.query.lang)">{{ t("auth.forgotPassword") }}</RouterLink>
          <RouterLink :to="withLang('/activate', route.query.lang)">{{ t("auth.activateAccount") }}</RouterLink>
        </div>
        <div v-else class="mt-4 text-xs">
          <RouterLink :to="withLang('/login', route.query.lang)" class="text-primary">{{ t("auth.backToSignIn") }}</RouterLink>
        </div>

        <div v-if="mode === 'login'" class="mt-6 border-t pt-4">
          <p class="mb-2.5 text-2xs font-semibold uppercase tracking-wider text-text-soft">
            {{ t("auth.demoAccounts") }}
          </p>
          <div class="grid grid-cols-2 gap-2">
            <button
              v-for="role in ['Administrator', 'Office Head', 'UniFAST Staff', 'Student']"
              :key="role"
              type="button"
              class="flex h-10 items-center gap-2.5 rounded-md border bg-white px-3 text-left hover:bg-surface-muted"
              @click="quickLogin(role)"
            >
              <UserRound :size="17" class="text-text-muted" />
              <span class="text-xs font-medium">{{ demoAccountLabel(role) }}</span>
            </button>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>
