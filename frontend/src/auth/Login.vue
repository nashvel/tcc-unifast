<script setup lang="ts">
import { onMounted, onUnmounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import { ArrowRight, ChevronDown, ChevronUp, Eye, EyeOff, HelpCircle, Lock, Mail, ShieldCheck, UserRound } from "lucide-vue-next";
import logo from "@/assets/system-logo.webp";
import studentsCutout from "@/assets/auth/Faculties_UNifast1.webp";
import { authSession } from "@/auth/session";
import { studentHomePath } from "@/auth/onboardingResume";
import { login } from "@/api/auth";
import { apiFetch } from "@/api/client";
import LanguageSwitcher from "@/components/LanguageSwitcher.vue";
import { withLang } from "@/i18n/routeLang";
import VueRecaptcha from "vue3-recaptcha2";

const activeSlide = ref<'text' | 'image'>('text');
let slideInterval: number | ReturnType<typeof setInterval>;

// Force light mode on login page - never use dark mode here
onMounted(() => {
  slideInterval = setInterval(() => {
    activeSlide.value = activeSlide.value === 'text' ? 'image' : 'text';
  }, 5000);

  document.documentElement.classList.remove("dark", "dev-dark");
});

const route = useRoute();
const router = useRouter();
const { t } = useI18n();
const email = ref("");
const password = ref("");
const showPassword = ref(false);
const showCaptchaModal = ref(false);
const captchaCode = ref("");
const recaptcha = ref<any>(null); // Ref to reset captcha
const error = ref("");
const busy = ref(false);
const mode = route.path.includes("forgot")
  ? "forgot"
  : route.path.includes("activate")
    ? "activate"
    : "login";

type Term = { id: number; title: string; content: string; version: string };

const terms = ref<Term | null>(null);
const showTerms = ref(false);

const siteKey = import.meta.env.VITE_RECAPTCHA_SITE_KEY || "";
const bypassCaptcha = import.meta.env.VITE_DEV_BYPASS_CAPTCHA === 'true';

const demoAccounts: Record<string, string> = {
  Developer: "admin@unifast.gov.ph",
  Administrator: "head@unifast.gov.ph",
  "UniFAST Staff": "staff@unifast.gov.ph",
  Student: "student@tcc.edu.ph",
};

function openCaptchaModal() {
  if (mode === "login" && (!email.value || !password.value)) {
    error.value = t("auth.emailPasswordRequired");
    return;
  }
  
  if (bypassCaptcha) {
    submit();
    return;
  }
  
  error.value = "";
  showCaptchaModal.value = true;
}

function onCaptchaVerify(response: string) {
  captchaCode.value = response;
  submit();
}

function onCaptchaExpired() {
  captchaCode.value = "";
}

async function submit() {
  if (mode === "login" && (!email.value || !password.value)) {
    error.value = t("auth.emailPasswordRequired");
    return;
  }

  // If bypassing, provide dummy value
  if (bypassCaptcha && !captchaCode.value) {
    captchaCode.value = "BYPASS";
  }

  busy.value = true;
  error.value = "";
  try {
    const user = await login(email.value, password.value, captchaCode.value);
    // Success — close modal and navigate
    showCaptchaModal.value = false;
    authSession.user = user;
    authSession.loaded = true;
    // Mid-onboarding students resume at KYC / ID scan / liveness (no reactivation).
    await router.push(withLang(studentHomePath(user), route.query.lang));
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : t("auth.signInFailed");
    // Reset captcha on failure since tokens are single-use
    captchaCode.value = "";
    if (recaptcha.value) {
      recaptcha.value.reset();
    }
    // If login failed (e.g. wrong password), close the modal so they can fix it
    showCaptchaModal.value = false;
  } finally {
    busy.value = false;
  }
}

async function quickLogin(label: string) {
  email.value = demoAccounts[label];
  password.value = "password";
  openCaptchaModal();
}

function demoAccountLabel(role: string) {
  const labels: Record<string, string> = {
    Developer: t("auth.developer"),
    Administrator: t("auth.administrator"),
    "UniFAST Staff": t("auth.staff"),
    Student: t("auth.student"),
  };
  return labels[role] ?? role;
}

onMounted(async () => {
  try {
    const termsRes = await apiFetch<{ data: Term }>("/api/terms/active");
    terms.value = termsRes.data;
  } catch {}
});

onUnmounted(() => {
  clearInterval(slideInterval);
});
</script>

<template>
  <div class="grid h-screen overflow-hidden bg-surface lg:h-[80vh] lg:grid-cols-[1.08fr_.92fr]">
    <section
      class="relative hidden h-screen overflow-hidden px-8 py-5 lg:flex lg:h-[80vh] lg:flex-col xl:px-10 xl:py-6 transition-colors duration-1000"
      :class="activeSlide === 'text' ? 'bg-[#57151f]' : 'bg-white'"
    >
      <!-- Dynamic Header Bar -->
      <div 
        class="absolute top-0 left-0 w-full h-[76px] xl:h-[84px] transition-colors duration-1000 z-40"
        :class="activeSlide === 'image' ? 'bg-[#57151f]' : 'bg-white'"
      ></div>

      <div
        class="absolute inset-0 transition-opacity duration-1000 z-0"
        :class="activeSlide === 'text' ? 'opacity-100' : 'opacity-0'"
        style="
          background:
            radial-gradient(circle at 41% 44%, rgba(126, 31, 44, 0.9), transparent 34%),
            radial-gradient(circle at 96% 3%, rgba(255, 255, 255, 0.08), transparent 20%),
            radial-gradient(circle at 58% 58%, rgba(154, 42, 42, 0.46), transparent 14%),
            linear-gradient(135deg, #4a141d 0%, #681b29 52%, #4a141d 100%);
        "
      />
      <div
        class="absolute bottom-32 right-10 h-36 w-36 transition-opacity duration-1000 z-0"
        :class="activeSlide === 'text' ? 'opacity-20' : 'opacity-0'"
        style="
          background-image: radial-gradient(rgba(255, 255, 255, 0.48) 1px, transparent 1px);
          background-size: 14px 14px;
        "
      />
      
      <div 
        class="relative z-50 flex items-center gap-3 transition-all duration-1000 w-full"
        :class="activeSlide === 'image' ? 'justify-center mt-3 text-white' : 'justify-start mt-0 text-[#57151f]'"
      >
        <div class="h-10 w-10 rounded-lg p-1.5 shadow-sm border border-transparent transition-colors duration-1000"
             :class="activeSlide === 'image' ? 'bg-white shadow-sm' : 'bg-[#57151f]'">
          <img :src="logo" class="h-full w-full object-contain" alt="UniFAST TES" />
        </div>
        <div class="transition-colors duration-1000">
          <p class="text-base font-semibold transition-all duration-1000" :class="activeSlide === 'image' ? 'text-xl uppercase tracking-widest' : ''">
            {{ activeSlide === 'image' ? 'Meet the Faculties' : 'UniFAST TES' }}
          </p>
          <p v-show="activeSlide === 'text'" class="text-xs transition-opacity duration-1000" :class="activeSlide === 'image' ? 'text-white/80' : 'text-[#57151f]/80'">
            {{ t("app.granteeManagement") }}
          </p>
        </div>
      </div>
      <div 
        class="relative z-10 mt-16 max-w-xl xl:mt-20 transition-all duration-1000 absolute text-white"
        :class="activeSlide === 'text' ? 'opacity-100 translate-y-0' : 'opacity-0 -translate-y-8 pointer-events-none absolute'"
      >
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-white/90">
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
        class="absolute bottom-0 left-1/2 z-10 w-[94%] max-w-none -translate-x-1/2 object-contain drop-shadow-2xl brightness-105 contrast-105 transition-all duration-1000 hover:scale-[1.02] hover:brightness-110 xl:w-[90%]"
        :class="activeSlide === 'image' ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-12 pointer-events-none'"
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

        <form class="mt-5 space-y-3.5">
          <label class="block">
            <span class="mb-1.5 block text-xs font-medium text-text">{{ t("common.email") }} <b class="text-danger">*</b></span>
            <div class="relative">
              <Mail :size="17" class="absolute left-3 top-1/2 -translate-y-1/2 text-text-soft" />
              <input
                v-model="email"
                type="email"
                placeholder="you@unifast.gov.ph"
                class="h-10 w-full rounded-md border bg-[#f1f5fb] pl-10 pr-3 text-sm text-text shadow-inner shadow-slate-200/40"
              />
            </div>
          </label>
          <label v-if="mode === 'login'" class="block">
            <span class="mb-1.5 block text-xs font-medium text-text"
              >{{ t("common.password") }} <b class="text-danger">*</b></span
            >
            <div class="relative">
              <Lock :size="17" class="absolute left-3 top-1/2 -translate-y-1/2 text-text-soft" />
              <input
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                :placeholder="t('common.password')"
                autocomplete="current-password"
                class="h-10 w-full rounded-md border bg-[#f1f5fb] pl-10 pr-10 text-sm text-text shadow-inner shadow-slate-200/40"
              />
              <button
                type="button"
                class="absolute right-2 top-1/2 grid h-7 w-7 -translate-y-1/2 place-items-center rounded text-text-soft hover:text-text"
                :aria-label="showPassword ? t('common.hidePassword') : t('common.showPassword')"
                :aria-pressed="showPassword"
                @click="showPassword = !showPassword"
              >
                <EyeOff v-if="showPassword" :size="16" />
                <Eye v-else :size="16" />
              </button>
            </div>
          </label>
          <p v-if="error" class="text-xs text-danger mb-2">{{ error }}</p>

          <button
            type="button"
            :disabled="busy"
            class="flex h-10 w-full items-center justify-center gap-2 rounded-md bg-primary text-sm font-semibold text-white shadow-sm hover:bg-primary-hover disabled:opacity-60"
            @click="mode === 'login' ? openCaptchaModal() : submit()"
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
              v-for="role in ['Developer', 'Administrator', 'UniFAST Staff', 'Student']"
              :key="role"
              type="button"
              class="flex h-10 items-center gap-2.5 rounded-md border bg-white px-3 text-left hover:bg-surface-muted"
              @click="quickLogin(role)"
            >
              <UserRound :size="17" class="text-text-muted" />
              <span class="text-xs font-medium">{{ demoAccountLabel(role) }}</span>
            </button>
          </div>
          <RouterLink
            :to="withLang('/help/support', route.query.lang)"
            class="mt-4 flex items-center justify-center gap-2 rounded-md border py-2.5 text-xs font-medium text-text-muted hover:bg-surface-muted"
          >
            <HelpCircle :size="14" />
            Help & Support
          </RouterLink>
        </div>

        <!-- Terms & Conditions -->
        <div v-if="terms && mode === 'login'" class="mt-6 border-t pt-4">
          <button class="flex w-full items-center gap-2 text-xs font-medium text-text-muted hover:text-text" @click="showTerms = !showTerms">
            <ShieldCheck :size="14" />
            <span>{{ terms.title }} (v{{ terms.version }})</span>
            <component :is="showTerms ? ChevronUp : ChevronDown" :size="14" class="ml-auto" />
          </button>
          <div v-if="showTerms" class="mt-3 max-h-48 overflow-y-auto rounded-md border bg-surface p-3 text-xs text-text-muted">
            <div v-html="terms.content" />
          </div>
        </div>
      </div>
    </main>
  </div>

  <!-- reCAPTCHA Modal Overlay -->
  <div v-if="showCaptchaModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
    <div class="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl relative">
      <h3 class="mb-4 text-center text-lg font-semibold tracking-tight text-text">
        Security Verification
      </h3>
      <p class="mb-5 text-center text-sm text-text-muted">
        Please complete the CAPTCHA to continue signing in.
      </p>
      
      <div class="flex justify-center mb-6">
        <VueRecaptcha
          ref="recaptcha"
          :sitekey="siteKey"
          size="normal"
          theme="light"
          @verify="onCaptchaVerify"
          @expired="onCaptchaExpired"
          @error="onCaptchaExpired"
        />
      </div>

      <button
        type="button"
        class="w-full rounded-md border py-2 text-sm font-medium hover:bg-surface-muted"
        @click="showCaptchaModal = false"
      >
        Cancel
      </button>
    </div>
  </div>
</template>
