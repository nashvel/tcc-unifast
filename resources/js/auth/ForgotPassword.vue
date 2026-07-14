<script setup lang="ts">
import { ref } from "vue";
import { IconCheck } from "@tabler/icons-vue";
import AuthLayout from "./AuthLayout.vue";

const email = ref("");
const sent = ref(false);
</script>

<template>
  <AuthLayout>
    <template v-if="sent">
      <span class="mb-3 grid h-9 w-9 place-items-center rounded-full bg-success-soft text-success"
        ><IconCheck :size="18"
      /></span>
      <h1 class="text-xl font-semibold">Check your email</h1>
      <p class="mt-1 text-sm text-text-muted">
        If <b>{{ email }}</b> is registered, we've sent password reset instructions.
      </p>
      <RouterLink to="/login" class="mt-5 inline-block text-sm text-primary hover:underline"
        >← Back to sign in</RouterLink
      >
    </template>
    <template v-else>
      <h1 class="text-xl font-semibold tracking-tight">Forgot password</h1>
      <p class="mt-1 text-sm text-text-muted">Enter your email and we'll send you a reset link.</p>
      <form class="mt-5 space-y-4" @submit.prevent="sent = true">
        <label class="block"
          ><span class="mb-1.5 block text-xs font-medium">Email *</span
          ><input
            v-model="email"
            required
            type="email"
            placeholder="you@unifast.gov.ph"
            class="h-10 w-full rounded-md border bg-surface px-3 text-sm"
        /></label>
        <button class="h-10 w-full rounded-md bg-primary text-sm font-medium text-white">
          Send reset link
        </button>
      </form>
      <RouterLink to="/login" class="mt-4 inline-block text-sm text-primary hover:underline"
        >← Back to sign in</RouterLink
      >
    </template>
  </AuthLayout>
</template>
