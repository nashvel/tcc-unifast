<script setup lang="ts">
import { IconCheck } from "@tabler/icons-vue";
import AuthLayout from "./AuthLayout.vue";
</script>

<template>
  <AuthLayout>
    <div class="text-center">
      <span
        class="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-full bg-success-soft text-success"
      >
        <IconCheck :size="22" />
      </span>
      <h1 class="text-xl font-semibold">Account activated</h1>
      <p class="mt-1 text-sm text-text-muted">
        Your student account is now active. Sign in next, then upload your student ID and complete
        live face verification before the dashboard and document uploads unlock.
      </p>
      <RouterLink
        to="/login"
        class="mt-5 inline-block text-sm font-medium text-primary hover:underline"
      >
        Go to sign in →
      </RouterLink>
    </div>
  </AuthLayout>
</template>
