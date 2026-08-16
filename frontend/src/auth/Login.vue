<script setup lang="ts">
import { onMounted, onUnmounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import { ArrowRight, ChevronDown, ChevronUp, Eye, EyeOff, HelpCircle, Lock, Mail, ShieldCheck, UserRound } from "lucide-vue-next";
import logo from "@/assets/system-logo.webp";
import backgroundLogo from "@/assets/auth/imresizer-TCC_UNIFAST.png";
import studentsCutout from "@/assets/auth/Faculties_UNifast1.webp";
import { authSession } from "@/auth/session";
import { studentHomePath } from "@/auth/onboardingResume";
import { login } from "@/api/auth";
import { apiFetch } from "@/api/client";
import LanguageSwitcher from "@/components/LanguageSwitcher.vue";
import { withLang } from "@/i18n/routeLang";
import VueRecaptcha from "vue3-recaptcha2";

const activeSlide = ref<'text'>('text'); // Kept for legacy bindings just in case, but no longer sliding

// Remove forced light mode so it respects the user's system theme / active Flare theme.
onMounted(() => {
  // Setup if needed
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
</script>

<template>
  <div class="relative flex min-h-screen w-full items-center justify-center overflow-hidden bg-bg">
    <!-- Animated Mesh Background -->
    <div class="pointer-events-none absolute inset-0 z-0 overflow-hidden opacity-40">
      <div class="absolute -left-[10%] -top-[20%] h-[60%] w-[60%] animate-pulse rounded-full bg-primary/20 blur-[120px] mix-blend-multiply duration-[8000ms]"></div>
      <div class="absolute -right-[10%] top-[20%] h-[60%] w-[60%] animate-pulse rounded-full bg-accent-gold/20 blur-[120px] mix-blend-multiply duration-[10000ms]"></div>
      <div class="absolute -bottom-[20%] left-[20%] h-[60%] w-[60%] animate-pulse rounded-full bg-primary-hover/20 blur-[120px] mix-blend-multiply duration-[12000ms]"></div>
    </div>

    <!-- Main Glass Split Screen -->
    <main class="relative z-10 w-full h-screen overflow-hidden bg-surface/80 backdrop-blur-3xl lg:grid lg:grid-cols-[1.05fr_.95fr]">
      
      <!-- Left Side: Branding & Hero (Desktop) -->
      <section class="relative hidden h-full flex-col border-r border-border bg-surface-muted/30 lg:flex">
        
        <div class="relative z-20 flex flex-1 flex-col justify-between p-12 xl:p-16">
          <!-- Logo -->
          <div class="flex items-center gap-4">
            <div class="flex h-12 w-12 items-center justify-center rounded-xl border border-border bg-surface p-2 shadow-sm">
              <img :src="logo" class="h-full w-full object-contain" alt="UniFAST TES" />
            </div>
            <div>
              <p class="font-display text-lg font-bold text-text">{{ t("app.name") }}</p>
              <p class="text-xs font-medium text-text-muted">{{ t("app.college") }}</p>
            </div>
          </div>
          
          <!-- Hero Text -->
          <div class="mb-10 max-w-lg xl:mb-20">
            <p class="mb-3 text-xs font-bold uppercase tracking-widest text-primary">
              Welcome to the future
            </p>
            <h1 class="font-display text-4xl font-bold leading-[1.15] tracking-tight text-text xl:text-5xl">
              {{ t("auth.heroTitle") }}
            </h1>
            <p class="mt-5 text-sm leading-relaxed text-text-muted">
              {{ t("auth.heroDescription") }}
            </p>
          </div>
        </div>

        <!-- Cutout Image pinned to bottom -->
        <div class="absolute bottom-0 left-0 right-0 z-10 h-[45vh] max-h-[500px] overflow-hidden">
          <div class="absolute inset-0 z-10 bg-gradient-to-t from-surface-muted/90 via-surface-muted/20 to-transparent pointer-events-none"></div>
          <img :src="studentsCutout" class="absolute bottom-0 left-1/2 z-0 w-[85%] max-w-[650px] -translate-x-1/2 object-contain drop-shadow-2xl saturate-150 transition-transform duration-700 hover:scale-[1.02]" />
        </div>
      </section>

      <!-- Right Side: Form -->
      <section class="flex flex-col justify-center overflow-y-auto p-8 sm:p-12 lg:p-16">
        <div class="absolute right-6 top-6 hidden lg:block z-50">
          <LanguageSwitcher />
        </div>

        <div class="mx-auto w-full max-w-sm">
          <!-- Mobile Logo (Hidden on Desktop) -->
          <div class="mb-8 flex items-center gap-3 lg:hidden">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg border border-border bg-surface p-1.5 shadow-sm">
              <img :src="logo" class="h-full w-full object-contain" alt="UniFAST TES" />
            </div>
            <div>
              <p class="font-display text-base font-bold text-text">{{ t("app.name") }}</p>
              <p class="text-xs font-medium text-text-muted">{{ t("app.college") }}</p>
            </div>
            <div class="ml-auto"><LanguageSwitcher /></div>
          </div>

          <!-- Form Header -->
          <h2 class="font-display text-2xl font-bold tracking-tight text-text sm:text-3xl">
            {{
              mode === "forgot"
                ? t("auth.forgotTitle")
                : mode === "activate"
                  ? t("auth.activateTitle")
                  : t("auth.loginTitle")
            }}
          </h2>
          <p class="mt-2 text-sm text-text-muted">
            {{
              mode === "login"
                ? t("auth.loginDescription")
                : t("auth.emailDescription")
            }}
          </p>

          <!-- The Form -->
          <form class="mt-8 space-y-5" @submit.prevent="mode === 'login' ? openCaptchaModal() : submit()">
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-text">{{ t("common.email") }} <span class="text-danger">*</span></label>
              <div class="relative">
                <Mail :size="18" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-text-soft transition-colors group-focus-within:text-primary" />
                <input
                  v-model="email"
                  type="email"
                  placeholder="you@unifast.gov.ph"
                  class="group h-11 w-full rounded-xl border border-border bg-surface-muted/50 pl-11 pr-4 text-sm text-text transition-all focus:border-primary focus:bg-surface focus:ring-4 focus:ring-primary/10"
                />
              </div>
            </div>

            <div v-if="mode === 'login'" class="space-y-1.5">
              <label class="text-xs font-semibold text-text">{{ t("common.password") }} <span class="text-danger">*</span></label>
              <div class="relative">
                <Lock :size="18" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-text-soft transition-colors group-focus-within:text-primary" />
                <input
                  v-model="password"
                  :type="showPassword ? 'text' : 'password'"
                  :placeholder="t('common.password')"
                  autocomplete="current-password"
                  class="group h-11 w-full rounded-xl border border-border bg-surface-muted/50 pl-11 pr-11 text-sm text-text transition-all focus:border-primary focus:bg-surface focus:ring-4 focus:ring-primary/10"
                />
                <button
                  type="button"
                  class="absolute right-2 top-1/2 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-lg text-text-soft transition-colors hover:bg-surface-muted hover:text-text"
                  :aria-label="showPassword ? t('common.hidePassword') : t('common.showPassword')"
                  :aria-pressed="showPassword"
                  @click="showPassword = !showPassword"
                >
                  <EyeOff v-if="showPassword" :size="16" />
                  <Eye v-else :size="16" />
                </button>
              </div>
            </div>

            <p v-if="error" class="text-xs font-medium text-danger">{{ error }}</p>

            <button
              type="submit"
              :disabled="busy"
              class="group flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 text-sm font-semibold text-white shadow-md transition-all hover:bg-primary-hover hover:shadow-lg focus:ring-4 focus:ring-primary/30 disabled:opacity-60"
            >
              {{ busy ? t("auth.signingIn") : mode === "login" ? t("common.signIn") : t("common.continue") }}
              <ArrowRight :size="16" class="transition-transform group-hover:translate-x-1" />
            </button>
          </form>

          <!-- Links -->
          <div v-if="mode === 'login'" class="mt-5 flex items-center justify-between text-xs font-medium text-primary">
            <RouterLink :to="withLang('/forgot-password', route.query.lang)" class="hover:underline">{{ t("auth.forgotPassword") }}</RouterLink>
            <RouterLink :to="withLang('/activate', route.query.lang)" class="hover:underline">{{ t("auth.activateAccount") }}</RouterLink>
          </div>
          <div v-else class="mt-5 text-center text-xs font-medium">
            <RouterLink :to="withLang('/login', route.query.lang)" class="text-primary hover:underline">{{ t("auth.backToSignIn") }}</RouterLink>
          </div>

          <!-- Demo Accounts -->
          <div v-if="mode === 'login'" class="mt-8 border-t border-border pt-6">
            <p class="mb-4 text-center text-xs font-bold uppercase tracking-wider text-text-soft">
              {{ t("auth.demoAccounts") }}
            </p>
            <div class="grid grid-cols-2 gap-3">
              <button
                v-for="role in ['Developer', 'Administrator', 'UniFAST Staff', 'Student']"
                :key="role"
                type="button"
                class="flex h-11 items-center gap-2.5 rounded-xl border border-border bg-surface px-3 text-left shadow-sm transition-all hover:border-primary/40 hover:bg-surface-muted hover:shadow-md"
                @click="quickLogin(role)"
              >
                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary/10 text-primary">
                  <UserRound :size="14" stroke-width="2.5" />
                </div>
                <span class="text-xs font-semibold text-text">{{ demoAccountLabel(role) }}</span>
              </button>
            </div>
            
            <RouterLink
              :to="withLang('/help/support', route.query.lang)"
              class="mt-4 flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-border bg-surface text-xs font-semibold text-text-muted transition-all hover:bg-surface-muted hover:text-text"
            >
              <HelpCircle :size="16" />
              Help & Support
            </RouterLink>
          </div>

          <!-- Terms & Conditions -->
          <div v-if="terms && mode === 'login'" class="mt-6">
            <button class="flex w-full items-center justify-between rounded-xl border border-border bg-surface px-4 py-3 text-xs font-medium text-text-muted transition-colors hover:bg-surface-muted hover:text-text" @click="showTerms = !showTerms">
              <span class="flex items-center gap-2">
                <ShieldCheck :size="16" class="text-primary" />
                {{ terms.title }} (v{{ terms.version }})
              </span>
              <component :is="showTerms ? ChevronUp : ChevronDown" :size="16" />
            </button>
            <div v-if="showTerms" class="mt-2 max-h-48 overflow-y-auto rounded-xl border border-border bg-surface-muted/50 p-4 text-xs leading-relaxed text-text-muted shadow-inner">
              <div v-html="terms.content" />
            </div>
          </div>
        </div>
      </section>
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
