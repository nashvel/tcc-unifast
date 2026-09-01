<script setup lang="ts">
import { onMounted, onUnmounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import { ArrowLeft, ArrowRight, ChevronDown, ChevronUp, Eye, EyeOff, HelpCircle, Lock, Mail, ShieldCheck, UserRound } from "lucide-vue-next";
import logo from "@/assets/system-logo.webp";
import backgroundLogo from "@/assets/auth/imresizer-TCC_UNIFAST.png";
import studentsCutout from "@/assets/auth/Faculties_UNifast1.webp";
import { authSession } from "@/auth/session";
import { studentHomePath } from "@/auth/onboardingResume";
import { beginGoogleLogin, login, verifyTwoFactor } from "@/api/auth";
import { apiFetch } from "@/api/client";
import { useTheme } from "@/composables/useTheme";
import LanguageSwitcher from "@/components/LanguageSwitcher.vue";
import { withLang } from "@/i18n/routeLang";
import VueRecaptcha from "vue3-recaptcha2";
import DOMPurify from "dompurify";

// Force light mode on login page - never use dark mode here
onMounted(() => {
  document.documentElement.classList.remove("dark", "dev-dark");
});

const route = useRoute();
const router = useRouter();
const { t } = useI18n();
const { isFlare } = useTheme();
const email = ref("");
const password = ref("");
const showPassword = ref(false);
const showCaptchaModal = ref(false);
const captchaCode = ref("");
const twoFactorCode = ref("");
const twoFactorChallenge = ref("");
const twoFactorExpiresAt = ref("");
const recaptcha = ref<any>(null); // Ref to reset captcha
const error = ref("");
const busy = ref(false);
const isTwoFactorStep = ref(false);
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
    const result = await login(email.value, password.value, captchaCode.value);
    if ("two_factor_required" in result) {
      twoFactorChallenge.value = result.challenge_id;
      twoFactorExpiresAt.value = result.expires_at;
      isTwoFactorStep.value = true;
      showCaptchaModal.value = false;
      return;
    }

    const user = result.user;
    // Success — close modal and navigate
    showCaptchaModal.value = false;
    await finishSignIn(user);
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

async function submitTwoFactor() {
  if (!twoFactorChallenge.value || !twoFactorCode.value.trim()) {
    error.value = "Enter your six-digit authenticator code or a recovery code.";
    return;
  }

  busy.value = true;
  error.value = "";
  try {
    const user = await verifyTwoFactor(twoFactorChallenge.value, twoFactorCode.value);
    await finishSignIn(user);
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "Two-factor verification failed.";
  } finally {
    busy.value = false;
  }
}

async function startGoogleLogin() {
  busy.value = true;
  error.value = "";
  try {
    window.location.assign(await beginGoogleLogin());
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "Google sign-in is unavailable.";
    busy.value = false;
  }
}

async function finishSignIn(user: typeof authSession.user) {
  if (!user) return;
  authSession.user = user;
  authSession.loaded = true;
  await router.push(withLang(studentHomePath(user), route.query.lang));
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
  const oauthError = route.query.oauth_error;
  const oauth2fa = route.query.oauth_2fa;
  if (typeof oauthError === "string") {
    const messages: Record<string, string> = {
      state: "Google sign-in could not be verified. Please try again.",
      denied: "Google sign-in was cancelled.",
      token: "Google did not complete sign-in. Please try again.",
      email: "Google must return a verified email address.",
      unknown: "No active UniFAST account matches that Google email.",
      inactive: "This account cannot sign in yet. Contact the UniFAST office.",
    };
    error.value = messages[oauthError] ?? "Google sign-in failed.";
  } else if (typeof oauth2fa === "string") {
    twoFactorChallenge.value = oauth2fa;
    twoFactorExpiresAt.value = typeof route.query.expires_at === "string" ? route.query.expires_at : "";
    isTwoFactorStep.value = true;
  } else if (route.query.signed_in === "google") {
    const user = await import("@/api/auth").then((mod) => mod.fetchCurrentUser());
    if (user) await finishSignIn(user);
  }

  try {
    const termsRes = await apiFetch<{ data: Term }>("/api/terms/active");
    terms.value = termsRes.data;
  } catch {}
});

</script>

<template>
  <div class="grid h-screen overflow-hidden bg-surface lg:h-[80vh] lg:grid-cols-[1.08fr_.92fr]">
    <section
      :class="[
        'relative hidden h-screen overflow-hidden px-8 py-5 lg:flex lg:h-[80vh] lg:flex-col xl:px-10 xl:py-6',
        isFlare ? 'bg-surface' : 'bg-[#4a141d]'
      ]"
    >
      <div
        class="absolute inset-0 z-0"
        :style="
          isFlare
            ? 'background: radial-gradient(circle at 41% 44%, rgba(255, 97, 21, 0.08), transparent 34%), radial-gradient(circle at 58% 58%, rgba(255, 97, 21, 0.04), transparent 14%), linear-gradient(135deg, #ffffff 0%, #fff5f0 52%, #ffffff 100%);'
            : 'background: radial-gradient(circle at 41% 44%, rgba(126, 31, 44, 0.9), transparent 34%), radial-gradient(circle at 96% 3%, rgba(255, 255, 255, 0.08), transparent 20%), radial-gradient(circle at 58% 58%, rgba(154, 42, 42, 0.46), transparent 14%), linear-gradient(135deg, #4a141d 0%, #681b29 52%, #4a141d 100%);'
        "
      />
      <div
        class="absolute bottom-32 right-10 h-36 w-36 z-0 opacity-20"
        :style="`
          background-image: radial-gradient(${isFlare ? 'rgba(255, 97, 21, 0.3)' : 'rgba(255, 255, 255, 0.48)'} 1px, transparent 1px);
          background-size: 14px 14px;
        `"
      />
      
      <div :class="['relative z-50 flex items-center gap-3 w-full justify-start mt-0', isFlare ? 'text-text' : 'text-white']">
        <div :class="['h-10 w-10 rounded-lg p-1.5 shadow-sm bg-white', isFlare ? 'border border-surface-muted' : '']">
          <img :src="logo" class="h-full w-full object-contain" alt="UniFAST TES" />
        </div>
        <div>
          <p class="text-base font-semibold">
            UniFAST TES
          </p>
          <p :class="['text-xs', isFlare ? 'text-text-muted' : 'text-white/80']">
            {{ t("app.granteeManagement") }}
          </p>
        </div>
      </div>

      <div :class="['relative z-20 mt-12 max-w-xl xl:mt-16', isFlare ? 'text-text' : 'text-white']">
        <p :class="['text-xs font-semibold uppercase tracking-[0.2em]', isFlare ? 'text-primary' : 'text-white/90']">
          {{ t("app.college") }}
        </p>
        <h1
          class="mt-4 max-w-[31rem] text-3xl font-semibold leading-[1.14] tracking-tight xl:text-4xl"
        >
          {{ t("auth.heroTitle") }}
        </h1>
        <p :class="['mt-4 max-w-md text-sm leading-6', isFlare ? 'text-text-muted' : 'text-white/82']">
          {{ t("auth.heroDescription") }}
        </p>
      </div>

      <!-- Large Watermark Logo Behind Image -->
      <img
        :src="backgroundLogo"
        alt="Watermark"
        class="absolute right-0 bottom-0 z-0 w-[90%] max-w-[600px] object-contain opacity-10 mix-blend-overlay pointer-events-none translate-x-[15%] translate-y-[15%]"
      />

      <img
        :src="studentsCutout"
        alt="Tagoloan Community College students"
        class="absolute bottom-0 right-[-5%] z-10 w-[85%] max-w-[650px] object-contain drop-shadow-2xl brightness-105 contrast-105 hover:scale-[1.02] hover:brightness-110 transition-all duration-500 xl:w-[80%]"
      />
    </section>

    <main
      class="relative flex h-screen items-center justify-center overflow-hidden bg-white p-5 sm:p-6 lg:h-[80vh]"
    >
      <RouterLink
        :to="withLang('/', route.query.lang)"
        class="absolute left-5 top-5 inline-flex items-center gap-2 rounded-full border border-[#e7dde0] bg-white px-3 py-2 text-xs font-black text-[#6b1020] shadow-sm transition hover:bg-[#fff9f6] sm:left-6 sm:top-6"
        aria-label="Back to home"
      >
        <ArrowLeft :size="15" aria-hidden="true" />
        Back
      </RouterLink>

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
            isTwoFactorStep
              ? "Two-factor verification"
              : mode === "forgot"
              ? t("auth.forgotTitle")
              : mode === "activate"
                ? t("auth.activateTitle")
                : t("auth.loginTitle")
          }}
        </h2>
        <p class="mt-1 text-sm text-text-muted">
          {{
            isTwoFactorStep
              ? "Enter the code from your authenticator app to finish signing in."
              : mode === "login"
              ? t("auth.loginDescription")
              : t("auth.emailDescription")
          }}
        </p>

        <form class="mt-5 space-y-3.5" @submit.prevent="isTwoFactorStep ? submitTwoFactor() : mode === 'login' ? openCaptchaModal() : submit()">
          <label v-if="!isTwoFactorStep" class="block">
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
          <label v-if="mode === 'login' && !isTwoFactorStep" class="block">
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
          <label v-if="isTwoFactorStep" class="block">
            <span class="mb-1.5 block text-xs font-medium text-text">Authenticator code <b class="text-danger">*</b></span>
            <div class="relative">
              <ShieldCheck :size="17" class="absolute left-3 top-1/2 -translate-y-1/2 text-text-soft" />
              <input
                v-model="twoFactorCode"
                type="text"
                inputmode="numeric"
                autocomplete="one-time-code"
                placeholder="123456"
                class="h-10 w-full rounded-md border bg-[#f1f5fb] pl-10 pr-3 text-sm text-text shadow-inner shadow-slate-200/40"
              />
            </div>
            <span v-if="twoFactorExpiresAt" class="mt-1 block text-micro text-text-muted">
              This challenge expires at {{ new Date(twoFactorExpiresAt).toLocaleTimeString() }}.
            </span>
          </label>
          <p v-if="error" class="text-xs text-danger mb-2">{{ error }}</p>

          <button
            type="submit"
            :disabled="busy"
            class="flex h-10 w-full items-center justify-center gap-2 rounded-md bg-primary text-sm font-semibold text-white shadow-sm hover:bg-primary-hover disabled:opacity-60"
          >
            {{ busy ? t("auth.signingIn") : isTwoFactorStep ? "Verify code" : mode === "login" ? t("common.signIn") : t("common.continue") }}
            <ArrowRight :size="15" />
          </button>
          <button
            v-if="mode === 'login' && !isTwoFactorStep"
            type="button"
            :disabled="busy"
            class="flex h-10 w-full items-center justify-center gap-2 rounded-md border bg-white text-sm font-semibold text-text hover:bg-surface-muted disabled:opacity-60"
            @click="startGoogleLogin"
          >
            <svg class="h-5 w-5" viewBox="0 0 24 24" aria-hidden="true">
              <path
                fill="#4285F4"
                d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
              />
              <path
                fill="#34A853"
                d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
              />
              <path
                fill="#FBBC05"
                d="M5.84 14.1c-.22-.66-.35-1.36-.35-2.1s.13-1.44.35-2.1V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l3.66-2.84z"
              />
              <path
                fill="#EA4335"
                d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06L5.84 9.9C6.71 7.3 9.14 5.38 12 5.38z"
              />
            </svg>
            Continue with Google
          </button>
          <button
            v-if="isTwoFactorStep"
            type="button"
            class="w-full rounded-md border py-2 text-sm font-medium hover:bg-surface-muted"
            @click="
              isTwoFactorStep = false;
              twoFactorCode = '';
              twoFactorChallenge = '';
            "
          >
            Back to sign in
          </button>
        </form>

        <div v-if="mode === 'login' && !isTwoFactorStep" class="mt-4 flex justify-between text-xs text-primary">
          <RouterLink :to="withLang('/forgot-password', route.query.lang)">{{ t("auth.forgotPassword") }}</RouterLink>
          <RouterLink :to="withLang('/activate', route.query.lang)">{{ t("auth.activateAccount") }}</RouterLink>
        </div>
        <div v-else class="mt-4 text-xs">
          <RouterLink :to="withLang('/login', route.query.lang)" class="text-primary">{{ t("auth.backToSignIn") }}</RouterLink>
        </div>

        <div v-if="mode === 'login' && !isTwoFactorStep" class="mt-6 border-t pt-4">
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
        <div v-if="terms && mode === 'login' && !isTwoFactorStep" class="mt-6 border-t pt-4">
          <button class="flex w-full items-center gap-2 text-xs font-medium text-text-muted hover:text-text" @click="showTerms = !showTerms">
            <ShieldCheck :size="14" />
            <span>{{ terms.title }} (v{{ terms.version }})</span>
            <component :is="showTerms ? ChevronUp : ChevronDown" :size="14" class="ml-auto" />
          </button>
          <div v-if="showTerms" class="mt-3 max-h-48 overflow-y-auto rounded-md border bg-surface p-3 text-xs text-text-muted">
            <div v-html="DOMPurify.sanitize(terms.content)" />
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
