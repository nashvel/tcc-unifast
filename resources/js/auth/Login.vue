<script setup lang="ts">
import { ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { ArrowRight, Lock, Mail, UserRound } from "lucide-vue-next";
import logo from "@/assets/system-logo.png";
import studentsCutout from "@/assets/auth/tcc-students-cutout.png";
import { authSession, csrfToken } from "@/auth/session";

const route = useRoute();
const router = useRouter();
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
    error.value = "Enter email and password.";
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
    if (!response.ok) throw new Error(payload.message || "Sign in failed.");
    authSession.user = payload.user;
    authSession.loaded = true;
    await router.push(payload.user.role === "student" ? "/student" : "/app");
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "Sign in failed.";
  } finally {
    busy.value = false;
  }
}

async function quickLogin(label: string) {
  email.value = demoAccounts[label];
  password.value = "password";
  await submit();
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
          <p class="text-xs text-white/65">Grantee Management</p>
        </div>
      </div>
      <div class="relative z-10 mt-16 max-w-xl xl:mt-20">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#f2bd4c]">
          Tagoloan Community College
        </p>
        <h1
          class="mt-4 max-w-[31rem] text-3xl font-semibold leading-[1.14] tracking-tight xl:text-4xl"
        >
          Making scholarship services simpler, faster, and more transparent.
        </h1>
        <p class="mt-4 max-w-md text-sm leading-6 text-white/82">
          A unified workspace for grantees, documents, eligibility, releases, and academic
          monitoring.
        </p>
      </div>
      <img
        :src="studentsCutout"
        alt="Tagoloan Community College students"
        class="absolute bottom-0 left-1/2 z-10 w-[94%] max-w-none -translate-x-1/2 object-contain xl:w-[90%]"
      />
    </section>

    <main
      class="flex h-screen items-center justify-center overflow-hidden bg-white p-5 sm:p-6 lg:h-[80vh]"
    >
      <div class="w-full max-w-[31rem]">
        <div class="mb-6 flex items-center gap-3 lg:hidden">
          <span
            class="grid h-12 w-12 place-items-center rounded-xl border bg-white p-1.5 shadow-sm"
          >
            <img :src="logo" class="h-full w-full object-contain" alt="UniFAST TES" />
          </span>
          <div>
            <p class="font-semibold">UniFAST TES</p>
            <p class="text-xs text-text-muted">Tagoloan Community College</p>
          </div>
        </div>

        <h2 class="text-xl font-semibold tracking-tight text-text">
          {{
            mode === "forgot"
              ? "Reset your password"
              : mode === "activate"
                ? "Activate your account"
                : "Sign in to your account"
          }}
        </h2>
        <p class="mt-1 text-sm text-text-muted">
          {{
            mode === "login"
              ? "Use a seeded account or choose a demo role below."
              : "Enter your institutional email to continue."
          }}
        </p>

        <form class="mt-5 space-y-3.5" @submit.prevent="submit">
          <label class="block">
            <span class="mb-1.5 block text-xs font-medium">Email <b class="text-danger">*</b></span>
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
              >Password <b class="text-danger">*</b></span
            >
            <div class="relative">
              <Lock :size="17" class="absolute left-3 top-1/2 -translate-y-1/2 text-text-soft" />
              <input
                v-model="password"
                type="password"
                placeholder="Password"
                class="h-10 w-full rounded-md border bg-[#f1f5fb] pl-10 pr-3 text-sm shadow-inner shadow-slate-200/40"
              />
            </div>
          </label>
          <p v-if="error" class="text-xs text-danger">{{ error }}</p>
          <button
            :disabled="busy"
            class="flex h-10 w-full items-center justify-center gap-2 rounded-md bg-primary text-sm font-semibold text-white shadow-sm hover:bg-primary-hover disabled:opacity-60"
          >
            {{ busy ? "Signing in..." : mode === "login" ? "Sign in" : "Continue" }}
            <ArrowRight :size="15" />
          </button>
        </form>

        <div v-if="mode === 'login'" class="mt-4 flex justify-between text-xs text-primary">
          <RouterLink to="/forgot-password">Forgot password?</RouterLink>
          <RouterLink to="/activate">Activate your account</RouterLink>
        </div>
        <div v-else class="mt-4 text-xs">
          <RouterLink to="/login" class="text-primary">Back to sign in</RouterLink>
        </div>

        <div v-if="mode === 'login'" class="mt-6 border-t pt-4">
          <p class="mb-2.5 text-2xs font-semibold uppercase tracking-wider text-text-soft">
            Seeded demo accounts - password: password
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
              <span class="text-xs font-medium">{{ role }}</span>
            </button>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>
