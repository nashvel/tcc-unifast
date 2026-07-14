<script setup lang="ts">
import { ref } from "vue";
import { useRouter } from "vue-router";
import { IconAlertTriangle, IconKey, IconMail, IconShieldCheck } from "@tabler/icons-vue";
import AuthLayout from "./AuthLayout.vue";

const router = useRouter();
const step = ref<"email" | "password">("email");
const email = ref("");
const temporaryPassword = ref("");
const password = ref("");
const confirm = ref("");
const error = ref("");

function verifyInvite() {
  error.value = "";
  if (!email.value || !temporaryPassword.value) {
    error.value = "Enter your registered email and temporary password from the activation email.";
    return;
  }
  step.value = "password";
}

function activate() {
  error.value = "";
  if (password.value.length < 8) error.value = "Password must be at least 8 characters.";
  else if (password.value !== confirm.value) error.value = "Passwords do not match.";
  else router.push("/activate-success");
}
</script>

<template>
  <AuthLayout>
    <div class="mb-3 flex items-center gap-2">
      <span class="grid h-8 w-8 place-items-center rounded-md bg-primary-soft text-primary">
        <IconShieldCheck :size="16" />
      </span>
      <p class="text-micro font-semibold uppercase tracking-wider text-text-soft">
        Account Activation
      </p>
    </div>
    <h1 class="text-xl font-semibold tracking-tight">
      {{ step === "email" ? "Activate your masterlist account" : "Create your own password" }}
    </h1>
    <p class="mt-1 text-sm text-text-muted">
      {{
        step === "email"
          ? "Your account was created inactive from the Head-uploaded masterlist. Use the temporary password sent to your registered email."
          : "Replace the temporary password before continuing to identity verification."
      }}
    </p>

    <form v-if="step === 'email'" class="mt-5 space-y-4" @submit.prevent="verifyInvite">
      <label class="block">
        <span class="mb-1.5 block text-xs font-medium">Registered email *</span>
        <span class="relative block">
          <IconMail :size="15" class="absolute left-3 top-1/2 -translate-y-1/2 text-text-soft" />
          <input
            v-model="email"
            type="email"
            placeholder="student001@tcc.edu.ph"
            class="h-10 w-full rounded-md border pl-9 pr-3 text-sm"
          />
        </span>
      </label>
      <label class="block">
        <span class="mb-1.5 block text-xs font-medium">Temporary password *</span>
        <span class="relative block">
          <IconKey :size="15" class="absolute left-3 top-1/2 -translate-y-1/2 text-text-soft" />
          <input
            v-model="temporaryPassword"
            placeholder="TCC-8F4K-29QZ"
            class="h-10 w-full rounded-md border pl-9 pr-3 text-sm"
          />
        </span>
      </label>
      <div
        v-if="error"
        class="flex gap-2 rounded-md border border-danger/30 bg-danger-soft p-2.5 text-xs text-danger"
      >
        <IconAlertTriangle :size="14" />{{ error }}
      </div>
      <button class="h-10 w-full rounded-md bg-primary text-sm font-medium text-white">
        Continue activation
      </button>
    </form>

    <form v-else class="mt-5 space-y-4" @submit.prevent="activate">
      <label class="block">
        <span class="mb-1.5 block text-xs font-medium">New password *</span>
        <input v-model="password" type="password" class="h-10 w-full rounded-md border px-3" />
      </label>
      <label class="block">
        <span class="mb-1.5 block text-xs font-medium">Confirm password *</span>
        <input v-model="confirm" type="password" class="h-10 w-full rounded-md border px-3" />
      </label>
      <p class="rounded-md border bg-surface-muted p-2.5 text-xs text-text-muted">
        After login, you still need to upload your student ID and pass live face verification before
        dashboard menus unlock.
      </p>
      <p v-if="error" class="text-xs text-danger">{{ error }}</p>
      <button class="h-10 w-full rounded-md bg-primary text-sm font-medium text-white">
        Activate account
      </button>
    </form>
    <RouterLink to="/login" class="mt-4 inline-block text-sm text-primary hover:underline">
      ← Back to sign in
    </RouterLink>
  </AuthLayout>
</template>
